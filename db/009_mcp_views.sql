-- 009: the mcp_* visibility views -- the only thing the two read roles can see (memory.md §1).
-- Every WHERE clause IS the row rule; the servers never filter in Python. Primary keys are
-- re-aliased <entity>_id; columns that hide sensitive facts are simply not selected.
--
-- The rules, in one place:
--   hr_can_see_person(m)  own record | super-admin | HR staff | the person's manager | an admin of
--                         a department they are in | any admitted AGENT (employment without pay)
--   hr_can_see_pay(m)     own | super-admin | HR staff | the person's manager  -- humans only
--   hr_can_see_review(r)  reviewer | HR staff | super-admin | the person's manager | the person, once shared
--   pay runs              HR staff and super-admins; a person sees their own item on a run that is not a draft
BEGIN;

-- hr_is_manager_of / hr_can_see_person / hr_can_see_pay live in db/005 (the pay function needs them).
CREATE OR REPLACE FUNCTION hr_can_see_review(p_review_id bigint) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_active_member() AND app_member_kind() = 'human' AND EXISTS (
        SELECT 1 FROM reviews r
         WHERE r.id = p_review_id
           AND (r.reviewer_member_id = app_current_member_id()
                OR hr_is_staff()
                OR hr_is_manager_of(r.member_id)
                OR (r.member_id = app_current_member_id() AND r.shared_at IS NOT NULL)));
$$;

GRANT EXECUTE ON FUNCTION hr_can_see_review(bigint) TO hr_rw, hr_records_ro, hr_activity_ro;

-- ---------------------------------------------------------------------------------------------
-- The directory, as mirrored. Anyone admitted to HR may read who works here.
-- ---------------------------------------------------------------------------------------------
CREATE OR REPLACE VIEW mcp_members WITH (security_barrier = true) AS
SELECT m.id AS member_id, m.display_name, m.email, m.member_kind, m.business_role, m.is_external,
       m.status, m.job_title, m.phone, m.timezone, m.capability AS hr_capability,
       (SELECT d.name FROM department_members dm JOIN departments d ON d.id = dm.department_id
         WHERE dm.member_id = m.id AND dm.left_at IS NULL ORDER BY dm.is_primary DESC, d.name LIMIT 1) AS primary_department,
       (SELECT dm.department_id FROM department_members dm
         WHERE dm.member_id = m.id AND dm.left_at IS NULL ORDER BY dm.is_primary DESC, dm.department_id LIMIT 1) AS primary_department_id,
       (e.member_id IS NOT NULL) AS has_employment, e.status AS employment_status, m.synced_at
FROM members m
LEFT JOIN employment_profiles e ON e.member_id = m.id
WHERE app_is_active_member();

CREATE OR REPLACE VIEW mcp_departments WITH (security_barrier = true) AS
SELECT d.id AS department_id, d.name, d.description, d.parent_id, p.name AS parent_name,
       d.manager_member_id, mg.display_name AS manager_name, d.is_system, d.system_key, d.archived_at,
       (SELECT count(*) FROM department_members dm JOIN members m ON m.id = dm.member_id
         WHERE dm.department_id = d.id AND dm.left_at IS NULL AND m.member_kind = 'human' AND m.status = 'active') AS people,
       (SELECT count(*) FROM department_members dm JOIN members m ON m.id = dm.member_id
         WHERE dm.department_id = d.id AND dm.left_at IS NULL AND m.member_kind = 'agent' AND m.status = 'active') AS agents,
       app_is_admin_of(d.id) AS i_administer
FROM departments d
LEFT JOIN departments p ON p.id = d.parent_id
LEFT JOIN members mg ON mg.id = d.manager_member_id
WHERE app_is_active_member();

CREATE OR REPLACE VIEW mcp_department_members WITH (security_barrier = true) AS
SELECT dm.member_id, m.display_name, m.member_kind, dm.department_id, d.name AS department_name,
       dm.is_admin, dm.is_primary, dm.joined_at, dm.left_at
