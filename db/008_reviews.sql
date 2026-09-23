-- 008: performance reviews for people. A review is scheduled (by HR or the manager), written by
-- the reviewer, completed, and optionally shared with the person, who may add a comment. Agents'
-- reviews are the kernel's (Agent HR); this table refuses agents.
BEGIN;

CREATE TABLE reviews (
    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    member_id           bigint NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    reviewer_member_id  bigint NOT NULL REFERENCES members(id) ON DELETE RESTRICT,
    kind                text NOT NULL DEFAULT 'annual' CHECK (kind IN ('annual', 'probation', 'quarterly', 'other')),
    period_start        date,
    period_end          date,
    due_date            date NOT NULL,
    status              text NOT NULL DEFAULT 'scheduled'
                            CHECK (status IN ('scheduled', 'in_progress', 'completed', 'cancelled')),
    rating              smallint CHECK (rating BETWEEN 1 AND 5),
    summary             text,
    strengths           text,
    improvements        text,
    goals               text,
    employee_comment    text,
    employee_commented_at timestamptz,
    shared_at           timestamptz,
    completed_at        timestamptz,
    cancelled_at        timestamptz,
    cancel_reason       text,
    created_by          bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at          timestamptz NOT NULL DEFAULT now(),
    updated_at          timestamptz NOT NULL DEFAULT now(),
    CHECK (period_end IS NULL OR period_start IS NULL OR period_end >= period_start),
    CHECK (reviewer_member_id <> member_id)
);
CREATE INDEX reviews_member_idx   ON reviews (member_id, due_date DESC);
CREATE INDEX reviews_reviewer_idx ON reviews (reviewer_member_id, status);
CREATE INDEX reviews_due_idx      ON reviews (due_date) WHERE status IN ('scheduled', 'in_progress');
CREATE TRIGGER reviews_touch BEFORE UPDATE ON reviews FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE TRIGGER reviews_humans_only BEFORE INSERT OR UPDATE OF member_id ON reviews
    FOR EACH ROW EXECUTE FUNCTION employment_profiles_humans_only();

GRANT SELECT, INSERT, UPDATE, DELETE ON reviews TO hr_rw;

COMMIT;
