<?php
declare(strict_types=1);

function pledge_version(): string { return substr(hash('sha256', load_pledge()), 0, 12); }
function all_degrees(): array { return q_all('SELECT * FROM degrees ORDER BY num'); }

function c_home(): void {
    $deg = q_all("SELECT num, title, status FROM degrees WHERE status IN ('released','available') ORDER BY num");
    render('home', ['title' => 'Members', 'degrees' => $deg]);
}

function c_degrees(): void {
    if (degrees_soon()) { render_coming_soon(); return; }
    render('degrees', ['title' => 'The Degrees', 'degrees' => all_degrees()]);
}

function c_degree(int $n): void {
    if ($n >= 1 && degrees_soon()) { render_coming_soon(); return; }
    $d = q_one('SELECT * FROM degrees WHERE num = ?', [$n]);
    if (!$d) { http_response_code(404); render('error', ['title' => 'Not found', 'message' => 'There is no such Degree.']); return; }
    $u = current_user();
    $open = (int)$d['is_public'] === 1;
    $degrees = degrees_by_num();
    $st = ['state' => 'locked'];
    $lessons = [];
    if ($n === 0) $st = ['state' => 'free'];
    elseif ($u) $st = degree_state($n, prog_all((int)$u['id']), $degrees);
    $isAdmin = $u && $u['role'] === 'admin';
    $mayRead = $open || in_array($st['state'], ['open', 'complete'], true) || $isAdmin;
    if ($mayRead) {
        $prow = $st['row'] ?? null;
        foreach (lessons_of($n, !$isAdmin) as $l) {
            $l['access'] = lesson_access($l, $u, $prow, $d);
            $lessons[] = $l;
        }
    }
    $fee = ['a' => (float)$d['part_a_fee'], 'b' => (float)$d['part_b_fee']];
    $pb = null;   // piece 3: Part B choices and how to pay, only while the Degree is open and Part B not confirmed
    if ($u && $n >= 1 && $st['state'] === 'open') {
        $row = $st['row'];
        $elig = eligible_facilitators($n, (int)$u['id']);
        $chosen = $row['part_b_chosen_at'] !== null;
        $fid = $row['part_b_facilitator_id'] !== null ? (int)$row['part_b_facilitator_id'] : null;
        $ref = part_b_reference((int)$u['id'], $n);
        $pb = ['elig' => $elig, 'ken' => ken_offered($n, $elig), 'chosen' => $chosen, 'fid' => $fid, 'ref' => $ref,
               'payee' => $chosen ? part_b_payee($fid, $fee['b'], $ref) : null, 'claimed' => $row['part_b_claimed_at'] !== null, 'row' => $row];
    }
    render('degree', [
        'title' => $d['num'] . '° ' . ($d['title'] ?: 'Degree ' . $d['num']), 'd' => $d, 'open' => $open, 'st' => $st,
        'lessons' => $lessons, 'mayRead' => $mayRead, 'isAdmin' => $isAdmin, 'pb' => $pb,
        'paypal' => ($st['state'] === 'ready') ? paypal_link($fee['a'], $u ? pay_reference((int)$u['id'], $n) : '') : null,
        'ref' => $u ? pay_reference((int)$u['id'], $n) : '',
        'prevDeg' => $n >= 1 ? ($degrees[$n - 1] ?? null) : null,
    ]);
}

function c_register_form(array $old = [], array $errors = []): void {
    // Signed in already: do not silently bounce away (Ken saw that as being "kicked out"); explain and offer sign-out.
    if ($u = current_user()) { render('register_signed_in', ['title' => 'Sign up', 'u' => $u]); return; }
    render('register', ['title' => 'Sign up', 'old' => $old, 'errors' => $errors, 'pledge' => load_pledge()]);
}

