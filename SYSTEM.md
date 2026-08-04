# Trucking System — Architecture + Progress Reference

Local path: `C:\xampp\htdocs\trucking_system`
Stack: PHP (procedural, no framework) + Tailwind CDN + vanilla JS. DB connected, backend logic in progress on several pages (see below).

STATUS LEGEND: ✅ Done | 🟡 UI only / placeholder | 🔴 Not started | ⚠️ Has known bug

RULE FOR WHOEVER CONTINUES THIS: the moment a page/function moves to a new status, update its tag in this file immediately. This file is the single source of truth read at the start of every new session/account.

## SESSION HANDOFF (read this first)

THIS SESSION (latest) — Two small fixes, both confirmed working by user:

1. `includes/topbar.php` — added optional `$topbarExtra` slot (raw HTML string, rendered between title and theme toggle). Left unset/empty by default, backward compatible with all other pages that include topbar.php without setting it.
2. `timesheet/index.php` — wired the employee-only "History" button into `$topbarExtra` (was previously a standalone row below the topbar). Set before `include topbar.php`, admin gets empty string (no button). `str_replace` failed (File not found) on this file again — used `write_file` full overwrite per established workaround, verified after write.
3. `timesheet/log/index.php` (History page) — redesigned entry list. WAS: two stacked rows per date (Time Out row on top, Time In row below, each with its own avatar/placeholder). NOW: one container per date, single row, Time In (photo + green dot + time) on the left, Time Out (red dot + time, no photo) on the right, flex justify-between. If only one of time_in/time_out exists for a date, only that side renders. `str_replace` failed on this file too (same known issue) — used `write_file` full overwrite, verified after write.

Both fixes tested and confirmed working by user this session.

