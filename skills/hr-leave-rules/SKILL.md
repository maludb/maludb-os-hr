---
name: hr-leave-rules
description: The rules HR applies to leave — the annual grant, pro-rating, work days and holidays, half days, over-balance and overlap flags, who may decide, and how an agent should read a request before recommending.
---

# Leave rules

- **Granted up front.** Each leave type with an allowance grants the whole year's days on 1 January,
  or pro-rated from the start date for someone who starts during the year (allowance × days left ÷
  days in the year, rounded DOWN to the half day). Types with no allowance (unpaid leave) are not
  limited. Carry-over never happens by itself: HR adjusts a balance by hand (`leave_balance_adjust`).
- **Days** are the business's work days (`hr_settings.work_days`, ISO weekdays) between the dates,
  less holidays. A half day is 0.5 on one date. A request stays within one calendar year.
- **Balance** shows `remaining` (accrued + carried − used) and `committed` (requested + approved this
  year). Approved leave moves days into `used`; cancelling approved leave that has not started gives
  them back.
- **Flags, not refusals.** A request beyond the balance is recorded with `over_balance`; one that
  overlaps another live request with `has_overlap`. Approving beyond the balance is refused unless
  HR staff pass `override`.
- **Who decides**: the person's manager, an administrator of their department, HR staff, the
  super-admin — never the requester. A type with `requires_approval = false` (sick leave) is approved
  as it is recorded.
- **Reading a request before recommending**: the balance for that type and year; the flags; who else in
  the department is off on those dates (`whos_off`); whether the type needs approval at all.
- **An agent's decision pauses** for a person (`leave_request_decide` carries an approval category).
  Record, check, recommend; decide only when told to, and say who the decision waits for.
