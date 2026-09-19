# Presentation Guide — Iznahanyachay Trucking System

A screen-by-screen order for demonstrating the system. Top to bottom covers every page and
every function. Each step says which page to open, what to click, and what to explain.

Base URL: `http://localhost/trucking_system`

**Verified end-to-end on 2026-09-16**, then **revised 2026-09-19** after the landing-page rebuild and
the pre-demo bug sweep. Every step was walked in a browser against the live database on the 16th,
except the webcam capture in step 27 (needs a real camera). The 19th's changes affect steps 1, 27, 37
and 40, add step 40b, and **retire one ordering rule that is no longer true** — all marked below.

> ### Read this before you record
>
> **Almost every button opens a "Confirm Action" dialog first.** Assign Trip, Mark Delivered,
> Accept, Return, Cancel, Save Changes, Approve Period, Run Payroll and Finalize all pop a small
> modal with **Cancel** and **Confirm**. Nothing happens until you click **Confirm**.
>
> Build it into your narration rather than letting it interrupt you — say what you are about to do,
> click the button, then click Confirm while you finish the sentence. If you forget to confirm, the
> page simply does nothing, which looks like a bug on camera.

---

## Before you start

1. **Apache + MySQL running** in the XAMPP Control Panel. Load the base URL to confirm.
2. **Migrations 023 and 024 applied.**
   - `023_trip_delivery_approval.sql` adds the `delivered` status the two-step trip flow needs.
     Without it, every button on the Trips page fails.
   - `024_timesheet_approval_unique.sql` (new 2026-09-19) stops the same period being approved twice.
     Both are already applied on the machine this was written on; check before recording elsewhere.
3. **Camera permission granted to localhost** in the browser, before recording. The permission
   prompt appearing mid-demo is awkward, and the time-in step needs the webcam.
4. **Demo data — already seeded on 2026-09-16.** The database now has:

   | What | Detail |
   |------|--------|
   | Routes | Valenzuela ₱6,000 · Batangas Port ₱8,500 (both active) |
   | Drivers | Charles Morales · Kenneth Daryl Villamayor |
   | Helper | Laden Deguzman |
   | Hourly | Jayron Javier (Dispatcher) — 5 approved Sept entries, 40 reg + 7 OT hours |
   | Trips | 3 accepted (incl. one delivered 09-13 and accepted 09-16) |
   | Payroll | Sept 1–30 run for 4 employees; Charles finalized, other 3 still draft |

   **Test logins:** `admin@trucking.com` / `DemoAdmin2026!` · `charles@trucking.com` (driver) and
   `employee1@trucking.com` (dispatcher) / `DemoUser2026!`. Change these before the system is used
   for real.

5. **Use AUGUST 2026 as the fresh payroll period.** This solves a problem the earlier version of this
   guide left open. September is already run, so Run Payroll on it reports everyone skipped. As of
   2026-09-19 the period list also includes months that only contain trip activity, and **August 2026
   appears as "trips only" and has never been run** — it holds Charles Morales' trip completed
   2026-08-07, worth ₱900 in commission. Running August live is a clean, honest demo of a real
   payroll run, and it doubles as a demonstration that commission-only periods are payable.
   Do not run it before recording, or you lose the moment.
6. **Two window sizes ready** — full width for the admin tables, about 420px for the employee
   pages. The employee side is built mobile-first with a fixed bottom nav.
7. Use **throwaway passwords you don't mind saying out loud**. The invite form shows the
   temporary password in plain text by design, so it will be visible on screen.

---

## The flow, in one picture

Two kinds of employee, two paths into payroll. Both paths have an acceptance step, and payroll
only reads the accepted side of each.

```
DRIVER / HELPER  (paid per trip)
  Route (destination + rate)
    -> Trip assigned (driver + optional helper)
      -> Marked delivered        <- records the report only
        -> Admin accepts         <- writes attendance, makes it payable
          -> Trip attendance

HOURLY STAFF  (dispatcher, secretary, maintenance, liaison, operator manager)
  Time in (webcam photo required)
    -> Entry created as pending
      -> Time out closes the day
        -> Admin approves the period   <- records who approved what, and when

BOTH  ->  Payroll run  ->  Finalize  ->  Payslip
          driver 15% / helper 8% of route rate
          hourly PHP 100/hr, PHP 110/hr past 8 hours a day
          less SSS, PhilHealth, Pag-IBIG
```

Two ordering rules follow from this, and they decide the sequence below:

