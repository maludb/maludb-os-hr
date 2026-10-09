#!/usr/bin/env bash
# Proves the record picker (app/picker.php, app/pickers.php, html/pick/index.php, shared/picker-rows.php, assets/js/record-picker.js;
# the design-system reference record-picker.md) on a SCRATCH install:
#   1. the code: php -l, the shell carries the one modal and the script, no <select> of a person remains in the converted forms;
#   2. under php -S (scripts/dev-router.php = deploy/apache-hr.conf's rewrites) as five people signed in by a hand-minted hand-off
#      token (scripts/dev-sso.php — HR has no login form): the endpoint's refusals (anonymous, unknown source, a source's gate), the
#      PEOPLE RULE (a manager's lists hold their reports and themself, an employee only themself, HR everyone), search, escaping,
#      paging past 25 rows, the `except` and `run_id` and `department_id` params;
#   3. each converted form renders its picker under the old name and id, a 422 keeps the chosen label, a pay run takes a person and a
#      review is scheduled through the hidden inputs exactly as through the selects.
# Reads DB_NAME and APP_INTERNAL_PORT from config/.env, which must be a scratch copy (hr_dev): a proof writes. Repeats: its rows go first.
#   mkdir -p storage; cp /srv/apps/hr/config/.env config/.env  # then DB_NAME=hr_dev, APP_INTERNAL_PORT=8394, a scratch ACTION_TOKEN_KEY
set -u
cd "$(dirname "$0")/.."
envv() { grep -E "^$1=" config/.env | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
PORT=$(envv APP_INTERNAL_PORT); DB=$(envv DB_NAME); DBUSER=$(envv DB_USER); export PGPASSWORD=$(envv DB_PASSWORD)
case "$DB" in *_dev|*_prove*) ;; *) echo "refusing to run against $DB: a proof writes — only a *_dev or *_prove* database (prove from a scratch checkout with its own config/.env)" >&2; exit 2;; esac
pass=0; fail=0
ok()  { pass=$((pass+1)); echo "ok   $1"; }
bad() { fail=$((fail+1)); echo "FAIL $1"; }
check(){ if eval "$2"; then ok "$1"; else bad "$1"; fi; }
sql() { psql -h 127.0.0.1 -U "$DBUSER" -d "$DB" -At -v ON_ERROR_STOP=1 -c "$1"; }
LOG=/tmp/hr-picker-php.log
T=/tmp/hr-picker-proof; mkdir -p $T

echo "== 1. the code"
for f in app/picker.php app/pickers.php html/pick/index.php app/views/shared/picker-rows.php app/views/layout.php app/bootstrap.php \
         app/views/people/partials/employment-form.php app/views/departments/partials/form.php app/views/departments/view.php app/views/leave/partials/form.php \
         app/views/leave/partials/balance-form.php app/views/reviews/partials/form.php app/views/payruns/view.php scripts/dev-router.php; do
  php -l "$f" >/dev/null 2>&1 && ok "lint $f" || bad "lint $f"; done
check "the shell carries the one modal, its search box and the script" "grep -q 'id=\"record-picker\"' app/views/layout.php && grep -q 'hx-vals=\"js:{...recordPickerValues()}\"' app/views/layout.php && grep -q 'record-picker.js' app/views/layout.php"
check "the bootstrap requires app/picker.php" "grep -q \"require_once __DIR__ . '/picker.php'\" app/bootstrap.php"
check "app-overrides.css has the picker's section" "grep -q 'RECORD PICKER' html/assets/css/app-overrides.css"
check "node parses the script" "node --check html/assets/js/record-picker.js"
check "no <select> of a person remains in the converted forms" "! grep -rnE '<select[^>]*name=\"(person|manager|reviewer)\"' app/views/people/partials/employment-form.php app/views/departments app/views/leave/partials app/views/reviews/partials app/views/payruns/view.php"
check "the department's parent and every enumeration stay selects" "grep -q 'name=\"parent\"' app/views/departments/partials/form.php && grep -q 'name=\"leave_type\"' app/views/leave/partials/form.php"

echo "== 2. the fixtures (HR admin 1001, manager 1002 with three reports, employee 1003, payroll 1004, department admin 1005; 30 more people)"
sql "DELETE FROM pay_run_items WHERE pay_run_id IN (SELECT id FROM pay_runs WHERE note = 'picker proof');
     DELETE FROM pay_runs WHERE note = 'picker proof';
     DELETE FROM reviews WHERE member_id BETWEEN 1001 AND 2999;
     DELETE FROM employment_profiles WHERE member_id BETWEEN 1001 AND 2999;
     DELETE FROM departments WHERE id IN (901, 902);
     DELETE FROM members WHERE id BETWEEN 1001 AND 2999 OR id = 3001" >/dev/null