FROM department_members dm
JOIN members m ON m.id = dm.member_id
JOIN departments d ON d.id = dm.department_id
WHERE app_is_active_member();

-- ---------------------------------------------------------------------------------------------
-- Employment. Home contact details (personal email, address, emergency contact, birth date, the
-- id fragment) are in NO view: only the edit form, behind the HR gate, reads them.
-- ---------------------------------------------------------------------------------------------
CREATE OR REPLACE VIEW mcp_employment_profiles WITH (security_barrier = true) AS
SELECT e.member_id, m.display_name, m.job_title, e.employee_no, e.employment_type, e.status,
       e.manager_member_id, mg.display_name AS manager_name, e.work_location,
       e.start_date, e.probation_end_date, e.end_date, e.end_reason, e.weekly_hours, e.pay_schedule,
       (SELECT d.name FROM department_members dm JOIN departments d ON d.id = dm.department_id
         WHERE dm.member_id = e.member_id AND dm.left_at IS NULL ORDER BY dm.is_primary DESC, d.name LIMIT 1) AS primary_department,
       (SELECT dm.department_id FROM department_members dm
         WHERE dm.member_id = e.member_id AND dm.left_at IS NULL ORDER BY dm.is_primary DESC, dm.department_id LIMIT 1) AS primary_department_id,
       e.notes, e.created_at, e.updated_at
FROM employment_profiles e
JOIN members m ON m.id = e.member_id
LEFT JOIN members mg ON mg.id = e.manager_member_id
WHERE hr_can_see_person(e.member_id);

CREATE OR REPLACE VIEW mcp_compensation_changes WITH (security_barrier = true) AS
SELECT c.id AS compensation_change_id, c.member_id, m.display_name, c.effective_date, c.pay_type,
       c.pay_rate, c.currency, c.reason, c.created_by, cb.display_name AS created_by_name, c.created_at
FROM compensation_changes c
JOIN members m ON m.id = c.member_id
LEFT JOIN members cb ON cb.id = c.created_by
WHERE hr_can_see_pay(c.member_id);

-- The rate in force today, one row per person who has one.
CREATE OR REPLACE VIEW mcp_current_pay WITH (security_barrier = true) AS
SELECT e.member_id, m.display_name, p.compensation_change_id, p.effective_date, p.pay_type, p.pay_rate,
       p.currency, e.pay_schedule, e.weekly_hours,
       CASE p.pay_type
            WHEN 'salary' THEN round(p.pay_rate / 12, 2)
            WHEN 'hourly' THEN round(p.pay_rate * COALESCE(e.weekly_hours, 0) * 52 / 12, 2)
            WHEN 'daily'  THEN round(p.pay_rate * 21.67, 2)
            ELSE NULL END AS monthly_base_estimate
FROM employment_profiles e
JOIN members m ON m.id = e.member_id
CROSS JOIN LATERAL hr_pay_as_of(e.member_id, current_date) p
WHERE hr_can_see_pay(e.member_id);

-- ---------------------------------------------------------------------------------------------
-- Pay runs: HR staff and super-admins. A person sees their own item once the run is approved.
-- ---------------------------------------------------------------------------------------------
CREATE OR REPLACE VIEW mcp_pay_runs WITH (security_barrier = true) AS
SELECT r.id AS pay_run_id, r.run_no, r.period_start, r.period_end, r.pay_date, r.currency, r.status,
       r.gross_total, r.deductions_total, r.employer_cost_total, r.reimbursements_total, r.net_total,
       r.note, r.approved_by, ab.display_name AS approved_by_name, r.approved_at, r.paid_at, r.voided_at,
       r.void_reason, (SELECT count(*) FROM pay_run_items i WHERE i.pay_run_id = r.id) AS people,
       r.created_at, r.updated_at
FROM pay_runs r
LEFT JOIN members ab ON ab.id = r.approved_by
WHERE app_member_kind() = 'human' AND hr_is_staff();

