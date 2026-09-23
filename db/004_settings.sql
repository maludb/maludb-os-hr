-- 004: the business's HR settings (one row) and its public holidays.
BEGIN;

CREATE TABLE hr_settings (
    id                 smallint PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    currency           char(3) NOT NULL DEFAULT 'USD',
    timezone           text NOT NULL DEFAULT 'UTC',
    work_days          smallint[] NOT NULL DEFAULT '{1,2,3,4,5}',   -- ISO weekdays that count as leave days
    employee_no_prefix text NOT NULL DEFAULT 'E-',
    leave_year_start_month smallint NOT NULL DEFAULT 1 CHECK (leave_year_start_month BETWEEN 1 AND 12),
    updated_by         bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at         timestamptz NOT NULL DEFAULT now(),
    updated_at         timestamptz NOT NULL DEFAULT now(),
    CHECK (array_length(work_days, 1) BETWEEN 1 AND 7)
);
INSERT INTO hr_settings (id) VALUES (1) ON CONFLICT DO NOTHING;
CREATE TRIGGER hr_settings_touch BEFORE UPDATE ON hr_settings FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

CREATE TABLE holidays (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    holiday_date  date NOT NULL UNIQUE,
    name          text NOT NULL,
    created_by    bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER holidays_touch BEFORE UPDATE ON holidays FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

CREATE SEQUENCE employee_no_seq START 1;

GRANT SELECT, UPDATE ON hr_settings TO hr_rw;
GRANT SELECT, INSERT, UPDATE, DELETE ON holidays TO hr_rw;

COMMIT;