/** Neutral placeholder. QDI- plus the row number. It does not use the person's name. */
function placeholder_member_id(int $id, int $extra = 0): string {
    $base = 'QDI-' . str_pad((string)max(0, $id), 4, '0', STR_PAD_LEFT);
    if ($extra > 0) $base .= '-' . $extra;
    return $base;
}
function member_id_taken(string $candidate, int $exceptUserId = 0): bool {
    if ($exceptUserId > 0) return q_one('SELECT id FROM users WHERE member_id = ? AND id <> ?', [$candidate, $exceptUserId]) !== null;
    return q_one('SELECT id FROM users WHERE member_id = ?', [$candidate]) !== null;
}
function free_placeholder_member_id(int $id): string {
    for ($extra = 0; $extra < 50; $extra++) {
        $candidate = placeholder_member_id($id, $extra);
        if (strlen($candidate) > 20) break;
        if (!member_id_taken($candidate, $id)) return $candidate;
    }
    return placeholder_member_id($id);
}
function store_placeholder_member_id(int $id): string {
    for ($extra = 0; $extra < 50; $extra++) {
        $candidate = placeholder_member_id($id, $extra);
        if (strlen($candidate) > 20) break;
        if (member_id_taken($candidate, $id)) continue;
        try {
            q('UPDATE users SET member_id = ? WHERE id = ?', [$candidate, $id]);
            return $candidate;
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') throw $ex;
        }
    }
    throw new RuntimeException('No free placeholder Member ID.');
}
/** Old sign-ups used six letters from the legal name, a hyphen, and this row number. */
function is_legacy_name_member_id(string $memberId, int $userId): bool {
    if ($userId < 1 || !preg_match('/^[A-Z]{6}-(\d+)$/', $memberId, $m)) return false;
    return (int)$m[1] === $userId;
}
function signup_auto_signin(): bool { return (bool)cfg('signup_auto_signin', true); }

function c_register_post(): void {
    csrf_check();
    $ip = client_ip();
    if (rl_blocked('reg_ip', $ip, 15, 3600)) { http_response_code(429); render('error', ['title' => 'Too many tries', 'message' => 'Too many sign-up attempts. Please wait an hour and try again.']); return; }
    rl_add('reg_ip', $ip, 3600);

    if (current_user()) { c_register_form(); return; }
    $first = posted('first_name'); $last = posted('last_name');
    $name = posted('pseudonym'); $email = mb_strtolower(posted('email'));
    $pw = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $pw2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';
    $pledgeName = posted('pledge_name'); $agree = isset($_POST['pledge']) && $_POST['pledge'] === '1';
    $err = [];
    $legal = '/^[\p{L}][\p{L}\p{M} .\'-]{0,58}[\p{L}.]$|^[\p{L}]$/u';
    if (!preg_match($legal, $first)) $err['first_name'] = 'Please enter your legal first name (letters, spaces, dashes and apostrophes).';
    if (!preg_match($legal, $last)) $err['last_name'] = 'Please enter your legal last name (letters, spaces, dashes and apostrophes).';
    if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._\'-]{1,38}[\p{L}\p{N}]$/u', $name)) $err['pseudonym'] = 'Use 3 to 40 letters or numbers. Spaces, dots, dashes and apostrophes are allowed inside.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $err['email'] = 'Please enter a valid email address.';
    if (!isset($err['email']) && !isset($err['pseudonym'])) { if ($p = password_problem($pw, $email, $name)) $err['password'] = $p; }
    elseif (mb_strlen($pw) < (int)cfg('min_password_length', 10)) $err['password'] = 'Please use at least ' . (int)cfg('min_password_length', 10) . ' characters.';
    if ($pw !== $pw2) $err['password2'] = 'The two passwords do not match.';
    if (!$agree) $err['pledge'] = 'Please tick the box to give your Pledge.';
    if (mb_strlen($pledgeName) < 2 || mb_strlen($pledgeName) > 120) $err['pledge_name'] = 'Please type your name (or pseudonym) as your signature.';
    if (!isset($err['pseudonym']) && q_one('SELECT id FROM users WHERE pseudonym = ?', [$name])) $err['pseudonym'] = 'That name is already taken. Please choose another.';
    if ($err) { http_response_code(422); c_register_form(['first_name' => $first, 'last_name' => $last, 'pseudonym' => $name, 'email' => $email, 'pledge_name' => $pledgeName], $err); return; }

    $existing = q_one('SELECT id FROM users WHERE email = ?', [$email]);
    if ($existing) {
        // Do not reveal that the address is already registered; tell the owner by email instead.
        send_mail($email, 'Invisible Society: you already have an account',
            "Someone (maybe you) tried to sign up with this email address, but it already has an account.\n\nIf it was you, you can sign in here:\n" . cfg('base_url') . "/login\n\nForgot your password? Use:\n" . cfg('base_url') . "/forgot\n\nIf it was not you, you can ignore this email.\n");
    } else {
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $t = now();
        try {
            $auto = signup_auto_signin();
            q('INSERT INTO users (first_name, last_name, pseudonym, email, password_hash, email_verified_at, pledge_name, pledge_version, pledged_at, created_at, pw_changed_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)', [$first, $last, $name, $email, $hash, $auto ? $t : null, $pledgeName, pledge_version(), $t, $t, $t]);
            $id = (int)db()->lastInsertId();
            $mid = store_placeholder_member_id($id);
            if ($auto) {
                session_regenerate_id(true);
                $_SESSION['uid'] = $id; $_SESSION['pwv'] = hash('sha256', $hash);
                q('UPDATE users SET last_login_at = ? WHERE id = ?', [$t, $id]);
                flash("Welcome! Your account is ready and you are signed in. Your Member ID is $mid. Your username is your email address.");
                redirect('/dashboard');
            }
            send_verify_email($id, $email, $name);
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') throw $ex;   // duplicate raced in; treat as already registered
        }
    }
    if (signup_auto_signin()) { render('message', ['title' => 'Please sign in', 'heading' => 'Please sign in',
        'message' => 'We could not create a new account with that email address. If you already have an account, please sign in (your username is your email). Forgot your password? Use "Forgot your password?" on the sign-in page.']); return; }
    render('message', ['title' => 'Check your email', 'heading' => 'Check your email',
        'message' => 'If that address can be used, we have sent a message with a link to confirm it. The link works for 48 hours. Please also look in your spam folder.']);
}

