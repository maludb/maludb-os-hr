# Roles and rights — HR's own, granted by the kernel (2026-09-27)

The owner's decision: the Business OS assigns rights **inside** an application. HR says which roles it
offers and what each lets its holder do; the kernel's super-admin grants a person any **set** of those
roles; HR enforces them. Kernel side: `/var/www/docs/build-specs/kernel-application-roles.md` (db/145);
the contract: `maludb-os-integration` 0.4.0, `references/roles-and-rights.md`.

## The catalogue (db/012)

| Role | Name | Amounts to | Rights |
|---|---|---|---|
| `employee` | Employee | read | directory.read, self.read, leave.request |
| `manager` | Manager | write | + team.manage |
| `payroll` | Payroll | write | directory.read, self.read, leave.request, pay.read, pay.write |
| `hr_admin` | HR Admin (the admin role; a super-admin holds it) | admin | all seven, including hr.staff |

| Right | What it lets them do |
|---|---|
| `directory.read` | See the directory, departments, leave types and holidays |
| `self.read` | See their own employment, pay, leave and shared reviews |
| `leave.request` | Request their own leave |
| `team.manage` | Manage the people in the departments they administer: employment, pay and leave, leave decisions, reviews |
| `pay.read` | See everyone's pay |
| `pay.write` | Record pay changes; prepare, approve and pay pay runs |
| `hr.staff` | Run HR — everything |

The first three are what every admitted person could already do; they are listed so a role reads whole.

## How it flows

1. **Publish.** `app_roles` on the records MCP server answers `os.app-roles/1` from `mcp_app_roles` and
   `hr_rights`. It takes no arguments. The kernel's own token (`kernel.{exp}.hr.{nonce}.{hmac}`, 60 s,
   `db.verify_kernel_token()`) is admitted to this tool and nothing else (`server_common.KERNEL_TOOLS`);
   a person or agent may call it too.
2. **Grant.** In the kernel: *Read roles from the application* on HR's Access tab, then grants tick roles.
3. **Receive.** The hand-off claims carry `roles` (`html/sso.php` → `mirror_apply_roles()`); the change
   feed's `access[]` carries each affected member's whole holding (`mirror_apply_access()`: capability
   and roles; nothing left = no HR and every session ended). HR applied `access[]` for the first time
   here — before, a revocation reached HR only at the person's next sign-in.
4. **Enforce.** `hr_has_right(right)` (super-admin: every right). The rule functions ask for rights:
   `hr_is_staff()` = hr.staff; `hr_is_manager_of(p)` = the employment record's manager **or**
   team.manage over a department they administer (the owner's "both"); `hr_can_see_pay(p)` adds
   pay.read; `hr_can_run_pay()` (new) = pay.write or staff — the pay-run views, the pay screens and
   handlers (`require_pay()`), the Pay runs menu and the Pay tab's controls. Reviews: a Manager
   schedules for the people they manage (`is_manager_of()`).

**Before the kernel sends roles** (`members.roles = '{}'`), capability `admin` still reads as HR Admin,
so nothing changes for anyone until a grant names roles.

## Built and proven (2026-09-27)

- The kernel read HR's four roles and seven rights through `app_roles` with its token; a wrong
  application key and any other tool were refused.
- Kernel smoke `mcp/smoke/30-hr-roles.json` (16/16): Payroll granted → the claims and HR's mirror say
  `{payroll}`, `hr_can_run_pay()` and `hr_can_see_pay(1)` true, `hr_is_staff()` false; changed to
  Employee → both false; revoked → HR holds nothing for the person.
- Manager (rolled back): administering department 4, which member 1 is in, `hr_is_manager_of(1)` and
  `hr_can_see_pay(1)` are true with `{manager}` and false with `{employee}`.
- A real hand-off for member 1 wrote `{hr_admin}` into the mirror.
- The kernel's grants 19, 20 and 47 (admin, no role) were changed to HR Admin — the same rights,
  now named. The HR expert agent's own grant (21, write, no role) is unchanged: an agent's reach in HR
  is by kind and by the tools the kernel grants, not by role.
