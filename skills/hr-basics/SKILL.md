---
name: hr-basics
description: How the HR application is organised and how to answer people questions with its tools — the directory versus employment, who may see what, the order of a typical lookup, and which actions pause for approval.
---

# HR basics

## Two things that look alike
- **The directory** is the kernel's: who exists, name, email, role (super_admin, dept_admin, user),
  external or not, status, job title, phone, timezone, departments and who administers each. HR mirrors
  it and shows it. Changing it goes through the kernel (`person_directory_update`, `membership_save`,
  `department_create`, `department_update`, `person_invite`) as the acting person; the kernel may refuse.
- **Employment** is HR's: the employment record (`employment_save`, `employment_end`), pay changes,
  pay runs, leave, reviews.

A person can be in the directory with no employment record (the outside accountant, a customer with
portal access). `find_people` with `employed=false` lists them.

## Who may see what (the tools already enforce it — this is so you can explain a refusal)
- Anyone admitted to HR sees the directory, the departments, leave types, holidays and settings.
- A person sees their own employment, pay, leave and shared reviews.
- A manager (named on the employment record) sees their reports' employment, pay and leave.
- A department administrator sees the employment (not pay) and leave of the people in their departments.
- HR staff (the HR application at capability `admin`) and the super-admin see everything.
- Agents see employment, departments and leave — never pay, never reviews, never home contact details.

## The order of a lookup
1. `find_people` (a name, an email, an employee number, or a department) → the `member_id`.
2. `get_person` for the whole picture; `record_history` when someone asks what changed and when.
3. Never guess an id. Two matches → ask one short question. No match → say so.

## Actions that pause for a person
Ending employment, any pay change, deciding leave, adjusting balances, approving or paying a run,
completing a review, deleting anything, and any directory change when the business's policy says so.
When a result is `pending_approval`, report who it waits for and stop.

## Vocabulary
`employee_no` (E-0001), `employment_type` (employee, contractor, intern), `employment status`
(candidate, active, on_leave, ended), `pay_schedule` (weekly, biweekly, semimonthly, monthly), `pay_type`
(salary per year, hourly, daily, per_invoice), `pay run` status (draft, approved, paid, void), leave
`remaining` = accrued + carried − used, `committed` = requested + approved this year.
