# Presentation Guide — Iznahanyachay Trucking System

A screen-by-screen order for demonstrating the system, including every panel revision. Each step
says which page to open, who to be signed in as, what to click and what to explain.

Base URL: `http://localhost/trucking_system`

Rewritten 2026-10-03 for the delivered version (`main`, after the client revision round). It
replaces the earlier guide, which targeted the pre-revision system and listed demo passwords.
**This file deliberately contains no passwords** — the repository is public. Demo logins live in
the local-only `SYSTEM.md`; production logins went to the client separately.

> ### Read this before you record
>
> **Almost every button opens a "Confirm Action" dialog first** (Assign Trip, Mark Delivered,
> Accept, Save, Approve, Run Payroll, Finalize...). Nothing happens until you click **Confirm**.
> Say what you are about to do, click the button, then click Confirm while you finish the
> sentence. If you forget, the page just does nothing, which looks like a bug on camera.

---

## Before you start

1. **Apache + MySQL running** in the XAMPP Control Panel. Load the base URL to confirm.
2. **Migrations applied through 035.** Run `php database/migrate.php run` — it is safe to repeat,
   and prints nothing to do when the database is current.
3. **Load the demo data first — the local database is currently production-clean.** It holds only
   the two staff accounts (`admin`, `payroll`) and no employees, routes, trips or payroll, so
   most pages will be empty. Import `iznahanyachay_DEMO_DATA_BACKUP.sql` (Desktop) into a
   **separate** database such as `iznahanyachay_demo`, and point `includes/config.php` at it while
   recording, so the clean database stays exportable. Putting it back afterwards is a one-line
   change in the same file.
4. **Camera permission granted to localhost**, before recording. The time-in step needs the webcam.
5. **Sign in once as every role before you press record** (Admin, Payroll Master, one hourly
   employee, one driver). Passwords are hashed and cannot be read back, so find out about a wrong
   one now. A first-time login on a freshly reset account lands on Change Password.
6. **Two window sizes** — full width for admin tables, about 420px for the employee pages (built
   mobile-first with a fixed bottom nav).
7. Use throwaway passwords you don't mind saying aloud; the invite form shows the temporary
   password in plain text by design.

### Who can do what (the part the panel cares about)

| Role | How they sign in | What they can do |
|------|------------------|------------------|
| **Owner/Admin** | `admin` or company email | Everything. Only role that finalizes payroll, approves proposed trip rates, edits contribution brackets, creates Payroll Masters and issues temp passwords. |
| **Payroll Master** | `payroll` or company email | Proposes route rates (needs Admin approval), runs payroll (cannot finalize), records cash advances, reviews timesheets and attendance-correction requests. |
| **Employee** (driver, helper, hourly staff) | Personal email | Own dashboard, own timesheet and time-in, own payslips, files correction requests. |

Everyone uses the **one** login form at `/`. There is no separate admin login (the client reversed
that on 2026-10-01). The topbar shows a badge — *Owner/Admin*, *Payroll Master*, or the employee's
position — so the signed-in role is always visible.

---

## The flow, in one picture

```
DRIVER / HELPERS  (paid per trip)
  Route (destination + rate)  <- Payroll Master proposes, Admin approves
    -> Trip assigned (driver + one OR MORE helpers)
      -> Marked delivered        <- records the report only
        -> Admin accepts         <- writes attendance, makes it payable
          -> Trip attendance (one row per person)

HOURLY STAFF  (dispatcher, secretary, maintenance, liaison, operator manager)
  Time in (webcam photo required) -> pending entry -> Time out
    -> Missed a punch? Employee files a correction request
    -> Admin / Payroll Master approves the period

BOTH  ->  Payroll run (Admin or Payroll Master)  ->  Finalize (Admin only)  ->  Payslip
          driver 15% / each helper 8% of route rate
          hourly PHP 100/hr, PHP 110/hr past 8 hours a day
          less SSS, PhilHealth, Pag-IBIG (from the bracket tables; skipped for self-remit staff)
          less cash advance (one-time flat amount, capped at what is left of the pay)
```

Ordering rules that decide the sequence below:

- **Approve a period before payroll — for hourly staff.** Approval makes their hours payable. The
  payroll dropdown also lists months that only have accepted trips, and labels each option
  `timesheets only`, `trips only` or `timesheets + trips`.
- **Trip commission is attributed by acceptance date**, so deliver and accept inside the same
  period or it lands in the next run.
- **Payroll will not run twice for the same dates** — anyone already run for them is skipped.

---

## Part 1 — Sign in (revision: single login, short username, role badge)