php -S 127.0.0.1:$PORT -t html scripts/dev-router.php >$LOG 2>&1 & PHP_PID=$!
trap 'kill $PHP_PID 2>/dev/null' EXIT
sleep 1.5
B="http://127.0.0.1:$PORT"
sql "INSERT INTO departments (id, name) VALUES (901, 'Picker Dept'), (902, 'Picker Other Dept')" >/dev/null
login() { local jar=$1; shift; curl -s -o /dev/null -w '%{http_code}' -c $jar "$B/sso?$(php scripts/dev-sso.php "$@")"; }
JA=$T/a.jar; JM=$T/m.jar; JE=$T/e.jar; JP=$T/p.jar; JD=$T/d.jar; : > $JA
for j in $JA $JM $JE $JP $JD; do rm -f $j; done
c1=$(login $JA 1001 "Picker HR Admin" admin user hr_admin); c2=$(login $JM 1002 "Picker Manager" write user manager); c3=$(login $JE 1003 "Picker Employee" read user employee)
c4=$(login $JP 1004 "Picker Payroll" write user payroll); c5=$(login $JD 1005 "Picker DeptAdmin" write dept_admin employee 901:Picker\ Dept:1)
check "five people are signed on through the hand-off ($c1 $c2 $c3 $c4 $c5)" "[ \"$c1$c2$c3$c4$c5\" = 302302302302302 ]"
sql "INSERT INTO members (id, member_kind, display_name, email, business_role, status, job_title) SELECT 2000 + n, 'human', format('Picker Person %s', lpad(n::text, 2, '0')), format('pp%s@example.test', n), 'user', 'active', CASE WHEN n = 1 THEN 'Cutter' END FROM generate_series(1, 30) n;
     INSERT INTO members (id, member_kind, display_name, business_role, status) VALUES (2101, 'human', 'Picker Report A', 'user', 'active'), (2102, 'human', 'Picker Report B', 'user', 'active'), (2103, 'human', 'Picker Report C', 'user', 'active'),
            (2900, 'agent', 'Picker Agent', 'user', 'active');
     INSERT INTO employment_profiles (member_id, employment_type, status, start_date, manager_member_id)
       SELECT id, 'employee', 'active', date '2024-01-01', CASE WHEN id BETWEEN 2101 AND 2103 THEN 1002 END FROM members WHERE id BETWEEN 1001 AND 2103 AND member_kind = 'human';
     INSERT INTO department_members (member_id, department_id, is_admin, is_primary) VALUES (2101, 901, false, true) ON CONFLICT DO NOTHING" >/dev/null
EMP=$(sql "SELECT count(*) FROM employment_profiles e JOIN members m ON m.id = e.member_id WHERE e.status IN ('active','on_leave') AND m.member_kind = 'human' AND m.status = 'active'")
HUMANS=$(sql "SELECT count(*) FROM members WHERE member_kind = 'human' AND status = 'active'")
get() { local jar=$1; shift; curl -s -b $jar -c $jar "$B$1"; }
code() { curl -s -o $T/body.html -w '%{http_code}' -b ${2:-$JA} "$B$1"; }
csrf() { get $1 /activity | grep -o 'id="csrf-token-meta" content="[^"]*"' | head -1 | sed 's/.*content="//;s/"$//'; }
rowsof() { grep -o 'data-label="[^"]*"' | sed 's/data-label="//;s/"$//'; }

echo "== 3. the endpoint"
check "anonymous: sent to the launcher, never a list" "[ \$(curl -s -o /dev/null -w '%{http_code}' \"$B/pick/?source=employee\") = 302 ]"
check "an unknown source is 404 with a fragment, not a page" "[ \$(code '/pick/?source=nope') = 404 ] && grep -q 'That list does not exist' $T/body.html && ! grep -q '<html' $T/body.html"
check "a source key is cleaned before the lookup" "[ \$(code '/pick/?source=../etc') = 404 ]"
check "an employee with no reports may not schedule reviews: reviewee is 403 (a fragment)" "[ \$(code '/pick/?source=reviewee' $JE) = 403 ] && grep -q 'permission' $T/body.html && ! grep -q '<html' $T/body.html"
check "...nor choose a manager or reviewer: manager is 403" "[ \$(code '/pick/?source=manager' $JE) = 403 ]"
check "a manager may not choose from a pay run (payrun_person 403) nor add to a department (department_candidate 403)" "[ \$(code '/pick/?source=payrun_person&run_id=1' $JM) = 403 ] && [ \$(code '/pick/?source=department_candidate&department_id=901' $JM) = 403 ]"
check "the employee may choose from the people they can see (employee 200)" "[ \$(code '/pick/?source=employee' $JE) = 200 ] && grep -q 'record-picker-row-' $T/body.html"

