<?php
declare(strict_types=1);

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = '/'): string { return base_path() . $path; }
function site_url(string $path = ''): string { return rtrim((string)cfg('site_url', '/'), '/') . '/' . ltrim($path, '/'); }
function redirect(string $path): void { header('Location: ' . url($path), true, 303); exit; }
function money($n): string { return '$' . number_format((float)$n, 0); }
function flash(?string $msg = null, string $type = 'ok') {
    if ($msg !== null) { $_SESSION['flash'] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function current_user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $row = q_one('SELECT * FROM users WHERE id = ?', [(int)$_SESSION['uid']]);
        // signing out everywhere after a password change
        if ($row && !empty($row['email_verified_at']) && hash_equals(hash('sha256', $row['password_hash']), (string)($_SESSION['pwv'] ?? ''))) $u = $row;
        else { unset($_SESSION['uid']); }
    }
    return $u;
}
function require_login(): array {
    $u = current_user();
    if (!$u) { flash('Please sign in first.', 'err'); redirect('/login'); }
    return $u;
}
function render(string $view, array $vars = []): void {
    $vars['user'] = $vars['user'] ?? current_user();
    $vars['flash'] = flash();
    extract($vars, EXTR_SKIP);
    ob_start();
    require APP_DIR . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require APP_DIR . '/views/layout.php';
}
function status_label(string $s): string { return ['planned' => 'Planned', 'available' => 'Available', 'released' => 'Released'][$s] ?? $s; }
function paragraphs(?string $t): string {
    $out = '';
    foreach (preg_split('/\R{2,}/', trim((string)$t)) as $p) if ($p !== '') $out .= '<p>' . nl2br(e($p)) . '</p>';
    return $out;
}
function load_pledge(): string {
    $f = APP_DIR . '/content/pledge.txt';
    return is_file($f) ? (string)file_get_contents($f) : '';
}
/** Ken's pledge, with the "(give your name)" line marked so the sign-up page can fill it. */
function pledge_html(string $text): string {
    $mark = '(give your name)';
    $pos = strpos($text, $mark);
    if ($pos === false) return e($text);
    return e(substr($text, 0, $pos)) . '<span id="pledge-given-name">' . e($mark) . '</span>' . e(substr($text, $pos + strlen($mark)));
}
function curriculum_url(): string { return 'https://qabbalah.institute/#curriculum'; }
function curriculum_fees_link(): string {
    return '<a href="' . e(curriculum_url()) . '">Curriculum section (dues and fee schedule)</a>';
}
function posted(string $k): string { return is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : ''; }

/** Words + symbol + CSS class for a Degree state (never colour alone). */
function state_info(array $st): array {
    switch ($st['state']) {
        case 'free':     return ['▶ Free, open to all', 'current'];
        case 'complete': return ['✓ Complete', 'complete'];
        case 'open':     return ['▶ Open: lessons', 'current'];
        case 'claimed':  return ['⏳ Waiting for Ken', 'pending'];
        case 'ready':    return ['● Ready: pay Part A', 'ready'];
        case 'waiting':  return ['⏳ Opens ' . fmt_ts((int)$st['until']), 'pending'];
        case 'prevb':    return ['✕ Locked', 'locked'];
        case 'notyet':   return ['✕ Not open yet', 'locked'];
        default:         return ['✕ Locked', 'locked'];
    }
}
function part_b_help(): string {
    return (string)cfg('text.part_b_help', 'Part B (tuition) is paid to a Member Facilitator, not through this site. You choose who to pay on the Degree page, pay them directly, and they confirm it.');
}
function link_host(string $url): string { return (string)(parse_url($url, PHP_URL_HOST) ?: $url); }

// ---- "Degrees — Coming Soon" (Ken, Nov 2026): Degrees, fees, payments and Facilitators are hidden from members
// until 'degrees_coming_soon' => false in the config. The Degree code is kept; Ken (admin) still sees everything.
function degrees_soon(): bool {
    if (!cfg('degrees_coming_soon', true)) return false;
    $u = current_user();
    return !($u && $u['role'] === 'admin');
}
function coming_soon_box(): string {
    return '<div class="box next coming-soon" role="status"><h2>Degrees — Coming Soon</h2>'
         . '<p>The Degrees are being prepared. You will be able to start them here soon. Your membership is ready, and nothing else is needed from you for now.</p></div>';
}
function render_coming_soon(): void { render('coming_soon', ['title' => 'Degrees — Coming Soon']); }
