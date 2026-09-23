-- 005: employment records and compensation history.
--
-- A person is a mirror member (db/001). Their employment record is HR's own fact, one per
-- person, optional (a mirror member with no record is in the directory but not employed here:
-- an external accountant, a portal customer). Job title, phone, timezone and departments are
-- DIRECTORY facts and stay in the kernel; HR changes them through the directory API, never here.
-- Pay lives only in compensation_changes: the rate in force is the newest change on or before
-- the day asked about (hr_pay_as_of). Bank details are not stored: HR keeps no secrets store,
-- and paying is the accountant's system's job.
BEGIN;

CREATE TABLE employment_profiles (
    member_id            bigint PRIMARY KEY REFERENCES members(id) ON DELETE RESTRICT,
    employee_no          text UNIQUE,
    employment_type      text NOT NULL DEFAULT 'employee'
                             CHECK (employment_type IN ('employee', 'contractor', 'intern')),
    manager_member_id    bigint REFERENCES members(id) ON DELETE SET NULL,
    work_location        text,
    start_date           date,
    probation_end_date   date,
    end_date             date,
    end_reason           text,
    status               text NOT NULL DEFAULT 'active'
                             CHECK (status IN ('candidate', 'active', 'on_leave', 'ended')),
    weekly_hours         numeric(5,2) CHECK (weekly_hours > 0),
    pay_schedule         text CHECK (pay_schedule IN ('weekly', 'biweekly', 'semimonthly', 'monthly')),
    personal_email       citext,
    address              text,
    emergency_contact    text,
    birth_date           date,
    national_id_last4    text CHECK (national_id_last4 IS NULL OR national_id_last4 ~ '^[0-9A-Za-z]{2,4}$'),
    notes                text,
    created_by           bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at           timestamptz NOT NULL DEFAULT now(),
    updated_at           timestamptz NOT NULL DEFAULT now(),
    CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date),
    CHECK (manager_member_id IS NULL OR manager_member_id <> member_id)
);
CREATE INDEX employment_profiles_manager_idx ON employment_profiles (manager_member_id);
CREATE INDEX employment_profiles_status_idx  ON employment_profiles (status);
CREATE INDEX employment_profiles_start_idx   ON employment_profiles (start_date);
CREATE INDEX employment_profiles_end_idx     ON employment_profiles (end_date);
CREATE TRIGGER employment_profiles_touch BEFORE UPDATE ON employment_profiles FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- An agent is not an employee: employment, pay and leave are for people.
CREATE OR REPLACE FUNCTION employment_profiles_humans_only() RETURNS trigger
    LANGUAGE plpgsql AS $$
BEGIN
    IF (SELECT member_kind FROM members WHERE id = NEW.member_id) IS DISTINCT FROM 'human' THEN
        RAISE EXCEPTION 'An agent is not an employee: employment, pay and leave are for people.'
            USING ERRCODE = 'P0001';
    END IF;
    RETURN NEW;
END$$;
CREATE TRIGGER employment_profiles_humans_only BEFORE INSERT OR UPDATE OF member_id ON employment_profiles
    FOR EACH ROW EXECUTE FUNCTION employment_profiles_humans_only();

-- Every change to what someone is paid, kept forever. per_invoice = a contractor paid on
-- invoices: on no pay run, but the rate is still the record.
CREATE TABLE compensation_changes (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    member_id       bigint NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    effective_date  date NOT NULL,
    pay_type        text NOT NULL CHECK (pay_type IN ('salary', 'hourly', 'daily', 'per_invoice')),
    pay_rate        numeric(14,2) NOT NULL CHECK (pay_rate >= 0),      -- a salary is per year
    currency        char(3) NOT NULL,
    reason          text,
    created_by      bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (member_id, effective_date)
);
CREATE INDEX compensation_changes_member_idx ON compensation_changes (member_id, effective_date DESC);
CREATE TRIGGER compensation_changes_humans_only BEFORE INSERT OR UPDATE OF member_id ON compensation_changes
    FOR EACH ROW EXECUTE FUNCTION employment_profiles_humans_only();

-- Who may see a person and their pay (the rules, in one place; db/009 builds views on them).
CREATE OR REPLACE FUNCTION hr_is_manager_of(p_member_id bigint) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_insider() AND EXISTS (
        SELECT 1 FROM employment_profiles e
         WHERE e.member_id = p_member_id AND e.manager_member_id = app_current_member_id());
$$;

CREATE OR REPLACE FUNCTION hr_can_see_person(p_member_id bigint) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_active_member() AND (
           app_member_kind() = 'agent'
        OR p_member_id = app_current_member_id()
        OR hr_is_staff()
        OR hr_is_manager_of(p_member_id)
        OR app_can_admin_member(p_member_id));
$$;

CREATE OR REPLACE FUNCTION hr_can_see_pay(p_member_id bigint) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_active_member() AND app_member_kind() = 'human' AND (
           p_member_id = app_current_member_id()
        OR hr_is_staff()
        OR hr_is_manager_of(p_member_id));
$$;

GRANT EXECUTE ON FUNCTION hr_is_manager_of(bigint), hr_can_see_person(bigint), hr_can_see_pay(bigint)
    TO hr_rw, hr_records_ro, hr_activity_ro;

-- The rate in force for a person on a day: the newest change on or before it.
CREATE OR REPLACE FUNCTION hr_pay_as_of(p_member_id bigint, p_date date)
    RETURNS TABLE (compensation_change_id bigint, effective_date date, pay_type text,
                   pay_rate numeric, currency char(3))
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT c.id, c.effective_date, c.pay_type, c.pay_rate, c.currency
      FROM compensation_changes c
     WHERE c.member_id = p_member_id AND c.effective_date <= p_date
       AND hr_can_see_pay(p_member_id)                     -- the gate travels with the function
     ORDER BY c.effective_date DESC
     LIMIT 1;
$$;
GRANT EXECUTE ON FUNCTION hr_pay_as_of(bigint, date) TO hr_rw, hr_records_ro;

GRANT SELECT, INSERT, UPDATE, DELETE ON employment_profiles, compensation_changes TO hr_rw;

COMMIT;
