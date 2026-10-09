<?php
// php -S router for the scratch install: the rewrites of deploy/apache-hr.conf, in the same order. Used only by scripts/prove-picker.sh.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$root = dirname(__DIR__) . '/html';
if ($path !== '/' && is_file($root . $path)) {
    return false;                                              // a static file (assets)
}
$run = static function (string $file, array $get = []) use ($root): void {
    $_GET = $get + $_GET;
    $_SERVER['SCRIPT_NAME'] = '/' . $file;
    $_SERVER['SCRIPT_FILENAME'] = $root . '/' . $file;
    chdir(dirname($root . '/' . $file));
    require $root . '/' . $file;
};
if ($path === '/sso') { $run('sso.php'); return true; }
if (preg_match('#^/(.+)/new$#', $path, $m) && is_file("$root/$m[1]/form.php")) { $run("$m[1]/form.php"); return true; }
if (preg_match('#^/(.+)/([0-9]+)/edit$#', $path, $m) && is_file("$root/$m[1]/form.php")) { $run("$m[1]/form.php", ['id' => $m[2]]); return true; }
if (preg_match('#^/(.+)/([0-9]+)$#', $path, $m) && is_file("$root/$m[1]/view.php")) { $run("$m[1]/view.php", ['id' => $m[2]]); return true; }
if (preg_match('#^/(.+?)/?$#', $path, $m) && is_file("$root/$m[1].php")) { $run("$m[1].php"); return true; }
if (preg_match('#^/(.+[^/])$#', $path, $m) && is_file("$root/$m[1]/index.php")) { $run("$m[1]/index.php"); return true; }
if (is_file($root . rtrim($path, '/') . '/index.php')) { $run(ltrim(rtrim($path, '/') . '/index.php', '/')); return true; }
http_response_code(404);
echo 'Not found';
return true;
