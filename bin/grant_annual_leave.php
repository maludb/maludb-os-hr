<?php
declare(strict_types=1);

/** The January job: grant the new year's allowance to everyone employed. Idempotent. `php bin/grant_annual_leave.php [YYYY]` */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/features/leave/queries.php';

$year = isset($argv[1]) && ctype_digit($argv[1]) ? (int) $argv[1] : (int) date('Y');
$pdo = db();
[$people, $granted] = grant_leave_year($pdo, $year);
log_activity($pdo, 'leave_balance.grant_year', null, null, ['actor_member_id' => null, 'after' => ['year' => $year, 'people' => $people, 'granted' => $granted, 'by' => 'cron']]);
echo "{$year}: {$granted} balances granted across {$people} people\n";
