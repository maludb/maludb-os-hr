# Build spec: Phase 2 — sign-on, the mirror, the shell

Built by the planning-class model before any slice. What exists at the end: a person clicks HR on the
launcher and lands, signed in, on an empty HR dashboard that looks finished; the mirror refreshes every
minute; every request is logged.

## Files
- `app/bootstrap.php` (env from `config/.env`, session hardening, `json_mode_begin()`, the acting member set on the PDO connection, `os_gate()`), `app/db.php`, `app/http.php` (the kernel's helper contracts: `e`, `view`, `is_htmx_request`, `require_post`, `request_*`, `csrf_*`, `emit_action_status`, `json_mode_finish`, `respond_*`, `hx_location`, `hx_trigger`), `app/activity.php` (`log_activity`, `log_screen_view`, `request_id`, run-aware `default_activity_source`), `app/auth.php` (`verify_sso_token`, `verify_sso_claims`, `verify_sso_logout_notice`, `verify_action_token` + relay, `verify_approval_replay`, `current_member`, `require_login`, gates `require_hr()`, `require_admin_of()`, `require_can_admin_member()`, `require_own_or_hr()`), `app/directory.php` (mirror upsert from claims and from the feed; `directory_call()` to the kernel with `X-Acting-Member`), `app/partial_update.php`, `app/mail.php` (unused until a feature sends mail — MaluMail helper kept for parity).
- `html/sso.php` (the receiver; `/sso`), `html/sso/logout.php` (`/sso/logout`, 204), `html/logout.php` (own sign-out, POST + CSRF, back to the launcher), `html/login.php` (redirects to `OS_LAUNCHER_URL?app=hr`), `html/index.php` (dashboard), `html/activity.php`, `html/assistant/message.php` (posts to the kernel's chat endpoint; renders reply + actions; `HX-Trigger` per action's entity), `html/api/v1/health.php`.
- `app/views/layout.php` (the nxl shell copied from the design system with `#page-content`, the command bar partial, CSRF meta + listener, `htmx:afterSwap` re-init), `app/views/shared/assistant-bar.php`, `app/views/shared/flash.php`, `app/views/shared/validation-errors.php`, `app/views/sso/refused.php`, `app/views/home/page.php`.
- `html/assets/` = the design system's `examples/assets/` verbatim (+ `htmx.min.js`).
- `bin/directory_sync.php` (the change-feed timer: advisory lock, `GET changes.php?since=`, upsert members / departments / memberships, remove what left, advance `directory_sync_state.next_cursor`; `--full` on first run; logs `directory.sync`), `bin/mint_mcp_token.php`, `bin/build_action_registry.php` (copied from the kernel, paths changed).
- `mcp/activity_ingest.py` (copied from the kernel; payload `application: "hr"` first).
- `deploy/apache-hr.conf` (public vhost `hr.<domain>` with `/mcp/records`, `/mcp/activity` proxied `ProxyPreserveHost Off`, the canonical-URL rewrites, the API allow-list `/api/v1/(health|pay-runs)`; internal vhost `127.0.0.1:${APP_INTERNAL_PORT}`), `deploy/hr-*.service|timer` (records MCP, activity MCP, activity ingest, directory sync).

## The receiver (`/sso`) — in order
1. `verify_sso_token($token, APP_KEY)` and `verify_sso_claims($claims)` — constant-time, both must pass, member ids must match.
2. Expiry, audience, then the nonce: `INSERT INTO sso_nonces … ON CONFLICT DO NOTHING`; zero rows = replay → refuse.
3. Upsert the mirror row from the claims (`members`; `department_members` for the claimed departments; departments named but unknown are inserted by id with the claimed name until the feed fills them).
4. Open the session: `session_regenerate_id(true)`, `$_SESSION['member_id']`, fresh CSRF token; `INSERT INTO member_sessions (sha256(session_id), member_id)`.
5. `log_activity('member.sign_on', 'member', $id, ['source' => 'web', 'request_id' => X-Request-Id ?? new])`; redirect to `/`.
6. Any failure: one page `sso/refused.php` (*"This sign-on link has expired. Open HR from app.<domain> again."*), `member.sign_on.refused` logged with the reason in `after.reason` — never shown.

## Every request (bootstrap)
- Session cookie `SameSite=Lax`, `Secure` when HTTPS, `HttpOnly`, name `HRSID`, strict mode.
- A signed-in request re-checks the mirror row: `status = 'active'` and `capability IS NOT NULL`, and that `member_sessions` has an unended row for this session; otherwise the session is destroyed and the visitor sent to the launcher.
- Action token (`X-Action-Token` + `X-Action-Relay`, or `X-Approval-Replay`): verified with the tenant's keys; acts as that member for one request; `Set-Cookie` removed; refuses a member id with no mirror row; CSRF satisfied; `source = 'agent'` when the token carries a run id, else `'assistant'`; `request_id` = the run's when the token carries a run (asked of the kernel's run-facts call, cached per run).
- `SET app.member_id` on the PDO connection before any query.
- `log_screen_view()` on every GET that renders a screen (HTMX or full page); on a JSON request only with `X-Screen-View: 1`.

## `/sso/logout` (POST from the kernel)
`verify_sso_logout_notice($notice, APP_KEY)` → `UPDATE member_sessions SET ended_at = now(), ended_by = 'kernel' WHERE member_id = … AND ended_at IS NULL`; log `member.sign_out` with `after.by = 'kernel'`; 204. Invalid → 204 too (fail quietly; log `member.sign_out.refused`).

## The shell
- Design-system skeleton, exactly. Sidebar groups: **Home** (Dashboard), **People** (People, Departments), **Leave** (Requests, Calendar, Balances), **Pay** (Pay runs — HR staff only; My payslips), **Reviews**, **Settings** (Settings, Leave types, Holidays, Tokens, Activity). Items the member may not open are not rendered (the gate decides, not the menu — the endpoint still checks).
- Header: the brand (`APP_NAME` from `.env`, "HR"), the member's name and role badge, **Sign out** (POST), a link back to the launcher ("All applications").
- `#assistant-bar` per chat-actions; `html/assistant/message.php` posts `{utterance, screen, context:{record_id}, conversation_id (session), wait: 60}` to the kernel's chat endpoint with `OS_APPLICATION_TOKEN` and `X-Acting-Member`; renders the reply and the actions list (`shared/assistant-reply.php`); for each action with a known entity emits `HX-Trigger`; a 202 polls with `GET ?run=` up to `wait`; refusals in one sentence (403 "You are not allowed to use the assistant here", 404 "HR has no expert yet — a super-admin names one in the kernel", 409 "The expert is busy; try again in a moment").
- Dashboard (`/`): cards from the views the member can see — leave left, waiting for me, my next review; for HR staff: starting/leaving this month, unapproved runs, overdue reviews. Empty states say what the screen will show.
- 375 px: sidebar collapses to the hamburger, no horizontal scroll, the bar is thumb-reachable.

## Proof before Phase 3 — DONE 2026-09-23
- [x] A token minted by the kernel's own `sso_launch_url()` for member 1 on application 39 opened a session once (302 → `/`, dashboard 200); the same token again → 403 (`member.sign_on.refused` reason `replay`); a token for `app_key = 'reservations'` → 403 (`token`). Expiry is the kernel's 60 s in `verify_sso_token()` (not waited for; the check is the same line as the audience).
- [x] Unknown members: an action token for id 999999 → 401 and NO mirror row. **Correction to the spec's wording:** a hand-off whose signed claims name a member HR has never seen IS admitted and mirrored — the claims are the kernel's word (§1 says "upsert the mirror row from the claims"); "unknown id refused, never created" is the rule for action and run tokens, which carry no claims.
- [x] A run token without the relay → 401; with a valid relay but a run id the kernel's run-facts call does not vouch for → 401, and the agent's capability stays NULL.
- [x] `bin/directory_sync.php --full` applied 14 members, 16 departments, 14 memberships and stored the cursor; the incremental run applied 0 and advanced it; the timer runs every minute (`hr-directory-sync.timer`). A suspended member arrives as `status = inactive` and `end_member_sessions()` closes their sessions in the same pass.
- [x] `/sso/logout` with a notice minted by the kernel's `mint_sso_logout_notice()` → 204, `member_sessions` ended (1), the next request → 302 to the launcher; an invalid notice → 204 and `member.sign_out.refused`.
- [x] The ingest shipped rows and advanced the checkpoint (`hr-activity-ingest.timer`); `/api/v1/health` answers on both ports with `ingest_lag` and the directory sync state.
- [x] The command bar posted to the kernel's chat endpoint and rendered the 404 sentence ("HR has no expert yet — a super-admin names one in the kernel"); with the expert hired (Phase 4) it rendered a real reply and the tool calls the run made — `build-specs/mcp-and-registration.md`.
- [x] Browser check (headless Chromium): 375 × 740 and 1280 × 800, `scrollWidth` = viewport, the bar at the bottom edge, HTMX navigation to `/activity` pushes the URL and title. Finding fixed: the fixed bar (z-index 1030) covered the bottom of the slide-in mobile menu (1026) — `app-overrides.css` raises the open menu above it and pads its list.
- Kernel side: `html/applications/save.php` now accepts a catalog key of kind `ours`; HR is catalog `hr` + application 39 with `sso_path`, `sso_logout_path`, `directory_writes`, url `http://hr.subello.com`; grants: member 1 admin, department HR admin; the application token is in `config/.env`.

## Open Questions
- The design system's `logo-full.png` renders blank on the white sidebar header (the theme's own mark); HR needs its own mark — the owner's artwork, as the kernel got (db/134).