CREATE OR REPLACE VIEW mcp_pay_run_items WITH (security_barrier = true) AS
SELECT i.id AS pay_run_item_id, i.pay_run_id, r.run_no, r.status AS run_status, r.period_start, r.period_end,
       r.pay_date, r.currency, i.member_id, m.display_name, i.hours, i.base_note, i.gross_amount,
       i.deductions, i.employer_cost, i.reimbursements, i.net_amount, i.note
FROM pay_run_items i
JOIN pay_runs r ON r.id = i.pay_run_id
JOIN members m ON m.id = i.member_id
WHERE app_member_kind() = 'human'
  AND (hr_is_staff() OR (i.member_id = app_current_member_id() AND r.status IN ('approved', 'paid')));

CREATE OR REPLACE VIEW mcp_pay_run_lines WITH (security_barrier = true) AS
SELECT l.id AS pay_run_line_id, l.pay_run_item_id, i.pay_run_id, i.member_id, l.kind, l.code,
       l.description, l.quantity, l.rate, l.amount, l.is_base
FROM pay_run_lines l
JOIN pay_run_items i ON i.id = l.pay_run_item_id
JOIN pay_runs r ON r.id = i.pay_run_id
WHERE app_member_kind() = 'human'
  AND (hr_is_staff() OR (i.member_id = app_current_member_id() AND r.status IN ('approved', 'paid')));

-- ---------------------------------------------------------------------------------------------
-- Leave: visible wherever the person is (who is off is a department fact); balances too.
-- ---------------------------------------------------------------------------------------------
CREATE OR REPLACE VIEW mcp_leave_types WITH (security_barrier = true) AS
SELECT id AS leave_type_id, name, is_paid, accrual_days_per_year, carry_over_days, requires_approval, archived_at
FROM leave_types
WHERE app_is_active_member();

CREATE OR REPLACE VIEW mcp_leave_balances WITH (security_barrier = true) AS
SELECT b.id AS leave_balance_id, b.member_id, m.display_name, b.leave_type_id, lt.name AS leave_type,
       b.year, b.accrued_days, b.carried_days, b.used_days,
       (b.accrued_days + b.carried_days - b.used_days) AS remaining_days,
       hr_leave_committed(b.member_id, b.leave_type_id, b.year) AS committed_days
FROM leave_balances b
JOIN members m ON m.id = b.member_id
JOIN leave_types lt ON lt.id = b.leave_type_id
WHERE hr_can_see_person(b.member_id);

CREATE OR REPLACE VIEW mcp_leave_requests WITH (security_barrier = true) AS
SELECT lr.id AS leave_request_id, lr.member_id, m.display_name,
       (SELECT d.name FROM department_members dm JOIN departments d ON d.id = dm.department_id
         WHERE dm.member_id = lr.member_id AND dm.left_at IS NULL ORDER BY dm.is_primary DESC, d.name LIMIT 1) AS primary_department,
       lr.leave_type_id, lt.name AS leave_type, lt.requires_approval, lr.start_date, lr.end_date, lr.days,
       lr.half_day, lr.reason, lr.status, lr.over_balance, lr.has_overlap, lr.decided_by,
       db.display_name AS decided_by_name, lr.decided_at, lr.decision_note,
       (lr.status = 'requested' AND lr.member_id <> app_current_member_id()
        AND (hr_is_staff() OR hr_is_manager_of(lr.member_id) OR app_can_admin_member(lr.member_id))) AS awaiting_me,
       lr.created_at
FROM leave_requests lr
JOIN members m ON m.id = lr.member_id
JOIN leave_types lt ON lt.id = lr.leave_type_id
LEFT JOIN members db ON db.id = lr.decided_by
WHERE hr_can_see_person(lr.member_id);

CREATE OR REPLACE VIEW mcp_holidays WITH (security_barrier = true) AS
SELECT id AS holiday_id, holiday_date, name FROM holidays WHERE app_is_active_member();

CREATE OR REPLACE VIEW mcp_hr_settings WITH (security_barrier = true) AS
SELECT currency, timezone, work_days, leave_year_start_month FROM hr_settings WHERE app_is_active_member();