echo "== 4. the people rule"
get $JE '/pick/?source=employee' | rowsof > $T/e.rows
check "an employee's list is themself and nobody else" "[ \"\$(cat $T/e.rows)\" = 'Picker Employee (you)' ]"
get $JM '/pick/?source=employee' | rowsof > $T/m.rows
check "a manager's list is themself and their three reports" "[ \$(wc -l < $T/m.rows) = 4 ] && grep -qx 'Picker Manager (you)' $T/m.rows && grep -qx 'Picker Report A' $T/m.rows && grep -qx 'Picker Report C' $T/m.rows && ! grep -q 'Picker Person' $T/m.rows"
check "a manager's search for someone outside their reports finds no one" "get $JM '/pick/?source=employee&q=Picker%20Person' | grep -q 'No person matches' && ! get $JM '/pick/?source=employee&q=Picker%20Person' | grep -q 'record-picker-row-'"
get $JM '/pick/?source=reviewee' | rowsof > $T/mr.rows
check "the reviewee source for a manager is exactly their three reports" "[ \$(wc -l < $T/mr.rows) = 3 ] && ! grep -q 'Picker Manager' $T/mr.rows && ! grep -q 'Picker Person' $T/mr.rows"
check "...and a search for HR's other people returns none of them" "! get $JM '/pick/?source=reviewee&q=Person%2001' | grep -q 'record-picker-row-'"
get $JA '/pick/?source=employee' > $T/a1.html
check "HR sees every active employee: the first page is 25 rows in name order ($EMP in all)" "[ \$(grep -c 'class=\"list-group-item list-group-item-action record-picker-row' $T/a1.html) = 25 ] && [ \"\$(rowsof < $T/a1.html | head -1)\" = \"\$(sql \"SELECT m.display_name FROM members m JOIN employment_profiles e ON e.member_id = m.id WHERE e.status IN ('active','on_leave') AND m.member_kind = 'human' AND m.status = 'active' ORDER BY m.display_name LIMIT 1\")\" ]"
check "Show more asks for page 2 and says 25 of $EMP shown" "grep -q 'id=\"record-picker-more\"' $T/a1.html && grep -q 'page=2' $T/a1.html && grep -q \"25 of $EMP shown\" $T/a1.html"
get $JA '/pick/?source=employee&page=2' > $T/a2.html
check "page 2 is the rest, rows only, no list wrapper, no Show more" "[ \$(grep -c 'id=\"record-picker-row-' $T/a2.html) = $((EMP - 25)) ] && ! grep -q 'record-picker-list' $T/a2.html && ! grep -q 'record-picker-more' $T/a2.html"
check "HR is marked (you) in their own list" "get $JA '/pick/?source=employee&q=HR%20Admin' | grep -q 'data-label=\"Picker HR Admin (you)\"'"
check "the chosen row is marked active and selected" "get $JA '/pick/?source=employee&selected=2005' | grep -q 'record-picker-row active\" id=\"record-picker-row-2005\" role=\"option\" aria-selected=\"true\"'"
check "search narrows by name" "[ \$(get $JA '/pick/?source=employee&q=person%2007' | grep -c 'id=\"record-picker-row-') = 1 ]"
check "no match says so with the words escaped" "get $JA '/pick/?source=employee&q=%3Cb%3Enothing' | grep -q 'No person matches “&lt;b&gt;nothing”'"
check "a search longer than 80 characters is cut, not refused" "[ \$(code \"/pick/?source=employee&q=\$(printf 'a%.0s' \$(seq 1 120))\") = 200 ]"
check "an undeclared param changes nothing" "[ \$(get $JA '/pick/?source=employee&department_id=901&except=1001' | grep -c 'id=\"record-picker-row-') = 25 ]"
get $JA '/pick/?source=manager' > $T/mg.html
check "the manager source lists active people with their job title ($HUMANS humans)" "grep -q \"of $HUMANS shown\" $T/mg.html && get $JA '/pick/?source=manager&q=cutter' | grep -q 'record-picker-detail\">Cutter<'"
check "the except param leaves the person out of their own manager list" "get $JA '/pick/?source=manager&except=2001&q=Person%2001' | grep -q 'No person matches' && get $JA '/pick/?source=manager&q=Person%2001' | grep -q 'data-value=\"2001\"'"
check "an agent is not a manager" "! get $JA '/pick/?source=manager&q=Agent' | grep -q 'data-value=\"2900\"'"
check "a department admin may choose a manager (200) and add to their department" "[ \$(code '/pick/?source=manager' $JD) = 200 ] && [ \$(code '/pick/?source=department_candidate&department_id=901' $JD) = 200 ]"
check "the department source badges an agent and leaves out who is in already" "get $JD '/pick/?source=department_candidate&department_id=901' > $T/dc.html; grep -A6 'data-value=\"2900\"' $T/dc.html | grep -q '>Agent<' && ! get $JD '/pick/?source=department_candidate&department_id=901&q=Report' | grep -q 'data-value=\"2101\"' && get $JD '/pick/?source=department_candidate&department_id=901&q=Report' | grep -q 'data-value=\"2102\"'"
check "...and shows a department admin nothing for a department they do not administer" "get $JD '/pick/?source=department_candidate&department_id=902' | grep -q 'Nothing to choose from yet' || get $JD '/pick/?source=department_candidate&department_id=902' | grep -q 'No member'"
check "...no department at all shows nothing" "! get $JD '/pick/?source=department_candidate' | grep -q 'record-picker-row-'"

