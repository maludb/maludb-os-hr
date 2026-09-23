-- 010: ADDITIVE. The frozen-run guard (db/006) refused the cascade of a deleted draft: when a run or
-- an item is deleted, its lines are deleted after the parent row is gone, the guard finds no status
-- and raised "This pay run is <NULL>". A parent that no longer exists is being deleted with its
-- children; the guard lets that through and keeps refusing changes to approved, paid and void runs.
BEGIN;

CREATE OR REPLACE FUNCTION pay_run_frozen_guard() RETURNS trigger
    LANGUAGE plpgsql AS $$
DECLARE
    v_status text;
    v_key bigint;
    v_found boolean;
BEGIN
    IF TG_TABLE_NAME = 'pay_run_lines' THEN
        v_key := CASE WHEN TG_OP = 'DELETE' THEN OLD.pay_run_item_id ELSE NEW.pay_run_item_id END;
        SELECT r.status, true INTO v_status, v_found FROM pay_run_items i JOIN pay_runs r ON r.id = i.pay_run_id
         WHERE i.id = v_key;
    ELSE
        v_key := CASE WHEN TG_OP = 'DELETE' THEN OLD.pay_run_id ELSE NEW.pay_run_id END;
        SELECT r.status, true INTO v_status, v_found FROM pay_runs r WHERE r.id = v_key;
    END IF;
    IF v_found IS NULL THEN
        -- the parent is gone: this is its cascade (a deleted draft), not a change to a frozen run
        IF TG_OP = 'DELETE' THEN
            RETURN OLD;
        END IF;
        RAISE EXCEPTION 'This pay run does not exist.' USING ERRCODE = 'P0001';
    END IF;
    IF v_status <> 'draft' THEN
        RAISE EXCEPTION 'This pay run is %; only a draft can be changed.', v_status USING ERRCODE = 'P0001';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END$$;

COMMIT;
