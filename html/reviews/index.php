<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();
render_module_stub('Reviews', 'reviews-list', 'Performance reviews — slice 6.');
