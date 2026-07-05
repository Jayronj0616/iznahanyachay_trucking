# Trucking System — Architecture + Progress Reference

Local path: `C:\laragon\www\trucking_system`
Stack: PHP (procedural, no framework) + Tailwind CDN + vanilla JS. No DB yet. No backend logic yet.

STATUS LEGEND: ✅ Done | 🟡 UI only / placeholder | 🔴 Not started | ⚠️ Has known bug

RULE FOR WHOEVER CONTINUES THIS: the moment a page/function moves to a new status, update its tag in this file immediately. This file is the single source of truth read at the start of every new session/account.

## Global Status
- DB name: iznahanyachay_trucking
- DB connection: ✅ done — `includes/config.php` has DB_HOST/DB_NAME/DB_USER/DB_PASS (root, no password) + getDB() PDO singleton
- Schema: 🟡 in progress — `database/schema.sql` has users table only (id, name, email, password, role enum admin/employee, created_at). More tables needed (see below).
- Seed data: ✅ `database/seed_admin.sql` — admin@trucking.com / admin123@ (bcrypt hashed), role=admin
- Auth/session/role guard: 🟡 in progress — `includes/auth.php` done (requireLogin, requireAdmin, attemptLogin, logout). `login/index.php` wired to real DB check + role-based redirect. NOT YET applied: requireLogin()/requireAdmin() not called at top of any home/timesheet/payroll/admin pages — all pages still publicly reachable by URL.
- `home/index.php`: ✅ requireLogin() applied, label fixed "Admin Clock-In" → "Clock-In" (was wrong on employee page)
- `admin/home/index.php`: ✅ requireAdmin() applied. Admin Clock-In button → `/admin/home/clock-in/`. Invite link fixed → `/admin/home/invite/` (new placeholder page created).
- Schema migrations: `002_clock_records.sql`, `003_timesheet_entries.sql`, `004_trips.sql`, `005_payroll_runs.sql`, `006_payslips.sql` — all run and confirmed executed. `invites` table still not started.
- Payroll calc rules (placeholder, not compliant — for demo/presentation only): rate_per_hour 100, ot_rate_per_hour 110 (base +10%), OT = hours beyond 8/day. Trip incentive: flat 50/trip via new `trips` table (no source doc had real trip incentive rules). Deductions: SSS 4.5%, PhilHealth 3%, Pag-IBIG 2% — flat placeholder percentages, NOT real government tables. IZNAHANYACHAY paper checked — contains no usable payroll formulas (Chapter 4/ERD/Data Dictionary empty in uploaded draft).
- NEXT after sample data seeded: write PHP computation logic to populate payroll_runs from timesheet_entries + trips.
- Helper file created: `PROMPT.md` — session-start prompt for new Claude accounts to pick up context fast.
- Reference: IZNAHANYACHAY paper (uploaded doc) is the source of truth for payroll calc rules (base wage + OT + trip incentives + deductions), DoEmploy is UX/feature reference only.

## Directory Tree with Status
```
trucking_system/
├── index.php                      ✅ Public landing page (static marketing)
├── login/index.php                🟡 UI only — form does nothing, links straight to /home/ or /admin/home/
├── signup/index.php               🟡 UI only — form does nothing, links to /login/
├── includes/
│   ├── config.php                 🟡 only defines BASE_PATH, needs DB connection added
│   ├── head.php                   ✅ done (Tailwind config, theme init)
│   ├── foot.php                   ✅ done
│   ├── topbar.php                 ✅ fixed (was dark-mode-only, now shows in both themes)
│   ├── theme-toggle.php           ✅ done
│   ├── bottom-nav.php             ✅ done — 5 tabs: Home, Timesheet, Overview, Payroll, More
│   └── placeholder.php            ✅ done (generic placeholder body)
├── home/
│   ├── index.php                  🟡 UI only — clock-in status, invite link, timesheet/payroll summary all static
│   ├── overview/index.php         🟡 UI only — cards show static "No data"/"--"
│   ├── clock-in/index.php         🔴 placeholder only, no backend
│   └── invite/index.php           🔴 placeholder only, no backend
├── timesheet/
│   ├── index.php                  ⚠️🟡 UI only + BUG: light mode = calendar list, dark mode = manual entry form (different CONTENT per theme, not just color — needs same fix as Home)
│   └── entry/index.php            🔴 placeholder only, reads $_GET['date'] but no backend
├── payroll/
│   ├── index.php                  ⚠️🟡 UI only + BUG: light mode = empty state, dark mode = breakdown table (different content per theme)
│   └── run/index.php              🔴 placeholder only, no backend
├── more/
│   ├── index.php                  ✅ done (static links, no backend needed for this page itself)
│   ├── profile/index.php          🔴 placeholder only, no backend
│   ├── privacy-policy/index.php   🔴 placeholder, needs policy text written
│   └── about/index.php            🔴 placeholder, needs version/info content
├── admin/
│   ├── home/
│   │   ├── index.php              🟡 UI only, same static content as employee home
│   │   └── overview/index.php     🟡 UI only, mirrors home/overview/index.php
│   ├── timesheet/index.php        ⚠️🟡 same light/dark content-swap bug as employee version
│   ├── payroll/index.php          ⚠️🟡 same light/dark content-swap bug as employee version
│   └── more/index.php             ⚠️🟡 UI only + BUG: links point to non-admin /more/profile/ etc instead of /admin/more/profile/
└── assets/
    ├── images/                    🔴 empty, all backdrops are CSS gradients
    └── js/theme.js                🟡 exists, unverified if still used (head.php has its own inline theme script — possible duplicate/dead file)
```

## Known Bugs (not yet fixed)
1. `timesheet/index.php`, `admin/timesheet/index.php`, `payroll/index.php`, `admin/payroll/index.php` — light/dark modes show different CONTENT, not just different colors. Same root issue already fixed on Home pages (topbar.php fix). Needs: split into single always-visible view, recolor with `dark:` variants instead of `dark:hidden`/`hidden dark:block`.
2. `admin/more/index.php` — links to `/more/profile/`, `/more/privacy-policy/`, `/more/about/` instead of `/admin/more/...` equivalents (which don't even exist yet).
3. No auth guard anywhere — any `/admin/*` URL is publicly reachable.

## Navigation Model
`bottom-nav.php` shared by employee + admin. Needs `$activeNav` (home|timesheet|overview|payroll|more) and `$navBase` (BASE_PATH or BASE_PATH.'/admin') set before include. Overview link is hardcoded to `$navBase/home/overview/`.

## Theming Convention (for new pages going forward)
Render ONE version of content colored for both themes (`text-gray-900 dark:text-white`), never swap content via `dark:hidden`/`hidden dark:block`.

## Reference App
System is modeled after DoEmploy (payroll/attendance app): automated payroll calc + tax/OT rules, GPS-verified clock-in/out, employee profiles, job board. Job board is NOT planned yet. GPS on clock-in is a LATER feature, not part of initial clock_records schema.

## Suggested DB Tables (none exist yet)
`users` (role: admin/employee), `clock_records` (add lat/lng columns later when GPS feature is built), `timesheet_entries` (manual + QR), `payroll_runs`, `payslips` (basic, OT, holiday pay, SSS, Pag-IBIG, PhilHealth, total), `invites` (email, token, status), `performance_metrics`.

## Architectural Decision Needed Before Backend Work
`admin/*` duplicates `timesheet/`, `payroll/`, `more/` almost byte-for-byte. Decide now: keep two parallel trees, or collapse into one set of pages with a role check + conditional UI. Duplicating backend logic on top of duplicated files will double the work later.
