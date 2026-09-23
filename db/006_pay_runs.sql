-- 006: pay runs AS RECORDS. Statutory payroll is out of scope: no tax is calculated, nothing is
-- filed, no money moves, no bank file. A run records what the business decided to pay for a
-- period; the accountant's figures are keyed in as lines; the accounting application (optional)
-- pulls paid runs through the token API (hr.pay-runs/1). Nothing is posted anywhere.
BEGIN;

CREATE SEQUENCE pay_run_no_seq START 1;

CREATE TABLE pay_runs (
    id                   bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    run_no               text NOT NULL UNIQUE DEFAULT ('PR-' || lpad(nextval('pay_run_no_seq')::text, 4, '0')),
    period_start         date NOT NULL,
    period_end           date NOT NULL,
    pay_date             date NOT NULL,
    currency             char(3) NOT NULL,
    status               text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'approved', 'paid', 'void')),
    gross_total          numeric(16,2) NOT NULL DEFAULT 0,
    deductions_total     numeric(16,2) NOT NULL DEFAULT 0,
    employer_cost_total  numeric(16,2) NOT NULL DEFAULT 0,
    reimbursements_total numeric(16,2) NOT NULL DEFAULT 0,
    net_total            numeric(16,2) NOT NULL DEFAULT 0,
    note                 text,
    approved_by          bigint REFERENCES members(id) ON DELETE SET NULL,
    approved_at          timestamptz,
    paid_by              bigint REFERENCES members(id) ON DELETE SET NULL,
    paid_at              timestamptz,
    voided_by            bigint REFERENCES members(id) ON DELETE SET NULL,
    voided_at            timestamptz,
    void_reason          text,
    created_by           bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at           timestamptz NOT NULL DEFAULT now(),
    updated_at           timestamptz NOT NULL DEFAULT now(),
    CHECK (period_end >= period_start)
);
CREATE INDEX pay_runs_period_idx ON pay_runs (period_end DESC);
CREATE INDEX pay_runs_status_idx ON pay_runs (status);
CREATE TRIGGER pay_runs_touch BEFORE UPDATE ON pay_runs FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

CREATE TABLE pay_run_items (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    pay_run_id      bigint NOT NULL REFERENCES pay_runs(id) ON DELETE CASCADE,
    member_id       bigint NOT NULL REFERENCES members(id) ON DELETE RESTRICT,
    hours           numeric(8,2) CHECK (hours >= 0),            -- hourly people: keyed on the item
    base_note       text,                                        -- why there is no base line, when there is none
    gross_amount    numeric(14,2) NOT NULL DEFAULT 0,
    deductions      numeric(14,2) NOT NULL DEFAULT 0,
    employer_cost   numeric(14,2) NOT NULL DEFAULT 0,
    reimbursements  numeric(14,2) NOT NULL DEFAULT 0,
    net_amount      numeric(14,2) NOT NULL DEFAULT 0,
    note            text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (pay_run_id, member_id)
);
CREATE INDEX pay_run_items_member_idx ON pay_run_items (member_id);
CREATE TRIGGER pay_run_items_touch BEFORE UPDATE ON pay_run_items FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE TRIGGER pay_run_items_humans_only BEFORE INSERT OR UPDATE OF member_id ON pay_run_items
    FOR EACH ROW EXECUTE FUNCTION employment_profiles_humans_only();