function send_verify_email(int $id, string $email, string $name): void {
    $tok = token_issue($id, 'verify', 172800);
    send_mail($email, 'Invisible Society: please confirm your email',
        "Hello $name,\n\nPlease confirm your email address by opening this link (it works for 48 hours):\n\n" . cfg('base_url') . "/verify?token=$tok\n\nIf you did not sign up, you can ignore this email.\n");
}

function c_verify_form(): void {
    $tok = (string)($_GET['token'] ?? '');
    $ok = token_find($tok, 'verify') !== null;
    render('verify', ['title' => 'Confirm your email', 'token' => $tok, 'valid' => $ok]);
}
function c_verify_post(): void {
    csrf_check();
    $tok = posted('token');
    $row = token_find($tok, 'verify');
    if (!$row) { render('message', ['title' => 'Link not valid', 'heading' => 'This link has expired or was already used', 'message' => 'Please sign in. If your email is not confirmed yet, you can ask for a new link on the sign-in page.']); return; }
    q('UPDATE users SET email_verified_at = ? WHERE id = ? AND email_verified_at IS NULL', [now(), $row['user_id']]);
    q('UPDATE tokens SET used_at = ? WHERE id = ?', [now(), $row['id']]);
    flash('Your email is confirmed. You can sign in now.');
    redirect('/login');
}

