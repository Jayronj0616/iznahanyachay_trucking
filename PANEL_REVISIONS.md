# Panel Revisions — Tracking

Source: `System_Website_Revisions_from_Panel.pdf` (panel discussion transcript, thesis defense).
STATUS LEGEND: 🔴 NOT STARTED | 🟡 IN PROGRESS | ✅ DONE | ⚠️ CONFLICTS WITH EXISTING BUILT FEATURE | 📄 DOCS/PAPER-ONLY (no code change)

RULE: update status the moment work starts/finishes, same as SYSTEM.md. This file tracks panel items only; SYSTEM.md remains the overall source of truth for architecture/schema.

---

## A. General System / Website Revisions

1. **Fix typos/consistency in content** — 📄 DOCS-ONLY. Paper/UI label proofreading, not a code task tracked here.
2. **Clearly define modules/features vs objectives** — 📄 DOCS-ONLY. Paper alignment task.
3. **Fix inconsistent actor terms (driver/staff/employee/user/admin)** — 📄 DOCS-ONLY for diagrams/paper. Code-side, roles already exist as `employee_profiles.position` enum (driver/helper/dispatcher/secretary/maintenance/liaison/operator_manager) + `users.role` (admin/employee). No code change planned unless terminology itself needs to change in UI labels.
4. **Remove/clarify "Payroll Administrator"** — 📄 DOCS-ONLY. Confirmed: no such role exists anywhere in code/schema. Nothing to remove in system. Action needed only in the paper — remove the mention or clarify it's not a separate system role.
5. **Remove public employee self-sign-up** — ✅ DONE. See Current Work section below.
6. **Account creation should be Admin-controlled** — ✅ DONE. `home/invite/index.php` is now the sole account-creation path (self-signup removed).
7. **Clarify online/web-based vs local network deployment** — 📄 DOCS-ONLY. System is live on InfinityFree (public web), per SYSTEM.md DEPLOYMENT note. Just needs stating in the paper.
8. **Fix/clarify attendance approval workflow** — mostly ✅ built (`timesheet/review/index.php`, `timesheet_approvals` table, per-period approve). 📄 May just need clearer diagram/doc explanation of the existing flow — no code gap identified yet.
9. **Strengthen RRL support** — 📄 DOCS-ONLY. Paper writing task.
10. **Specify methodology/features clearly** — 📄 DOCS-ONLY. Paper writing task.
11. **Improve UI design (colors/blending)** — 🟡 CODE DONE, NOT TESTED. See Current Work section below.
12. **Show automation more clearly in the system** — 🔴 NOT STARTED, overlaps directly with B-19/20/C-6/7 below (Trip→Attendance→Payroll automation is the concrete version of this ask).

## B. Trip Management + Payroll Revisions

