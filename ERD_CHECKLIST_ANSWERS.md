# FINAL ERD SYSTEM INFORMATION CHECKLIST — Answers

System: **Iznahanyachay Trucking Services — Employee & Payroll System**
Stack: PHP 8.1 (procedural, no framework) + MySQL/MariaDB
Answered: 2026-09-22, verified against the live database schema and the actual source code.
Revised: 2026-09-25 — Section L updated after payroll reporting was built. That change adds no
tables and does not affect the data model or any other answer.

> **Read this first — three structural notes that affect the ERD.**
>
> 1. **There is no Overtime table, no Incentive table, and no Pay Period table.** The checklist
>    asks about all three as if they were separate entities. In this system they are *derived
>    values*, not stored records. Drawing them as entities would produce an ERD that does not
>    match the database.
> 2. **The checklist never asks about Routes or Trips**, which are the core of this system.
>    A trucking company pays drivers and helpers per delivered trip, not by the hour. Those two
>    tables, plus trip attendance, are answered in **Section N (Q47)** and must appear in the ERD.
> 3. **There are two different pay models in one system.** Drivers and helpers are paid a
>    commission on completed trips and never file a timesheet. Everyone else is paid hourly from
>    approved timesheets. Several answers below differ depending on which of the two applies.

---

## A. EMPLOYEE INFORMATION

**1. May employee information ba ang system?**
☑ Yes

**2. Anong employee information ang currently stored?**

☑ Employee ID/Employee Number — *the `users.id` primary key; there is no separate company-issued employee number*
☑ Full Name — *stored as one `name` field*
☑ Address
☑ Contact Number
☑ Email Address
☑ Position/Job Title
☑ Employment Status
☑ Salary/Salary Rate — *stored, but see the note below*
☑ Date Hired
☑ Other: **Driver's license number** and **license expiry date** (this is a trucking company, so licence validity is tracked), and **rest days**

☐ First Name ☐ Middle Name ☐ Last Name — **not stored separately.** There is a single `name` field only.

> **Important caveat on salary:** `monthly_salary` exists on the employee record and can be set by
> the administrator, but **it is not used in any payroll computation.** Hourly pay uses fixed
> system-wide rates (₱100/hr regular, ₱110/hr overtime) and driver/helper pay uses a percentage of
> the route rate. The stored monthly salary is currently reference information only.

**3. May unique identifier ba ang bawat employee?**
☑ Yes
If yes, ano? **`users.id`** — an auto-incrementing integer primary key. **`users.email`** is also
enforced as unique and is what the employee actually logs in with.

---

## B. USER ACCOUNT / LOGIN

**4. May login/account functionality ba?**
☑ Yes

**5. Sino ang may system account?**
☑ Both Administrator and Employee
*Only two roles exist: `admin` and `employee`. There is no separate "Management" role.*

**6. Sino ang gumagawa ng employee account?**
☑ Administrator
*Self-registration was built at one point and then deliberately removed. The sign-up route now
just redirects to the login page. Accounts are created only by an admin through an invite form.*

**7. Anong account information ang stored?**
☑ Password — *bcrypt-hashed, never stored in plain text*
☑ User ID
☑ Role
☑ Account Status — *on the employee profile, as `active` / `inactive` / `pending`*
☑ Other: **Email address** (used as the login identifier), **forced password-change flag**, **account created date**

☐ Username — **there is no username.** The email address is the login credential.
☐ Employee ID — *not a separate field; the account and the employee are the same record*

> The **forced password-change flag** is worth noting for the ERD's attribute list. Because an
> administrator sets the initial password, every invited account is flagged, and the employee
> cannot reach any page until they replace it on first login.

---

## C. ATTENDANCE

> **This system has two separate and unrelated attendance mechanisms.** Both must appear in the ERD.

### C-1. Timesheet attendance — hourly staff only

**8. May attendance management ba?**
☑ Yes