function c_login_form(array $old = [], ?string $error = null): void {
    if (current_user()) redirect('/dashboard');
    render('login', ['title' => 'Sign in', 'old' => $old, 'error' => $error]);
}
function c_login_post(): void {
    csrf_check();
    $email = mb_strtolower(posted('email')); $pw = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $ip = client_ip();
    if (rl_blocked('login_ip', $ip, 30, 900) || rl_blocked('login_acct', $email, 5, 900)) {
        http_response_code(429);
        render('error', ['title' => 'Too many tries', 'message' => 'Too many sign-in attempts. Please wait 15 minutes and try again, or use "Forgot password".']);
        return;
    }
    $u = q_one('SELECT * FROM users WHERE email = ?', [$email]);
    if ($u) $good = password_verify($pw, $u['password_hash']);
    else { password_hash($pw, PASSWORD_DEFAULT); $good = false; }   // same work, so timing does not reveal accounts
    if (!$good) {
        rl_add('login_ip', $ip, 900); rl_add('login_acct', $email, 900);
        http_response_code(401);
        c_login_form(['email' => $email], 'That email and password do not match. Please try again.');
        return;
    }
    if (empty($u['email_verified_at'])) {
        http_response_code(403);
        c_login_form(['email' => $email], 'Your email is not confirmed yet. Please open the link we emailed you, or ask for a new link below.');
        return;
    }
    rl_clear('login_acct', $email);
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) { $nh = password_hash($pw, PASSWORD_DEFAULT); q('UPDATE users SET password_hash = ? WHERE id = ?', [$nh, $u['id']]); $u['password_hash'] = $nh; }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id']; $_SESSION['pwv'] = hash('sha256', $u['password_hash']);
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $u['id']]);
    redirect('/dashboard');
}
function c_logout(): void {
    csrf_check();
    $_SESSION = [];
    session_regenerate_id(true);
    $next = posted('next') === 'register' ? '/register' : '/';
    session_boot_flash('You are signed out.');
    redirect($next);
}
function session_boot_flash(string $m): void { $_SESSION['flash'] = ['ok', $m]; }

function c_resend_post(): void {
    csrf_check();
    $email = mb_strtolower(posted('email')); $ip = client_ip();
    if (!rl_blocked('resend_ip', $ip, 5, 3600) && !rl_blocked('resend_acct', $email, 3, 3600)) {
        rl_add('resend_ip', $ip, 3600); rl_add('resend_acct', $email, 3600);
        $u = q_one('SELECT id, pseudonym FROM users WHERE email = ? AND email_verified_at IS NULL', [$email]);
        if ($u) send_verify_email((int)$u['id'], $email, $u['pseudonym']);
    }
    render('message', ['title' => 'Check your email', 'heading' => 'Check your email', 'message' => 'If that address is waiting to be confirmed, we have sent a new link.']);
}

