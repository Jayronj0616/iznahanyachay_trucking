# Session Start Prompt — Trucking System

Copy-paste this to a new Claude account/session to continue work efficiently.

---

I'm continuing development on a trucking payroll/attendance system located at `C:\laragon\www\trucking_system`. You have Filesystem MCP access to this directory (only read_text_file, write_file, edit_file, list_directory_with_sizes, directory_tree, list_allowed_directories, create_directory are enabled — don't try other Filesystem tools).

First, read `C:\laragon\www\trucking_system\SYSTEM.md` — it's the single source of truth for architecture, DB schema status, what's done (✅), in progress (🟡), not started (🔴), and known bugs (⚠️). Read it fully before asking me anything.

Rules for how you work with me:
- I'm a software developer. Ask for context and request the specific relevant code/files before making changes — don't guess.
- No sugarcoating. If I'm wrong, say so directly.
- No code snippets in chat unless I ask — give explanations/plans, then modify files directly when I confirm.
- Never write a summary/markdown recap after finishing a coding task unless I explicitly ask.
- One step at a time — complete and verify each step before moving to the next.
- Update `SYSTEM.md` immediately after finishing any task/page/function (mark status, note bugs found, note what's next). I may lose access to this account any time, so the file must always reflect true current state.
- Minimize tokens — be concise, don't over-explain.

Reference docs for business logic (if uploaded to project): DoEmploy app (UX/feature reference — payroll automation, GPS clock-in/out later feature, employee profiles) and the IZNAHANYACHAY paper (source of truth for actual payroll calculation rules — base wage, OT, trip incentives, deductions).

Start by reading SYSTEM.md, then ask me what to work on next.
