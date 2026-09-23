# HR — an application from us for the Business OS

The first application beside the Business OS kernel: employment records, compensation history, pay runs
as records (no statutory payroll), leave and reviews for the people who work here — and the one
application allowed to change the kernel's directory, through its directory API.

- People reach it from the launcher (`app.<domain>`) at `hr.<domain>`; there is no login form.
- Agents reach it through its two read MCP servers; writes go through the kernel's Actions MCP running
  HR's action registry. HR ships its expert and a leave desk agent; it never calls a model.
- Stack: Ubuntu 24.04, Apache, PHP 8.3, HTMX, Bootstrap 5.3 (the nxl design system), PostgreSQL 17,
  MaluDB for activity memory. Built with the `htmx-php-builder` plugin; fitted with `maludb-os-integration`.

## Layout
```
maludb-os.json          what the installation agent reads (registration.md)
db/                     numbered, additive migrations — run in order as postgres
docs/hr-design.md       Phase 0–1: purpose, memory model, gates, question inventory, slice order, decisions
docs/hr-mcp-tool-surface.md · docs/hr-action-manifest.md · docs/build-specs/
os/                     each shipped agent's job description (expert.md, leave_desk.md)
skills/                 the skills HR ships, ingested at application scope
html/ app/ config/      the application (Phase 2 onward)
mcp/ bin/ deploy/       the read servers, the CLI jobs, the vhost and units
```

## Install (by hand, until the installation agent)
```
sudo -u postgres createdb subello_hr
for f in db/*.sql; do sudo -u postgres psql -v ON_ERROR_STOP=1 -d subello_hr -f "$f"; done
sudo -u postgres psql -c "ALTER ROLE hr_rw PASSWORD '…'"   # and hr_records_ro, hr_activity_ro
cp config/.env.example config/.env   # fill in; chmod 640, group www-data
```
See `docs/hr-design.md` §8 for the state of the build.
