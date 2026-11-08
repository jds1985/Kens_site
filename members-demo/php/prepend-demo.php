<?php
declare(strict_types=1);

require __DIR__ . '/demo-guard.php';
$cfg = demo_guard();
demo_register_session_handler($cfg);
