<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_login();
render_module_stub('Leave balances', 'leave-balances', 'Balances per person and type — slice 3.');
