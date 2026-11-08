<?php
declare(strict_types=1);

/**
 * Off-by-default gate for the isolated demo container.
 * The imported members application is not modified. Apache loads this
 * file through auto_prepend_file. CLI seed/wait scripts disable prepend.
 */

function demo_config_path(): string
{
    $path = getenv('KEN_MEMBERS_CONFIG') ?: '';
    if ($path === '' || !is_file($path)) {
        demo_fail('KEN_MEMBERS_CONFIG is missing.');
    }
    return $path;
}

function demo_config(): array
{
    $cfg = require demo_config_path();
    if (!is_array($cfg)) {
        demo_fail('Demo config did not return an array.');
    }
    return $cfg;
}

function demo_fail(string $message): void
{
    fwrite(STDERR, "members-demo refused to start: {$message}\n");
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        echo 'Demo configuration refused.';
    }
    exit(1);
}

function demo_guard(): array
{
    if (getenv('MEMBERS_DEMO') !== '1') {
        demo_fail('MEMBERS_DEMO is not 1. This container does not boot as a production site.');
    }
    $cfg = demo_config();
    $base = (string)($cfg['base_url'] ?? '');
    if (!preg_match('#^http://localhost(:\d+)?$#', $base)) {
        demo_fail('base_url must be http://localhost with an optional port.');
    }
    $db = $cfg['db'] ?? null;
    if (!is_array($db) || ($db['host'] ?? '') !== 'db' || ($db['name'] ?? '') !== 'members_demo') {
        demo_fail('Database host/name is not the isolated demo database.');
    }
    if (($db['user'] ?? '') !== 'members_demo' || ($db['pass'] ?? '') === '' || ($db['pass'] ?? '') === 'CHANGE-ME') {
        demo_fail('Database user is not the synthetic demo user.');
    }
    $secret = (string)($cfg['app_secret'] ?? '');
    if (strlen($secret) < 32 || strpos($secret, 'CHANGE-ME') !== false || strpos($secret, 'demo-only-') !== 0) {
        demo_fail('app_secret is not the synthetic demo secret.');
    }
    $mail = $cfg['mail'] ?? [];
    if (!is_array($mail) || ($mail['driver'] ?? '') !== 'log') {
        demo_fail('Mail driver must be log. mail and smtp are refused.');
    }
    $smtp = $mail['smtp'] ?? [];
    if (!is_array($smtp) || ($smtp['host'] ?? '') !== '' || ($smtp['user'] ?? '') !== '' || ($smtp['pass'] ?? '') !== '') {
        demo_fail('SMTP settings must be empty.');
    }
    $paypal = $cfg['paypal'] ?? [];
    if (!is_array($paypal) || trim((string)($paypal['me_name'] ?? '')) !== '' || trim((string)($paypal['link_template'] ?? '')) !== '') {
        demo_fail('PayPal name and link template must be empty.');
    }
    if (trim((string)($cfg['admin_notify_email'] ?? '')) !== '') {
        demo_fail('admin_notify_email must be empty so claims do not address a real inbox.');
    }
    if (!empty($cfg['degrees_coming_soon'])) {
        demo_fail('degrees_coming_soon must be false in this demo or member progress cannot be exercised.');
    }
    if (!empty($cfg['debug'])) {
        demo_fail('debug must stay false.');
    }
    return $cfg;
}

function demo_pdo(array $cfg): PDO
{
    $db = $cfg['db'];
    $dsn = 'mysql:host=' . $db['host'] . ';port=' . (int)($db['port'] ?? 3306) . ';dbname=' . $db['name'] . ';charset=utf8mb4';
    return new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

final class DemoMysqlSessionHandler implements SessionHandlerInterface
{
    private ?PDO $pdo = null;

    public function __construct(private array $cfg)
    {
    }

    public function open(string $path, string $name): bool
    {
        $this->pdo = demo_pdo($this->cfg);
        return true;
    }

    public function close(): bool
    {
        $this->pdo = null;
        return true;
    }

    public function read(string $id): string|false
    {
        if (!$this->valid($id)) {
            return '';
        }
        $st = $this->pdo()->prepare('SELECT payload FROM demo_sessions WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? (string)$row['payload'] : '';
    }

    public function write(string $id, string $data): bool
    {
        if (!$this->valid($id)) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO demo_sessions (id, payload, touched) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), touched = VALUES(touched)'
        );
        return $st->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        if (!$this->valid($id)) {
            return true;
        }
        $st = $this->pdo()->prepare('DELETE FROM demo_sessions WHERE id = ?');
        return $st->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $st = $this->pdo()->prepare('DELETE FROM demo_sessions WHERE touched < ?');
        $st->execute([time() - $max_lifetime]);
        return $st->rowCount();
    }

    private function pdo(): PDO
    {
        if (!$this->pdo) {
            $this->pdo = demo_pdo($this->cfg);
        }
        return $this->pdo;
    }

    private function valid(string $id): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9,-]{1,128}$/', $id);
    }
}

function demo_register_session_handler(array $cfg): void
{
    session_set_save_handler(new DemoMysqlSessionHandler($cfg), true);
}
