<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();
render_module_stub('Departments', 'departments-list', 'Departments, their people and administrators — slice 2.');