function c_forgot_form(): void { render('forgot', ['title' => 'Forgot password']); }
function c_forgot_post(): void {
    csrf_check();
    $email = mb_strtolower(posted('email')); $ip = client_ip();
    if (!rl_blocked('forgot_ip', $ip, 5, 3600) && !rl_blocked('forgot_acct', $email, 3, 3600)) {
        rl_add('forgot_ip', $ip, 3600); rl_add('forgot_acct', $email, 3600);
        $u = q_one('SELECT id, pseudonym FROM users WHERE email = ? AND email_verified_at IS NOT NULL', [$email]);
        if ($u) {
            $tok = token_issue((int)$u['id'], 'reset', 3600);
            send_mail($email, 'Invisible Society: reset your password',
                "Hello {$u['pseudonym']},\n\nTo choose a new password, open this link (it works for 1 hour, once):\n\n" . cfg('base_url') . "/reset?token=$tok\n\nIf you did not ask for this, you can ignore this email. Your password has not changed.\n");
        }
    }
    render('message', ['title' => 'Check your email', 'heading' => 'Check your email', 'message' => 'If that address has an account, we have sent a link to choose a new password. It works for 1 hour.']);
}
function c_reset_form(array $errors = []): void {
    $tok = (string)($_GET['token'] ?? posted('token'));
    $row = token_find($tok, 'reset');
    render('reset', ['title' => 'Choose a new password', 'token' => $tok, 'valid' => $row !== null, 'errors' => $errors]);
}
function c_reset_post(): void {
    csrf_check();
    $tok = posted('token'); $row = token_find($tok, 'reset');
    if (!$row) { http_response_code(400); c_reset_form(); return; }
    $pw = is_string($_POST['password'] ?? null) ? $_POST['password'] : ''; $pw2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';
    $u = q_one('SELECT email, pseudonym FROM users WHERE id = ?', [$row['user_id']]);
    $err = [];
    if ($p = password_problem($pw, $u['email'], $u['pseudonym'])) $err['password'] = $p;
    if ($pw !== $pw2) $err['password2'] = 'The two passwords do not match.';
    if ($err) { http_response_code(422); $_GET['token'] = $tok; c_reset_form($err); return; }
    q('UPDATE users SET password_hash = ?, pw_changed_at = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), now(), $row['user_id']]);
    q('UPDATE tokens SET used_at = ? WHERE id = ?', [now(), $row['id']]);
    q('DELETE FROM tokens WHERE user_id = ? AND kind = ?', [$row['user_id'], 'reset']);
    $_SESSION = []; session_regenerate_id(true);
    flash('Your password is changed. Please sign in.');
    redirect('/login');
}

function c_dashboard(): void {
    $u = require_login();
    $degrees = degrees_by_num();
    $prog = prog_all((int)$u['id']);
    $rows = []; $next = null;
    foreach ($degrees as $n => $d) {
        $st = degree_state($n, $prog, $degrees);
        $rows[$n] = ['d' => $d, 'st' => $st];
        if ($next === null && $n >= 1 && $st['state'] !== 'complete') $next = [$n, $st];
    }
    render('dashboard', ['title' => 'My page', 'user' => $u, 'rows' => $rows, 'next' => $next, 'admin' => $u['role'] === 'admin']);
}
function c_account_form(array $errors = []): void {
    $u = require_login();
    render('account', ['title' => 'My account', 'user' => $u, 'errors' => $errors]);
}
function c_account_post(): void {
    csrf_check();
    $u = require_login();
    // A member cannot change member_id from this form. Only an administrator can.
    $cur = is_string($_POST['current'] ?? null) ? $_POST['current'] : '';
    $pw = is_string($_POST['password'] ?? null) ? $_POST['password'] : ''; $pw2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';
    if (rl_blocked('acct_pw', (string)$u['id'], 5, 900)) { http_response_code(429); render('error', ['title' => 'Too many tries', 'message' => 'Please wait 15 minutes and try again.']); return; }
    $err = [];
    if (!password_verify($cur, $u['password_hash'])) { rl_add('acct_pw', (string)$u['id'], 900); $err['current'] = 'That is not your current password.'; }
    if ($p = password_problem($pw, $u['email'], $u['pseudonym'])) $err['password'] = $p;
    if ($pw !== $pw2) $err['password2'] = 'The two passwords do not match.';
    if ($err) { http_response_code(422); c_account_form($err); return; }
    $newHash = password_hash($pw, PASSWORD_DEFAULT);
    q('UPDATE users SET password_hash = ?, pw_changed_at = ? WHERE id = ?', [$newHash, now(), $u['id']]);
    session_regenerate_id(true);
    $_SESSION['pwv'] = hash('sha256', $newHash);   // this browser stays signed in; all others are signed out
    flash('Your password is changed.');
    redirect('/account');
}

// ---- piece 2: Part A, lessons ----
function c_claim_post(int $n): void {
    csrf_check();
    $u = require_login();
    $d = $n >= 1 && $n <= MAX_DEGREE ? q_one('SELECT * FROM degrees WHERE num = ?', [$n]) : null;
    if (!$d) { http_response_code(404); render('error', ['title' => 'Not found', 'message' => 'There is no such Degree.']); return; }
    if (rl_blocked('claim', (string)$u['id'], 10, 3600)) { http_response_code(429); render('error', ['title' => 'Too many tries', 'message' => 'Please wait an hour and try again.']); return; }
    rl_add('claim', (string)$u['id'], 3600);
    $st = degree_state($n, prog_all((int)$u['id']), degrees_by_num());
    if ($st['state'] !== 'ready') { flash('You cannot pay Part A for this Degree yet. The page shows what is needed first.', 'err'); redirect('/degree/' . $n); }
    $ref = posted('ref');
    if (!preg_match('/^[A-Za-z0-9 _.\-]{0,64}$/', $ref)) { flash('The receipt number may only have letters, numbers, spaces, dots and dashes (64 at most).', 'err'); redirect('/degree/' . $n); }
    prog_claim((int)$u['id'], $n, (float)$d['part_a_fee'], $ref);
    audit('part_a_claimed', (int)$u['id'], $n, 'ref=' . $ref);
    $to = trim((string)cfg('admin_notify_email', ''));
    if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
        send_mail($to, 'Invisible Society: ' . $u['pseudonym'] . ' says Part A of ' . $n . '° is paid',
            "{$u['pseudonym']} ({$u['email']}) says they paid Part A (" . money($d['part_a_fee']) . ") for Degree $n.\nReceipt number: " . ($ref ?: '(none given)') . "\n\nPlease check your PayPal, then confirm here:\n" . cfg('base_url') . "/admin\n");
    }
    flash('Thank you. Ken will check his PayPal and confirm your payment. You will get an email when the Degree opens.');
    redirect('/degree/' . $n);
}
function c_unclaim_post(int $n): void {
    csrf_check();
    $u = require_login();
    if (prog_unclaim((int)$u['id'], $n)) { audit('part_a_withdrawn', (int)$u['id'], $n); flash('Your payment note was withdrawn.'); }
    else flash('There was no payment note to withdraw.', 'err');
    redirect('/degree/' . $n);
}