CREATE TABLE pay_run_lines (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    pay_run_item_id   bigint NOT NULL REFERENCES pay_run_items(id) ON DELETE CASCADE,
    kind              text NOT NULL CHECK (kind IN ('earning', 'deduction', 'employer_cost', 'reimbursement')),
    code              text NOT NULL,                             -- base, overtime, bonus, tax, pension, expenses
    description       text,
    quantity          numeric(10,2),
    rate              numeric(14,4),
    amount            numeric(14,2) NOT NULL CHECK (amount >= 0),
    is_base           boolean NOT NULL DEFAULT false,            -- computed from the pay basis, not keyed
    created_at        timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX pay_run_lines_item_idx ON pay_run_lines (pay_run_item_id);

-- Totals are derived, always: the item from its lines, the run from its items.
CREATE OR REPLACE FUNCTION hr_recompute_pay_run_item(p_item_id bigint) RETURNS void
    LANGUAGE plpgsql AS $$
DECLARE v_run bigint;
BEGIN
    UPDATE pay_run_items i SET
        gross_amount   = COALESCE((SELECT sum(amount) FROM pay_run_lines l WHERE l.pay_run_item_id = i.id AND l.kind = 'earning'), 0),
        deductions     = COALESCE((SELECT sum(amount) FROM pay_run_lines l WHERE l.pay_run_item_id = i.id AND l.kind = 'deduction'), 0),
        employer_cost  = COALESCE((SELECT sum(amount) FROM pay_run_lines l WHERE l.pay_run_item_id = i.id AND l.kind = 'employer_cost'), 0),
        reimbursements = COALESCE((SELECT sum(amount) FROM pay_run_lines l WHERE l.pay_run_item_id = i.id AND l.kind = 'reimbursement'), 0)
     WHERE i.id = p_item_id
     RETURNING pay_run_id INTO v_run;
    UPDATE pay_run_items SET net_amount = gross_amount + reimbursements - deductions WHERE id = p_item_id;
    IF v_run IS NOT NULL THEN
        PERFORM hr_recompute_pay_run(v_run);
    END IF;
END$$;

CREATE OR REPLACE FUNCTION hr_recompute_pay_run(p_run_id bigint) RETURNS void
    LANGUAGE sql AS $$
    UPDATE pay_runs r SET
        gross_total          = COALESCE((SELECT sum(gross_amount)   FROM pay_run_items i WHERE i.pay_run_id = r.id), 0),
        deductions_total     = COALESCE((SELECT sum(deductions)     FROM pay_run_items i WHERE i.pay_run_id = r.id), 0),
        employer_cost_total  = COALESCE((SELECT sum(employer_cost)  FROM pay_run_items i WHERE i.pay_run_id = r.id), 0),
        reimbursements_total = COALESCE((SELECT sum(reimbursements) FROM pay_run_items i WHERE i.pay_run_id = r.id), 0),
        net_total            = COALESCE((SELECT sum(net_amount)     FROM pay_run_items i WHERE i.pay_run_id = r.id), 0)
     WHERE r.id = p_run_id;
$$;

CREATE OR REPLACE FUNCTION pay_run_lines_recompute() RETURNS trigger
    LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        PERFORM hr_recompute_pay_run_item(OLD.pay_run_item_id);
        RETURN OLD;
    END IF;
    PERFORM hr_recompute_pay_run_item(NEW.pay_run_item_id);
    RETURN NEW;
END$$;
CREATE TRIGGER pay_run_lines_recompute AFTER INSERT OR UPDATE OR DELETE ON pay_run_lines
    FOR EACH ROW EXECUTE FUNCTION pay_run_lines_recompute();

CREATE OR REPLACE FUNCTION pay_run_items_recompute() RETURNS trigger
    LANGUAGE plpgsql AS $$
BEGIN
    PERFORM hr_recompute_pay_run(COALESCE(NEW.pay_run_id, OLD.pay_run_id));
    RETURN NULL;
END$$;
CREATE TRIGGER pay_run_items_recompute AFTER INSERT OR DELETE ON pay_run_items
    FOR EACH ROW EXECUTE FUNCTION pay_run_items_recompute();

-- A run that is not a draft is frozen: only its status columns move.
CREATE OR REPLACE FUNCTION pay_run_frozen_guard() RETURNS trigger
    LANGUAGE plpgsql AS $$
DECLARE
    v_status text;
    v_key bigint;
BEGIN
    IF TG_TABLE_NAME = 'pay_run_lines' THEN
        v_key := CASE WHEN TG_OP = 'DELETE' THEN OLD.pay_run_item_id ELSE NEW.pay_run_item_id END;
        SELECT r.status INTO v_status FROM pay_run_items i JOIN pay_runs r ON r.id = i.pay_run_id
         WHERE i.id = v_key;
    ELSE
        v_key := CASE WHEN TG_OP = 'DELETE' THEN OLD.pay_run_id ELSE NEW.pay_run_id END;
        SELECT r.status INTO v_status FROM pay_runs r WHERE r.id = v_key;
    END IF;
    IF v_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'This pay run is %; only a draft can be changed.', v_status USING ERRCODE = 'P0001';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END$$;
CREATE TRIGGER pay_run_items_frozen BEFORE INSERT OR DELETE ON pay_run_items
    FOR EACH ROW EXECUTE FUNCTION pay_run_frozen_guard();
CREATE TRIGGER pay_run_items_frozen_update BEFORE UPDATE OF hours, note, base_note, member_id ON pay_run_items
    FOR EACH ROW EXECUTE FUNCTION pay_run_frozen_guard();
CREATE TRIGGER pay_run_lines_frozen BEFORE INSERT OR UPDATE OR DELETE ON pay_run_lines
    FOR EACH ROW EXECUTE FUNCTION pay_run_frozen_guard();

GRANT SELECT, INSERT, UPDATE, DELETE ON pay_runs, pay_run_items, pay_run_lines TO hr_rw;
GRANT USAGE, SELECT ON SEQUENCE pay_run_no_seq, employee_no_seq TO hr_rw;

COMMIT;
