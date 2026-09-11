# Trucking System — Architecture + Progress Reference

Local path: `C:\laragon\www\trucking_system`
Stack: PHP 8.1 (procedural, no framework) + Tailwind CDN (dark-mode via `class`) + vanilla JS.
DB: MySQL, local via Laragon. `includes/config.php` — `iznahanyachay_trucking`, user `root`, no password.

**RULE FOR WHOEVER CONTINUES THIS: the moment a page/function moves to a new status, update its tag in this file immediately.** This file is the single source of truth read at the start of every new session/account.

STATUS LEGEND: ✅ Done | 🟡 UI only / placeholder | 🔴 Not started | ⚠️ Has known bug

---

## READ THIS FIRST — 2026-09-11/12 autonomous session note

A previous local checkout of this repo was stuck on a stale branch (`aftermain0705`, forked before a lot of real work happened on `main` — self-signup, then its removal, biometrics, driver/helper roles, routes/trips/trip-attendance, real payroll). **If you ever find the working tree missing files like `more/employees/`, `more/routes/`, `more/trips/`, `timesheet/review/`, or `includes/biometric.php` — check `git branch -a` and make sure you're on `main`, not an old fork.** `git log origin/main --oneline` is the actual history; this file is rewritten from scratch this session to match it, since the old copy predated most of the current app and was actively misleading.

**#1 priority for the user tomorrow, before anything else:** two tables are used throughout the code (`payroll/index.php`, `timesheet/review/index.php`) but have **no migration file anywhere in this repo's git history** — confirmed via `grep -rn "CREATE TABLE" database/`. Whether your local DB already has them ad-hoc (out of migration tracking) or not, run these to make the repo self-consistent:
- `database/migrations/019_deductions.sql` — written and verified against the exact INSERT/SELECT shape `payroll/index.php` uses. NOT run.
- `database/migrations/020_timesheet_approvals.sql` — written and verified against `timesheet/review/index.php` + `payroll/index.php`'s usage. NOT run.

Without these: **Run Payroll and Approve Period both fail outright** on any database that doesn't already have them. This wasn't caused by tonight's session — it's a pre-existing gap, just never surfaced because MySQL wasn't running to test against.