echo "== 5. the forms"
FORM=$T/form.html
for spec in "/leave/new|leave-form-field-person|employee|person" "/leave/balances/new|balance-form-field-person|employee|person" "/reviews/new|review-form-field-person|reviewee|person" \
            "/reviews/new|review-form-field-reviewer|manager|reviewer" "/people/employment/2001/edit|employment-form-field-manager|manager|manager" \
            "/departments/new|department-form-field-manager|manager|manager" "/departments/901/edit|department-form-field-manager|manager|manager" "/departments/901|membership-form-field-person|department_candidate|person"; do
  IFS='|' read -r url fid src nm <<<"$spec"
  get $JA "$url" > $FORM
  check "$url: $fid is a $src picker (hidden input keeps name=$nm and the id, opener, label points at the opener)" "grep -q \"name=\\\"$nm\\\" id=\\\"$fid\\\" value=\\\"[0-9]*\\\" data-picker-value\" $FORM && grep -q \"data-picker-source=\\\"$src\\\"\" $FORM && grep -q \"id=\\\"$fid-open\\\"\" $FORM && grep -q \"for=\\\"$fid-open\\\"\" $FORM && [ \$(grep -c 'id=\"record-picker\"' $FORM) = 1 ]"
done
get $JA /leave/new > $FORM
check "the leave form for HR: required (no Clear), the caller pre-chosen as '(you)'" "! grep -q 'leave-form-field-person-clear' $FORM && grep -q 'record-picker-label\">Picker HR Admin (you)<' $FORM"
get $JE /leave/new > $FORM
check "the leave form for someone who can only act for themself keeps its hidden input and no picker" "grep -q 'type=\"hidden\" name=\"person\" value=\"1003\"' $FORM && ! grep -q 'data-picker-source=\"employee\"' $FORM"
get $JA /reviews/new > $FORM
check "the reviewer is optional: Clear present, placeholder shows the manager default" "grep -q 'review-form-field-reviewer-clear' $FORM && grep -q 'record-picker-label text-muted\">The person&#039;s manager<' $FORM"
get $JA /people/employment/2101/edit > $FORM
check "the employment form pre-chooses the manager by label and excludes the person themself" "grep -q 'record-picker-label\">Picker Manager<' $FORM && grep -q 'data-picker-params=\"{&quot;except&quot;:2101}\"' $FORM"
get $JM /reviews/new > $FORM
check "a manager's review form still opens (their reports to pick)" "grep -q 'data-picker-source=\"reviewee\"' $FORM"
PR=$(curl -s -b $JP -c $JP -o /dev/null -D - -X POST --data-urlencode "csrf_token=$(csrf $JP)" --data-urlencode "people[]=1004" --data-urlencode period_start=2026-09-01 --data-urlencode period_end=2026-09-30 --data-urlencode pay_date=2026-10-05 --data-urlencode currency=USD --data-urlencode note="picker proof" "$B/payruns/save.php" >/dev/null; sql "SELECT id FROM pay_runs WHERE note = 'picker proof' ORDER BY id DESC LIMIT 1")
check "a draft pay run exists for the payroll fixture (run $PR)" "[ -n \"$PR\" ]"
get $JP "/payruns/$PR" > $FORM
check "the pay run's Add a person is a payrun_person picker bound to the run" "grep -q 'name=\"person\" id=\"pay-run-add-field-person\" value=\"\" data-picker-value' $FORM && grep -q \"data-picker-params=\\\"{&quot;run_id&quot;:$PR}\\\"\" $FORM"
check "the payrun source lists eligible people with the note why not paid, nothing for HR-less roles" "get $JP '/pick/?source=payrun_person&run_id=$PR' | grep -q 'record-picker-detail\">no pay recorded<' && [ \$(code \"/pick/?source=payrun_person&run_id=$PR\" $JE) = 403 ]"
check "a missing or unknown run lists nobody" "! get $JP '/pick/?source=payrun_person' | grep -q 'record-picker-row-' && ! get $JP '/pick/?source=payrun_person&run_id=999999' | grep -q 'record-picker-row-'"