/** Load a lesson and decide if the person may read it. Sends the answer page itself when not allowed. */
function lesson_gate(int $id): ?array {
    $l = q_one('SELECT * FROM lessons WHERE id = ?', [$id]);
    $d = $l ? q_one('SELECT * FROM degrees WHERE num = ?', [$l['degree_num']]) : null;
    $u = current_user();
    if (!$l || !$d) { http_response_code(404); render('error', ['title' => 'Not found', 'message' => 'There is no such lesson.']); return null; }
    $row = $u ? q_one('SELECT * FROM progress WHERE user_id = ? AND degree_num = ?', [$u['id'], $l['degree_num']]) : null;
    $a = lesson_access($l, $u, $row, $d);
    if ($a['ok']) return ['l' => $l, 'd' => $d, 'u' => $u];
    if ($a['why'] === 'signin') { flash('Please sign in first.', 'err'); redirect('/login'); }
    if ($a['why'] === 'draft') { http_response_code(404); render('error', ['title' => 'Not found', 'message' => 'There is no such lesson.']); return null; }
    http_response_code(403);
    $msg = $a['why'] === 'wait' ? 'This lesson opens on ' . fmt_ts((int)$a['until']) . '.'
         : ($a['why'] === 'closed' ? 'This Degree is being prepared and is closed for now.' : 'You have not opened this Degree yet.');
    render('error', ['title' => 'Not open yet', 'message' => $msg]);
    return null;
}
function c_lesson(int $id): void {
    $g = lesson_gate($id);
    if (!$g) return;
    render('lesson', ['title' => $g['l']['title'], 'l' => $g['l'], 'd' => $g['d']]);
}
function c_lesson_pdf(int $id): void {
    $g = lesson_gate($id);
    if (!$g) return;
    $l = $g['l'];
    $path = $l['pdf_file'] ? pdf_path((string)$l['pdf_file']) : null;
    if (!$path || !is_file($path)) { http_response_code(404); render('error', ['title' => 'Not found', 'message' => 'There is no PDF for this lesson.']); return; }
    $name = preg_replace('/[^A-Za-z0-9 ._-]/', '_', (string)($l['pdf_name'] ?: 'lesson.pdf'));
    $dl = isset($_GET['download']);
    header_remove('Content-Security-Policy');                      // the browser's own PDF viewer needs this
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($dl ? 'attachment' : 'inline') . '; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile($path);
}