- **Approve the period before opening Payroll — for HOURLY staff.** ~~The period dropdown is built
  from approved periods, so it is empty otherwise.~~ **No longer true as of 2026-09-19.** The dropdown
  is now built from approved timesheet periods *unioned with* the calendar months of accepted trips,
  and each option is labelled `timesheets only`, `trips only` or `timesheets + trips`. Approval is
  still what makes an hourly employee's *hours* payable, but a month with only trip commissions in it
  now appears on its own. Before the fix it did not, which meant those commissions could not be paid
  at all — Charles' August trip was stuck behind exactly that.
- **Log in as a newly invited employee straight away**, while the forced password-change flag
  is still set.

---

## Part A — Admin setup (steps 1–19)

Full browser width.

| # | Page | Do this | Explain |
|---|------|---------|---------|
| 1 | `/` | **Scroll the whole page.** Use the nav: How It Works → Features → Pay Model → About | Rebuilt 2026-09-19 and now a real demo beat, not a glance. The **stats strip is live** — routes, trips, employees and payroll periods queried on load, which is worth saying out loud since it proves the page is wired to the same database as the rest. Then walk the four-step lifecycle panel; it sets up Parts A and B before you have clicked anything. Note it deliberately says **Photo-Verified Time-In**, not "biometric" — nothing is matched against an enrolled record, and claiming otherwise would be a promise the system does not keep. |
| 2 | `/` → Login | Sign in as **admin** | Login is a modal on the landing page. There is no public sign-up. |
| 3 | `/home/` | Read the cards | Admin dashboard. The invite card only appears for admins. |
| 4 | `/home/overview/` | Read the four cards | Company-wide totals. Total Late and Performance say "Not tracked" — say this is deliberate, not broken. |
| 5 | `/more/` | Scroll the menu | Settings menu. Employees, Routes, Trips and Trip Attendance only appear for admins. |
| 6 | `/more/profile/` | Scroll down | Anyone can edit their own name, email, phone, address and password. Employment details below are read-only. |
| 7 | `/more/employees/` | Open the **Edit** modal on a row, then close it | The roster. Admin changes position, salary, license and active status here. |
| 8 | `/home/invite/` | Fill the form, set **Position: Driver**, submit | How accounts are created. Position decides trip-commission vs hourly pay. |
| 9 | `/logout/` → `/login/` | Log in as the employee you just created | You are redirected straight to Change Password. |
| 10 | `/more/change-password/` | Click a bottom-nav item — you bounce back. Then change the password | Admin-set passwords flag the account. No page is reachable until it is replaced. |
| 11 | — | Log out, log back in as admin | Back to the admin side. |
| 12 | `/more/routes/` | **Add Route** — destination + rate, submit | Routes hold the rate. All trip money comes from here. |
| 13 | `/more/routes/` | **Edit** a rate, save. Then **toggle one inactive** | Routes are deactivated, never deleted — old trips and payslips still reference them. |
| 14 | `/more/trips/` | Show the route dropdown — the inactive one is gone. Go back and reactivate it | Only active routes can be assigned. |
| 15 | `/more/trips/` | **Assign Trip** — route, driver, helper. Submit | The rate is copied onto the trip row now, so later price changes don't rewrite old trips. Row shows **In progress**. |
| 16 | `/more/trips/` | Assign the **same driver** again. Submit. Let the error sit | Blocked. One person cannot be on two open trips — it checks both the driver and helper slots. |
| 17 | `/more/trips/` | **Edit** the open trip — change route and helper, save | Re-validates and re-copies the rate. It skips this trip in the double-booking check, so the driver isn't flagged against himself. |
| 18 | `/more/trips/` | **Cancel** a second trip | Goes to Cancelled, not deleted. No attendance, never paid. |
| 19 | `/more/trips/` | **Mark Delivered** on the live trip | Status becomes **Awaiting acceptance**. This only records that the run was reported finished. |

---

## Part B — The two-step close (steps 20–23)

This is the part worth slowing down on. Checking the attendance report before *and* after
acceptance is what makes the two steps visible.

| # | Page | Do this | Explain |
|---|------|---------|---------|
| 20 | `/more/trip-attendance/` | Look at it — **nothing has been recorded** | Delivered is not the same as done. Payroll cannot see the trip either. |
| 21 | `/more/trips/` | Click **Return**, then **Mark Delivered** again | Delivery is reversible, because nothing was written. |
| 22 | `/more/trips/` | Click **Accept** | The committing step. One transaction: sets the status and inserts attendance for driver and helper. Now it is payable. |
| 23 | `/more/trip-attendance/` | Look again — both rows are there. Try the filters | One row for the driver, one for the helper. Dated by the delivery, not by when you accepted it — verified by accepting a trip three days after its delivery and watching the row keep the delivery date. |

---

## Part C — Employee side (steps 24–30)

Narrow the browser to about 420px.

