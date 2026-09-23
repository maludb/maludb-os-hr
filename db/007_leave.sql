-- 007: leave. The owner's rule (2026-09-19, decision 12): the year's allowance is granted UP
-- FRONT (1 January, or pro-rated from the start date, rounded down to the half day); approved
-- leave draws it down; carry-over is a manual HR adjustment; nothing carries by itself.
BEGIN;

CREATE TABLE leave_types (
    id                      bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name                    text NOT NULL UNIQUE,
    is_paid                 boolean NOT NULL DEFAULT true,
    accrual_days_per_year   numeric(5,2) CHECK (accrual_days_per_year >= 0),   -- NULL = not limited
    carry_over_days         numeric(5,2) NOT NULL DEFAULT 0 CHECK (carry_over_days >= 0),
    requires_approval       boolean NOT NULL DEFAULT true,
    archived_at             timestamptz,
    created_at              timestamptz NOT NULL DEFAULT now(),
    updated_at              timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER leave_types_touch BEFORE UPDATE ON leave_types FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

CREATE TABLE leave_balances (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    member_id      bigint NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    leave_type_id  bigint NOT NULL REFERENCES leave_types(id) ON DELETE CASCADE,
    year           integer NOT NULL CHECK (year BETWEEN 2000 AND 2200),
    accrued_days   numeric(6,2) NOT NULL DEFAULT 0 CHECK (accrued_days >= 0),
    carried_days   numeric(6,2) NOT NULL DEFAULT 0 CHECK (carried_days >= 0),
    used_days      numeric(6,2) NOT NULL DEFAULT 0 CHECK (used_days >= 0),
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now(),
    UNIQUE (member_id, leave_type_id, year)
);
CREATE INDEX leave_balances_type_idx ON leave_balances (leave_type_id);
CREATE TRIGGER leave_balances_touch BEFORE UPDATE ON leave_balances FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE TRIGGER leave_balances_humans_only BEFORE INSERT OR UPDATE OF member_id ON leave_balances
    FOR EACH ROW EXECUTE FUNCTION employment_profiles_humans_only();

CREATE TABLE leave_requests (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    member_id       bigint NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    leave_type_id   bigint NOT NULL REFERENCES leave_types(id) ON DELETE RESTRICT,
    start_date      date NOT NULL,
    end_date        date NOT NULL,
    days            numeric(6,2) NOT NULL CHECK (days > 0),
    half_day        boolean NOT NULL DEFAULT false,
    reason          text,
    status          text NOT NULL DEFAULT 'requested'
                        CHECK (status IN ('requested', 'approved', 'rejected', 'cancelled')),
    over_balance    boolean NOT NULL DEFAULT false,          -- flagged, never refused, when requested
    has_overlap     boolean NOT NULL DEFAULT false,          -- another live request covers a day of it
    decided_by      bigint REFERENCES members(id) ON DELETE SET NULL,
    decided_at      timestamptz,
    decision_note   text,
    created_by      bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    CHECK (end_date >= start_date),
    CHECK (EXTRACT(year FROM start_date) = EXTRACT(year FROM end_date)),   -- one calendar year
    CHECK (NOT half_day OR start_date = end_date)
);
CREATE INDEX leave_requests_member_idx  ON leave_requests (member_id, start_date DESC);
CREATE INDEX leave_requests_pending_idx ON leave_requests (status, start_date) WHERE status = 'requested';
CREATE INDEX leave_requests_dates_idx   ON leave_requests (start_date, end_date) WHERE status IN ('requested', 'approved');
CREATE TRIGGER leave_requests_touch BEFORE UPDATE ON leave_requests FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE TRIGGER leave_requests_humans_only BEFORE INSERT OR UPDATE OF member_id ON leave_requests
    FOR EACH ROW EXECUTE FUNCTION employment_profiles_humans_only();

-- Leave days between two dates: the business's work days, less its holidays; a half day is 0.5.
CREATE OR REPLACE FUNCTION hr_leave_days(p_start date, p_end date, p_half_day boolean DEFAULT false)
    RETURNS numeric LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT CASE WHEN p_half_day THEN 0.5 ELSE
        (SELECT count(*)::numeric FROM generate_series(p_start, p_end, interval '1 day') g(d)
          WHERE EXTRACT(isodow FROM g.d)::smallint = ANY (ARRAY(SELECT unnest(work_days) FROM hr_settings WHERE id = 1))
            AND NOT EXISTS (SELECT 1 FROM holidays h WHERE h.holiday_date = g.d::date))
    END;
$$;

-- The year's allowance for one person and type: full, or pro-rated from a start date inside the
-- year (allowance x days left / days in the year), rounded DOWN to the half day.
CREATE OR REPLACE FUNCTION hr_annual_allowance(p_member_id bigint, p_leave_type_id bigint, p_year integer)
    RETURNS numeric LANGUAGE sql STABLE AS $$
    SELECT CASE
        WHEN lt.accrual_days_per_year IS NULL THEN NULL
        WHEN e.start_date IS NULL OR e.start_date <= make_date(p_year, 1, 1) THEN lt.accrual_days_per_year
        WHEN e.start_date > make_date(p_year, 12, 31) THEN 0
        ELSE floor(lt.accrual_days_per_year
                   * (make_date(p_year, 12, 31) - e.start_date + 1)::numeric
                   / (make_date(p_year, 12, 31) - make_date(p_year, 1, 1) + 1)::numeric * 2) / 2
    END
    FROM leave_types lt
    LEFT JOIN employment_profiles e ON e.member_id = p_member_id
    WHERE lt.id = p_leave_type_id;
$$;

-- Grant the year's balances to one person for every accruing type; idempotent.
CREATE OR REPLACE FUNCTION hr_grant_annual_leave(p_member_id bigint, p_year integer) RETURNS integer
    LANGUAGE plpgsql AS $$
DECLARE n integer;
BEGIN
    INSERT INTO leave_balances (member_id, leave_type_id, year, accrued_days)
    SELECT p_member_id, lt.id, p_year, COALESCE(hr_annual_allowance(p_member_id, lt.id, p_year), 0)
      FROM leave_types lt
     WHERE lt.archived_at IS NULL AND lt.accrual_days_per_year IS NOT NULL
    ON CONFLICT (member_id, leave_type_id, year) DO NOTHING;
    GET DIAGNOSTICS n = ROW_COUNT;
    RETURN n;
END$$;

-- Days of leave already committed (requested or approved) in a year for a person and type.
CREATE OR REPLACE FUNCTION hr_leave_committed(p_member_id bigint, p_leave_type_id bigint, p_year integer)
    RETURNS numeric LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT COALESCE(sum(days), 0) FROM leave_requests
     WHERE member_id = p_member_id AND leave_type_id = p_leave_type_id
       AND status IN ('requested', 'approved') AND EXTRACT(year FROM start_date) = p_year
       AND hr_can_see_person(p_member_id);
$$;

GRANT EXECUTE ON FUNCTION hr_leave_days(date, date, boolean), hr_annual_allowance(bigint, bigint, integer),
    hr_grant_annual_leave(bigint, integer), hr_leave_committed(bigint, bigint, integer) TO hr_rw;
GRANT EXECUTE ON FUNCTION hr_leave_days(date, date, boolean), hr_leave_committed(bigint, bigint, integer) TO hr_records_ro;

INSERT INTO leave_types (name, is_paid, accrual_days_per_year, requires_approval) VALUES
    ('Paid time off', true, 20, true),
    ('Sick leave',    true, 10, false),
    ('Unpaid leave',  false, NULL, true)
ON CONFLICT (name) DO NOTHING;

GRANT SELECT, INSERT, UPDATE, DELETE ON leave_types, leave_balances, leave_requests TO hr_rw;

COMMIT;