Everything else below reflects a full manual read-through of every page in the live tree tonight (not guessed from old notes). Local MySQL was not running and was deliberately left untouched — no migrations were run, no schema/data was touched, per explicit instruction that backend/DB work waits for the user. All fixes below are pure code (PHP/HTML/JS), verified with `php -l` (all files pass, zero syntax errors) and careful manual tracing, not live click-through (couldn't — no DB connection available tonight).

### What changed tonight
- Committed a large chunk of already-finished, already-tested work that existed only as uncommitted changes in a stale branch's working tree (the admin/-tree-kill refactor) — see git log, this was superseded by real upstream work before it could be pushed, so it was left on the abandoned local `aftermain0705` branch and not merged into `main`. No data lost; `main` already had the real version of this refactor plus everything after it.
- `database/migrations/019_deductions.sql`, `020_timesheet_approvals.sql` — written, NOT run (see above).
- `more/about/index.php`, `more/privacy-policy/index.php` — were both missing `requireLogin()` (publicly reachable by URL, the last two pages with this gap). Fixed, and given real content instead of the generic placeholder (About: what the system does; Privacy: plain-language summary of what's collected/why, explicitly marked as not lawyer-reviewed).
- `home/clock-in/index.php` — was a dead, misleading placeholder (text literally said "no backend yet" while sitting right below a working `requireLogin()` call; nothing links to it anymore — the real clock-in flow is `/timesheet/entry/?date=<today>`, built later and never cleaned up here). Now redirects to that real flow, same pattern already established by `payroll/run/index.php`.
- `home/index.php` — removed a no-op double `array_reverse()` (query fetched DESC, reversed to ASC, then reversed again at render time — net effect was always just DESC; simplified to one un-reversed fetch).
- `timesheet/index.php`, `timesheet/review/index.php` — `$entry['time_out']` can be genuinely NULL (an in-progress shift, time_in recorded but not time_out yet) in the unguarded table-row loops on these two pages specifically (traced every other `htmlspecialchars($entry[...])` call in the codebase — all others are inside conditionals that already guarantee non-null). Added `?? ''` so PHP 8.1 doesn't emit a deprecation notice.
- `home/overview/index.php` — was 100% static ("No data" / "--" on every card, no query at all). Wired up **Total Salary** (`SUM(net_pay)` from finalized `payroll_runs`) and **Total Hours** (computed from approved `timesheet_entries`) with real queries — admin sees company-wide totals, employee sees their own. Left **Total Late** and **Performance** as an honest "Not tracked" — no late-threshold or performance-scoring concept exists anywhere in the schema, and fabricating a number would violate the same "don't invent data" rule `home/index.php` already follows for paid/unpaid leave.
- `more/profile/index.php` — Employment Details grid showed license/hire-date/status but not Position, even though it's already fetched into `$profile`. Added it (and the missing `'position' => null` key in the no-profile-row fallback array, which the new code would otherwise have hit as an undefined-array-key warning).
- `login/index.php` — added `autocomplete="email"` / `autocomplete="current-password"`, the only two inputs in the app missing it (checked every form in every page tonight; every other password/email field already had it).

### What was checked and found already fine (don't re-litigate)
- Every page's SQL uses prepared statements — no injection risk found anywhere.
- `saveBiometricPhoto()`'s `$date` parameter looked like a path-traversal risk at first glance (concatenated directly into a filename) — traced the only call site (`timesheet/entry/index.php`) and confirmed `$date` is always validated as `=== date('Y-m-d')` (today, server-generated, safe format) before that function is ever reached. Not exploitable, no fix needed.
- The dark/light "different content per theme" bug class from an old note is gone — repo-wide grep for `dark:hidden`/`hidden dark:block` found exactly one hit, `includes/theme-toggle.php`, which is the *correct* use of that pattern (swapping the sun/moon icon on the toggle button itself).
- `employee_profiles.status` still has a `'pending'` enum value (migration 013) and `attemptLogin()` still checks for it — this is **not a live gap**. Self-signup (which used to create pending profiles) was fully removed per a "panel revision" commit; `signup/index.php` is now just a redirect to `/login/`. Nothing creates `'pending'` profiles anymore. Left as-is; harmless vestige, not worth ripping out for a one-line dead branch.
- Responsive/design conventions (`px-4 sm:px-6` page padding, `max-w-*` containers, `overflow-x-auto` + `whitespace-nowrap` on every data table, `pb-32` on every `<main>` to clear the fixed bottom nav, dark: variants used consistently) are already applied correctly on every single page. No systemic responsive bugs found.

### Not done tonight (needs the user, or needs DB access to verify)
- **Run the two migrations above.** Do this before testing anything payroll- or approval-related.
- Full click-through testing — couldn't, MySQL wasn't running. Static review (read every file, `php -l` every file) only. Test the payroll/approval flow especially carefully once the migrations are in, since that code path has never been confirmed to actually execute successfully.
- Minor a11y gap noticed but not touched (large mechanical change, lower value than the above): most `<label>` elements aren't associated with their input via `for`/`id` (rely on visual adjacency only). Not a functional bug, just a screen-reader nicety, skipped tonight to focus on higher-value fixes.
- `home/index.php`'s stat grid (`grid-cols-3`, 6 items) wasn't visually verified at narrow widths — the classes look fine on paper but couldn't confirm without a live render.

---

## Global Status
- Auth/session/role guard: ✅ done. `includes/auth.php` — `requireLogin()`, `requireAdmin()`, `attemptLogin()`, `logout()`. Every page that renders real content now calls one of the two guards (verified via repo-wide grep tonight — only `index.php`, `login/`, `logout/`, `signup/` [dead redirect] correctly have none).
- Roles: `users.role` is `admin`/`employee` only. Within `employee`, `employee_profiles.position` sub-classifies as `driver`/`helper`/`dispatcher`/`secretary`/`maintenance`/`liaison`/`operator_manager`. Driver/helper pay is trip-commission-only (15%/8% of route rate); everyone else is hourly (₱100/hr, ₱110/hr OT, >8h/day = OT).
- Self-signup: removed (see above). Account creation is admin-only via `/home/invite/`.
- Biometric time-in: webcam photo captured client-side (canvas → base64 → `saveBiometricPhoto()`), required for employee time-in (not admin, admin can't time in/out at all — blocked server-side even via crafted POST). Stored in `assets/uploads/biometrics/`.
- Trip/attendance model: `routes` (destination + rate) → `trips_new` (assign driver [+ optional helper] to a route) → mark completed → auto-inserts `trip_attendance` rows (presence-only, no approval needed, verified by trip completion itself) and makes that trip eligible for commission in the next payroll run. The old `trips` table (migration 004) is dead/unused, fully superseded by `trips_new`.
- Timesheet (hourly employees only): time in/out at `/timesheet/entry/`, starts `'pending'`, admin approves/rejects per-entry there or in bulk per-period at `/timesheet/review/` (**needs migration 020**, see top of file). Payroll only ever counts `status = 'approved'` hours.
- Payroll: admin picks an approved period (**dropdown sourced from `timesheet_approvals`, needs migration 020**) and runs it — per employee, skips anyone already run for that exact period, computes real 2026 SSS/PhilHealth/Pag-IBIG (halved for semi-monthly cutoffs), writes summary columns to `payroll_runs` AND itemized rows to `deductions` (**needs migration 019**). Admin finalizes a draft run → snapshots to `payslips`, locked, no further edits.

## Directory Tree with Status
```
trucking_system/
├── index.php                      ✅ Public marketing landing page
├── login/index.php                ✅ Real DB auth, role-neutral redirect to /home/
├── signup/index.php                — dead redirect to /login/ (self-signup removed)
├── logout/index.php               ✅ POST-only, calls logout(), redirects to /login/
├── includes/
│   ├── config.php                 ✅ DB connection (PDO singleton), BASE_PATH, timezone
│   ├── auth.php                   ✅ requireLogin/requireAdmin/attemptLogin/logout
│   ├── biometric.php              ✅ saveBiometricPhoto() — safe, see audit note above
│   ├── head.php / foot.php        ✅ shared shell, theme init (no FOUC), Tailwind CDN config
│   ├── topbar.php                 ✅ per-page icon/label + optional extra action + theme toggle
│   ├── theme-toggle.php           ✅ sun/moon icon button
│   ├── bottom-nav.php             ✅ 5-tab fixed nav, shared by both roles (no more admin/ base)
│   ├── confirm-modal.php          ✅ reusable confirm dialog, `data-confirm="..."` on any form
│   └── placeholder.php            ✅ generic "not built yet" shell — only used by nothing now (last two callers given real content tonight)
├── home/
│   ├── index.php                  ✅ real dashboard — this month's hours/absences, recent payroll, admin gets invite card
│   ├── overview/index.php         ✅ real Total Salary + Total Hours (wired tonight); Total Late/Performance honestly "Not tracked"
│   ├── clock-in/index.php          — redirects to /timesheet/entry/?date=today (fixed tonight, was dead)
│   └── invite/index.php           ✅ admin-only, creates employee account + profile (position, license, salary, etc.)
├── timesheet/
│   ├── index.php                  ✅ month calendar, admin picks employee, links to entry/review
│   ├── entry/index.php            ✅ per-day time in/out (biometric required for employees), admin approve/reject/soft-delete
│   ├── review/index.php           ✅ bulk per-period approve + per-entry reject — **needs migration 020 to actually work**
│   └── log/index.php              ✅ employee-only monthly history with photos, month nav
├── payroll/
│   ├── index.php                  ✅ Run Payroll (dropdown of approved periods) + Finalize + itemized deductions modal — **needs migrations 019 and 020 to actually work**
│   └── run/index.php               — dead redirect to /payroll/ (deprecated, kept as stub)
├── more/
│   ├── index.php                  ✅ settings menu, role-aware links, logout
│   ├── profile/index.php          ✅ self-editable name/email/phone/address/password + read-only employment details (Position added tonight)
│   ├── employees/index.php        ✅ admin-only list + edit modal (position, salary, license, status)
│   ├── routes/index.php           ✅ admin-only CRUD (add/edit/toggle active)
│   ├── trips/index.php            ✅ admin-only assign + mark-completed (row-locked, transactional)
│   ├── trip-attendance/index.php  ✅ admin-only read-only report, filterable
│   ├── about/index.php            ✅ real content + guard added tonight (was unguarded placeholder)
│   └── privacy-policy/index.php   ✅ real content + guard added tonight (was unguarded placeholder)
├── database/
│   ├── schema.sql                 users table only (base)
│   ├── migrations/002-018         all CREATE/ALTER, run and in git history
│   └── migrations/019-020         written tonight, **NOT run** — see top of file
└── assets/
    ├── uploads/biometrics/        time-in photos land here
    └── images/                    still empty — index.php and login/index.php both have CSS-gradient placeholder backdrops with a comment marking where a real photo would go
```

## Known Gaps (not bugs, just unbuilt)
1. QR clock-in — the Timesheet page still has a static "Scan QR / Waiting for scan..." card. Never designed, explicitly deferred in earlier sessions. Left alone.
2. No forced-password-change flow after admin creates an account with a temp password (documented directly in `home/invite/index.php`'s own UI copy).
3. `assets/images/` is empty — both public-facing pages use CSS gradients with a code comment marking where a real photo goes.
4. A11y: labels aren't `for`/`id`-associated with their inputs anywhere in the app (see session note above).
