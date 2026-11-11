<?php
declare(strict_types=1);

// ============================================================================
// Piece 2: the admin pages for Ken (members with role = admin only).
// Every page checks the role again. Every change is a POST with a form token and is written to the audit log.
// ============================================================================

function require_admin(): array {
    $u = current_user();
    if (!$u) { flash('Please sign in first.', 'err'); redirect('/login'); }
    if ($u['role'] !== 'admin') { http_response_code(403); render('error', ['title' => 'Not allowed', 'message' => 'This page is only for the site owner.']); exit; }
    return $u;
}
function adm_render(string $view, array $vars = []): void {
    $vars['title'] = $vars['title'] ?? 'Admin';
    render('admin/' . $view, $vars);
}
function not_found(string $msg = 'That page does not exist.'): void {
    http_response_code(404); render('error', ['title' => 'Not found', 'message' => $msg]);
}
function money_ok(string $s): bool { return (bool)preg_match('/^\d{1,9}(\.\d{1,2})?$/', $s); }

/** Sign-up totals for the admin pages. require_admin() runs again here so this query cannot be reused on a public page. */
function signup_counts(): array {
    require_admin();
    $row = q_one(
        'SELECT COUNT(*) AS total,
                COALESCE(SUM(email_verified_at IS NOT NULL), 0) AS confirmed,
                COALESCE(SUM(email_verified_at IS NULL), 0) AS unconfirmed,
                COALESCE(SUM(created_at >= ?), 0) AS recent
           FROM users',
        [now(-7 * 86400)]
    );
    return [
        'total' => (int)($row['total'] ?? 0),
        'confirmed' => (int)($row['confirmed'] ?? 0),
        'unconfirmed' => (int)($row['unconfirmed'] ?? 0),
        'recent' => (int)($row['recent'] ?? 0),
    ];
}

