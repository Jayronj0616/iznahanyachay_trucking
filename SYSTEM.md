# Trucking System — Architecture + Progress Reference

Local path: `C:\xampp\htdocs\trucking_system`
Stack: PHP (procedural, no framework) + Tailwind CDN + vanilla JS. DB connected, backend logic in progress on several pages (see below).

STATUS LEGEND: ✅ Done | 🟡 UI only / placeholder | 🔴 Not started | ⚠️ Has known bug

RULE FOR WHOEVER CONTINUES THIS: the moment a page/function moves to a new status, update its tag in this file immediately. This file is the single source of truth read at the start of every new session/account.

## SESSION HANDOFF (read this first)

## IN PROGRESS: Fixed monthly salary for non-driver/helper roles + payroll period fix (STARTED, NOT FINISHED)

CONTEXT: User clarified pay model split further than originally scoped:
- Driver/Helper: trip-commission-only, already implemented (see steps 1-6 below), correct.
- Everyone else (dispatcher/secretary/maintenance/liaison/operator_manager): should be FIXED MONTHLY SALARY set by admin, split in half per semi-monthly cutoff (15th/30th) — NOT hourly x rate as currently coded. This is a change from the original hourly-for-everyone-else design.
- OT decision (confirmed by user): OT still applies on top of fixed salary, derived hourly-equivalent rate = `monthly_salary / 22 / 8` (22 = PH DOLE Mon-Fri 5-day-week standard, divisor 261/12≈21.75 rounded to 22), OT pay = `otHours × hourlyRate × 1.25`.
- Attendance (`timesheet_entries` approval) still required/tracked for these roles but no longer drives base pay amount — only OT hours matter for pay purposes now, base is fixed.

ALSO STILL UNRESOLVED (found during this same work): payroll period dropdown in `payroll/index.php` is generated ONLY from `DISTINCT period_start, period_end FROM timesheet_approvals` — meaning a period only exists to select if some hourly employee triggered an approval. Driver/helper (and now, fixed-salary employees whenever they have zero approved timesheet in a period) have no reliable way to get a period option. REPRODUCED THIS SESSION: Charles Morales (driver) had 1 completed trip but did not appear in any payroll run because no period existed covering his trip's completed_at date. DECISION NOT YET MADE: Option A (decouple period dropdown from timesheet_approvals entirely, generate standard semi-monthly periods directly) vs Option B (union trip-derived periods into the existing dropdown). User has not chosen — leaning implied toward A given the fixed-salary direction (periods should be a calendar concept, not an approval artifact) but NOT CONFIRMED, ask user explicitly next session.

IMPLEMENTATION STEPS (agreed: one at a time, SYSTEM.md updated after each):

1. DONE — Migration `017_employee_monthly_salary.sql`: `ALTER TABLE employee_profiles ADD COLUMN monthly_salary DECIMAL(10,2) NULL AFTER position;`. RUN by user, confirmed.
2. DONE — `more/employees/index.php` wired: new "Monthly Salary" column in All Employees table (₱ formatted or —), new number input in Edit modal (step 0.01, min 0.01), POST handler validates (numeric, >0, or empty=null) + persists on both INSERT and UPDATE branches, data-monthly-salary attr + modal JS populate on edit-button click. Tested and confirmed working by user.
3. NOT STARTED — Rewrite non-driver/helper branch in `payroll/index.php`: replace `regularHours * RATE_PER_HOUR` base pay with `monthly_salary / 2` (fixed cutoff amount). OT stays computed from approved `timesheet_entries` (hours beyond 8/day), but OT pay formula changes to `otHours * (monthly_salary / 22 / 8) * 1.25` instead of flat `OT_RATE_PER_HOUR`. Need to handle null `monthly_salary` case (employee has no salary set yet — block payroll run for them with a clear error, or skip + report, TBD — not yet decided, ask user). This is the main remaining code change.
4. NOT STARTED — Resolve period-dropdown bug (Option A vs B above) — blocks testing step 3 for real, same way it blocked testing driver/helper commission payroll for Charles.
5. NOT STARTED — Update `payroll/index.php`'s Payroll Breakdown table if needed to reflect fixed-salary vs hourly distinction visually (may overlap with pre-existing step 7 cosmetic item below in the trip-commission section).
6. NOT STARTED — Full re-test of payroll run end-to-end for BOTH tracks (driver/helper commission AND fixed-salary+OT) once period bug is resolved.

BUG FIXED THIS SESSION (unrelated, found during Routes testing) — `more/routes/index.php` had wrong include paths throughout: `/../includes/` (one level short) instead of `/../../includes/` (file is 2 levels deep from root, same as `more/employees/index.php` and `more/trips/index.php`). Affected 6 includes: auth.php, head.php, topbar.php, bottom-nav.php, confirm-modal.php, foot.php. Fixed via edit_file (worked fine — contradicts old TOOLING NOTE below, which may be stale/project-specific to files touched in much earlier sessions). `more/trips/index.php` checked, was already correct, no fix needed there.

TESTED AND CONFIRMED WORKING THIS SESSION: Routes CRUD (add/edit/deactivate/reactivate) after the include-path fix. Trip assignment + Mark Completed flow (driver Charles Morales, 1 completed trip).

## PLANNED (Step 1 of 6 DONE, migration run + tested by user pending): Trip-based commission payroll for Driver + Helper

CONFIRMED SCOPE (unchanged from prior planning):

