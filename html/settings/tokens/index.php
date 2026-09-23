<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_login();
render_module_stub('Tokens', 'tokens', 'Your access tokens for the MCP servers and the API — Phase 4.');
