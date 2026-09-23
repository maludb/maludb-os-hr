# Build spec: Phase 4 — the MCP servers, the tokens screen, the registration

Built by the planning-class model, 2026-09-23. What exists at the end: every question in
`docs/hr-design.md` §4 is answerable through a tool; every action a button can do is a tool on the
kernel's Actions MCP; a person connects their own AI with a token from the Tokens screen; the
kernel knows HR's endpoints, its skills and its expert.

## Files
- `mcp/db.py` (the kernel's, with HR's roles and a `request_token` contextvar; a run token names a member the mirror must already admit), `mcp/server_common.py` (bearer middleware; **the run-facts gate**: an agent's run token is shown to the kernel's `POST /api/v1/runs/facts.php` once per run, cached 5 min; a vouched agent with endpoints here is admitted through `mcp_admit_agent()` (db/011) and then sees and calls only the tools the kernel names for this endpoint; anything unvouched sees nothing), `mcp/business_common.py`.
- `mcp/records_server.py` + `hr_people.py`, `hr_pay.py`, `hr_leave.py`, `hr_reviews.py` — 18 named tools + `records_search` (`docs/hr-mcp-tool-surface.md`), port `MCP_RECORDS_PORT`, endpoint name **Records MCP**.
- `mcp/activity_server.py` — 7 named tools + `activity_search`, port `MCP_ACTIVITY_PORT`, endpoint name **Activity MCP**.
- `html/settings/tokens/` (`index.php`, `mint.php`, `revoke.php`) + `app/views/settings/tokens/page.php`: the two MCP URLs and the feed URL, a personal `mcp` token (or `api` for HR staff) shown once, revocation.
- `deploy/hr-records-mcp.service`, `deploy/hr-activity-mcp.service`; Apache proxies `/mcp/records` and `/mcp/activity` under the name with `ProxyPreserveHost Off`.
- `deploy/kernel-registry-hr.json` = what the kernel holds at `mcp/registries/hr.json` (schema `maludb-os.registry/1`, `base_url` the loopback port, `records_url` under the name, `resolve` per entity → HR's `find_*` tools, `registry` = `mcp/action_registry.json`).

## Registration in the kernel (done by hand, as the installation agent will)
1. Catalog entry `hr` (kind `ours`) and application 39 with `sso_path`, `sso_logout_path`, `directory_writes`, url `http://hr.subello.com` — Phase 2.
2. Endpoints, after the servers answered: 15 Records MCP (`mcp`, bearer, agent-reachable, 1.0), 16 Activity MCP, 17 Health (`http_api`, none), 18 Pay runs feed (`http_api`, bearer, not agent-reachable).
3. `mcp/registries/hr.json` copied to the kernel; `certstudy-actions-mcp` restarted: 35 HR actions on the one Actions MCP (114 tools in all), described in the manifest's own words (kernel change `mcp/application_actions.py`).
4. Skills `hr-basics` and `hr-leave-rules` imported into the tenant's MaluDB (`bin/import_skill.php`, ids 6 and 7) and assigned at application scope (assignments 11, 12).
5. The expert hired as agent 48 (`HR Expert`, department HR, manager the owner, Claude Sonnet 5 on the Hermes harness, 20 USD a month, kind subagent, job description `os/expert.md`), version 1 activated, named the expert on application 39, granted `write` on HR, and granted its tools: 13 on Records MCP, 4 on Activity MCP, 6 on the Actions MCP — exactly `maludb-os.json`'s `agents[0]`. The leave desk (`os/leave_desk.md`) is not hired: that is the owner's one click.

## Proof — 2026-09-23
- A person's `mcp` token: `initialize` through Apache under the name (200); `find_people`, `records_search`, `get_pay_run`, `whos_off`, `find_reviews`, `starters_and_leavers`, `headcount`, `payroll_summary`, `my_payslip` answer; the activity server's `system_jobs` answers. No token → 401. A run token signed with the tenant's key for a run the kernel does not vouch for → refused (the kernel answered `valid:false`).
- **The command bar, end to end**: "who is off in the week of October 12 and how much paid time off does Priya have left this year" → the kernel ran the expert (run 76, Hermes, Sonnet 5, 0.064 USD): HR's endpoints were attached with the run token, the records server asked the kernel's run-facts call, admitted agent 48 (capability `write` in the mirror), offered its 13 granted tools, and the expert called `find_people`, `whos_off`, `leave_balance`, `find_leave_requests` and answered correctly (Priya off Oct 12–16 plus a half-day sick; 15 days left = 16 + 3 − 4 − 7 committed). The bar rendered the reply and the five actions.
- The first attempt (run 75) found two defects, both fixed: HR's `kernel_call` gave up at 15 s while the kernel holds a chat run up to 60 s (now 75 s, then polled); and `hr.subello.com` did not resolve on this server, so the agent's harness could not reach HR's MCP at all — `127.0.0.1 hr.subello.com` was added to `/etc/hosts` (and the cloud-init template) until the owner's DNS exists. `whos_off` and `find_reviews` passed dates as text to a date parameter (fixed).
- Tokens screen: the URLs shown; a token minted and shown once (a second view does not show it); revoked; a `read` person cannot mint `api` (403).

## Open Questions
- The leave desk agent is written (`os/leave_desk.md`) but not hired; the kernel's "propose" step does not exist yet (the installation agent's), so a super-admin hires it by hand from `maludb-os.json`'s `agents[1]`.
- The kernel's `/etc/hosts` line is a stopgap: the owner's DNS and proxy entries for `hr.subello.com` (like `app.` and `os.`) replace it, and the proxy must forward the name to this host's :80.