| # | Page | Sign in as | Do this | Explain |
|---|------|------------|---------|---------|
| 1 | `/` | — | Scroll the landing page; the stats strip is live from the database | There is no public sign-up. |
| 2 | `/` → Login | Admin | Type just `admin` (not an email) and the password | The field takes an email **or** a short username. Admin and Payroll Master accounts have a username; employees use their email. |
| 3 | `/more/change-password/` | Admin (first login) | Show you are bounced here, then change the password | Reset or admin-issued passwords force a change before anything else is reachable. |
| 4 | Any page | Admin | Point at the topbar badge | "Owner/Admin". Sign in as Payroll Master later and it reads "Payroll Master". |

### Forgot password (revision)

| # | Page | Do this | Explain |
|---|------|---------|---------|
| 5 | `/` → **Forgot password?** | Enter an **employee's** email and submit | No email service exists, so it files a request for the Owner. The reply is the same wording whether or not the account exists. |
| 6 | `/more/staff/` (Admin) | **Password Reset Requests** → **Issue Temp Password** | Admin sets a temporary password; the employee is forced to change it on next login. |
| 7 | `/forgot-password/` | Enter `admin` | If the Owner has set a security question, it is asked here; a correct answer issues a temporary password directly. The Owner is top of the chain, so nobody else could approve this. |
| 8 | `/more/staff/` | **Your Password Recovery** | Where the Owner sets that question and answer. |

---

## Part 2 — Setup as Admin

Full browser width.

| # | Page | Do this | Explain |
|---|------|---------|---------|
| 9 | `/home/` | Read the cards | Admin dashboard. |
| 10 | `/home/overview/` | Read the four cards | Company-wide totals. Late and Performance say "Not tracked" on purpose. |
| 11 | `/more/` | Scroll the menu | Role-aware: Routes and Cash Advances for Payroll Master too; Employees, Trips, Trip Attendance, Contribution Brackets and Staff Accounts are Admin only. |
| 12 | `/home/invite/` | Create an employee, **Position: Driver** | Position decides trip-commission vs hourly pay. |
| 13 | `/more/employees/` | **Edit** an employee | Roster. Note the **government contributions** select: *employer withholds* (default) or *self-remit*. Staff who remit their own SSS/PhilHealth/Pag-IBIG are skipped on payroll. |
| 14 | `/more/staff/` | **Add Payroll Master** | Admin creates the Payroll Master account here. The same page holds the password-reset queue and the Owner's recovery question. |

### Revision: deduction brackets are in the database, not in code

| # | Page | Do this | Explain |
|---|------|---------|---------|
| 15 | `/more/contribution-brackets/` | Show the SSS, PhilHealth and Pag-IBIG tables. **Edit** one range and save | Fully editable by the Owner. SSS is a fixed share per salary range; PhilHealth and Pag-IBIG are a rate on a clamped base. Changing a row changes the next payroll run, with no code change. Revert the edit before moving on. |

---

## Part 3 — Routes and the trip-rate approval (revision)

| # | Page | Sign in as | Do this | Explain |
|---|------|------------|---------|---------|
| 16 | `/more/routes/` | Payroll Master | **Add Route** or **Edit** a rate | A Payroll Master's change does **not** go live. It is saved as a *pending* rate. |
| 17 | `/more/routes/` | Admin | Open **Pending rate changes**, **Approve** one, **Reject** another | Only Approve makes it the live rate. Reject discards it. When the Owner edits a rate directly it applies immediately — no one above them. |
| 18 | `/more/routes/` | Admin | **Toggle** a route inactive | Routes are deactivated, never deleted — old trips and payslips still point at them. |

---

## Part 4 — Trips, with more than one helper (revision), and the two-step close

| # | Page | Sign in as | Do this | Explain |
|---|------|------------|---------|---------|
| 19 | `/more/trips/` | Admin | **Assign Trip**: route, driver, then **tick two or more helpers** | A trip can carry several helpers. The route rate is copied onto the trip now, so later rate changes do not rewrite it. |
| 20 | `/more/trips/` | Admin | Assign the **same driver or a busy helper** again | Blocked — nobody can be on two open trips, checked for the driver and every helper. |
| 21 | `/more/trips/` | Admin | **Edit** the open trip; **Cancel** a spare one | Edit re-validates and skips itself in the double-booking check. Cancelled goes to Cancelled, never paid. |
| 22 | `/more/trips/` | Admin | **Mark Delivered** | Becomes *Awaiting acceptance*. Nothing is recorded yet. |
| 23 | `/more/trip-attendance/` | Admin | Look — **empty** | Delivered is not done. Payroll cannot see it either. |
| 24 | `/more/trips/` | Admin | **Return**, then **Mark Delivered**, then **Accept** | Delivery is reversible; Accept commits in one transaction. |
| 25 | `/more/trip-attendance/` | Admin | Look again | One row for the driver **and one for each helper**, dated by the delivery. |