| # | Page | Do this | Explain |
|---|------|---------|---------|
| 24 | `/login/` | Log in as the **hourly** employee | Not a driver — drivers have no timesheet and the screen will look broken. |
| 25 | `/home/` | Read the cards | Employee dashboard: days present/absent and hours this month. |
| 26 | `/timesheet/` | Look at the month calendar, then tap **today** | Coloured days are recorded entries. Tapping a day opens that day's entry. |
| 27 | `/timesheet/entry/` | Read the **notice above the button**, then **Time In** — allow the camera, capture | A photo is required. Captured to a canvas, sent as base64, saved to the uploads folder. The entry starts as pending. The notice (added 2026-09-19) tells the employee the photo is taken and shown to the admin at review — say this is a **photo capture, not biometrics**: nothing is matched against an enrolled record anywhere in the system. Being precise here is better than being caught overclaiming if someone asks. |
| 28 | `/timesheet/entry/` | **Time Out** | Closes the day. Admins cannot do either — blocked server-side, not just hidden in the UI. |
| 29 | `/timesheet/log/` | Scroll, then use the month arrows | Employee-only history. Admins are redirected away. Note: the seeded September entries show "—" where the photo would be, because they were inserted directly rather than clocked in. The entry you create live in step 27 will have a real photo. |
| 30 | `/payroll/` | Look — no Run Payroll button | Same page, different role. An employee sees only their own runs. |

---

## Part D — Driver view, then payroll (steps 31–40)

Back to full browser width at step 33.

| # | Page | Do this | Explain |
|---|------|---------|---------|
| 31 | `/home/` | Log out, log in as the **driver** | No hours card — he gets completed trips this month and the commission they total. |
| 32 | `/timesheet/` | Show it is empty for him | The calendar is there but there are no entries, and his pay ignores hours entirely. Note the page is not blocked for drivers — it just has nothing in it, so don't say "drivers can't open this". |
| 33 | — | Log out, log in as admin, widen the browser | Full width for the tables. |
| 34 | `/timesheet/` | Pick the hourly employee from the dropdown | Admins choose whose calendar they are looking at. |
| 35 | `/timesheet/entry/` | Open one day, **Approve**. On another, **Reject** with a reason | Per-entry review. The photo is here too. Admin can also soft-delete an entry. |
| 36 | `/timesheet/review/` | Pick employee + date range → **Load Entries** → **Approve Period** | Bulk approval. Writes a record of who approved which dates and when. |
| 37 | `/payroll/` | Open the period dropdown and **read the labels aloud**, then pick **August 2026 — trips only** and **Run Payroll** | Each option says what is waiting in it: `timesheets only`, `trips only`, or `timesheets + trips`. August holds nothing but Charles' accepted trip, and running it pays his ₱900 commission with no timesheet involved anywhere — which is the clearest possible demonstration that the two pay models share one run. Pick September instead and everyone is skipped, because it has already been run. |
| 38 | `/payroll/` | Compare the **driver row** with the **hourly row** | Hourly: PHP 100 regular, PHP 110 past 8 hours a day. Driver: percentage of accepted trips, no hours at all. |
| 39 | `/payroll/` | Click **View** on a row's deductions | SSS, PhilHealth and Pag-IBIG, each itemized with the bracket it came from, halved for a semi-monthly period. |
| 40 | `/payroll/` | **Finalize** one run, then **Run Payroll** again for the same period | Finalize snapshots it to payslips and locks it. Re-running skips anyone already paid for those exact dates. Watch the Status cell — it turns into a **View Payslip** link. |
| 40b | `/payroll/payslip/?id=…` | Click **View Payslip** on the row you just finalized. Then hit **Print** | **New 2026-09-19 — the strongest place to end the payroll section.** Before this, Finalize had no visible result at all: the payslips table was written and never read. This renders the **snapshot**, not the live run, which is the point — what someone was told they were paid cannot change afterwards if a rate constant is edited. Itemised earnings, itemised deductions with the bracket each came from, and the net. Print strips the app shell. **Worth demonstrating the access rule too:** log in as a different employee and change the `id` in the URL — you get "You do not have access to that payslip". Admins see everyone's; an employee sees only their own. |

---

## Part E — Wrap up (steps 41–43)

| # | Page | Do this | Explain |
|---|------|---------|---------|
| 41 | Any page | Hit the **theme toggle** in the top bar | Dark and light throughout. Do it on a table-heavy page. |
| 42 | `/more/about/`, `/more/privacy-policy/` | Open both briefly | The last two content pages. Both require login like everything else. |
| 43 | — | Close on what is not built | QR clock-in, leave and holidays, BIR withholding, 13th-month pay, manual adjustments. |