- System covers attendance (company-house time-in/out, unchanged) for the whole org chart (Operator Manager, Secretary, Dispatcher, Liaison, Maintenance, Driver, Helper/Pahinante) PLUS a new trip-commission payroll track that applies ONLY to Driver and Helper roles. Everyone else stays on the existing hourly/OT payroll system, untouched.
- Both Driver and Helper are employees (need their own user/employee_profiles rows, both get paid per trip).
- Pay split: Driver = 15% of trip route amount, Helper = 8% of trip route amount (confirmed math from notes: P78,000 total x 15% = P11,700; x 8% = P6,240, both check out).
- A driver CAN run multiple trips per day (confirmed by user) — rules out reusing `timesheet_entries` for trips. Trips get their own table, loosely linked to (not nested inside) the daily attendance row.
- Helper is assigned PER TRIP (confirmed by user), not a fixed pairing with one driver.
- Deductions on trip-commission pay: copy existing SSS/PhilHealth/Pag-IBIG calc functions applied to trip-commission gross. PLUS meal allowance (ADDITION, amount/rule TBD) and Personal Adv/Pay (DEDUCTION, cash advance, amount presumably entered per payroll run). "Employer 300" — UNRESOLVED, deferred, do not implement until clarified.
- NOT YET DECIDED: settlement cadence — batch into existing semi-monthly 15/30 `payroll_runs` periods, or settle per-trip/on-completion? Blocks step 5/6 below, does not block steps already done or steps 2-4.

IMPLEMENTATION STEPS (agreed: one at a time, SYSTEM.md updated after each):