**9. Ano ang nire-record sa attendance?**
☑ Attendance ID
☑ Employee ID
☑ Date
☑ Time In
☑ Time Out
☑ Approval Status — *`pending` / `approved` / `rejected`*
☑ Remarks — *only as a **rejection reason**, filled in when an entry is rejected*
☑ Other: **Time-in photo** (a webcam photo captured at the moment of time-in and stored as a file path), **entry type**, **soft-delete timestamp**

☐ Hours Worked — **not stored.** Hours are computed from Time In and Time Out whenever needed.
☐ Attendance Status — *there is no present/absent/late field. Absence is inferred from the lack of a record; lateness is not tracked at all.*

**10. Paano nire-record ang attendance?**
☑ Employee enters it
*The employee records their own time in and time out, and a webcam photo is required for time-in.
The administrator can approve, reject or delete an entry, but **cannot create one** — this is
blocked on the server, not merely hidden in the interface, so the person approving hours can never
also create them.*

### C-2. Trip attendance — drivers and helpers only

Drivers and helpers **never file a timesheet.** Their attendance is generated automatically when an
administrator accepts a delivered trip. One row is written per person per trip, so a driver who
completes two trips in a day gets two rows.

Stored: **Attendance ID, Trip ID, Employee ID, Role on that trip (driver or helper), Date.**
There is no time in, no time out, no hours, and no approval status — presence is proven by the
accepted trip itself. The date is taken from the **delivery**, not from when the administrator
accepted it.

---

## D. ATTENDANCE APPROVAL

**11. Kailangan bang ma-approve ang attendance/time entry?**
☑ Yes — *for timesheet entries. **No** for trip attendance, which is verified by the trip acceptance itself.*

**12. Sino ang nag-aapprove?**
☑ Payroll Administrator
*The `admin` role is the only approver. There is no separate supervisor or management role.*

**13. May approval status ba?**
☑ Pending ☑ Approved ☑ Rejected
*A rejected entry also stores a required **rejection reason**. The employee sees a generic
"contact your administrator" message rather than the reason itself.*

**14. May record ba kung sino ang nag-approve?**
☑ Yes — **but only for period-level approval, not per entry.**

This distinction matters for the ERD. There are two separate approval actions:

| Action | Who/when recorded? |
|---|---|
| Approving one entry | Status changes to `approved`. **The approver's identity is not stored.** |
| Approving a whole period | A separate record is written holding **employee, period start, period end, who approved it, and when**. Append-only. |

---

## E. SALARY

**15. May salary/rate information ba ang system?**
☑ Yes

**16. Anong salary information ang ginagamit?**
☑ Monthly Salary — *stored on the employee record, but **not used in any computation** (see A2)*
☑ Hourly Rate — *fixed system-wide constant, ₱100.00/hr*
☑ Other: **Overtime hourly rate** (fixed, ₱110.00/hr) and **per-trip commission rates** — 15% of the route rate for a driver, 8% for a helper

☐ Daily Rate ☐ Basic Salary ☐ Salary Rate — *not used*

**17. Saan naka-associate ang salary?**
☑ Employee — *the unused `monthly_salary` field*
☑ Payroll — *the rates actually used are **copied onto each payroll record** when it is created, so a historical payslip always shows the rate that applied at the time*
☑ Other: **the Route** — a route carries an amount per trip, and that amount is the basis for driver and helper commission

☐ Separate Salary Record — **there is no salary table.**

**18. Maaari bang magbago ang salary/rate ng employee?**
☑ Yes — *an administrator can change `monthly_salary`, and route rates can be edited*

Does the system keep previous salary records?
☐ Yes ☑ **No** — *there is no salary history table. However, two snapshots do preserve historical
values in practice: the hourly rates are written onto every payroll record, and a route's rate is
copied onto a trip at the moment it is assigned, so editing a route later never changes what an
already-assigned trip pays.*

---

## F. OVERTIME