---

## Page reference

Every page in the system and who can reach it.

| Page | Access | Notes |
|------|--------|-------|
| `/` | Public | Landing page with the login modal |
| `/login/` | Public | Real DB auth |
| `/logout/` | Public | POST only |
| `/home/` | Any login | Dashboard; content differs by position |
| `/home/overview/` | Any login | Totals; admin sees company-wide, employee sees their own |
| `/home/invite/` | **Admin only** | Creates the account and the employee profile |
| `/timesheet/` | Any login | Month calendar; admin picks the employee |
| `/timesheet/entry/` | Any login | Time in/out for employees; approve/reject/delete for admin |
| `/timesheet/log/` | Employee only | Monthly history with photos; admin is redirected away |
| `/timesheet/review/` | **Admin only** | Bulk period approval |
| `/payroll/` | Any login | Admin runs and finalizes; employee sees only their own runs |
| `/payroll/payslip/?id=N` | Any login | Finalized snapshot. Admin opens any; an employee opens only their own — a colleague's id is refused |
| `/more/` | Any login | Settings menu, role-aware |
| `/more/profile/` | Any login | Self-editable details + read-only employment info |
| `/more/employees/` | **Admin only** | Roster + edit modal |
| `/more/routes/` | **Admin only** | Add, edit, activate/deactivate |
| `/more/trips/` | **Admin only** | Assign, edit, cancel, deliver, return, accept |
| `/more/trip-attendance/` | **Admin only** | Read-only report with filters |
| `/more/change-password/` | Any login | Forced destination when the password flag is set |
| `/more/about/` | Any login | |
| `/more/privacy-policy/` | Any login | |

### Three URLs to stay away from

All three are dead redirect stubs kept for old links. Opening them mid-demo bounces you somewhere
unexpected.

- `/signup/` → redirects to login (self-signup was removed)
- `/home/clock-in/` → redirects to the timesheet entry page
- `/payroll/run/` → redirects to payroll

---

## Things that will cost you a take

- **Payroll's dropdown is empty until you approve.** The list comes from approved periods, not
  from the calendar. Review, approve, then payroll — in that order.
- **A driver with no *accepted* trip is invisible.** Delivered is not enough. Payroll only counts
  accepted trips, and a driver with none in the period does not appear in the run at all, not even
  as a zero row.
- **Acceptance date sets the payroll period.** A trip is attributed by when it was accepted, not
  when it was delivered. Deliver and accept inside the same period, or the commission lands in the
  next run.
- **Payroll will not run twice for the same period.** Anyone already run for those exact dates is
  skipped. Correct behaviour, and worth showing on purpose — but a rehearsal burns the take.
- **Time-in is once, and today only.** Past dates cannot be clocked into, and a day that already
  has a time-in will not offer the camera again. Keep a spare hourly employee for a second take.
- **Finalized runs do not unfinalize.** There is no undo in the interface.
- **Never time in on a driver or helper account.** They have no timesheet by design.

---

## Resetting between takes

Run these in phpMyAdmin against `iznahanyachay_trucking`. Fix your own period dates and row IDs
into them before you start.

**Undo a rehearsal payroll run** — children first, since payslips and deductions both reference
the run:

```sql
DELETE d FROM deductions d
  JOIN payroll_runs p ON p.id = d.payroll_run_id
  WHERE p.period_start = '2026-09-01' AND p.period_end = '2026-09-15';

DELETE FROM payslips
  WHERE period_start = '2026-09-01' AND period_end = '2026-09-15';

DELETE FROM payroll_runs
  WHERE period_start = '2026-09-01' AND period_end = '2026-09-15';
```

**Undo a period approval**, so the Approve Period button is live again:

```sql
DELETE FROM timesheet_approvals
  WHERE period_start = '2026-09-01' AND period_end = '2026-09-15';

UPDATE timesheet_entries
  SET status = 'pending', rejection_reason = NULL
  WHERE user_id = 5 AND date BETWEEN '2026-09-01' AND '2026-09-15';
```

**Re-open a trip you accepted in a rehearsal** — drop the attendance rows first:

```sql
DELETE FROM trip_attendance WHERE trip_id = 12;

UPDATE trips_new
  SET status = 'assigned', delivered_at = NULL, completed_at = NULL
  WHERE id = 12;
```

A trip only marked *delivered* needs no SQL — the **Return** button in the UI does exactly this.

**Clear today's time-in** so you can film the camera capture again:

```sql
DELETE FROM timesheet_entries WHERE user_id = 5 AND date = CURDATE();
```

The orphaned photo left in `assets/uploads/biometrics/` is harmless; delete it if you want a tidy
folder.
