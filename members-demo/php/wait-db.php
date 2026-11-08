<?php
declare(strict_types=1);

require __DIR__ . '/demo-guard.php';
$cfg = demo_guard();
for ($i = 0; $i < 60; $i++) {
    try {
        demo_pdo($cfg);
        fwrite(STDOUT, "database ready\n");
        exit(0);
    } catch (Throwable $e) {
        sleep(1);
    }
}
fwrite(STDERR, "database did not become ready\n");
exit(1);