-- ---------------------------------------------------------------------------------------------
-- Reviews: the reviewer, HR, the manager; the person once it is shared. Never an agent.
-- ---------------------------------------------------------------------------------------------
CREATE OR REPLACE VIEW mcp_reviews WITH (security_barrier = true) AS
SELECT r.id AS review_id, r.member_id, m.display_name, r.reviewer_member_id, rv.display_name AS reviewer_name,
       r.kind, r.period_start, r.period_end, r.due_date, r.status, r.rating, r.summary, r.strengths,
       r.improvements, r.goals, r.employee_comment, r.employee_commented_at, r.shared_at, r.completed_at,
       r.cancelled_at, r.cancel_reason, r.created_at, r.updated_at
FROM reviews r
JOIN members m ON m.id = r.member_id
JOIN members rv ON rv.id = r.reviewer_member_id
WHERE hr_can_see_review(r.id);

GRANT SELECT ON mcp_members, mcp_departments, mcp_department_members, mcp_employment_profiles,
    mcp_compensation_changes, mcp_current_pay, mcp_pay_runs, mcp_pay_run_items, mcp_pay_run_lines,
    mcp_leave_types, mcp_leave_balances, mcp_leave_requests, mcp_holidays, mcp_hr_settings, mcp_reviews
TO hr_records_ro, hr_rw;

-- ---------------------------------------------------------------------------------------------
-- Activity memory, for the activity server. A row is visible to HR staff and super-admins; to
-- its actor; and to anyone who may see the person it is about -- pay rows only to those who may
-- see that person's pay. hr_activity_subject() says which person a record is about.
-- ---------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION hr_activity_subject(p_entity_type text, p_entity_id bigint) RETURNS bigint
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT CASE p_entity_type
        WHEN 'member'              THEN p_entity_id
        WHEN 'employment_profile'  THEN p_entity_id
        WHEN 'compensation_change' THEN (SELECT member_id FROM compensation_changes WHERE id = p_entity_id)
        WHEN 'pay_run_item'        THEN (SELECT member_id FROM pay_run_items WHERE id = p_entity_id)
        WHEN 'leave_request'       THEN (SELECT member_id FROM leave_requests WHERE id = p_entity_id)
        WHEN 'leave_balance'       THEN (SELECT member_id FROM leave_balances WHERE id = p_entity_id)
        WHEN 'review'              THEN (SELECT member_id FROM reviews WHERE id = p_entity_id)
        ELSE NULL END;
$$;
GRANT EXECUTE ON FUNCTION hr_activity_subject(text, bigint) TO hr_rw, hr_activity_ro;

CREATE OR REPLACE VIEW mcp_activity_log WITH (security_barrier = true) AS
SELECT a.id AS activity_id, a.occurred_at, a.actor_member_id, m.display_name AS actor_name,
       m.member_kind AS actor_kind, a.source, a.action, a.screen, a.route, a.entity_type, a.entity_id,
       hr_activity_subject(a.entity_type, a.entity_id) AS subject_member_id,
       a.before, a.after, a.request_id, a.agent_run_id, a.department_id, a.kernel_request_id
FROM activity_log a
LEFT JOIN members m ON m.id = a.actor_member_id
WHERE app_is_active_member() AND app_member_kind() = 'human' AND (
       hr_is_staff()
    OR a.actor_member_id = app_current_member_id()
    OR (a.entity_type IN ('compensation_change', 'pay_run_item')
        AND hr_can_see_pay(hr_activity_subject(a.entity_type, a.entity_id)))
    OR (a.entity_type NOT IN ('compensation_change', 'pay_run', 'pay_run_item', 'review')
        AND hr_activity_subject(a.entity_type, a.entity_id) IS NOT NULL
        AND hr_can_see_person(hr_activity_subject(a.entity_type, a.entity_id)))
    OR (a.entity_type = 'review' AND hr_can_see_review(a.entity_id)));

GRANT SELECT ON mcp_activity_log TO hr_activity_ro, hr_rw;
GRANT SELECT ON mcp_members, mcp_departments TO hr_activity_ro;   -- names beside the trail

COMMIT;