---

## Part 5 — Employee side

Narrow the browser to about 420px.

| # | Page | Sign in as | Do this | Explain |
|---|------|------------|---------|---------|
| 26 | `/home/` | Hourly employee | Read the cards | Days present/absent and hours this month. |
| 27 | `/timesheet/entry/` | Hourly employee | **Time In** with the camera, then **Time Out** | A photo is required and shown to the reviewer. Say **photo capture, not biometrics** — nothing is matched against an enrolled face, and claiming otherwise would overstate it. |
| 28 | `/timesheet/log/` | Hourly employee | Scroll the history | Employee-only monthly log with photos. |
| 29 | `/home/` | Driver | Read the cards | No hours card — completed trips this month and their commission. |

### Revision: missed punch — correction request

| # | Page | Sign in as | Do this | Explain |
|---|------|------------|---------|---------|
| 30 | `/timesheet/entry/` | Hourly employee | Open a **past** date with no punch → **Request a correction** | Past days cannot be clocked into, so the dead end now leads here. |
| 31 | `/timesheet/request/` | Hourly employee | Enter the actual time in/out and a reason, optionally attach a proof photo, submit | There is intentionally no remarks box on the reviewer side — Approve or Decline only. |
| 32 | `/timesheet/requests/` | Payroll Master or Admin | Review the request and photo → **Approve** | Approve writes that day's entry as approved. Decline leaves the day as it was. |

---

## Part 6 — Timesheets and payroll

Full browser width.

| # | Page | Sign in as | Do this | Explain |
|---|------|------------|---------|---------|
| 33 | `/timesheet/` | Payroll Master | Pick the employee from the dropdown | Payroll Master reviews timesheets like Admin; they are not an employee and have no calendar of their own. |
| 34 | `/timesheet/entry/` | Payroll Master | Open a day, **Approve**; on another, **Reject** with a reason | Per-entry review with the photo. |
| 35 | `/timesheet/review/` | Payroll Master | Employee + date range → **Load Entries** → **Approve Period** | Bulk approval; records who approved which dates and when. |

### Revision: cash advance ("vale")

| # | Page | Sign in as | Do this | Explain |
|---|------|------------|---------|---------|
| 36 | `/more/cash-advances/` | Payroll Master | Record an advance for a driver or hourly employee | One-time flat deduction. It is taken from the next payroll run that employee appears in, then marked recovered. |

### Payroll

| # | Page | Sign in as | Do this | Explain |
|---|------|------------|---------|---------|
| 37 | `/payroll/` | Payroll Master | Open the period dropdown, read the labels aloud, then **Run Payroll** | Pays hourly staff and trip commissions in one run. Note there is **no Finalize** button for this role. |
| 38 | `/payroll/` | Admin | Compare a driver row with an hourly row | Hourly: PHP 100, PHP 110 past 8 hours a day. Driver: share of accepted trips, helpers each independent. |
| 39 | `/payroll/` | Admin | **View** a row's deductions | SSS, PhilHealth and Pag-IBIG itemized with the bracket used. A self-remit employee shows none. A **cash advance** column shows what was recovered. **Money columns are right-aligned**, headers included. |
| 40 | `/payroll/` | Admin | **Finalize** one run, then **Run Payroll** the same period again | Finalize snapshots to payslips and locks it. Re-running skips anyone already paid for those dates. |
| 41 | `/payroll/payslip/?id=…` | Admin | **View Payslip**, then **Print** | Shows the snapshot, not the live run, so a past payslip cannot change. Includes the cash advance line. An employee can open only their own payslip. |
| 42 | `/payroll/reports/` | Admin | Read the cards, Statutory Remittance, Payroll Register, then **Export CSV** | Period-scoped totals counting **finalized runs only**. The CSV carries raw numbers so a spreadsheet can sum them. |

---

## Wrap-up

| # | Page | Do this | Explain |
|---|------|---------|---------|
| 43 | Any page | **Theme toggle** in the topbar | Dark and light throughout. |
| 44 | `/more/about/`, `/more/privacy-policy/` | Open both briefly | The last content pages. |
| 45 | — | Close on what is not built | QR clock-in, leave and holidays, 13th-month pay, manual adjustments, PDF reports (CSV exists). The system withholds SSS, PhilHealth and Pag-IBIG only; do not volunteer income-tax withholding, and answer honestly if asked. |

---

## Page reference

