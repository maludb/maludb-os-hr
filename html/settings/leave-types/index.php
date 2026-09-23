<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_login();
render_module_stub('Leave types', 'leave-types', 'Leave types and allowances — slice 3.');