**19. May overtime functionality ba?**
☑ Yes — **but there is no overtime entity. Do not draw one.**

**20. Anong overtime information ang ginagamit?**
☑ Overtime Hours — *computed, then stored on the payroll record*
☑ Overtime Rate — *fixed ₱110.00/hr, stored on the payroll record*
☑ Overtime Pay — *computed as hours × rate*
☑ Employee
☑ Date — *indirectly, through the underlying timesheet entry*

☐ Approval Status — **overtime has no approval of its own** (see Q22)

**21. Ang overtime ay:**
☑ Based on attendance/time records ☑ Automatically calculated

*The rule is applied per day: anything beyond **8 hours in a single day** on an approved timesheet
entry is overtime. It is never entered by anybody. Drivers and helpers have no overtime at all,
since they are not paid by the hour.*

**22. Kailangan bang i-approve ang overtime?**
☐ Yes ☑ **No — not separately.**
*Approving the timesheet entry is what makes its overtime payable. There is no second approval step
specific to overtime, and no approver field for it.*

---

## G. DEDUCTIONS

**23. May deduction functionality ba?**
☑ Yes

**24. Anong deduction information ang ginagamit?**
☑ Deduction Type — *limited to exactly three: **SSS**, **PhilHealth**, **Pag-IBIG***
☑ Deduction Amount
☑ Date
☑ Employee
☑ Payroll Period — *indirectly; a deduction belongs to a payroll record, which carries the period*
☑ Remarks/Description — *a "basis note" recording the bracket the figure came from, e.g. "MSC ₱9,500 (monthly), 5% employee share"*

**25. Sino ang naglalagay ng deductions?**
☑ Automatically generated
*Computed from the 2026 government contribution tables when payroll is run, and halved for a
semi-monthly cutoff. **An administrator cannot add, edit or remove a deduction.** There is no
facility for cash advances, loans or any other manual deduction.*

> **Not implemented:** BIR withholding tax. Only the three statutory contributions above are deducted.

---

## H. INCENTIVES

**26. May incentive functionality ba?**
☑ Yes — **but there is no incentive entity. Do not draw one.**

**27. Anong incentive information ang ginagamit?**
☑ Incentive Amount — *the total commission for the period, stored on the payroll record*
☑ Employee
☑ Payroll Period

☐ Incentive Type — *there is only one kind: trip commission*
☐ Date ☐ Description/Remarks — *not stored on the incentive itself; the underlying trips carry their own dates*

**28. Sino ang naglalagay ng incentives?**
☑ Automatically generated
*Calculated at payroll time as a percentage of the route rate of every trip the employee completed
in the period — 15% for a driver, 8% for a helper. **There is no manual bonus or allowance
facility.** An administrator cannot add an incentive by hand.*

---

## I. PAY PERIOD

**29. May pay period functionality ba?**
☑ Yes — **but there is no pay period entity. Do not draw one.**

A pay period is not a stored record. It is a **start date and end date pair** that appears as two
columns on the payroll record, the payslip, and the period-approval record. There is no table of
periods, no period ID, and no period status.

**30. Anong information ang ginagamit para sa pay period?**
☑ Start Date ☑ End Date

☐ Pay Period ID ☐ Payroll Date ☐ Period Status — *none of these exist*

**31. Ano ang payroll frequency?**
☑ Other: **Not fixed — the administrator chooses the date range.**

*The system does not enforce a frequency. In practice the periods offered are derived from
approved timesheet periods and from the calendar months in which trips were accepted. The
government contribution tables are computed on a **semi-monthly** assumption (the monthly figure is
halved), so semi-monthly is the intended frequency even though nothing enforces it.*

---

## J. PAYROLL COMPUTATION

**32. May payroll computation functionality ba?**
☑ Yes