| Page | Access | Notes |
|------|--------|-------|
| `/` | Public | Landing page and the single login modal |
| `/forgot-password/` | Public | Email or username; Owner with a security question answers it, everyone else files a request |
| `/logout/` | Public | POST only |
| `/home/` | Any login | Dashboard; content differs by role and position |
| `/home/overview/` | Any login | Admin company-wide, employee their own |
| `/home/invite/` | **Admin** | Creates an account and its employee profile |
| `/timesheet/` | Any login | Month calendar; Admin / Payroll Master pick the employee |
| `/timesheet/entry/` | Any login | Employee time in/out; Admin / Payroll Master approve, reject, delete |
| `/timesheet/log/` | Employee | Monthly history with photos |
| `/timesheet/request/` | Employee | File a missed-punch correction |
| `/timesheet/requests/` | **Admin, Payroll Master** | Review correction requests |
| `/timesheet/review/` | **Admin, Payroll Master** | Bulk period approval |
| `/payroll/` | Any login | Admin / Payroll Master run payroll; **only Admin finalizes**; employees see their own |
| `/payroll/payslip/?id=N` | Any login | Admin any; employee only their own |
| `/payroll/reports/` and `export.php` | **Admin** | Register, remittance summary, CSV |
| `/more/` | Any login | Role-aware settings menu |
| `/more/profile/` | Any login | Self-editable details |
| `/more/employees/` | **Admin** | Roster, including the self-remit setting |
| `/more/routes/` | **Admin, Payroll Master** | Payroll Master proposes; Admin approves or rejects |
| `/more/trips/` | **Admin** | Assign (multiple helpers), edit, cancel, deliver, return, accept |
| `/more/trip-attendance/` | **Admin** | Read-only attendance report |
| `/more/cash-advances/` | **Admin, Payroll Master** | Record one-time advances |
| `/more/contribution-brackets/` | **Admin** | Edit SSS / PhilHealth / Pag-IBIG ranges |
| `/more/staff/` | **Admin** | Payroll Master accounts, recovery question, password-reset requests |
| `/more/change-password/` | Any login | Forced destination when the flag is set |
| `/more/about/`, `/more/privacy-policy/` | Any login | |

### URLs to stay away from

Dead redirect stubs kept for old links: `/signup/`, `/login/` (→ `/`), `/login/admin/`,
`/login/admin/forgot-password/`, `/home/clock-in/`, `/payroll/run/`.

---

## Things that will cost you a take

- **Run payroll only once per period on camera.** A repeat skips everyone. Keep a second period
  in reserve for a retake.
- **A driver or helper with no *accepted* trip is invisible** to payroll — delivered is not enough.
- **Time-in is once a day, today only.** Keep a spare hourly employee for a second take.
- **Finalized runs do not unfinalize.** There is no undo in the interface.
- **Never time in on a driver or helper account.** They have no timesheet by design.
- **A cash advance is recovered by the next run that includes that employee.** Record it before
  running payroll, not after.

---

## Resetting between takes

Run in phpMyAdmin against the **demo** database. Fix your own dates and row IDs into them.

**Undo a rehearsal payroll run** — children first, since cash advances, deductions and payslips
all reference the run:

```sql
UPDATE cash_advances SET status = 'outstanding', payroll_run_id = NULL, deducted_at = NULL
  WHERE payroll_run_id IN (SELECT id FROM payroll_runs
    WHERE period_start = '2026-09-01' AND period_end = '2026-09-15');

DELETE d FROM deductions d
  JOIN payroll_runs p ON p.id = d.payroll_run_id
  WHERE p.period_start = '2026-09-01' AND p.period_end = '2026-09-15';

DELETE FROM payslips
  WHERE period_start = '2026-09-01' AND period_end = '2026-09-15';

DELETE FROM payroll_runs
  WHERE period_start = '2026-09-01' AND period_end = '2026-09-15';
```

**Undo a period approval**, so Approve Period is live again:

```sql
DELETE FROM timesheet_approvals
  WHERE period_start = '2026-09-01' AND period_end = '2026-09-15';

UPDATE timesheet_entries
  SET status = 'pending', rejection_reason = NULL
  WHERE user_id = 5 AND date BETWEEN '2026-09-01' AND '2026-09-15';
```

**Re-open a trip accepted in a rehearsal** — drop the attendance rows first:

```sql
DELETE FROM trip_attendance WHERE trip_id = 12;

UPDATE trips_new
  SET status = 'assigned', delivered_at = NULL, completed_at = NULL
  WHERE id = 12;
```

A trip only marked *delivered* needs no SQL — **Return** does exactly this.

**Clear today's time-in** so you can film the capture again:

```sql
DELETE FROM timesheet_entries WHERE user_id = 5 AND date = CURDATE();
```

Orphaned photos in `assets/uploads/biometrics/` are harmless.
