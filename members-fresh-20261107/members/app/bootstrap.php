<?php
declare(strict_types=1);

define('APP_DIR', __DIR__);
define('ROOT_DIR', dirname(__DIR__));

mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');

// ---- config (never stored in the repo) ----
$cfgFile = getenv('KEN_MEMBERS_CONFIG') ?: '';
if ($cfgFile === '' || !is_file($cfgFile)) {
    $cfgFile = '';
    foreach ([dirname(ROOT_DIR, 2) . '/ken-members-config.php', APP_DIR . '/config.php'] as $f) {
        if (is_file($f)) { $cfgFile = $f; break; }
    }
}
if ($cfgFile === '') {
    http_response_code(500);
    echo 'Not set up yet: config file missing. See README.';
    exit;
}
$GLOBALS['CFG'] = require $cfgFile;

function cfg(string $path, $default = null) {
    $v = $GLOBALS['CFG'];
    foreach (explode('.', $path) as $p) {
        if (!is_array($v) || !array_key_exists($p, $v)) return $default;
        $v = $v[$p];
    }
    return $v;
}

$sec = (string)cfg('app_secret', '');
if (strlen($sec) < 32 || strpos($sec, 'CHANGE-ME') !== false) {
    http_response_code(500);
    echo 'Not set up yet: please set app_secret in the config file. See README.';
    exit;
}

if (!cfg('debug')) { ini_set('display_errors', '0'); error_reporting(E_ALL); }
ini_set('log_errors', '1');

require APP_DIR . '/db.php';
require APP_DIR . '/security.php';
require APP_DIR . '/mail.php';
require APP_DIR . '/helpers.php';
require APP_DIR . '/progress.php';
require APP_DIR . '/controllers.php';
require APP_DIR . '/admin.php';
require APP_DIR . '/facilitators.php';
require APP_DIR . '/fac_controllers.php';