// ---- home: what needs Ken's attention ----
function c_admin_home(): void {
    require_admin();
    $claims = q_all("SELECT p.*, u.pseudonym, u.email FROM progress p JOIN users u ON u.id = p.user_id WHERE p.part_a_status = 'claimed' ORDER BY p.part_a_claimed_at");
    // Part B not confirmed yet. Ken sees all of them (he can always confirm), with who the member pays.
    $partB = q_all("SELECT p.*, u.pseudonym, u.email, d.part_b_fee, fu.pseudonym AS fac_name FROM progress p JOIN users u ON u.id = p.user_id JOIN degrees d ON d.num = p.degree_num
                    LEFT JOIN users fu ON fu.id = p.part_b_facilitator_id
                    WHERE p.part_a_status = 'paid' AND p.part_b_status = 'none' ORDER BY p.part_b_facilitator_id IS NOT NULL, p.part_b_claimed_at IS NULL, p.part_b_claimed_at, p.opened_at");
    $facApplied = (int)(q_one("SELECT COUNT(*) AS c FROM facilitators WHERE status = 'applied'")['c'] ?? 0);
    adm_render('index', ['title' => 'Admin', 'claims' => $claims, 'partB' => $partB, 'signups' => signup_counts(), 'facApplied' => $facApplied]);
}

// ---- Degrees ----
function c_admin_degrees(): void {
    require_admin();
    $cnt = [];
    foreach (q_all('SELECT degree_num, COUNT(*) AS c, SUM(published) AS pub FROM lessons GROUP BY degree_num') as $r) $cnt[(int)$r['degree_num']] = $r;
    adm_render('degrees', ['title' => 'Degrees (admin)', 'degrees' => all_degrees(), 'cnt' => $cnt]);
}
function c_admin_degree_form(int $n, array $old = [], array $errors = []): void {
    require_admin();
    $d = $n >= 0 && $n <= MAX_DEGREE ? q_one('SELECT * FROM degrees WHERE num = ?', [$n]) : null;
    if (!$d) { not_found('There is no such Degree.'); return; }
    adm_render('degree', ['title' => 'Edit ' . $n . '°', 'd' => $d, 'old' => $old ?: $d, 'errors' => $errors, 'lessons' => lessons_of($n, false)]);
}
function c_admin_degree_post(int $n): void {
    csrf_check();
    $a = require_admin();
    $d = $n >= 0 && $n <= MAX_DEGREE ? q_one('SELECT * FROM degrees WHERE num = ?', [$n]) : null;
    if (!$d) { not_found('There is no such Degree.'); return; }
    $old = ['title' => posted('title'), 'status' => posted('status'), 'summary' => posted('summary'), 'intro' => is_string($_POST['intro'] ?? null) ? trim($_POST['intro']) : '',
            'wait_days' => posted('wait_days'), 'part_a_fee' => posted('part_a_fee'), 'part_b_fee' => posted('part_b_fee')];
    $err = [];
    if (mb_strlen($old['title']) > 120) $err['title'] = 'The title can have 120 characters at most.';
    if (!in_array($old['status'], ['planned', 'available', 'released'], true)) $err['status'] = 'Please choose a status.';
    if (mb_strlen($old['summary']) > 500) $err['summary'] = 'The short description can have 500 characters at most.';
    if (mb_strlen($old['intro']) > 20000) $err['intro'] = 'The text is too long (20,000 characters at most).';
    if (!preg_match('/^\d{1,4}$/', $old['wait_days']) || (int)$old['wait_days'] > 3650) $err['wait_days'] = 'Use a whole number of days from 0 to 3650.';
    if (!money_ok($old['part_a_fee'])) $err['part_a_fee'] = 'Use a number like 12 or 12.50.';
    if (!money_ok($old['part_b_fee'])) $err['part_b_fee'] = 'Use a number like 12 or 12.50.';
    if ($err) { http_response_code(422); c_admin_degree_form($n, $old, $err); return; }
    $chg = [];
    foreach (['title', 'status', 'summary', 'intro', 'wait_days', 'part_a_fee', 'part_b_fee'] as $k) if ((string)($d[$k] ?? '') !== (string)$old[$k] && !(is_numeric($d[$k] ?? null) && is_numeric($old[$k]) && (float)$d[$k] === (float)$old[$k])) $chg[] = $k;
    q('UPDATE degrees SET title = ?, status = ?, summary = ?, intro = ?, wait_days = ?, part_a_fee = ?, part_b_fee = ? WHERE num = ?',
      [$old['title'] !== '' ? $old['title'] : null, $old['status'], $old['summary'] !== '' ? $old['summary'] : null, $old['intro'] !== '' ? $old['intro'] : null,
       (int)$old['wait_days'], $old['part_a_fee'], $old['part_b_fee'], $n]);
    audit('degree_edited', null, $n, $chg ? 'changed: ' . implode(', ', $chg) : 'saved, no change');
    flash('Saved ' . $n . '°.');
    redirect('/admin/degree/' . $n);
}

// ---- Lessons ----
function c_admin_lesson_form(int $id, int $degree, array $old = [], array $errors = []): void {
    require_admin();
    $l = null;
    if ($id > 0) { $l = q_one('SELECT * FROM lessons WHERE id = ?', [$id]); if (!$l) { not_found('There is no such lesson.'); return; } $degree = (int)$l['degree_num']; }
    $d = $degree >= 0 && $degree <= MAX_DEGREE ? q_one('SELECT num, title FROM degrees WHERE num = ?', [$degree]) : null;
    if (!$d) { not_found('There is no such Degree.'); return; }
    if (!$old) $old = $l ?: ['position' => 10, 'title' => '', 'body' => '', 'link_url' => '', 'link_label' => '', 'unlock_after_days' => 0, 'published' => 0];
    adm_render('lesson', ['title' => $l ? 'Edit lesson' : 'New lesson', 'l' => $l, 'd' => $d, 'old' => $old, 'errors' => $errors]);
}

/** Check and store an uploaded PDF. Returns [file, name, size] or null; sets $err. */
function store_pdf(array $f, ?string &$err): ?array {
    $err = null;
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $max = (int)cfg('max_pdf_mb', 10) * 1048576;
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || ($f['size'] ?? 0) > $max) { $err = 'That PDF is too big. The limit is ' . (int)cfg('max_pdf_mb', 10) . ' MB.'; return null; }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) { $err = 'The upload did not work. Please try again.'; return null; }
    $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 8);
    $mime = function_exists('mime_content_type') ? (string)@mime_content_type($f['tmp_name']) : 'application/pdf';
    if (strpos($head, '%PDF-') !== 0 || $mime !== 'application/pdf') { $err = 'That file is not a PDF. Only PDF files can be added (no audio or video).'; return null; }
    if (!is_dir(pdf_dir()) && !@mkdir(pdf_dir(), 0750, true)) { $err = 'The server could not create the PDF folder (storage/pdfs). Check the folder permissions.'; return null; }
    $file = bin2hex(random_bytes(16)) . '.pdf';
    if (!move_uploaded_file($f['tmp_name'], pdf_dir() . '/' . $file)) { $err = 'The server could not save the PDF. Check the folder permissions.'; return null; }
    $name = preg_replace('/[^\p{L}\p{N} ._()-]/u', '_', basename((string)$f['name']));
    $name = mb_substr($name, 0, 116);
    if (!preg_match('/\.pdf$/i', $name)) $name .= '.pdf';
    return [$file, $name, (int)filesize(pdf_dir() . '/' . $file)];
}
function delete_pdf(?string $file): void {
    $p = $file ? pdf_path($file) : null;
    if ($p && is_file($p)) @unlink($p);
}