**33. Ano-ano ang ginagamit ng system sa payroll computation?**
☑ Attendance — *approved timesheet entries only; a pending entry is worth nothing*
☑ Hours Worked — *computed from time in/out*
☑ Overtime
☑ Incentives — *trip commission*
☑ Deductions
☑ Other: **Completed trips and their route rates**, and the employee's **position**, which decides which of the two pay models applies

☐ Salary/Basic Pay — *the stored monthly salary is not an input; hourly pay uses fixed rates*

**34. Ano ang output ng payroll computation?**
☑ Gross Pay ☑ Total Overtime Pay ☑ Total Incentives ☑ Total Deductions ☑ Net Pay
☑ Other: **Regular hours, overtime hours, the two hourly rates applied, trip count**, and each of
the three deductions **broken out individually**

**35. Sine-save ba ng system ang computed payroll?**
☑ Yes

If yes, what information is saved?

A payroll record per employee per period, holding: employee, period start and end, regular hours,
overtime hours, hourly rate, overtime rate, trip count, total trip commission, gross pay, SSS,
PhilHealth, Pag-IBIG, total deductions, net pay, and a status of **draft** or **finalized**.
Each of the three deductions is *also* written as its own itemised row with the basis note.

> Two behaviours worth recording on the ERD as business rules:
> **(a)** An employee who already has a payroll record for that exact period is skipped, so nobody
> can be paid twice for the same period.
> **(b)** Finalizing copies the whole computation into a separate payslip record and locks it.

---

## K. PAYSLIP

**36. May payslip generation ba?**
☑ Yes

**37. Anong information ang lumalabas sa payslip?**
☑ Employee Information — *name and email*
☑ Pay Period
☑ Attendance/Hours Worked — *regular hours*
☑ Overtime — *hours and pay*
☑ Incentives — *shown as "Trip commission", and only when it is greater than zero*
☑ Deductions — *SSS, PhilHealth and Pag-IBIG listed separately*
☑ Gross Pay
☑ Net Pay
☑ Other: **Date issued**

☐ Salary/Basic Pay — *shown as "Regular pay" derived from hours × rate, not as a monthly salary figure*

**38. Ang payslip ay:**
☑ **Stored as a separate database record**

*This is a deliberate snapshot, not a view over the payroll record. When a run is finalized, its
figures are copied into a payslip record and locked, so the payslip continues to show what was
actually paid even if anything upstream changes later. The payslip page always renders the
snapshot, never the live run.*

---

## L. PAYROLL REPORTS

**39. May payroll report generation ba?**
☑ **Yes — added 2026-09-25.**

`payroll/reports/` is an admin-only, period-scoped reporting page, with a CSV export at
`payroll/reports/export.php`. It contains:

| Report | What it gives |
|---|---|
| Payroll Register | One row per employee for the selected period, with the period totalled |
| Statutory Remittance | SSS, PhilHealth and Pag-IBIG totalled for the period (employee share) |
| Cost by Pay Model | Hourly wage bill and trip-commission cost, reported separately |
| CSV export | The register as a downloadable file — the system's only export |

**Important scope note for the ERD:** the reports module adds **no tables**. It is entirely
read-only, built from `payroll_runs` joined to `users` and `employee_profiles`. Nothing about the
data model changes.

The report counts **finalized runs only by default**, because a draft is a computation nobody has
committed to and including one would overstate a remittance figure. It states how many drafts it
excluded and offers an explicit opt-in labelled as not for remittance.

Still **not** implemented: PDF generation and printing.

The following on-screen listings also exist and predate the reports page:

| Screen | What it shows |
|---|---|
| Payroll breakdown | All payroll records in a table, per employee per period, with every figure |
| Deduction detail | A pop-up itemising the three contributions with their basis |
| Payslip | One finalized payslip, on screen |
| Trip attendance | A filterable read-only list of trip attendance |
| Timesheet log | One employee's own monthly history, with time-in photos |
| Dashboard overview | Company-wide total salary and total hours |

**40. Anong reports ang available?**

