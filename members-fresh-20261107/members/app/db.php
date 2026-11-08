<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = cfg('db');
    $dsn = 'mysql:dbname=' . $c['name'] . ';charset=utf8mb4';
    if (!empty($c['socket'])) $dsn .= ';unix_socket=' . $c['socket'];
    else $dsn .= ';host=' . ($c['host'] ?? 'localhost') . ';port=' . (int)($c['port'] ?? 3306);
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,   // real prepared statements
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

/** Run a prepared statement. Values are always bound, never pasted into SQL. */
function q(string $sql, array $args = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st;
}
function q_one(string $sql, array $args = []): ?array {
    $r = q($sql, $args)->fetch();
    return $r === false ? null : $r;
}
function q_all(string $sql, array $args = []): array { return q($sql, $args)->fetchAll(); }
function now(int $offsetSeconds = 0): string { return gmdate('Y-m-d H:i:s', time() + $offsetSeconds); }