function c_admin_lesson_post(): void {
    // A file bigger than the server's limit arrives as an empty form; say so clearly.
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        require_admin(); http_response_code(413);
        render('error', ['title' => 'File too big', 'message' => 'That upload is bigger than this server allows. Use a smaller PDF (the limit is ' . (int)cfg('max_pdf_mb', 10) . ' MB).']); return;
    }
    csrf_check();
    require_admin();
    $id = (int)posted('id');
    $l = $id > 0 ? q_one('SELECT * FROM lessons WHERE id = ?', [$id]) : null;
    if ($id > 0 && !$l) { not_found('There is no such lesson.'); return; }
    $degree = $l ? (int)$l['degree_num'] : (int)posted('degree_num');
    if (!q_one('SELECT num FROM degrees WHERE num = ?', [$degree])) { not_found('There is no such Degree.'); return; }
    $old = ['position' => posted('position'), 'title' => posted('title'), 'body' => is_string($_POST['body'] ?? null) ? trim($_POST['body']) : '',
            'link_url' => posted('link_url'), 'link_label' => posted('link_label'), 'unlock_after_days' => posted('unlock_after_days'),
            'published' => isset($_POST['published']) ? 1 : 0, 'degree_num' => $degree];
    $err = [];
    if ($old['title'] === '' || mb_strlen($old['title']) > 160) $err['title'] = 'Please give the lesson a title (160 characters at most).';
    if (!preg_match('/^-?\d{1,4}$/', $old['position'])) $err['position'] = 'Use a whole number such as 10, 20, 30. Small numbers come first.';
    if (!preg_match('/^\d{1,4}$/', $old['unlock_after_days']) || (int)$old['unlock_after_days'] > 3650) $err['unlock_after_days'] = 'Use a whole number of days from 0 to 3650. 0 = open at once.';
    if (mb_strlen($old['body']) > 100000) $err['body'] = 'The text is too long (100,000 characters at most).';
    if (mb_strlen($old['link_label']) > 160) $err['link_label'] = 'The link words can have 160 characters at most.';
    if ($old['link_url'] !== '') {
        $p = parse_url($old['link_url']);
        if (mb_strlen($old['link_url']) > 500 || !filter_var($old['link_url'], FILTER_VALIDATE_URL) || !in_array(strtolower((string)($p['scheme'] ?? '')), ['http', 'https'], true) || empty($p['host']))
            $err['link_url'] = 'Use a full web address that starts with https:// (or http://).';
    }
    $newPdf = null; $perr = null;
    if (!$err) { $newPdf = store_pdf($_FILES['pdf'] ?? [], $perr); if ($perr) $err['pdf'] = $perr; }
    if ($err) { if ($newPdf) delete_pdf($newPdf[0]); http_response_code(422); c_admin_lesson_form($id, $degree, $old, $err); return; }

    $link = $old['link_url'] !== '' ? $old['link_url'] : null;
    $label = $link && $old['link_label'] !== '' ? $old['link_label'] : null;
    $body = $old['body'] !== '' ? $old['body'] : null;
    if ($l) {
        $file = $l['pdf_file']; $pname = $l['pdf_name']; $psize = $l['pdf_size'];
        if ($newPdf) { delete_pdf($file); [$file, $pname, $psize] = $newPdf; }
        elseif (isset($_POST['remove_pdf'])) { delete_pdf($file); $file = $pname = $psize = null; }
        q('UPDATE lessons SET position=?, title=?, body=?, link_url=?, link_label=?, pdf_file=?, pdf_name=?, pdf_size=?, unlock_after_days=?, published=?, updated_at=? WHERE id=?',
          [(int)$old['position'], $old['title'], $body, $link, $label, $file, $pname, $psize, (int)$old['unlock_after_days'], $old['published'], now(), $id]);
        audit('lesson_edited', null, $degree, '#' . $id . ' ' . $old['title']);
        flash('Lesson saved.');
    } else {
        q('INSERT INTO lessons (degree_num, position, title, body, link_url, link_label, pdf_file, pdf_name, pdf_size, unlock_after_days, published, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [$degree, (int)$old['position'], $old['title'], $body, $link, $label, $newPdf[0] ?? null, $newPdf[1] ?? null, $newPdf[2] ?? null, (int)$old['unlock_after_days'], $old['published'], now(), now()]);
        $id = (int)db()->lastInsertId();
        audit('lesson_added', null, $degree, '#' . $id . ' ' . $old['title']);
        flash('Lesson added.' . ($old['published'] ? '' : ' It is a draft: tick "Published" so members can see it.'));
    }
    redirect('/admin/degree/' . $degree);
}
function c_admin_lesson_delete(int $id): void {
    csrf_check();
    require_admin();
    $l = q_one('SELECT * FROM lessons WHERE id = ?', [$id]);
    if (!$l) { not_found('There is no such lesson.'); return; }
    delete_pdf($l['pdf_file']);
    q('DELETE FROM lessons WHERE id = ?', [$id]);
    audit('lesson_deleted', null, (int)$l['degree_num'], '#' . $id . ' ' . $l['title']);
    flash('Lesson deleted.');
    redirect('/admin/degree/' . $l['degree_num']);
}