☑ Payroll Summary — *the Payroll Register on `payroll/reports/`, period-scoped and totalled, plus CSV export*
☑ Individual Employee Payroll — *an employee sees only their own records on the payroll page*
☑ Attendance Report — *trip attendance, filterable; and the employee's own timesheet log*
☑ Deduction Report — *the Statutory Remittance summary, plus the per-record detail pop-up*
☑ Incentive Report — *Cost by Pay Model separates trip commission from the hourly wage bill, and the register has a Commission column*

☐ Overtime Report — *no dedicated report; overtime hours and pay appear as columns in the register and are included in its totals*

---

## M. CONNECTIONS BETWEEN DATA

**41. Ang isang employee ay maaaring magkaroon ng:**

| | One only | Multiple | Note |
|---|---|---|---|
| Attendance records | | ☑ Multiple | One timesheet entry per day; trip attendance can be several per day |
| Payroll records | | ☑ Multiple | One per period, many periods |
| Overtime records | — | — | **No overtime records exist.** Overtime is a column on the payroll record. |
| Deduction records | | ☑ Multiple | Exactly three per payroll record |
| Incentive records | — | — | **No incentive records exist.** Commission is a column on the payroll record. |

**42. Ang isang attendance record ay connected sa:**
☑ One Employee
*A timesheet entry belongs to exactly one employee and one date. **It is not linked to a pay period
at all** — there is no period foreign key. Payroll finds entries by date range at run time.
A trip attendance row belongs to one employee **and one trip**.*

**43. Ang isang payroll record ay connected sa:**
☑ One Employee ☑ One Pay Period
*"One pay period" means one start/end date pair stored on the record itself, not a foreign key to a
period table. A payroll record also owns **three deduction rows** and, once finalized, **one payslip**.*

**44. Ang overtime record ay connected sa:**
☑ Other: **Not applicable — no overtime record exists.**
*Overtime hours and pay are columns on the payroll record, derived from approved timesheet entries.*

**45. Ang deduction record ay connected sa:**
☑ One Employee ☑ One Payroll
*Each deduction row carries both the payroll record it belongs to and the employee, and is one of
exactly three types.*

**46. Ang incentive record ay connected sa:**
☑ Other: **Not applicable — no incentive record exists.**
*Trip commission is a total column on the payroll record, computed from the employee's completed
trips within the period.*

---

## N. OTHER SYSTEM DATA

**47. May iba pa bang information/table/data na ginagamit ng system na hindi nabanggit sa checklist?**
☑ **Yes — and these are central to the system, not peripheral.**

### 1. Route
The destinations the fleet runs and what each pays. **All trip money originates here.**
Fields: id, destination, amount per trip, active flag, created date.
Routes are deactivated rather than deleted, because existing trips and payslips still reference them.

### 2. Trip
A delivery job: a route, a driver, and optionally a helper.
Fields: id, route, driver, helper (nullable), **amount per trip copied from the route at assignment**,
status, and four timestamps — started, delivered, accepted, cancelled.

The status moves **assigned → delivered → completed**, or to **cancelled**. This is a two-step
close and it is a deliberate business rule:

- **delivered** — the crew reported the run finished. Records nothing else. Reversible.
- **completed** — the administrator accepted it. **Only this step writes trip attendance and makes
  the trip payable.**

Business rules worth noting on the ERD: a driver or helper cannot be assigned a second trip while
one is still in progress; and the route's rate is copied onto the trip at assignment, so later rate
edits never change what an existing trip pays.

### 3. Trip Attendance
Described in Section C-2 above. Links a trip to an employee with the role they played on it.

### 4. Timesheet Period Approval
Described in Q14. Employee, period start, period end, approved by, approved at. Append-only, so the
approval history is preserved for payroll disputes.

### 5. Schema migrations
A housekeeping table tracking which database migrations have been applied. **Not part of the
business model — exclude it from the ERD.**