echo "== 6. a 422 keeps the label; a save goes through the hidden input"
post() { local jar=$1 url=$2; shift 2; curl -s -b $jar -c $jar -H 'HX-Request: true' -o $T/post.html -w '%{http_code}' -X POST --data-urlencode "csrf_token=$(csrf $jar)" "$@" "$B$url"; }
c=$(post $JA /leave/save.php --data-urlencode person=2003 --data-urlencode start_date=2026-11-02)
check "leave: a 422 re-render keeps the person's label ($c)" "[ $c = 422 ] && grep -q 'record-picker-label\">Picker Person 03<' $T/post.html && grep -q 'value=\"2003\" data-picker-value' $T/post.html"
check "an id that names nobody shows the placeholder, not an error" "post $JA /leave/save.php --data-urlencode person=999999 --data-urlencode start_date=2026-11-02 >/dev/null; grep -q 'record-picker-label text-muted' $T/post.html"
c=$(post $JA /reviews/save.php --data-urlencode person=2004 --data-urlencode reviewer=1002)
check "reviews: a 422 keeps the person's and the reviewer's labels ($c)" "[ $c = 422 ] && grep -q 'record-picker-label\">Picker Person 04<' $T/post.html && grep -q 'record-picker-label\">Picker Manager<' $T/post.html"
c=$(post $JA /departments/save.php --data-urlencode name= --data-urlencode manager=1002)
check "departments: a 422 keeps the manager's label ($c)" "[ $c = 422 ] && grep -q 'record-picker-label\">Picker Manager<' $T/post.html && grep -q 'name=\"parent\"' $T/post.html"
c=$(post $JA /people/employment/save.php --data-urlencode person=2101 --data-urlencode manager=1002 --data-urlencode weekly_hours=abc)
check "employment: a 422 keeps the manager's label ($c)" "[ $c = 422 ] && grep -q 'record-picker-label\">Picker Manager<' $T/post.html"
c=$(post $JP /payruns/items/add.php --data-urlencode pay_run=$PR --data-urlencode person=2001)
check "a pay run takes a person through the hidden input ($c)" "[ \"\$(sql \"SELECT count(*) FROM pay_run_items WHERE pay_run_id = $PR AND member_id = 2001\")\" = 1 ]"
check "...and that person is no longer offered" "! get $JP \"/pick/?source=payrun_person&run_id=$PR\" | grep -q 'data-value=\"2001\"' && get $JP \"/pick/?source=payrun_person&run_id=$PR\" | grep -q 'data-value=\"2002\"'"
c=$(post $JM /reviews/save.php --data-urlencode person=2101 --data-urlencode kind=annual --data-urlencode due_date=2026-12-01 --data-urlencode reviewer=)
check "a manager schedules a review of a report; the empty reviewer defaults to their manager as before ($c)" "[ \"\$(sql \"SELECT member_id || ' ' || reviewer_member_id FROM reviews WHERE member_id = 2101\")\" = '2101 1002' ]"
c=$(post $JM /reviews/save.php --data-urlencode person=2001 --data-urlencode kind=annual --data-urlencode due_date=2026-12-01)
check "...and the handler still refuses a person outside their reports ($c)" "[ $c = 422 ] && [ \"\$(sql \"SELECT count(*) FROM reviews WHERE member_id = 2001\")\" = 0 ]"
check "no PHP warning, notice or fatal in the server log" "! grep -qiE 'PHP (Warning|Notice|Fatal|Deprecated)' $LOG"
kill $PHP_PID 2>/dev/null
echo "== $pass passed, $fail failed"
[ $fail -eq 0 ]