// ---- Members ----
function c_admin_members(): void {
    require_admin();
    $qs = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['p'] ?? 1)); $per = 25;
    $where = ''; $args = [];
    if ($qs !== '') { $like = '%' . addcslashes(mb_substr($qs, 0, 80), '%_\\') . '%'; $where = 'WHERE pseudonym LIKE ? OR email LIKE ?'; $args = [$like, $like]; }
    $total = (int)q_one("SELECT COUNT(*) AS c FROM users $where", $args)['c'];
    $rows = q_all("SELECT id, pseudonym, email, role, current_degree, email_verified_at, created_at, last_login_at,
                   (SELECT COUNT(*) FROM progress p WHERE p.user_id = users.id AND p.part_a_status = 'claimed') AS pending
                   FROM users $where ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $args);
    adm_render('members', ['title' => 'Members', 'rows' => $rows, 'qs' => $qs, 'page' => $page, 'pages' => max(1, (int)ceil($total / $per)), 'total' => $total, 'signups' => signup_counts()]);
}
function c_admin_member(int $id): void {
    require_admin();
    $m = q_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$m) { not_found('There is no such member.'); return; }
    $degrees = degrees_by_num(); $prog = prog_all($id);
    $log = q_all('SELECT * FROM audit_log WHERE user_id = ? ORDER BY id DESC LIMIT 30', [$id]);
    adm_render('member', ['title' => $m['pseudonym'], 'm' => $m, 'degrees' => $degrees, 'prog' => $prog, 'log' => $log]);
}
function c_admin_member_post(int $id): void {
    csrf_check();
    $adm = require_admin();
    $m = q_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$m) { not_found('There is no such member.'); return; }
    $n = (int)posted('degree'); $do = posted('do');
    $d = $n >= 1 && $n <= MAX_DEGREE ? q_one('SELECT * FROM degrees WHERE num = ?', [$n]) : null;
    if (!$d) { flash('Please choose a Degree from 1 to ' . MAX_DEGREE . '.', 'err'); redirect('/admin/member/' . $id); }
    $row = prog_all($id)[$n] ?? null;
    $back = posted('back') === 'admin' ? '/admin' : '/admin/member/' . $id;
    $msg = null;
    switch ($do) {
        case 'a_confirm':      // also used to grant Part A by hand
            if ($row && $row['part_a_status'] === 'paid') { $msg = ['That Part A was already confirmed.', 'err']; break; }
            $was = $row['part_a_status'] ?? 'none';
            prog_confirm_a($id, $n, (int)$adm['id'], (float)$d['part_a_fee']);
            audit($was === 'claimed' ? 'part_a_confirmed' : 'part_a_marked_paid', $id, $n, ($was === 'claimed' ? 'member had said paid' : 'marked paid by hand') . ', ' . money(($row['part_a_amount'] ?? 0) > 0 ? $row['part_a_amount'] : $d['part_a_fee']));
            notify_member($m, 'Invisible Society: ' . $n . '° is open',
                "Your Part A payment for Degree $n is confirmed. The Degree is open." . (lessons_of($n) ? " Lessons open on the schedule shown on the Degree page." : " Lessons will be added soon."));
            $msg = ['Part A of ' . $n . '° is confirmed for ' . $m['pseudonym'] . '. The Degree is open.', 'ok'];
            break;
        case 'a_undo':
            if (!$row || $row['part_a_status'] === 'none') { $msg = ['Nothing to undo.', 'err']; break; }
            prog_undo_a($id, $n);
            audit('part_a_undone', $id, $n, 'was ' . $row['part_a_status'] . ($row['part_b_status'] === 'confirmed' ? ', Part B also cleared' : ''));
            $msg = ['Part A of ' . $n . '° was undone (and Part B with it).', 'ok'];
            break;
        case 'b_confirm':
            if (!$row || $row['part_a_status'] !== 'paid') { $msg = ['Part A must be confirmed first.', 'err']; break; }
            if (!confirm_part_b($m, $n, (int)$adm['id'], (float)$d['part_b_fee'], 'Ken')) { $msg = ['That Part B was already confirmed.', 'err']; break; }
            $msg = ['Part B of ' . $n . '° is confirmed for ' . $m['pseudonym'] . '.', 'ok'];
            break;
        case 'b_undo':
            if (!$row || $row['part_b_status'] !== 'confirmed') { $msg = ['Nothing to undo.', 'err']; break; }
            prog_undo_b($id, $n);
            audit('part_b_undone', $id, $n);
            $msg = ['Part B of ' . $n . '° was undone.', 'ok'];
            break;
        default: $msg = ['Unknown action.', 'err'];
    }
    flash($msg[0], $msg[1]);
    redirect($back);
}
function c_admin_log(): void {
    require_admin();
    adm_render('log', ['title' => 'Audit log', 'log' => q_all('SELECT * FROM audit_log ORDER BY id DESC LIMIT 300')]);
}
