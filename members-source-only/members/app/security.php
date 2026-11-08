<?php
declare(strict_types=1);

const SESSION_IDLE = 14400;               // 4 hours without activity -> sign in again

function base_path(): string {
    $d = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    return $d === '/' || $d === '.' ? '' : rtrim($d, '/');
}
function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strpos((string)cfg('base_url', ''), 'https://') === 0);
}
function security_headers(): void {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000');
}
function session_boot(): void {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('ismem');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => (base_path() ?: '/'),
        'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
    if (isset($_SESSION['last']) && time() - $_SESSION['last'] > SESSION_IDLE) {
        $_SESSION = []; session_regenerate_id(true);
    }
    $_SESSION['last'] = time();
}

// ---- CSRF ----
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void {
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        http_response_code(403);
        render('error', ['title' => 'Please try again', 'message' => 'Your page was out of date. Go back, reload it, and try again.']);
        exit;
    }
}

// ---- rate limiting (stored in the database, keys are scrambled) ----
function rl_key(string $bucket, string $id): string {
    return hash_hmac('sha256', $bucket . '|' . strtolower($id), (string)cfg('app_secret', 'x'));
}
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function rl_blocked(string $bucket, string $id, int $max, int $window): bool {
    $r = q_one('SELECT hits, window_start FROM rate_limits WHERE k = ?', [rl_key($bucket, $id)]);
    if (!$r) return false;
    if (strtotime($r['window_start'] . ' UTC') < time() - $window) return false;
    return (int)$r['hits'] >= $max;
}
function rl_add(string $bucket, string $id, int $window): void {
    $cut = now(-$window);
    q('INSERT INTO rate_limits (k, hits, window_start) VALUES (?, 1, ?)
       ON DUPLICATE KEY UPDATE
         hits = IF(window_start < ?, 1, hits + 1),
         window_start = IF(window_start < ?, VALUES(window_start), window_start)',
      [rl_key($bucket, $id), now(), $cut, $cut]);
    if (random_int(1, 50) === 1) q('DELETE FROM rate_limits WHERE window_start < ?', [now(-86400)]);
}
function rl_clear(string $bucket, string $id): void {
    q('DELETE FROM rate_limits WHERE k = ?', [rl_key($bucket, $id)]);
}

// ---- passwords ----
function password_problem(string $pw, string $email, string $pseudonym): ?string {
    $min = (int)cfg('min_password_length', 10);
    if (mb_strlen($pw) < $min) return "Please use at least $min characters. A few words in a row make a good password.";
    if (mb_strlen($pw) > 200) return 'That password is too long (200 characters at most).';
    $low = mb_strtolower($pw);
    if ($low === mb_strtolower($email) || $low === mb_strtolower($pseudonym)) return 'The password cannot be the same as your email or name.';
    $common = ['password12','password123','1234567890','qwertyuiop','0123456789','letmein123','iloveyou12','welcome123','abc1234567','1q2w3e4r5t'];
    if (in_array($low, $common, true) || preg_match('/^(.)\1+$/u', $pw)) return 'That password is too easy to guess. Please choose another.';
    return null;
}

// ---- one-time tokens (only a hash is stored) ----
function token_issue(int $userId, string $kind, int $ttl): string {
    q('DELETE FROM tokens WHERE user_id = ? AND kind = ?', [$userId, $kind]);
    $raw = bin2hex(random_bytes(32));
    q('INSERT INTO tokens (user_id, kind, token_hash, expires_at) VALUES (?,?,?,?)',
      [$userId, $kind, hash('sha256', $raw), now($ttl)]);
    return $raw;
}
function token_find(string $raw, string $kind): ?array {
    if (!preg_match('/^[0-9a-f]{64}$/', $raw)) return null;
    return q_one('SELECT * FROM tokens WHERE token_hash = ? AND kind = ? AND used_at IS NULL AND expires_at > ?',
                 [hash('sha256', $raw), $kind, now()]);
}