13. **Trip assignment with Driver + Helper** — ✅ DONE. `more/trips/index.php`, `trips_new` table (driver_id, helper_id).
14. **Automatic split of trip amount** — ✅ DONE. `payroll/index.php` commission calc: driver 15%, helper 8% of `trips_new.amount_per_trip`.
15. **Clarify "Split Management"** — 📄 DOCS-ONLY. No separate "Split Management" feature exists or is planned; the split IS the driver/helper % calc in payroll. Paper should clarify these are the same thing, not two features.
16. **Fix Trip Status (Assigned/Completed) clarity** — ✅ DONE. `trips_new.status` ENUM('assigned','completed'), enforced server-side.
17. **Assigned trip should NOT count toward gross pay** — ✅ ALREADY TRUE. Commission query filters `status = 'completed'` only.
18. **Payroll based on completed trips only** — ✅ ALREADY TRUE, same filter as #17.
19. **Driver attendance connected to completed trips** — ✅ DONE. `trip_attendance` table auto-populated on trip completion (see Current Work section below).
20. **Automate attendance from completed Trip Ticket** (Assigned → Completed → auto attendance → payroll) — ✅ DONE. Same work as #19. New `more/trip-attendance/index.php` report page also built.
21. **Integrate/automate existing company process, show it in the system** — 📄 mostly DOCS-ONLY (showing what's automated in the paper), but depends on #19/20 actually existing to describe truthfully.

## C. Priority Revisions (panel's own priority list)

1. Admin-created accounts, remove public sign-up — ✅ DONE, same as A-5.
2. Consistent roles: Admin/Employee/Driver/Helper — 📄 DOCS-ONLY, same as A-3. Actual system has a finer-grained position list; paper should reconcile the panel's simplified framing against it, not necessarily collapse the schema.
3. Trip assignment + Driver/Helper split computation — ✅ DONE, same as B-13/14.
4. Assigned vs Completed trip status — ✅ DONE, same as B-16.
5. Completed trips only → payroll — ✅ DONE, same as B-17/18.
6. Completed trip → automatic attendance — ✅ DONE, same as B-19.
7. Attendance → payroll integration — ✅ DONE — the trip-completion-generates-attendance link (B-19/20) is now done and tested; attendance→payroll for hourly roles was already working.
8. Improve UI colors/design — 🟡 CODE DONE, NOT TESTED, same as A-11.
9. Show actual automation instead of describing manual-process problems — 🔴 NOT STARTED, same as A-12/B-21.
10. Fix typos/labels/diagrams/objectives/terminology — 📄 DOCS-ONLY, same as A-1/2/3.

---

## Current Work: Item #5/6 — Remove self-signup, admin-only account creation

STATUS: ✅ DONE — tested and confirmed working by user.

Confirmed by reading actual files (`signup/index.php`, `login/index.php`, `includes/auth.php`, `more/employees/index.php`) before planning.

Steps:

1. ✅ `signup/index.php` — gutted to redirect to `/login/`. Old form/handler removed entirely.
2. ✅ `login/index.php` — removed "Don't have an account? SIGN UP" link/paragraph.
3. ✅ `more/employees/index.php` — removed the "Pending Approvals" section entirely: `$pendingSignups` query, the approve/reject POST branch, and the HTML block. Confirmed self-contained before removal.
4. 🔴 `includes/auth.php` — left `attemptLogin()`'s pending-status check as-is (harmless dead branch, `home/invite/` always creates `active` accounts). No change made, per plan.
5. ✅ Migration `013_employee_status_pending.sql` — never run, no DB action taken. `pending` stays an unused enum value.
6. ✅ `SYSTEM.md` updated (self-signup removed from feature list/status, admin-invite noted as sole account-creation path).

TESTED AND CONFIRMED WORKING BY USER: signup redirects to login, login page has no sign-up link, employees page loads clean.

---

## Next Work: Item #19/20 (B) / #6 (C) — Trip completion → automatic attendance

STATUS: ✅ DONE — tested and confirmed working by user, including the invite-Position fix and license-field show/hide fix found during testing.

Confirmed by reading `more/trips/index.php` (the `complete` POST action) and `payroll/index.php` (commission calc logic) before planning.

DECISIONS CONFIRMED BY USER:
- Auto-created attendance record is auto-approved immediately (trip completion = verified, no admin review needed) — different from manual `timesheet_entries` which stay pending until admin approval.
- One attendance record PER completed trip, not one per day (a driver can complete multiple trips same day = multiple records).
- Applies to BOTH driver and helper on the trip (not driver only).
- New separate table (not reusing `timesheet_entries`, which is one-row-per-user+date and used for hourly payroll/admin review — incompatible with multiple-trips-per-day).
- Presence-only record — no time-in/time-out or hours calculation. Driver/helper pay is commission-only (trip %), hourly calc already fully skipped for them in `payroll/index.php`. This table is for attendance/presence tracking and reporting only, does NOT feed the payroll gross-pay calculation (that still reads `trips_new` directly, unchanged).

IMPLEMENTATION STEPS (one at a time, confirm before moving to next):

1. ✅ Migration `018_trip_attendance.sql` WRITTEN, NOT YET RUN by user. Table `trip_attendance`: `id, trip_id INT NOT NULL (FK trips_new), user_id INT NOT NULL (FK users), role ENUM('driver','helper') NOT NULL, date DATE NOT NULL, created_at`. No status/approval column — existence of the row IS the approved attendance record. Index on (user_id, date) for reporting lookups. MUST BE RUN (local, then live) before step 2 can be tested.
2. ✅ `more/trips/index.php` — `complete` handler rewritten: now wrapped in a transaction, fetches `driver_id`/`helper_id` via `SELECT ... FOR UPDATE` before updating status, then inserts one `trip_attendance` row for the driver and one for the helper (if present) with `date = CURDATE()`. Rollback on any failure, `$error` shown if the transaction throws.
3. ✅ New admin-only report page `more/trip-attendance/index.php` created. Filterable by employee (driver/helper dropdown) and date range. Read-only, no actions. Linked from `more/index.php` nav (admin-only block, after Trips).
4. ✅ `SYSTEM.md` updated: Global Status, Directory Tree, Known Bugs, Suggested DB Tables all reflect the new table + pages.
5. ✅ Tested by user: trip completion (with/without helper) creates correct `trip_attendance` rows, report page filters work correctly.

BUGS FOUND DURING TESTING (both fixed and tested):
- `home/invite/index.php` had no Position field — admin-invited drivers/helpers wouldn't appear in Trip Assignment dropdowns until manually edited via `more/employees/`. Fixed: Position dropdown added to invite form, wired into insert.
- License Number/Expiry fields showed unconditionally on both `home/invite/index.php` and the Edit modal in `more/employees/index.php`, confusing since license only matters for drivers. Fixed: both fields hidden via JS unless Position = Driver.

ALL TESTS PASSED. Panel items B-19/20 and C-6/7 are now fully done.

---

## Current Work: Item #11 (A) / #8 (C) — UI color/design polish

STATUS: 🟡 IN PROGRESS.

Palette (defined in `includes/head.php` Tailwind config): `brand.orange` (#F97316), `brand.yellow` (#FBBF24), `brand.green` (#22C55E), plus `surface.*` for dark mode backgrounds. Problem found: all three brand colors were used interchangeably for primary actions, with no consistent role — this is almost certainly the panel's "doesn't blend well" complaint.

RULE ADOPTED (confirmed by user): keep all 3 colors, assign clear roles instead of dropping any.
- **Orange** = primary actions (submit/action buttons, active nav indication, main headings/branding).
- **Green** = success/positive STATUS only (approved, completed, active badges, success banners). NOT used for buttons/icons that aren't status indicators.
- **Yellow** = focus rings, subtle accents/highlights only (already mostly correct as-is).

FILES FIXED SO FAR:
1. ✅ `includes/bottom-nav.php` — active-tab indicator was `bg-brand-green` inside the orange nav bar (green sitting on top of orange, not a status). Changed to `bg-white/25` (subtle highlight). Affects every page (shared include).
2. ✅ `home/index.php` — "Invite your employee" icon + arrow chip were green (action, not status) → orange. Timesheet date-range label was green (decorative, not status) → neutral gray. Payroll "Finalized" status text correctly stayed green (real status), untouched.
3. ✅ `more/employees/index.php` — "+ Invite Employee" button and "Save Changes" button were green (actions, not status) → orange. Active/Inactive status badge and success banner correctly stayed green, untouched.
4. ✅ `more/trips/index.php` — "Assign Trip" submit button was green (action) → orange. Completed/Assigned status badge correctly stayed green/yellow, untouched.
5. ✅ `more/routes/index.php` — "Add Route" and "Save Changes" buttons were green (actions) → orange. "Activate" toggle button intentionally LEFT green (doubles as a status-transition affordance, consistent with the Active/Inactive badge it toggles). Active/Inactive badge correctly stayed green/gray, untouched.
6. ✅ `payroll/index.php` — "RUN PAYROLL" button was green (action) → orange. Finalized/Draft badges and Finalize/View buttons already correct (orange actions, green status), untouched.
7. ✅ `home/invite/index.php` — "Create Employee Account" submit button was green → orange.
8. ✅ `more/index.php`, `more/trip-attendance/index.php` — checked, no brand-color misuse found.
9. ✅ `timesheet/index.php` — History button, Review Period link, "Today" link were all green (actions) → orange.
10. ✅ `timesheet/review/index.php` — "Load Entries" button was yellow (should be orange as primary action) → orange. "Approve Period" button intentionally LEFT green (status-transition action, same reasoning as Routes' Activate). Approved/Rejected/Pending badges already correct, untouched.
11. ✅ `timesheet/entry/index.php` — "Back to calendar" link, "Time In" button, "Time Out" button, "Approve" button were all green (actions, one-directional workflow steps not paired with a toggle-style badge like Activate/Approve Period) → orange.
12. ✅ `timesheet/log/index.php` — checked; green/red dots are semantic time-in/time-out markers, not brand-color misuse, left as-is.
13. ✅ `more/profile/index.php` — "Save Changes" button was green → orange.
14. ✅ `index.php` (public landing page) — found during this pass, fixed with user confirmation (separate from color rule): removed dead "Sign Up" button/link (pointed to now-redirected `/signup/`), replaced with "Login". Replaced freight-delivery-to-clients stats (500+ deliveries, 98% on-time, 100+ clients, 24/7 — panel flagged this as not matching actual system scope) with accurate system-feature highlights (Automated Payroll, Trip-Based Attendance, Driver & Helper Commission Tracking, Admin-Controlled Account Access). Hero heading/copy also rewritten to describe the actual internal payroll/attendance system instead of a freight-delivery brand.

FILES CHECKED, NO CHANGES NEEDED: `login/index.php` (yellow focus rings only, already correct), `more/routes/index.php` edit-cancel button (neutral, correct as-is).

ALL FILES DONE. Panel item A-11/C-8 (UI color/design) is now fully addressed — palette roles applied consistently across every page. NOT YET TESTED BY USER (visual review recommended, low functional risk since only Tailwind classes changed, no logic touched).