1. DONE THIS SESSION — `routes` table + admin CRUD. Migration `014_routes.sql`: `id, destination VARCHAR(150), amount_per_trip DECIMAL(10,2), active TINYINT(1) DEFAULT 1, created_at`. RUN by user via phpMyAdmin, confirmed. New page `more/routes/index.php` (admin-only, requireAdmin()): Add Route form (destination + amount), All Routes table listing active AND inactive (sorted active-first), Edit via shared modal (same pattern as `more/employees/index.php` — populated from `data-*` attrs, not one form per row), separate Activate/Deactivate button per row (soft-hide via `active` flag toggle, explicitly NOT bundled into the edit modal per user direction — no hard delete exists). Nav link added to `more/index.php`, admin-only, between Employees and Privacy Policy. NOT YET TESTED BY USER: add route, edit route via modal, deactivate/reactivate toggle, inactive routes still visible in list (not hidden entirely).
2. DONE THIS SESSION — `trips` table rework. Migration `015_trips_new.sql` RUN by user via phpMyAdmin, confirmed. IMPORTANT NAMING NOTE: new table is named `trips_new`, NOT `trips` — user ran it as written without renaming. Old placeholder `trips` table (migration 004, dead) still exists alongside it, UNTOUCHED, not dropped. All code going forward (steps 3-6) must target `trips_new`, not `trips`. Schema: `id, route_id INT NOT NULL (FK routes), driver_id INT NOT NULL (FK users), helper_id INT NULL (FK users), amount_per_trip DECIMAL(10,2) NOT NULL (snapshotted from routes.amount_per_trip at creation time), status ENUM('assigned','completed') DEFAULT 'assigned', started_at DATETIME NULL, completed_at DATETIME NULL, created_at`. No UI built yet — table exists, nothing reads/writes it.
3. DONE THIS SESSION — `position` field added to `employee_profiles`. Migration `016_employee_position.sql` RUN by user, confirmed. Column: `position ENUM('driver','helper','dispatcher','secretary','maintenance','liaison','operator_manager') NULL`, nullable so existing rows aren't broken. Wired into `more/employees/index.php`: new "Position" column in the All Employees table (shows human-readable label or — if unset), new dropdown field in the Edit modal (— Not set — option included), POST handler updated (both insert and update branches) to validate + persist it. Reasoning: trip assignment (step 4 below) needs to filter driver/helper dropdowns to actual drivers/helpers, and the commission payroll calc (step 6) needs to know who's a driver vs helper to apply 15%/8%. NOT YET TESTED BY USER: assign a position via the edit modal, confirm it saves and displays correctly, confirm at least one employee is set to 'driver' and one to 'helper' before step 4 (trip assignment UI) is built — needed to populate its dropdowns.
4. DONE THIS SESSION — Trip assignment UI. New page `more/trips/index.php` (admin-only). "Assign Trip" form: route dropdown (active routes only), driver dropdown (users with `employee_profiles.position = 'driver' AND status = 'active'`), helper dropdown (position = 'helper' AND status = 'active', optional — "No helper" default). On submit: re-validates route/driver/helper server-side (not just trusting the dropdown), inserts into `trips_new` with `amount_per_trip` snapshotted from the route at that moment, `status = 'assigned'`, `started_at = NOW()`. Empty-state messages guide the admin to add a route / set a driver's position first if either list is empty. Nav link added to `more/index.php`, admin-only, between Routes and Privacy Policy.
5. DONE THIS SESSION (bundled with step 4, same page) — Trip completion flow. Same `more/trips/index.php` page: "Recent Trips" table (last 100, newest first) shows route/driver/helper/amount/status. Each `assigned` row gets a "Mark Completed" button (POST, confirm-modal) that sets `status = 'completed'`, `completed_at = NOW()` — guarded by `WHERE status = 'assigned'` so it can't double-fire. `completed` rows show no action button (terminal state, no un-complete path).
NO LIBRARIES USED ANYWHERE IN THIS PROJECT (confirmed this session, in case future sessions wonder): no Bootstrap, no jQuery, no SweetAlert, no DataTables, no Chart.js. Styling is Tailwind CDN only, all JS is hand-written vanilla, all confirms go through the custom `includes/confirm-modal.php`, all tables are plain server-rendered `<table>` with no client-side library.
NOT YET TESTED BY USER: assign a trip (needs ≥1 employee with position=driver, active status — set via step 3's Employee edit modal first), confirm route amount snapshots correctly, confirm invalid/inactive driver-helper selections are rejected server-side, Mark Completed flips status and hides the button, empty-state messages show correctly when no active routes/drivers exist yet.
6. DONE THIS SESSION — Commission payroll calc, wired into existing `payroll/index.php` Run Payroll flow (settlement batches into existing semi-monthly `payroll_runs` periods, per user confirmation — no separate per-trip settlement system built). Changes:
   - Eligible-employees query expanded: still includes everyone with an approved `timesheet_approvals` row for the period (unchanged), PLUS now also pulls in any user with `position IN ('driver','helper')` who has a `trips_new` row with `status = 'completed'` and `completed_at` in the period — deduplicated against the first list. Needed because driver/helper pay no longer depends on approved hourly timesheets at all, so without this they'd never appear in a payroll run.
   - Per-employee calc branches on `employee_profiles.position`: if `driver` or `helper`, hourly/OT is skipped entirely (`regularHours`/`otHours` stay 0, no `timesheet_entries` query runs for them) — CONFIRMED BY USER: trip-commission is their ONLY pay, not additive to hourly. Commission = `SUM(trips_new.amount_per_trip)` where they're `driver_id` (× 15%) or `helper_id` (× 8%), filtered `status = 'completed'` + `completed_at` in period. Everyone else (no position, or dispatcher/secretary/maintenance/liaison/operator_manager) is CONFIRMED BY USER to keep pure hourly+OT, zero trip involvement — completely unchanged from before this session.
   - Old dead `trips` table query removed entirely, replaced with `trips_new` queries as above. `trip_incentive_total` column (schema unchanged, no migration needed) now holds commission amount for driver/helper rows instead of the old flat-₱50 sum.
   - Deductions (SSS/PhilHealth/Pag-IBIG) unchanged in logic — still apply against whatever `grossPay` computes to, now correctly operating on commission gross for driver/helper.
   - STILL NOT IMPLEMENTED (deferred, not part of what was confirmed this round): meal allowance (addition) and Personal Adv/Pay (deduction) from the original handwritten notes. "Employer 300" line remains unresolved/deferred per earlier planning.
   - NOT YET TESTED BY USER: run payroll for a period where a driver has ≥1 completed trip — confirm driver appears in the run even with zero approved hourly entries, confirm hourly/OT show 0 for them, confirm commission math (15%/8%) is correct, confirm a non-driver/helper employee's payroll is completely unaffected (still pure hourly+OT), confirm duplicate-run-skip still works for both employee types.
7. NOT STARTED (renumbered from 6) — UI display polish for driver/helper rows on the Payroll Breakdown table (currently still shows Reg Hrs/OT Hrs/Basic/OT Pay columns which will just read 0 for driver/helper rows — functionally correct but not visually distinguished from a real 0-hours situation). Not blocking, cosmetic only, not yet requested by user.
8. NOT STARTED — meal allowance (addition) + Personal Adv/Pay (deduction) line items, if/when user wants those implemented. "Employer 300" still unresolved.

THIS SESSION (latest) — `timesheet/index.php` calendar day-list label corrected (superseded an earlier wrong version this same session). FINAL RULE: role-based, not entry-state-based — admin always sees "Tap to View" for every day (admin never adds, only reviews/views); employee always sees "Tap to Add" (unchanged from original). `$isAdmin ? 'Tap to View' : 'Tap to Add'`, no per-day entry lookup needed. Live 500 error also diagnosed and fixed this session: stray tab character inside `DB_HOST` string in live `config.php` (`'\tsql301...'`) broke the PDO connection on every DB-touching page — user removed it manually on the live server. NOT YET TESTED BY USER: confirm admin sees "Tap to View" / employee sees "Tap to Add" correctly; confirm live 500 is actually resolved now that the config typo is fixed.

PRIOR SESSION — `more/employees/index.php` edit form converted from `?edit={id}` page-reload to a modal. Single shared modal populated via JS from `data-*` attributes on each row's Edit button (not one form per row). POST handler logic (validation, upsert transaction) untouched. On validation error, modal does NOT auto-reopen with entered values — error banner shows at top of page, admin re-clicks Edit (accepted tradeoff, confirmed by user). NOT YET TESTED BY USER: open modal, field values populate correctly per row, save succeeds and closes/reloads, error path shows banner without reopening modal, cancel/backdrop-click closes without submitting.

DEPLOYMENT: project is now live on InfinityFree, domain truckingsystem.ct.ws, deployed at htdocs root (not a /trucking_system subfolder). Live `includes/config.php` has `BASE_PATH` set to `''` (empty string) — this differs from local, where it's `/trucking_system`. Live DB: host `sql301.infinityfree.com`, name `if0_42596519_iznahanyachay_trucking`, user `if0_42596519`. Local DB schema+seed data was exported/imported into live DB via phpMyAdmin. NOTE: local and live `config.php` will always differ — no env-based config split exists yet, whoever deploys changes must manually swap BASE_PATH/DB values and remember not to overwrite the live file with the local one.

PRIOR SESSION — Self-signup + admin approval flow BUILT, CODE DONE, migration NOT YET RUN by user (local or live), NOT YET TESTED.

1. Migration `013_employee_status_pending.sql` — widens `employee_profiles.status` enum from `('active','inactive')` to `('pending','active','inactive')`, default stays `'active'` (so admin-created accounts via `home/invite/` still land active by default; only self-signup explicitly sets `'pending'`). MUST BE RUN on both local and live DB before testing — not run yet.
2. `signup/index.php` — was pure UI-only placeholder (no name attrs, no form tag, no backend), now fully wired. Minimal fields only: name, email, password, confirm — deliberately does NOT collect license/hire-date/phone, those stay admin-owned via `more/employees/`. Creates `users` (role='employee') + `employee_profiles` (status='pending') in a transaction, same insert pattern as `home/invite/`. On success shows "awaiting admin approval" message instead of auto-login.
3. `includes/auth.php` — `attemptLogin()` signature changed, now takes an optional `&$failReason` by-ref param ('invalid' | 'pending'). Blocks login for any employee whose `employee_profiles.status = 'pending'`. Admin accounts have no employee_profiles row, unaffected. `login/index.php` updated to pass the new param and show a distinct "Your account is awaiting admin approval." message when reason is 'pending'.
4. `more/employees/index.php` — new "Pending Approvals" section at top of page (only renders when pending rows exist), lists name/email per pending signup with Approve/Reject buttons. Approve sets status→'active'. Reject hard-deletes both the `users` and `employee_profiles` rows (per explicit direction — nothing worth keeping for a rejected signup). Both actions are POST + confirm-modal, admin-only (page already behind requireAdmin()). Main "All Employees" table query updated to exclude status='pending' rows (they only show in the new pending section now, not duplicated below).
5. NOT YET TESTED: run migration 013 first (local AND live), then verify — signup creates a pending account, pending account cannot log in (correct message shown), pending account shows up in Pending Approvals, Approve flips it active and login then works, Reject deletes the account cleanly (row gone from both tables, email free to reuse).

PRIOR SESSION — fixed two live-deployment bugs found after first deploy: (a) "Get Started" link 404'd — root cause was `BASE_PATH` still set to `/trucking_system` while the live site is deployed at domain root; fixed by setting it to `''` on the live config only (local stays `/trucking_system`). (b) Admin login threw InfinityFree's generic "unable to handle request" error — root cause was `config.php` DB credentials still pointing at local XAMPP (`localhost`/`root`/no password) instead of the live InfinityFree DB; fixed by swapping in the real live DB host/name/user/pass (see DEPLOYMENT note above) and importing the local DB export into the live (then-empty) database.

PRIOR SESSION — Admin-can-time-in-as-employee bug: CODE DONE on `timesheet/entry/index.php`, NOT YET TESTED BY USER.

1. Server-side: `save_time_in`/`save_time_out` POST handlers now hard-blocked for admin at the very top of POST handling, before any DB work — redirects back immediately. Closes the crafted-POST bypass, not just a UI hide.
2. UI: Time In/Time Out card now has a dedicated `$isAdmin` branch, fully separate from the employee form branches. Covers all entry states read-only: no entry → "No entry recorded for this date."; time_in only → shows recorded Time In (+ photo if present) + "Time out not yet recorded."; both set → shows recorded Time In + Time Out (+ photo if present). Admin never sees a Time In/Time Out button or form anywhere on this page now.
3. Employee-side branches (no entry/future date, time-in form w/ camera, time-out form, both-set read-only) unchanged in behavior — just de-duplicated since the old inline admin-submit-button branch inside the time-in form was removed (dead now that admin has its own top-level branch).
4. Approval Status card (approve/delete) untouched, stays admin-only — that was already legitimate admin action, not part of this bug.
5. NOT YET TESTED BY USER: (a) admin viewing a date with no entry, time_in-only, and both-set — confirm read-only rendering correct in all three; (b) crafted POST as admin with `save_time_in`/`save_time_out` — confirm redirect happens, no DB write; (c) employee flow still works unaffected (time-in w/ camera, time-out, both-set display) — should be unchanged but re-verify after the edit.

PRIOR SESSION — Soft-delete + reject-UI removal on `timesheet/entry/index.php`, CODE DONE, MIGRATION RUN, NOT YET TESTED BY USER:

- Migration `012_timesheet_soft_delete.sql` — `ALTER TABLE timesheet_entries ADD COLUMN deleted_at DATETIME NULL AFTER rejection_reason`. Confirmed run by user.
- New POST handler `delete_entry` (admin-only): soft-deletes via `UPDATE ... SET deleted_at = NOW() WHERE user_id = ? AND date = ? AND status = 'pending' AND deleted_at IS NULL`. Only works while status is still `pending` — approved entries can never be deleted from here. Uses `rowCount()` to distinguish real success from a no-op (already approved/already deleted) and sets `$error` vs `$success` accordingly.
- Deleted entries are terminal for everyone: after fetch, if `deleted_at` is set, `$entry` is nulled out — treated identically to "no entry exists" for both roles. No recovery via this page.
- Reject UI removed from the Approval Status card. The `reject` POST handler still exists in code (dead code path, no form triggers it) — kept DB-side only per explicit direction, not deleted.
- Employee-facing rejected entries: no longer show status/reason. Replaced with a generic "There was an issue with this entry — please contact your admin." message on the Time In/Time Out card. Admin still sees full status + reason and can still approve a rejected entry (approve button hides only once status is already `approved`).
- Approval Status card: Approve button hidden once `status === 'approved'`; Delete button only shown while `status === 'pending'`.
- NOT YET TESTED: delete flow (soft-delete write + terminal display for both roles), rejected-entry generic message for employee, approve-then-hide-approve-button.

PRIOR SESSION — Two small fixes, both confirmed working by user:

1. `includes/topbar.php` — added optional `$topbarExtra` slot (raw HTML string, rendered between title and theme toggle). Left unset/empty by default, backward compatible with all other pages that include topbar.php without setting it.
2. `timesheet/index.php` — wired the employee-only "History" button into `$topbarExtra` (was previously a standalone row below the topbar). Set before `include topbar.php`, admin gets empty string (no button). `write_file` full overwrite used per established workaround (edit_file/str_replace tool unreliable on this project — see tooling note below).
3. `timesheet/log/index.php` (History page) — redesigned entry list. WAS: two stacked rows per date (Time Out row on top, Time In row below, each with its own avatar/placeholder). NOW: one container per date, single row, Time In (photo + green dot + time) on the left, Time Out (red dot + time, no photo) on the right, flex justify-between. If only one of time_in/time_out exists for a date, only that side renders.

Both fixes tested and confirmed working by user this session.

PRIOR SESSION — Biometric time-in/time-out rework on `timesheet/entry/index.php` (per-date page) and `timesheet/index.php` (calendar page's inline manual forms). Both pages now behave identically:

- DB: migration `011_timesheet_biometrics.sql` adds `time_in_photo VARCHAR(255) NULL` to `timesheet_entries`. RUN by user, confirmed live. New folder `assets/uploads/biometrics/` created for captured JPEGs.
- New helper `includes/biometric.php` — `saveBiometricPhoto($dataUrl, $userId, $date)` decodes a base64 data-URL and writes it to `assets/uploads/biometrics/{userId}_{date}_{timestamp}.jpg`, returns the relative path or null on failure.
- Backend logic split: old single `save_time` action (required time_in AND time_out together) replaced with two independent actions, `save_time_in` and `save_time_out`, on both pages:
  - `save_time_in`: blocks if an entry already has `time_in` set (server-side lock, not just UI). Employees MUST supply `photo_data` (base64 capture) or it errors — admin has no time-in path at all as of the admin-time-in-block fix above.
  - `save_time_out`: blocks unless an entry with `time_in` already exists. No photo required. Validates time_out > time_in.
- UI lock (entry page only, since it's per-date): no entry/no time_in → only Time In control shown. time_in set, no time_out → time_in rendered disabled, only Time Out control active. Both set → both fields locked read-only, no forms. NOTE: `timesheet/index.php`'s inline manual forms do NOT have this pre-emptive greyed-out lock (any date can be picked freely there, so there's no single date to lock against ahead of typing) — the same lock rules are enforced server-side only on that page. Flagged to user as a scoping tradeoff, not objected to.
- Webcam capture flow (employee-only, both pages, mirrored with `manual-` prefixed element IDs on the calendar page to avoid ID collisions): clicking "Time In" opens the camera live (`getUserMedia`) in place of submitting — button swaps to "Capture Photo" — clicking that snaps a canvas frame, stores it as a base64 JPEG in a hidden `photo_data` field, stops the camera stream, then calls `form.requestSubmit()`. No separate "Open Camera"/"Retake" step — matches user's explicit requested flow (click Time In → camera opens → employee captures → submits with confirm).
- Confirm modal (`includes/confirm-modal.php`) extended: now shows an image preview of the captured photo above the confirmation message, read automatically from the submitting form's `photo_data` field (generic — works for both pages without extra wiring, no-ops cleanly for time-out confirms which have no photo).
- BUG FOUND AND FIXED this session: the calendar page's manual "Time In"/"Time Out" `date` inputs had no default value and are `required`. Since the new flow calls `form.requestSubmit()` from JS (not a real user click on the submit button), the browser's native HTML5 validation silently blocked submission when the date was empty — no error, no confirm modal, nothing visibly happened. Fixed by defaulting both date inputs to `date('Y-m-d')` (today), same as the time inputs already default to now. Confirmed fixed by user in a subsequent session.
- OVERALL STATUS: entry page (`timesheet/entry/index.php`) time-in flow confirmed working by user. Calendar page (`timesheet/index.php`) inline manual Time In confirmed fixed. Time Out flow (either page) not explicitly confirmed working yet. Image preview in confirm modal not yet confirmed by user.

PRIOR SESSION — DONE: clock-in/out block (POST handler + pill UI + `$clockError`/`$clockState`/`$todayEntry`) fully removed from `home/index.php`, both roles. RESOLVED — no dedicated clock page needed: `timesheet/entry/index.php` is not a placeholder, it's a fully built manual time-in/time-out + approve/reject page, reachable via `timesheet/index.php` calendar grid. That page is the only clock mechanism for both roles (admin has no clock-in at all, by design — admin isn't a driver). `clock_records` table and `home/clock-in/index.php` are unambiguously dead (nothing writes to either) — still not deleted, still awaiting explicit go-ahead. Month summary, payroll history, admin invite link on `home/index.php` untouched and unaffected. NOT tested by user yet: page loads/renders with clock block gone, `timesheet/entry/` still fully functional as sole clock path.

PRIOR SESSION — Employee invite/create flow built:

- `home/invite/index.php` REBUILT (was placeholder). Admin-only (`requireAdmin()`). Direct-create, not token-based — no email/SMTP capability exists anywhere in this system, so a real invite-link flow was pointless right now. Form: name, email, temp password (plaintext input, min 8 chars, admin sees/sets it), license_number, license_expiry, hire_date, status. On submit: inserts into `users` (role='employee', password_hash()) AND `employee_profiles` in one transaction (both created together, not deferred to first edit). Duplicate email relies on the DB-level UNIQUE constraint on `users.email` (schema.sql) caught via PDOException, not a pre-check-then-insert (more/employees/index.php uses pre-check instead — inconsistent pattern between the two pages, flagged, not unified). On success shows the temp password once back to the admin (no other way to deliver it — share out-of-band).
- `more/employees/index.php` — added "+ Invite Employee" button above the employee table, links to `/home/invite/`.
- `invites` table still NOT created — this flow doesn't use it, deliberately. If a real token/email invite flow is ever wanted, that's still fully unbuilt.
- NOT tested by user yet: full create flow (users + employee_profiles insert together), duplicate-email rejection, temp password login working for the new account.
- Self-signup (this session, see top) is now a second, parallel account-creation path alongside this admin-direct-create one. Self-signup lands `pending`, invite lands `active` by default — deliberately different trust levels.

PRIOR SESSION — Employee Management CRUD built:

- New table `employee_profiles` (migration `010_employee_profiles.sql`, RUN by user, confirmed live): user_id (FK, unique), phone, address, license_number, license_expiry, hire_date, status enum, updated_at. Split from `users` on purpose — `users` also holds admin accounts, none of these fields apply to admin.
- Field ownership split, deliberate: `name`/`email`/password stay self-editable by anyone (own account) via `more/profile/index.php`. `phone`/`address` are self-editable (contact info). `license_number`/`license_expiry`/`hire_date`/`status` are ADMIN-ONLY edit via `more/employees/index.php` — these are compliance/identity fields for a trucking company (license expiry matters for driver legality), letting employees self-edit them would let payroll/compliance records get silently altered.
- `more/profile/index.php` REBUILT (was a placeholder): self-service, both roles. Edit name/email/phone/address/password (password optional, blank = unchanged, min 8 chars enforced). Read-only "Employment Details" block shows license/hire_date/status with a note to contact HR/admin to change.
- `more/employees/index.php`: admin-only (`requireAdmin()`). Lists all `role='employee'` users LEFT JOINed with `employee_profiles` (phone, license, hire_date, status shown in table). `?edit={id}` shows an edit form for that employee. Upserts into `employee_profiles` (insert if no row exists yet, update otherwise). This session added the Pending Approvals section above the main table (see top).
- Nav: `more/index.php` — "Employees" link, wrapped in `if (role === 'admin')`, sits between Profile Settings and Privacy Policy. Lives inside More tab per user direction, not a new bottom-nav tab.
- Test employee account exists: `employee1@trucking.com` / `employee123@` (seed_employee.sql run by user, local only — not yet confirmed present on live DB import).

PRIOR SESSION — `home/index.php` rebuilt from fake static placeholders to real data:

- Clock-in/out: now writes real rows to `timesheet_entries` for today's date, user_id from session. DECISION: `clock_records` table stays UNUSED/dead — payroll and timesheet logic only ever read `timesheet_entries`.
- Timesheet summary card: real query against `timesheet_entries` for current month. Regular/OT hours computed with same >8h/day split used in `payroll/index.php`. "Days Present" = distinct dates with an entry this month.
- Days Absent: counts weekdays (Mon-Fri) in month-to-date with no entry row. KNOWN GAP: no holiday calendar exists, will overcount on PH holidays.
- Paid Leave / Unpaid Leave: no leave table exists anywhere in the schema. Left as "Not tracked" labels.
- Payroll block: real list of the logged-in user's last 6 `payroll_runs` rows (period, net_pay, draft/finalized status).
- `home/overview/index.php` still fully fake ("No data"/"--" cards) — untouched, still open.

TOOLING NOTE (recurring across sessions): the Filesystem edit_file/str_replace tool has repeatedly returned "File not found" on this project despite `read_multiple_files`/`write_file` working fine on the identical path. Root cause unclear. Standing workaround: default to `write_file` full overwrites for this project, always re-read the file fresh immediately before editing to avoid clobbering concurrent changes.

NOT YET DONE / NOT YET TESTED END-TO-END BY USER:

- Full approval→payroll flow (reject an entry → approve period → confirm timesheet_approvals row → run payroll → confirm ONLY approved employee gets a payroll_runs row → view deductions modal renders clean → re-run same period confirms duplicate-skip still works) has NOT been walked through end-to-end.
- Known unverified edge case: `payroll/index.php`'s deductions-modal JSON payload uses `onclick='...'` with single quotes; `json_encode(..., ENT_QUOTES)` should escape embedded quotes in employee names safely, but untested against a real name with an apostrophe/quote.
- Employee Management CRUD — untested.
- home/index.php real-data rebuild — untested.
- QR clock-in: still deferred, untouched.
- Time Out flow confirmation (biometric rework) and confirm-modal image preview — not yet confirmed by user.
- Soft-delete / reject-UI-removal — not yet tested by user.
- Admin-can-time-in-as-employee fix — CODE DONE, not yet tested by user.
- Self-signup + approval flow — CODE DONE, migration not run, not yet tested (this session, see top).
- Live deployment (InfinityFree) — landing page + login fixes applied, not yet fully walked end-to-end by user beyond initial 404/DB-connection fixes.

NEXT PRIORITY: not decided — options: (a) run migration 013 (local + live) and test self-signup/approval flow (just built), (b) test admin-cannot-time-in fix, (c) test soft-delete + reject-UI-removal, (d) test Employee Management CRUD end-to-end, (e) test home/index.php clock-in/out + summary changes, (f) walk full approval→payroll test end-to-end, (g) scope more DoEmploy features, (h) check IZNAHANYACHAY paper's Statement of the Problem / Objectives against actual system coverage, (i) confirm live DB has seed data (admin/employee test accounts) after import.

## DoEmploy Feature Gap (noted, partially actioned)

User shared full DoEmploy feature list. Comparing against this system's actual scope: Employee Management CRUD is ✅ PARTIAL (admin can view/edit any employee's record; direct-create via invite AND self-signup-with-approval both now exist). Still entirely missing: Shift Management, Leave Management, Break In/Out, Employee document upload, Benefits tracking, Bonuses/Allowances as payroll line items, Reports section, Notifications, Company Settings (contribution rates hardcoded, not configurable), Email Verification, Forgot Password. Partially covered but thinner: Dashboard, Payroll (no bonuses/allowances/payslip PDF export). Still a large scope gap overall — flagged for user to decide priority.

## Global Status

- DB name: iznahanyachay_trucking (local); if0_42596519_iznahanyachay_trucking (live, InfinityFree)
- DB connection: ✅ done — `includes/config.php` has DB_HOST/DB_NAME/DB_USER/DB_PASS + getDB() PDO singleton. Local and live values differ (see DEPLOYMENT note at top).
- Schema: 🟡 in progress — see Suggested DB Tables below for full live table list.
- Seed data: ✅ locally — `database/seed_admin.sql` (admin@trucking.com / admin123@, role=admin), `database/seed_employee.sql` (employee1@trucking.com / employee123@, role=employee). Exported and imported into live DB — presence on live not yet independently confirmed by user.
- Auth/session/role guard: ✅ `includes/auth.php` (requireLogin, requireAdmin, attemptLogin w/ pending-status check, logout). `login/index.php` wired to real DB check + role-based redirect + pending-specific error message.
- `home/index.php`: ✅ requireLogin() applied. Real clock-in/out, real month timesheet summary, real payroll_runs history list. NOT tested end-to-end by user.
- `more/profile/index.php`: ✅ Self-service profile edit. Read-only employment details block.
- `more/employees/index.php`: 🟡 Admin-only roster + edit-via-modal (converted this session, was page-reload) + Pending Approvals section (approve/reject self-signups). NOT tested by user.
- `includes/topbar.php`: ✅ optional `$topbarExtra` slot, backward compatible.
- `timesheet/index.php`: ✅ History button via `$topbarExtra` slot (employee-only). Confirmed working. Admin-time-in-as-employee bug — fix coded, not yet tested.
- `timesheet/entry/index.php`: 🟡 Soft-delete + reject-UI-removal DONE but NOT tested; migration 012 run. Admin Time-In/Time-Out access bug — CODE DONE (server-side block + read-only admin UI), NOT YET TESTED.
- `timesheet/log/index.php`: ✅ redesigned, confirmed working.
- `signup/index.php`: ✅ real self-signup form, creates pending employee account. NOT tested — migration 013 not run yet.
- Schema migrations: `002_clock_records.sql` (UNUSED/dead), `003_timesheet_entries.sql`, `004_trips.sql`, `005_payroll_runs.sql`, `006_payslips.sql`, `007_timesheet_status.sql`, `008_deductions.sql`, `009_timesheet_approvals.sql`, `010_employee_profiles.sql`, `011_timesheet_biometrics.sql`, `012_timesheet_soft_delete.sql` — confirmed live in DB (local; live DB import should include these). `013_employee_status_pending.sql` — NEW this session, NOT YET RUN anywhere. `invites` table still not started.
- `clock_records` table exists in DB but is UNUSED/dead.
- Payroll calc rules: rate_per_hour 100, ot_rate_per_hour 110 (base +10%), OT = hours beyond 8/day. Trip incentive: flat 50/trip via `trips` table (placeholder). Deductions: real 2026 government contribution tables.
- Real government deduction tables (2026, halved for semi-monthly 15/30 cutoffs): SSS — 15% of Monthly Salary Credit, employee pays 5%, MSC bracketed in ₱500 steps ₱5,000-₱35,000. PhilHealth — 5% of basic salary, employee pays 2.5%, floor ₱10,000/ceiling ₱100,000 monthly. Pag-IBIG — employee pays 1% if monthly salary ≤₱1,500 else 2%, capped at ₱10,000 monthly. Implemented as `calculateSSS()`, `calculatePhilHealth()`, `calculatePagibig()` in payroll/index.php.
- `deductions` table: transaction-log, one row per SSS/PhilHealth/Pag-IBIG per payroll_run. "View" button per payroll run row opens a modal with line items + basis notes.
- Payroll: ✅ working end-to-end, role-branched. `payroll/run/index.php` deprecated, redirects to `/payroll/`.
- Timesheet dark/light bug: ✅ fixed.
- Finalize action: ✅ done.
- Reusable confirm modal: ✅ `includes/confirm-modal.php`, wired everywhere including new pending-approve/reject buttons.
- Run Payroll: employee list from JOIN against `timesheet_approvals` for exact period. Duplicate-run skip per user_id+period_start+period_end.
- `timesheet/review/index.php`: ✅ admin-only, employee+period picker, per-entry reject (modal), Approve Period bulk-approve + timesheet_approvals insert.
- Biometric time-in: ✅ `includes/biometric.php` saves captured photo. Employee time-in requires photo capture. Admin has NO time-in/time-out path at all (server-side blocked + no form rendered).
- Account creation: TWO paths now — (1) admin direct-create via `home/invite/` (lands active), (2) self-signup via `signup/` (lands pending, needs admin approval via `more/employees/`).
- NEXT: (1) run migration 013 + test signup/approval flow. (2) test admin-cannot-time-in fix. (3) test soft-delete + reject-UI-removal. (4) Employee CRUD untested. (5) home/index.php changes untested. (6) full approval→payroll flow untested end-to-end. (7) QR clock-in deferred. (8) DoEmploy gaps remain. (9) IZNAHANYACHAY paper alignment check — open. (10) confirm live DB seed data present.
- Helper file: `PROMPT.md` — session-start prompt for new Claude accounts.
- Reference: IZNAHANYACHAY paper is source of truth for payroll calc rules (checked, no usable formulas found — current rates are real 2026 gov't tables instead), DoEmploy is UX/feature reference only.

## Directory Tree with Status

```
trucking_system/
├── index.php                      ✅ Public landing page (static marketing)
├── login/index.php                ✅ real auth, pending-account message
├── signup/index.php               ✅ real self-signup, creates pending employee account, NOT tested (migration 013 pending)
├── includes/
│   ├── config.php                 ✅ BASE_PATH + DB connection. LOCAL vs LIVE values differ, see DEPLOYMENT note.
│   ├── head.php                   ✅ done
│   ├── foot.php                   ✅ done
│   ├── topbar.php                 ✅ done — optional $topbarExtra slot added
│   ├── theme-toggle.php           ✅ done
│   ├── bottom-nav.php             ✅ done — 5 tabs: Home, Timesheet, Overview, Payroll, More
│   ├── confirm-modal.php          ✅ done — includes biometric photo preview
│   ├── biometric.php              ✅ done — saveBiometricPhoto()
│   ├── auth.php                   ✅ done — attemptLogin() now returns pending-vs-invalid fail reason
│   └── placeholder.php            ✅ done
├── home/
│   ├── index.php                  ✅ real clock-in/out, real timesheet summary, real payroll history. NOT tested end-to-end.
│   ├── overview/index.php         🟡 UI only — cards show static "No data"/"--"
│   ├── clock-in/index.php         🔴 orphaned placeholder — no longer linked, candidate for deletion
│   └── invite/index.php           ✅ admin direct-create employee account (lands active). NOT tested by user.
├── timesheet/
│   ├── index.php                  ✅ role-branched, manual entry + records, History button (employee) via topbar slot, day label is role-based ("Tap to View" admin / "Tap to Add" employee), links to review screen (admin).
│   ├── entry/index.php            🟡 per-date view, approve/reject/delete built; soft-delete done not tested; admin time-in/out bug fix coded, not tested.
│   ├── log/index.php              ✅ employee History page. Confirmed working.
│   └── review/index.php           ✅ admin-only approval screen
├── payroll/
│   ├── index.php                  ✅ role-branched, Run Payroll, Finalize, deductions view modal
│   └── run/index.php              🔴 deprecated, redirects to /payroll/
├── more/
│   ├── index.php                  ✅ real logout POST form, Employees + Routes links (admin-only), Profile/Privacy/About links
│   ├── profile/index.php          ✅ self-service edit, NOT tested
│   ├── employees/index.php        🟡 admin roster + edit-via-modal (converted this session) + Pending Approvals section + Position field (this session), NOT tested
│   ├── routes/index.php           🟡 NEW — admin CRUD for delivery routes/rates (add, edit-via-modal, activate/deactivate toggle, soft-hide only). NOT tested.
│   ├── trips/index.php            🟡 NEW — admin trip assignment (route+driver+helper picker, server-validated) + Mark Completed action. Targets `trips_new` table. NOT tested.
│   ├── privacy-policy/index.php   🔴 placeholder, needs policy text written
│   └── about/index.php            🔴 placeholder, needs version/info content
└── assets/
    ├── images/                    🔴 empty, all backdrops are CSS gradients
    ├── uploads/biometrics/        ✅ captured time-in photos
    └── js/theme.js                🟡 exists, unverified if still used
```

Note: `admin/` tree no longer exists — fully merged into single role-branched pages (see historical decision section below).

## Known Bugs (not yet fixed)

1. `home/clock-in/index.php` — orphaned, dead code, should be deleted.
2. Minor inconsistency: `home/invite/index.php` catches duplicate-email via DB unique constraint + PDOException; `more/employees/index.php` pre-checks email uniqueness in PHP before updating. Two different patterns for the same guarantee — not unified, not a functional bug.
3. CLOSED (pending test) — admin could Time In / Time Out as the currently-selected employee on `timesheet/entry/index.php`. Fixed: server-side block + dedicated read-only admin UI branch. Not yet tested by user.
4. CLOSED (pending migration + test) — no self-signup path existed; signup page was pure UI. Fixed: real signup + pending-approval gate. Migration 013 must be run first.

## Navigation Model

`bottom-nav.php` needs `$activeNav` (home|timesheet|overview|payroll|more) and `$navBase` (BASE_PATH) set before include. Overview link hardcoded to `$navBase/home/overview/`. Employee management deliberately lives inside More (not a new bottom-nav tab) per user direction — avoids a 6th tab on mobile.

## Theming Convention (for new pages going forward)

Render ONE version of content colored for both themes (`text-gray-900 dark:text-white`), never swap content via `dark:hidden`/`hidden dark:block`.

## Reference App

System is modeled after DoEmploy (payroll/attendance app): automated payroll calc + tax/OT rules, GPS-verified clock-in/out, employee profiles, job board. Job board NOT planned. GPS on clock-in is a LATER feature.

## Suggested DB Tables (status)

`users` ✅, `employee_profiles` ✅ (phone, address, license_number, license_expiry, hire_date, status — status now `pending|active|inactive` as of migration 013), `clock_records` ✅ exists but UNUSED/dead, `timesheet_entries` ✅ (manual + QR type column, QR path unused; `time_in_photo` added for biometric capture; `deleted_at` added for soft-delete), `payroll_runs` ✅, `payslips` ✅, `deductions` ✅, `timesheet_approvals` ✅, `trips` ⚠️ OLD placeholder table, still exists but DEAD/unused (flat ₱50 incentive, free-text destination, single user_id). `trips_new` ✅ NEW (migration 015, route_id/driver_id/helper_id/amount_per_trip/status/started_at/completed_at) — this is the table all trip-commission payroll work targets going forward. NOTE: name is `trips_new`, not renamed to `trips` (user ran migration as-written, old table left in place). `routes` ✅ NEW (migration 014, destination/amount_per_trip/active) — admin CRUD built, feeds `trips_new`. `invites` 🔴 not started, `performance_metrics` 🔴 not started, leave/PTO table 🔴 not started.

## DECISION MADE (historical): Kill admin/ tree, single pages with role branching

CLOSED, historical record. `admin/` directory fully removed. `home/index.php`, `timesheet/index.php`, `payroll/index.php`, `more/index.php` are the only copies, each branching on `$_SESSION['user']['role']` only where behavior actually differs. `_deleted_admin_*` folders were left on disk as backups — safe to hard-delete via OS whenever.

## PLANNED: Timesheet approval workflow (manual entries only — GPS/QR deferred)

DONE. `timesheet_entries` has `status` ENUM('pending','approved','rejected') DEFAULT 'pending' + `rejection_reason` nullable + `deleted_at` nullable (soft-delete, pending-only, admin-only). Approval is per-period via `timesheet/review/index.php`. `timesheet_approvals` is the append-only audit trail. `payroll/index.php` hours query filters `AND status = 'approved'`. Reject has no UI trigger (DB-side only, dead code path).

GPS/QR clock-in: deferred. When built, should NOT default to 'pending' like manual entries — auto-verified sources should fast-track/auto-approve.

## PLANNED: Run Payroll — dropdown instead of date range picker

DONE. Run Payroll period input is a dropdown of distinct (period_start, period_end) from `timesheet_approvals`. Per-employee run tracking: skip if a payroll_runs row already exists for that exact user_id + period_start + period_end. Partial periods fine — re-running fills gaps.

## DONE: Employee Management CRUD

`employee_profiles` table added, admin-only edit via `more/employees/index.php`, self-service edit (contact info + credentials only, not compliance fields) via `more/profile/index.php`. Lives inside More tab per user direction. Two account-creation paths now exist: admin direct-create (`home/invite/`, lands active) and self-signup (`signup/`, lands pending, needs admin approval — see THIS SESSION at top).
