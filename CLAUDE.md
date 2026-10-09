# HR — CLAUDE.md

This is **HR**, the first application from us beside the Business OS kernel (kernel repo `/var/www`,
its design `docs/business-os-integration.md` there). Read, in order: `docs/hr-design.md` (what and why,
the gates, the question inventory, the decisions), `docs/hr-mcp-tool-surface.md`, `docs/hr-action-manifest.md`,
the slice specs in `docs/build-specs/` (Phase 2 first: `sso-shell.md`; the exemplar: `people.md`).

## Two plugins govern this repo
- `htmx-php-builder` — **how it is built**: `new-app` (the fixed phase order), `php-patterns` before any PHP,
  `design-system` before any markup, `php-session-auth` for session hardening and CSRF, `chat-actions`
  for the command bar, `mcp-servers` for the two read servers, `new-screen` for every screen.
- `maludb-os-integration` (skill `os-integration`, `~/maludb-os-integration`) — **how it fits**: the
  mirror with the kernel's ids, the `/sso` receivers, the directory API as the acting person, the run
  token honoured everywhere, the command bar through the kernel's chat endpoint, `maludb-os.json`.
  Where the two conflict, the integration contract wins: **no password, no login form, no account, no
  model key, no shared tables** — the builder's Google/TOTP/register scaffolding is not built here.

## Rules that do not move
- **Checkpoint gate**: no feature PHP until the owner approves the schema + tool surface + manifest
  together (Phase 1). Schema changes after that are numbered additive migrations, each with sign-off.
- Every state-changing handler: `require_post()` + `verify_csrf()` (an action token stands in) + the
  gate + `log_activity()` + `emit_action_status()`; a create's `location` ends in the record id.
- The kernel's rules copied here: activity `source` is `web` for the UI, `agent` under a run token;
  `action` is `entity.verb`; an activity row about pay never carries an amount; `mcp_*` views are the
  only thing the read roles see; `app.member_id` is set before any query; an unknown member id is
  refused, never created.
- Directory writes: `directory_call()` to the kernel with `X-Acting-Member`, then the mirror from the
  kernel's answer, then the local log with `kernel_request_id`. Never write the mirror from a form.
- Lists of named things are cards; ledgers (leave requests, pay runs, balances, lines) are tables.
  No modals — two exemptions: `hx-confirm`, and the **record picker** (2026-10-09, `docs/build-specs/record-picker.md`: the one `#record-picker` search dialog that CHOOSES a person; it shows no record and holds no form); every screen works at 375 px; ids per the kebab-case scheme; `hx-push-url` always explicit.
- Commit after every finished step on `main`, in the kernel repo's message style; never push.
- Smokes and fixtures are named `SMOKE <run>`; test tokens are signed with the tenant's key from the
  kernel's `config/.env` and never written down.

## Environment facts
- Repo and install path `/srv/apps/hr` (`/srv/apps/<catalog_key>` for every application from us). Apache: `deploy/apache-hr.conf` =
  `/etc/apache2/sites-available/hr.conf` (public `hr.subello.com`, loopback :8180); timers `hr-directory-sync` and `hr-activity-ingest`
  installed from `deploy/`. In the kernel HR is application 39, catalog `hr` (kind `ours`); its token is in `config/.env`.
- **State (2026-09-23):** every phase built and proven — `docs/hr-design.md` §8; each slice spec ends with "Built and proven";
  Phase 4 is `docs/build-specs/mcp-and-registration.md`. Migrations run to db/012 (HR's roles and rights, 2026-09-27: `docs/build-specs/roles-and-rights.md` — HR publishes Employee, Manager, Payroll, HR Admin through `app_roles`; the kernel grants any set; the rules ask `hr_has_right()`). Services: `hr-records-mcp` (8821),
  `hr-activity-mcp` (8822), timers `hr-directory-sync`, `hr-activity-ingest`. In the kernel: application 39, endpoints 15–18,
  skills 6/7 assigned (11/12), the expert = agent 48 (Sonnet 5, Hermes) with its grants; `mcp/registries/hr.json` there.
- Fixtures left in the data (all named or reasoned SMOKE, or Priya's): Priya (26) employed with pay, leave, reviews and
  pay run PR-0001; department 18 `SMOKE …` in the kernel; a test invitation. The owner's items: DNS + proxy for
  `hr.subello.com` (until then `/etc/hosts` maps it to this host), hire the leave desk (`maludb-os.json` agents[1]).
- Proving sign-on by hand: mint with the kernel's functions (`sso_launch_url()` as member 1 in a CLI with `$_SESSION` set), present
  the token to `http://127.0.0.1/sso?…` with `Host: hr.subello.com`; the headless-browser check is `node` + `/var/www/web/node_modules/playwright`
  with `--host-resolver-rules=MAP hr.subello.com 127.0.0.1`.
- Database `subello_hr` on the office's PostgreSQL 17 (roles `hr_rw`, `hr_records_ro`, `hr_activity_ro`).
- Ports are configuration: `APP_INTERNAL_PORT`, `MCP_RECORDS_PORT`, `MCP_ACTIVITY_PORT` in `config/.env`
  (the kernel owns 8080, 8811–8816). The kernel's internal API: `OS_INTERNAL_URL=http://127.0.0.1:8080`.
- The kernel's own copies to reuse verbatim: `app/auth.php` verifiers, `app/http.php` helper contracts,
  `app/activity.php`, `app/partial_update.php`, `mcp/db.py`, `mcp/server_common.py`, `mcp/activity_ingest.py`,
  `bin/build_action_registry.php`, `bin/mint_mcp_token.php`.