### 6. `clock_records` — DEAD TABLE, EXCLUDE FROM THE ERD
This table exists in the database but **is not referenced by a single line of code.** It is left
over from an early approach that was replaced by the timesheet entries table. It should not appear
in the ERD and ideally should be dropped.

---

## O. ACTUAL SYSTEM SCREENSHOTS

Screenshots can be provided for every page below. Note the mapping, since several checklist
categories do not correspond to a page of their own:

☑ Login — *a modal on the landing page*
☑ Employee Management
☑ User Account Management — *same page as Employee Management; accounts and employees are one record*
☑ Attendance — *two pages: the timesheet entry page, and the trip attendance report*
☑ Attendance Approval — *per-entry on the entry page, and per-period on the review page*
☑ Payroll Computation — *the payroll page*
☑ Payroll Records — *the payroll breakdown table on the same page*
☑ Deductions — *the itemised detail pop-up*
☑ Payslip
☑ Other: **Routes**, **Trips**, **Dashboard**, **Overview**, **Invite Employee**, **Timesheet Log**

☐ Overtime ☐ Incentives ☐ Pay Period — **no such pages exist.** These are columns and computed
values, not managed screens.
☑ Payroll Reports — *`payroll/reports/`, added 2026-09-25*

---

**48. If the system already has an actual database, please provide:**
☑ **List of actual database tables** ☑ **Database schema** — both below.

### Tables actually in the database

| # | Table | In the ERD? | Purpose |
|---|---|---|---|
| 1 | `users` | ✅ | Account and identity — name, email, hashed password, role |
| 2 | `employee_profiles` | ✅ | Employment detail — position, status, licence, hire date, contact, salary |
| 3 | `timesheet_entries` | ✅ | Daily time in/out with photo, and approval status |
| 4 | `timesheet_approvals` | ✅ | Period-level approval audit — who approved what, and when |
| 5 | `routes` | ✅ | Destination and the amount it pays per trip |
| 6 | `trips_new` | ✅ | A delivery job and its lifecycle |
| 7 | `trip_attendance` | ✅ | Presence generated when a trip is accepted |
| 8 | `payroll_runs` | ✅ | One computed payroll per employee per period |
| 9 | `deductions` | ✅ | Itemised statutory contributions per payroll record |
| 10 | `payslips` | ✅ | Locked snapshot created on finalization |
| 11 | `schema_migrations` | ❌ | Housekeeping only |
| 12 | `clock_records` | ❌ | **Dead — unused by any code** |

> The trips table is named `trips_new` because it replaced an earlier unused `trips` table.
> On the ERD it should simply be labelled **Trip**.

### Relationships

```
users 1 ──── 1 employee_profiles          (each employee has one profile)

users 1 ──── * timesheet_entries          (an employee files many daily entries)
users 1 ──── * timesheet_approvals        (as the employee whose period was approved)
users 1 ──── * timesheet_approvals        (as the administrator who approved it)

routes 1 ──── * trips_new                 (a route is run many times)
users  1 ──── * trips_new                 (as driver)
users  1 ──── * trips_new                 (as helper, optional)

trips_new 1 ──── * trip_attendance        (one row per person on the trip: 1 or 2)
users     1 ──── * trip_attendance

users        1 ──── * payroll_runs        (one per period)
payroll_runs 1 ──── 3 deductions          (always SSS, PhilHealth, Pag-IBIG)
payroll_runs 1 ──── 0..1 payslips         (created only on finalization)
users        1 ──── * payslips
```

Note that **`timesheet_approvals` has two separate relationships to `users`** — one for the employee
being approved and one for the approving administrator. Both are real foreign keys.

Also note there is **no foreign key from any attendance record to a payroll record.** Payroll
gathers timesheet entries and trips by date range when it runs; nothing is linked afterwards.

### Full schema

The authoritative schema is in the repository under `database/` — `schema.sql` plus numbered
migration files, which together are the complete history of every table and column. A live
`SHOW CREATE TABLE` dump can be provided for all twelve tables on request.
