-- 012: ADDITIVE. HR's own roles and the rights they give (2026-09-27; kernel db/145,
-- docs/build-specs/roles-and-rights.md).
--
-- HR publishes its roles to the kernel through its records MCP server (tool app_roles): the kernel
-- keeps a copy, a super-admin grants a person a SET of roles, and the claims and the directory feed
-- carry the role keys back. What a role lets its holder do is HR's to say and HR's to enforce: the
-- catalogue lives here, the mirror records each member's role keys, and hr_has_right() answers from
-- both. The four rule functions (db/001, db/005) now ask for rights instead of a capability:
--   hr.staff    → hr_is_staff()        (was: capability 'admin')
--   team.manage → hr_is_manager_of()   (the record's manager, OR a Manager over the departments they administer)
--   pay.read    → hr_can_see_pay()     (also Payroll)
--   pay.write   → hr_can_run_pay()     (new: pay runs and pay changes — Payroll or HR staff)
-- A member the kernel has not yet sent roles for (roles = '{}') is read as before: capability 'admin'
-- is HR staff. The super-admin holds every right.
BEGIN;

-- 1. The catalogue ------------------------------------------------------------------------------

CREATE TABLE hr_rights (
    right_key   text PRIMARY KEY CHECK (right_key ~ '^[a-z][a-z0-9_.]{0,59}$'),
    description text NOT NULL,
    sort_order  integer NOT NULL DEFAULT 0
);

CREATE TABLE hr_roles (
    role_key    text PRIMARY KEY CHECK (role_key ~ '^[a-z][a-z0-9_]{0,39}$'),
    name        text NOT NULL,
    description text NOT NULL,
    capability  text NOT NULL CHECK (capability IN ('read', 'write', 'admin')),
    is_admin    boolean NOT NULL DEFAULT false,
    sort_order  integer NOT NULL DEFAULT 0,
    CHECK (NOT is_admin OR capability = 'admin')
);
CREATE UNIQUE INDEX hr_roles_one_admin ON hr_roles (is_admin) WHERE is_admin;

CREATE TABLE hr_role_rights (
    role_key  text NOT NULL REFERENCES hr_roles(role_key) ON DELETE CASCADE,
    right_key text NOT NULL REFERENCES hr_rights(right_key) ON DELETE CASCADE,
    PRIMARY KEY (role_key, right_key)
);

INSERT INTO hr_rights (right_key, description, sort_order) VALUES
 ('directory.read', 'See the directory, departments, leave types and holidays', 1),
 ('self.read',      'See their own employment, pay, leave and shared reviews', 2),
 ('leave.request',  'Request their own leave', 3),
 ('team.manage',    'Manage the people in the departments they administer: see their employment, pay and leave, decide their leave, write their reviews', 4),
 ('pay.read',       'See everyone''s pay', 5),
 ('pay.write',      'Record pay changes and prepare, approve and pay pay runs', 6),
 ('hr.staff',       'Run HR: every employment record, pay, leave settings and balances, every review, directory changes', 7);

INSERT INTO hr_roles (role_key, name, description, capability, is_admin, sort_order) VALUES
 ('employee', 'Employee',  'Anyone employed here: their own records and leave.', 'read', false, 1),
 ('manager',  'Manager',   'Manages the people in the departments they administer, beyond those whose employment record names them as manager.', 'write', false, 2),
 ('payroll',  'Payroll',   'Keeps pay: sees everyone''s pay, records changes, runs pay runs.', 'write', false, 3),
 ('hr_admin', 'HR Admin',  'Runs HR. A super-admin holds this role.', 'admin', true, 4);

INSERT INTO hr_role_rights (role_key, right_key) VALUES
 ('employee', 'directory.read'), ('employee', 'self.read'), ('employee', 'leave.request'),
 ('manager', 'directory.read'), ('manager', 'self.read'), ('manager', 'leave.request'), ('manager', 'team.manage'),
 ('payroll', 'directory.read'), ('payroll', 'self.read'), ('payroll', 'leave.request'), ('payroll', 'pay.read'), ('payroll', 'pay.write'),
 ('hr_admin', 'directory.read'), ('hr_admin', 'self.read'), ('hr_admin', 'leave.request'), ('hr_admin', 'team.manage'),
 ('hr_admin', 'pay.read'), ('hr_admin', 'pay.write'), ('hr_admin', 'hr.staff');

-- 2. Each member's roles, as the kernel sends them -------------------------------------------------

ALTER TABLE members ADD COLUMN roles text[] NOT NULL DEFAULT '{}';
COMMENT ON COLUMN members.roles IS 'The HR roles the kernel says this member holds (claims.roles / feed access[].roles). Written only from the kernel''s word, like capability.';

-- 3. The rules --------------------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION hr_has_right(p_right text) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_super_admin()
        OR EXISTS (SELECT 1 FROM members m
                     JOIN hr_role_rights rr ON rr.role_key = ANY (m.roles)
                    WHERE m.id = app_current_member_id() AND m.status = 'active' AND m.capability IS NOT NULL
                      AND rr.right_key = p_right)
        -- Not yet told the roles: the grant of before, where admin was HR staff.
        OR EXISTS (SELECT 1 FROM members m
                     JOIN hr_role_rights rr ON rr.role_key = 'hr_admin'
                    WHERE m.id = app_current_member_id() AND m.status = 'active'
                      AND cardinality(m.roles) = 0 AND m.capability = 'admin' AND rr.right_key = p_right);
$$;

CREATE OR REPLACE FUNCTION hr_is_staff() RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_super_admin() OR (app_member_kind() = 'human' AND hr_has_right('hr.staff'));
$$;

CREATE OR REPLACE FUNCTION hr_is_manager_of(p_member_id bigint) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_insider() AND (
           EXISTS (SELECT 1 FROM employment_profiles e
                    WHERE e.member_id = p_member_id AND e.manager_member_id = app_current_member_id())
        OR (hr_has_right('team.manage') AND p_member_id <> app_current_member_id() AND EXISTS (
                SELECT 1 FROM department_members dm
                 WHERE dm.member_id = p_member_id AND dm.left_at IS NULL
                   AND dm.department_id IN (SELECT app_admin_department_ids()))));
$$;

CREATE OR REPLACE FUNCTION hr_can_see_pay(p_member_id bigint) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_active_member() AND app_member_kind() = 'human' AND (
           p_member_id = app_current_member_id()
        OR hr_is_staff()
        OR hr_has_right('pay.read')
        OR hr_is_manager_of(p_member_id));
$$;

-- Pay runs and pay changes: Payroll or HR staff.
CREATE OR REPLACE FUNCTION hr_can_run_pay() RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_member_kind() = 'human' AND (hr_is_staff() OR hr_has_right('pay.write'));
$$;

-- 4. Pay-run views: Payroll sees them as HR staff does -----------------------------------------------

CREATE OR REPLACE VIEW mcp_pay_runs WITH (security_barrier = true) AS
SELECT r.id AS pay_run_id, r.run_no, r.period_start, r.period_end, r.pay_date, r.currency, r.status,
       r.gross_total, r.deductions_total, r.employer_cost_total, r.reimbursements_total, r.net_total,
       r.note, r.approved_by, ab.display_name AS approved_by_name, r.approved_at, r.paid_at, r.voided_at,
       r.void_reason, (SELECT count(*) FROM pay_run_items i WHERE i.pay_run_id = r.id) AS people,
       r.created_at, r.updated_at
FROM pay_runs r
LEFT JOIN members ab ON ab.id = r.approved_by
WHERE app_member_kind() = 'human' AND hr_can_run_pay();

CREATE OR REPLACE VIEW mcp_pay_run_items WITH (security_barrier = true) AS
SELECT i.id AS pay_run_item_id, i.pay_run_id, r.run_no, r.status AS run_status, r.period_start, r.period_end,
       r.pay_date, r.currency, i.member_id, m.display_name, i.hours, i.base_note, i.gross_amount,
       i.deductions, i.employer_cost, i.reimbursements, i.net_amount, i.note
FROM pay_run_items i
JOIN pay_runs r ON r.id = i.pay_run_id
JOIN members m ON m.id = i.member_id
WHERE app_member_kind() = 'human'
  AND (hr_can_run_pay() OR (i.member_id = app_current_member_id() AND r.status IN ('approved', 'paid')));

CREATE OR REPLACE VIEW mcp_pay_run_lines WITH (security_barrier = true) AS
SELECT l.id AS pay_run_line_id, l.pay_run_item_id, i.pay_run_id, i.member_id, l.kind, l.code,
       l.description, l.quantity, l.rate, l.amount, l.is_base
FROM pay_run_lines l
JOIN pay_run_items i ON i.id = l.pay_run_item_id
JOIN pay_runs r ON r.id = i.pay_run_id
WHERE app_member_kind() = 'human'
  AND (hr_can_run_pay() OR (i.member_id = app_current_member_id() AND r.status IN ('approved', 'paid')));

-- 5. What app_roles answers, in one read ----------------------------------------------------------------

CREATE OR REPLACE VIEW mcp_app_roles AS
SELECT r.role_key, r.name, r.description, r.capability, r.is_admin, r.sort_order,
       COALESCE((SELECT array_agg(rr.right_key ORDER BY h.sort_order) FROM hr_role_rights rr
                   JOIN hr_rights h ON h.right_key = rr.right_key WHERE rr.role_key = r.role_key), '{}') AS rights
FROM hr_roles r;

GRANT SELECT ON hr_rights, hr_roles, hr_role_rights, mcp_app_roles TO hr_rw, hr_records_ro;
GRANT EXECUTE ON FUNCTION hr_has_right(text), hr_can_run_pay() TO hr_rw, hr_records_ro, hr_activity_ro;

-- 6. The directory says which HR roles each person holds (appended last) -----------------------------------

CREATE OR REPLACE VIEW mcp_members WITH (security_barrier = true) AS
 SELECT m.id AS member_id, m.display_name, m.email, m.member_kind, m.business_role, m.is_external, m.status,
    m.job_title, m.phone, m.timezone, m.capability AS hr_capability,
    ( SELECT d.name FROM department_members dm JOIN departments d ON d.id = dm.department_id
       WHERE dm.member_id = m.id AND dm.left_at IS NULL ORDER BY dm.is_primary DESC, d.name LIMIT 1) AS primary_department,
    ( SELECT dm.department_id FROM department_members dm
       WHERE dm.member_id = m.id AND dm.left_at IS NULL ORDER BY dm.is_primary DESC, dm.department_id LIMIT 1) AS primary_department_id,
    (e.member_id IS NOT NULL) AS has_employment,
    e.status AS employment_status,
    m.synced_at,
    ( SELECT array_agg(r.name ORDER BY r.sort_order) FROM hr_roles r WHERE r.role_key = ANY (m.roles)) AS hr_roles
   FROM members m
   LEFT JOIN employment_profiles e ON e.member_id = m.id
  WHERE app_is_active_member();

COMMIT;