PRIOR SESSION — Biometric time-in/time-out rework on `timesheet/entry/index.php` (per-date page) and `timesheet/index.php` (calendar page's inline manual forms). Both pages now behave identically:

- DB: migration `011_timesheet_biometrics.sql` adds `time_in_photo VARCHAR(255) NULL` to `timesheet_entries`. RUN by user, confirmed live. New folder `assets/uploads/biometrics/` created for captured JPEGs.
- New helper `includes/biometric.php` — `saveBiometricPhoto($dataUrl, $userId, $date)` decodes a base64 data-URL and writes it to `assets/uploads/biometrics/{userId}_{date}_{timestamp}.jpg`, returns the relative path or null on failure.
- Backend logic split: old single `save_time` action (required time_in AND time_out together) replaced with two independent actions, `save_time_in` and `save_time_out`, on both pages:
  - `save_time_in`: blocks if an entry already has `time_in` set (server-side lock, not just UI). Employees MUST supply `photo_data` (base64 capture) or it errors — admins entering on behalf of an employee are exempt from the photo requirement (per user's explicit direction: biometric applies to employee self-entry only). Saves `time_in` + `time_in_photo` path.
  - `save_time_out`: blocks unless an entry with `time_in` already exists. No photo required. Validates time_out > time_in.
- UI lock (entry page only, since it's per-date): no entry/no time_in → only Time In control shown. time_in set, no time_out → time_in rendered disabled, only Time Out control active. Both set → both fields locked read-only, no forms. NOTE: `timesheet/index.php`'s inline manual forms do NOT have this pre-emptive greyed-out lock (any date can be picked freely there, so there's no single date to lock against ahead of typing) — the same lock rules are enforced server-side only on that page. Flagged to user as a scoping tradeoff, not objected to.
- Webcam capture flow (employee-only, both pages, mirrored with `manual-` prefixed element IDs on the calendar page to avoid ID collisions): clicking "Time In" opens the camera live (`getUserMedia`) in place of submitting — button swaps to "Capture Photo" — clicking that snaps a canvas frame, stores it as a base64 JPEG in a hidden `photo_data` field, stops the camera stream, then calls `form.requestSubmit()`. No separate "Open Camera"/"Retake" step — matches user's explicit requested flow (click Time In → camera opens → employee captures → submits with confirm). Admin path has no camera, plain submit button.
- Confirm modal (`includes/confirm-modal.php`) extended: now shows an image preview of the captured photo above the confirmation message, read automatically from the submitting form's `photo_data` field (generic — works for both pages without extra wiring, no-ops cleanly for time-out confirms which have no photo).
- BUG FOUND AND FIXED this session: the calendar page's manual "Time In"/"Time Out" `date` inputs had no default value and are `required`. Since the new flow calls `form.requestSubmit()` from JS (not a real user click on the submit button), the browser's native HTML5 validation silently blocked submission when the date was empty — no error, no confirm modal, nothing visibly happened. Fixed by defaulting both date inputs to `date('Y-m-d')` (today), same as the time inputs already default to now. Confirmed fixed by user in a subsequent session (History button work this session happened on top of a working state).
- OVERALL STATUS: entry page (`timesheet/entry/index.php`) time-in flow confirmed working by user. Calendar page (`timesheet/index.php`) inline manual Time In confirmed fixed. Time Out flow (either page) not explicitly confirmed working yet. Image preview in confirm modal not yet confirmed by user.

PRIOR SESSION — DONE: clock-in/out block (POST handler + pill UI + `$clockError`/`$clockState`/`$todayEntry`) fully removed from `home/index.php`, both roles. RESOLVED — no dedicated clock page needed: `timesheet/entry/index.php` was WRONGLY flagged in this file as a "dead placeholder" (Known Bug #1) — it is NOT a placeholder, it's a fully built manual time-in/time-out + approve/reject page, reachable via `timesheet/index.php` calendar grid. That page is now the only clock mechanism for both roles (admin has no clock-in at all, by design — admin isn't a driver). Known Bug #1 below is STALE/WRONG and should be disregarded/removed. `str_replace` tool failed again on `home/index.php` (same File-not-found issue noted previously) — used `write_file` full overwrite per the established workaround. `clock_records` table and `home/clock-in/index.php` are now unambiguously dead (nothing writes to either) — still not deleted, still awaiting explicit go-ahead. Month summary, payroll history, admin invite link on `home/index.php` untouched and unaffected. NOT tested by user yet: page loads/renders with clock block gone, `timesheet/entry/` still fully functional as sole clock path.

PRIOR SESSION — Employee invite/create flow built (was fully missing, flagged as gap #3 below):

- `home/invite/index.php` REBUILT (was placeholder). Admin-only (`requireAdmin()`). Direct-create, not token-based — no email/SMTP capability exists anywhere in this system, so a real invite-link flow was pointless right now. Form: name, email, temp password (plaintext input, min 8 chars, admin sees/sets it), license_number, license_expiry, hire_date, status. On submit: inserts into `users` (role='employee', password_hash()) AND `employee_profiles` in one transaction (both created together, not deferred to first edit). Duplicate email relies on the DB-level UNIQUE constraint on `users.email` (schema.sql) caught via PDOException, not a pre-check-then-insert (more/employees/index.php uses pre-check instead — inconsistent pattern between the two pages, flagged, not unified). On success shows the temp password once back to the admin (no other way to deliver it — share out-of-band).
- `more/employees/index.php` — added "+ Invite Employee" button above the employee table, links to `/home/invite/`.
- `invites` table still NOT created — this flow doesn't use it, deliberately. If a real token/email invite flow is ever wanted, that's still fully unbuilt.
- NOT tested by user yet: full create flow (users + employee_profiles insert together), duplicate-email rejection, temp password login working for the new account.
- Gap #3 from Known Bugs ("no employee account creation flow") — now CLOSED pending user testing.

PRIOR SESSION — Employee Management CRUD built (was fully missing, flagged in DoEmploy gap):

- New table `employee_profiles` (migration `010_employee_profiles.sql`, RUN by user, confirmed live): user_id (FK, unique), phone, address, license_number, license_expiry, hire_date, status enum(active/inactive), updated_at. Split from `users` on purpose — `users` also holds admin accounts, none of these fields apply to admin.
- Field ownership split, deliberate: `name`/`email`/password stay self-editable by anyone (own account) via `more/profile/index.php`. `phone`/`address` are self-editable (contact info). `license_number`/`license_expiry`/`hire_date`/`status` are ADMIN-ONLY edit via `more/employees/index.php` — these are compliance/identity fields for a trucking company (license expiry matters for driver legality), letting employees self-edit them would let payroll/compliance records get silently altered. User initially asked for full employee self-edit; pushed back on this specifically for license/hire/status fields, user did not object.
- `more/profile/index.php` REBUILT (was a placeholder): self-service, both roles. Edit name/email/phone/address/password (password optional, blank = unchanged, min 8 chars enforced). Read-only "Employment Details" block shows license/hire_date/status with a note to contact HR/admin to change.
- `more/employees/index.php` NEW, admin-only (`requireAdmin()`). Lists all `role='employee'` users LEFT JOINed with `employee_profiles` (phone, license, hire_date, status shown in table, handles employees with no profile row yet via LEFT JOIN + null coalescing). `?edit={id}` shows an edit form for that employee: name, email, license_number, license_expiry, hire_date, status. Validates employee exists + role=employee before allowing edit (can't edit an admin account through this form). Upserts into `employee_profiles` (insert if no row exists yet, update otherwise).
- Nav: `more/index.php` — added "Employees" link, wrapped in `if (role === 'admin')`, sits between Profile Settings and Privacy Policy. Per user's explicit direction, this lives inside the More tab rather than as a new bottom-nav tab (avoiding a 6th tab on mobile).
- NOT tested by user yet: creating a new employee's profile row via the edit form (upsert path), editing an existing one, self-profile edit including password change, LEFT JOIN rendering correctly for the seeded employee1@trucking.com account (which has no employee_profiles row yet — should show blank/— fields, not error).
- Test employee account exists: `employee1@trucking.com` / `employee123@` (seed_employee.sql run by user). Use this to test both `more/profile/` self-edit and confirm `more/employees/` list+edit works against it from the admin side.
- No employee CREATE (invite/signup) flow exists yet — `more/employees/index.php` only edits existing `role='employee'` users. Employee accounts still only get created via direct SQL insert (see `database/seed_employee.sql` pattern) or eventually via `home/invite/index.php` (still an unbuilt placeholder). Not in scope this session.
- TOOLING NOTE: `str_replace` tool returned "File not found" for both `SYSTEM.md` and `more/index.php` in this session despite `read_multiple_files` and `write_file` on the same exact paths working fine. Root cause unclear (possibly a path-normalization mismatch specific to str_replace). Workaround used both times: read full file content, then `write_file` a full overwrite instead of a targeted diff. If str_replace keeps failing on this project going forward, default to write_file overwrites and be careful to re-read the file fresh immediately before to avoid clobbering concurrent changes.

PRIOR SESSION — `home/index.php` rebuilt from fake static placeholders to real data:

- Clock-in/out: now writes real rows to `timesheet_entries` (time_in on clock-in POST, time_out on clock-out POST) for today's date, user_id from session. Pill shows real state: Not Clocked-In / Clocked-In since HH:MM / Done for today (range). DECISION: `clock_records` table stays UNUSED/dead — payroll and timesheet logic only ever read `timesheet_entries`, so a second live clock-punch table would be a disconnected source of truth. Flagged as dead schema, not deleted, pending explicit decision to drop it.
- Timesheet summary card: real query against `timesheet_entries` for current month. Regular/OT hours computed with same >8h/day split used in `payroll/index.php`. "Not Clocked-In" counter removed, replaced with "Days Present" (distinct dates with an entry this month).
- Days Absent: counts weekdays (Mon-Fri) in month-to-date with no entry row. KNOWN GAP: no holiday calendar exists, will overcount on PH holidays — flagged, not fixed.
- Paid Leave / Unpaid Leave: no leave table exists anywhere in the schema. Left as "Not tracked" labels instead of fabricating 0.00h. Needs a real leave table before these can show real numbers (see DoEmploy gap section — Leave Management fully unstarted).
- Payroll block: fake hardcoded SVG sparkline (not connected to any query) removed. Replaced with real list of the logged-in user's last 6 `payroll_runs` rows (period, net_pay, draft/finalized status).
- NOT tested by user yet: clock-in button creates row, clock-out closes it, double clock-in/out blocked, days-absent count sane on a fresh month.
- `home/clock-in/index.php` (separate placeholder page) now orphaned — clock in/out happens inline via POST on `home/index.php` itself, pill no longer links out to it. Not deleted. Should confirm nothing else links to `/home/clock-in/`; if confirmed unused, mark for deletion.
- `home/overview/index.php` still fully fake ("No data"/"--" cards) — not touched this session, still open.
- `timesheet/entry/index.php` still a dead placeholder linked from every day in the timesheet grid — not touched this session, still open, known bug.

EARLIER SESSION — approval workflow correction:
CORRECTION TO PRIOR HANDOFF: an earlier note claimed the timesheet approval workflow (review screen, `timesheet_approvals` table) was fully "DONE." That was false — `timesheet/review/index.php` did not exist on disk (empty folder) despite being referenced by a working "Review Period" link on timesheet/index.php, and migrations `008_deductions.sql`/`009_timesheet_approvals.sql` were never saved as files. Recreated both migration files that session to match live schema. Lesson: "DONE" in this file must mean confirmed on disk AND tested, not just described as done.

NOT YET DONE / NOT YET TESTED END-TO-END BY USER:

- Full approval→payroll flow (reject an entry → approve period → confirm timesheet_approvals row → run payroll → confirm ONLY approved employee gets a payroll_runs row → view deductions modal renders clean → re-run same period confirms duplicate-skip still works) has NOT been walked through end-to-end.
- Known unverified edge case: `payroll/index.php`'s deductions-modal JSON payload uses `onclick='...'` with single quotes; `json_encode(..., ENT_QUOTES)` should escape embedded quotes in employee names safely, but untested against a real name with an apostrophe/quote.
- Employee Management CRUD — untested, see above.
- home/index.php real-data rebuild — untested, see above.
- User flagged the full DoEmploy feature list as scope this system is modeled after — most of it still not planned/started (see DoEmploy comparison note below). Employee Management CRUD closes ONE item on that list; rest remain open.
- QR clock-in: still deferred, untouched.
- Time Out flow confirmation (biometric rework) and confirm-modal image preview — not yet confirmed by user.

NEXT PRIORITY: not decided — options: (a) test Employee Management CRUD end-to-end (both self-profile edit and admin employee edit), (b) test home/index.php clock-in/out + summary changes end-to-end, (c) walk full approval→payroll test end-to-end, (d) fix `timesheet/entry/index.php` dead link (known bug, hit immediately from timesheet grid), (e) build employee invite/create flow since admin can now edit but not create employee accounts, (f) scope more DoEmploy features, (g) check IZNAHANYACHAY paper's Statement of the Problem / Objectives against actual system coverage (open item, user raised this, not yet actioned).

## DoEmploy Feature Gap (noted, partially actioned)

User shared full DoEmploy feature list. Comparing against this system's actual scope: Employee Management CRUD is now ✅ PARTIAL (admin can view/edit any employee's record — name, email, phone, address, license, hire date, status; CANNOT create new employee accounts yet, no invite/signup flow wired). Still entirely missing: Shift Management, Leave Management (request/approve/reject/cancel, balance, history), Break In/Out, Employee document upload, Benefits tracking (PTO/vacation/sick as first-class features), Bonuses/Allowances as payroll line items (distinct from existing deductions), Reports section (any kind, no PDF/Excel export), Notifications, Company Settings (payroll settings, attendance rules, leave rules, tax settings, holiday management, role/permission management — current gov't contribution rates are hardcoded constants in payroll/index.php, not configurable), Email Verification, Forgot Password. Partially covered but thinner: Dashboard (home/index.php now has real clock-in + timesheet summary + payroll history; home/overview/index.php still static placeholders), Payroll (has salary/OT/deductions calc, no bonuses/allowances/payslip PDF export/history export). Still a large scope gap overall — flagged for user to decide priority.

## Global Status

- DB name: iznahanyachay_trucking
- DB connection: ✅ done — `includes/config.php` has DB_HOST/DB_NAME/DB_USER/DB_PASS (root, no password) + getDB() PDO singleton
- Schema: 🟡 in progress — see Suggested DB Tables below for full live table list.
- Seed data: ✅ `database/seed_admin.sql` (admin@trucking.com / admin123@, role=admin), `database/seed_employee.sql` (employee1@trucking.com / employee123@, role=employee) — both run, both live.
- Auth/session/role guard: ✅ `includes/auth.php` (requireLogin, requireAdmin, attemptLogin, logout). `login/index.php` wired to real DB check + role-based redirect.
- `home/index.php`: ✅ requireLogin() applied. Real clock-in/out (writes timesheet_entries), real month timesheet summary, real payroll_runs history list. NOT tested end-to-end by user.
- `more/profile/index.php`: ✅ Self-service profile edit (name/email/phone/address/password), both roles. Read-only employment details block (license/hire_date/status).
- `more/employees/index.php`: ✅ Admin-only roster + edit any employee's record (name/email/license/hire_date/status). NOT tested by user.
- `includes/topbar.php`: ✅ optional `$topbarExtra` slot added, backward compatible.
- `timesheet/index.php`: ✅ History button now rendered via `$topbarExtra` slot (employee-only). Confirmed working.
- `timesheet/log/index.php`: ✅ redesigned — one container per date, Time In left (with photo)/Time Out right (no photo), same row. Confirmed working.
- Schema migrations: `002_clock_records.sql` (UNUSED/dead — see note below), `003_timesheet_entries.sql`, `004_trips.sql`, `005_payroll_runs.sql`, `006_payslips.sql`, `007_timesheet_status.sql`, `008_deductions.sql`, `009_timesheet_approvals.sql`, `010_employee_profiles.sql`, `011_timesheet_biometrics.sql` — all confirmed live in DB. `invites` table still not started.
- `clock_records` table exists in DB but is UNUSED/dead — nothing reads or writes it, all clock/timesheet logic goes through `timesheet_entries` instead.
- Payroll calc rules: rate_per_hour 100, ot_rate_per_hour 110 (base +10%), OT = hours beyond 8/day. Trip incentive: flat 50/trip via `trips` table (still placeholder, no source doc had real trip incentive rules). Deductions: real 2026 government contribution tables (see below). IZNAHANYACHAY paper checked — contains no usable payroll formulas.
- Real government deduction tables (2026, halved for semi-monthly 15/30 cutoffs): SSS — 15% of Monthly Salary Credit, employee pays 5%, MSC bracketed in ₱500 steps ₱5,000-₱35,000 (per-cutoff employee share ₱125-₱875). PhilHealth — 5% of basic salary, employee pays 2.5%, floor ₱10,000/ceiling ₱100,000 monthly (per-cutoff share ₱125-₱1,250). Pag-IBIG — employee pays 1% if monthly salary ≤₱1,500 else 2%, capped at ₱10,000 monthly (per-cutoff max ₱100). Implemented as `calculateSSS()`, `calculatePhilHealth()`, `calculatePagibig()` in payroll/index.php.
- `deductions` table: transaction-log, one row per SSS/PhilHealth/Pag-IBIG per payroll_run. "View" button per payroll run row in payroll/index.php opens a modal with line items + basis notes.
- Payroll: ✅ working end-to-end, role-branched. `payroll/index.php` single page — admin sees Run Payroll form + all runs + Finalize; employee sees own runs only, no Run Payroll/Finalize, "Draft" badge until finalized. `payroll/run/index.php` deprecated, redirects to `/payroll/`.
- Timesheet dark/light bug: ✅ fixed on `timesheet/index.php`.
- Finalize action: ✅ done — draft → payslips snapshot + status flip, locked after.
- Reusable confirm modal: ✅ `includes/confirm-modal.php`, wired on Run Payroll and Finalize forms, extended with image preview for biometric captures.
- Run Payroll: employee list comes from JOIN against `timesheet_approvals` for exact period. Duplicate-run skip per user_id+period_start+period_end.
- `timesheet/review/index.php`: ✅ admin-only, employee+period picker, per-entry reject (modal), Approve Period bulk-approve + timesheet_approvals insert.
- Biometric time-in: ✅ `includes/biometric.php` saves captured photo, `time_in_photo` column live. Employee time-in requires photo capture; admin exempt. Time-in lock server-side on both `timesheet/entry/` and `timesheet/index.php`.
- NEXT: (1) Employee CRUD untested. (2) home/index.php changes untested. (3) full approval→payroll flow untested end-to-end. (4) QR clock-in deferred. (5) DoEmploy gaps remain (see above). (6) IZNAHANYACHAY paper problem/objective alignment check — open, not yet actioned.
- Helper file: `PROMPT.md` — session-start prompt for new Claude accounts.
- Reference: IZNAHANYACHAY paper is source of truth for payroll calc rules (checked, no usable formulas found — current rates are real 2026 gov't tables instead), DoEmploy is UX/feature reference only.

## Directory Tree with Status

```
trucking_system/
├── index.php                      ✅ Public landing page (static marketing)
├── login/index.php                🟡 UI only — form does nothing, links straight to /home/
├── signup/index.php               🟡 UI only — form does nothing, links to /login/
├── includes/
│   ├── config.php                 ✅ BASE_PATH + DB connection (getDB() PDO singleton)
│   ├── head.php                   ✅ done
│   ├── foot.php                   ✅ done
│   ├── topbar.php                 ✅ done — optional $topbarExtra slot added
│   ├── theme-toggle.php           ✅ done
│   ├── bottom-nav.php             ✅ done — 5 tabs: Home, Timesheet, Overview, Payroll, More
│   ├── confirm-modal.php          ✅ done — includes biometric photo preview
│   ├── biometric.php              ✅ done — saveBiometricPhoto()
│   └── placeholder.php            ✅ done
├── home/
│   ├── index.php                  ✅ real clock-in/out, real timesheet summary, real payroll history. NOT tested end-to-end.
│   ├── overview/index.php         🟡 UI only — cards show static "No data"/"--"
│   ├── clock-in/index.php         🔴 orphaned placeholder — no longer linked, candidate for deletion
│   └── invite/index.php           ✅ admin direct-create employee account (users + employee_profiles). NOT tested by user.
├── timesheet/
│   ├── index.php                  ✅ role-branched, manual entry + records, History button (employee) via topbar slot, links to review screen (admin)
│   ├── entry/index.php            ✅ per-date manual time in/out + biometric capture, approve/reject
│   ├── log/index.php              ✅ employee History page — monthly log, one container per date, Time In left/Time Out right. Confirmed working.
│   └── review/index.php           ✅ admin-only approval screen
├── payroll/
│   ├── index.php                  ✅ role-branched, Run Payroll, Finalize, deductions view modal
│   └── run/index.php              🔴 deprecated, redirects to /payroll/
├── more/
│   ├── index.php                  ✅ real logout POST form, Employees link (admin-only), Profile/Privacy/About links
│   ├── profile/index.php          ✅ self-service edit, NOT tested
│   ├── employees/index.php        ✅ admin roster + edit, NOT tested
│   ├── privacy-policy/index.php   🔴 placeholder, needs policy text written
│   └── about/index.php            🔴 placeholder, needs version/info content
└── assets/
    ├── images/                    🔴 empty, all backdrops are CSS gradients
    ├── uploads/biometrics/        ✅ captured time-in photos
    └── js/theme.js                🟡 exists, unverified if still used
```

Note: `admin/` tree no longer exists — fully merged into single role-branched pages (see historical decision section below).

## Known Bugs (not yet fixed)

1. STALE ENTRY, DISREGARD — was: "`timesheet/entry/index.php` dead placeholder." Corrected: it is fully built (time in/out form + approve/reject), not a placeholder. No action needed here.
2. `home/clock-in/index.php` — orphaned, and now doubly so since `home/index.php` clock block was removed entirely. Dead code, should be deleted.
3. CLOSED (pending test) — `home/invite/index.php` now creates employee accounts directly (users + employee_profiles). Was: no creation flow existed.
4. Minor inconsistency: `home/invite/index.php` catches duplicate-email via DB unique constraint + PDOException; `more/employees/index.php` pre-checks email uniqueness in PHP before updating. Two different patterns for the same guarantee — not unified, not a functional bug.

## Navigation Model

`bottom-nav.php` needs `$activeNav` (home|timesheet|overview|payroll|more) and `$navBase` (BASE_PATH) set before include. Overview link hardcoded to `$navBase/home/overview/`. Employee management deliberately lives inside More (not a new bottom-nav tab) per user direction — avoids a 6th tab on mobile.

## Theming Convention (for new pages going forward)

Render ONE version of content colored for both themes (`text-gray-900 dark:text-white`), never swap content via `dark:hidden`/`hidden dark:block`.

## Reference App

System is modeled after DoEmploy (payroll/attendance app): automated payroll calc + tax/OT rules, GPS-verified clock-in/out, employee profiles, job board. Job board NOT planned. GPS on clock-in is a LATER feature.

## Suggested DB Tables (status)

`users` ✅, `employee_profiles` ✅ (phone, address, license_number, license_expiry, hire_date, status), `clock_records` ✅ exists but UNUSED/dead, `timesheet_entries` ✅ (manual + QR type column, QR path unused; `time_in_photo` added for biometric capture), `payroll_runs` ✅, `payslips` ✅, `deductions` ✅, `timesheet_approvals` ✅, `trips` ✅, `invites` 🔴 not started, `performance_metrics` 🔴 not started, leave/PTO table 🔴 not started (needed for Paid/Unpaid Leave to show real data on home/index.php).

## DECISION MADE (historical): Kill admin/ tree, single pages with role branching

CLOSED, historical record. `admin/` directory fully removed. `home/index.php`, `timesheet/index.php`, `payroll/index.php`, `more/index.php` are the only copies, each branching on `$_SESSION['user']['role']` only where behavior actually differs. `_deleted_admin_*` folders were left on disk as backups (no delete tool via Filesystem MCP, only move) — safe to hard-delete via OS whenever.

## PLANNED: Timesheet approval workflow (manual entries only — GPS/QR deferred)

DONE. `timesheet_entries` has `status` ENUM('pending','approved','rejected') DEFAULT 'pending' + `rejection_reason` nullable. Approval is per-period via `timesheet/review/index.php`. `timesheet_approvals` is the append-only audit trail. `payroll/index.php` hours query filters `AND status = 'approved'`, confirmed in place.

GPS/QR clock-in: deferred. When built, should NOT default to 'pending' like manual entries — auto-verified sources should fast-track/auto-approve.

## PLANNED: Run Payroll — dropdown instead of date range picker

DONE. Run Payroll period input is a dropdown of distinct (period_start, period_end) from `timesheet_approvals`. Per-employee run tracking: skip if a payroll_runs row already exists for that exact user_id + period_start + period_end. Partial periods fine — re-running fills gaps.

## DONE: Employee Management CRUD

See SESSION HANDOFF at top for full detail. Summary: `employee_profiles` table added, admin-only edit via `more/employees/index.php`, self-service edit (contact info + credentials only, not compliance fields) via `more/profile/index.php`. Lives inside More tab per user direction. Employee account CREATION still not built — that's the `home/invite/` gap, separate from this.
