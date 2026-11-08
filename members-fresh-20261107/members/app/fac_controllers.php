<?php
declare(strict_types=1);

// ============================================================================
// Piece 3 pages: the member's Part B steps, the Facilitator's own page, and Ken's Facilitator list.
// Every change is a POST with a form token; every rule is checked again on the server.
// ============================================================================

/** Load the member's open Degree $n for a Part B step, or send them back with a message. */
function part_b_gate(array $u, int $n): array {
    $d = $n >= 1 && $n <= MAX_DEGREE ? q_one('SELECT * FROM degrees WHERE num = ?', [$n]) : null;
    if (!$d) { http_response_code(404); render('error', ['title' => 'Not found', 'message' => 'There is no such Degree.']); exit; }
    $row = prog_all((int)$u['id'])[$n] ?? null;
    if (!$row || $row['part_a_status'] !== 'paid') { flash('Part B comes after this Degree is open.', 'err'); redirect('/degree/' . $n); }
    if ($row['part_b_status'] === 'confirmed') { flash('Part B of this Degree is already confirmed.', 'err'); redirect('/degree/' . $n); }
    return [$d, $row];
}

function c_choose_facilitator_post(int $n): void {
    csrf_check();
    $u = require_login();
    [$d, $row] = part_b_gate($u, $n);
    if ($row['part_b_claimed_at']) { flash('You already said you paid. Withdraw that note first if you want to choose someone else.', 'err'); redirect('/degree/' . $n); }
    $pick = posted('facilitator');
    $elig = eligible_facilitators($n, (int)$u['id']);
    if ($pick === 'ken' && ken_offered($n, $elig)) $fid = null;
    elseif (ctype_digit($pick) && in_array((int)$pick, array_map(fn($f) => (int)$f['user_id'], $elig), true)) $fid = (int)$pick;
    else { flash('Please choose one of the Facilitators in the list.', 'err'); redirect('/degree/' . $n); }
    prog_choose_b((int)$u['id'], $n, $fid);
    $who = part_b_payee($fid, (float)$d['part_b_fee'], '')['who'];
    audit('facilitator_chosen', (int)$u['id'], $n, 'chose ' . $who);
    flash('You chose ' . $who . ' for Part B of ' . $n . '°. How to pay is shown below.');
    redirect('/degree/' . $n . '#partb');
}

function c_part_b_claim_post(int $n): void {
    csrf_check();
    $u = require_login();
    [$d, $row] = part_b_gate($u, $n);
    if (rl_blocked('bclaim', (string)$u['id'], 10, 3600)) { http_response_code(429); render('error', ['title' => 'Too many tries', 'message' => 'Please wait an hour and try again.']); return; }
    rl_add('bclaim', (string)$u['id'], 3600);
    if (!$row['part_b_chosen_at']) { flash('Please choose who you pay Part B to first.', 'err'); redirect('/degree/' . $n); }
    $ref = posted('ref');
    if (!preg_match('/^[A-Za-z0-9 _.\-]{0,64}$/', $ref)) { flash('The receipt number may only have letters, numbers, spaces, dots and dashes (64 at most).', 'err'); redirect('/degree/' . $n); }
    if (!prog_claim_b((int)$u['id'], $n, $ref)) { flash('You already said you paid. Please wait for the confirmation.', 'err'); redirect('/degree/' . $n); }
    $fid = $row['part_b_facilitator_id'] !== null ? (int)$row['part_b_facilitator_id'] : null;
    $who = part_b_payee($fid, (float)$d['part_b_fee'], '')['who'];
    audit('part_b_claimed', (int)$u['id'], $n, 'to ' . $who . ', ref=' . $ref);
    notify_confirmer($fid, $u, $n, (float)$d['part_b_fee'], $ref);
    flash('Thank you. ' . $who . ' will check and confirm your Part B. You will get an email when it is confirmed.');
    redirect('/degree/' . $n . '#partb');
}
function c_part_b_unclaim_post(int $n): void {
    csrf_check();
    $u = require_login();
    if (prog_unclaim_b((int)$u['id'], $n)) { audit('part_b_withdrawn', (int)$u['id'], $n); flash('Your Part B note was withdrawn.'); }
    else flash('There was no Part B note to withdraw.', 'err');
    redirect('/degree/' . $n . '#partb');
}

// ---- the Facilitator's own page ----
function c_facilitator(array $errors = [], array $old = []): void {
    $u = require_login();
    $f = fac_row((int)$u['id']);
    $done = completed_degree((int)$u['id']);
    $members = [];
    if ($f) {
        $members = q_all("SELECT p.*, u.pseudonym, d.part_b_fee FROM progress p JOIN users u ON u.id = p.user_id JOIN degrees d ON d.num = p.degree_num
                          WHERE p.part_b_facilitator_id = ? ORDER BY p.part_b_status = 'confirmed', p.part_b_claimed_at IS NULL, p.part_b_claimed_at, p.part_b_confirmed_at DESC LIMIT 200", [$u['id']]);
    }
    render('fac/facilitator', ['title' => 'Facilitator', 'f' => $f, 'done' => $done, 'may' => $done >= fac_min_degree(),
        'members' => $members, 'errors' => $errors, 'old' => $old ?: ($f ?: ['pay_method' => 'paypal', 'paypal_me' => '', 'pay_note' => '', 'intro' => ''])]);
}
function c_facilitator_post(): void {
    csrf_check();
    $u = require_login();
    if (!may_apply_facilitator($u)) { http_response_code(403); render('error', ['title' => 'Not yet', 'message' => 'Members can become Facilitators after completing ' . fac_min_degree() . '°.']); return; }
    $f = fac_row((int)$u['id']);
    if ($f && $f['status'] === 'removed') { http_response_code(403); render('error', ['title' => 'Not allowed', 'message' => 'Please speak to Ken about being a Facilitator again.']); return; }
    $old = ['pay_method' => posted('pay_method'), 'paypal_me' => trim(preg_replace('#^(https?://)?(www\.)?paypal\.(me|com/paypalme)/#i', '', posted('paypal_me')), '/'), 'pay_note' => posted('pay_note'), 'intro' => posted('intro')];
    $err = [];
    if (!in_array($old['pay_method'], ['paypal', 'other'], true)) $err['pay_method'] = 'Please choose how members pay you.';
    if ($old['pay_method'] === 'paypal' && !preg_match('/^[A-Za-z0-9._-]{1,60}$/', $old['paypal_me'])) $err['paypal_me'] = 'Type the name after paypal.me/ in your PayPal link (letters, numbers, dots, dashes).';
    if ($old['pay_method'] === 'other' && mb_strlen($old['pay_note']) < 5) $err['pay_note'] = 'Please write how members pay you (for example a bank transfer or a postal address).';
    if (mb_strlen($old['pay_note']) > 500) $err['pay_note'] = '500 characters at most.';
    if (mb_strlen($old['intro']) > 500) $err['intro'] = '500 characters at most.';
    if ($err) { http_response_code(422); c_facilitator($err, $old); return; }
    if ($old['pay_method'] === 'other') $old['paypal_me'] = '';
    $payChanged = $f && ($f['pay_method'] !== $old['pay_method'] || (string)$f['paypal_me'] !== $old['paypal_me'] || (string)$f['pay_note'] !== $old['pay_note']);
    $status = !$f ? 'applied' : (($payChanged && $f['status'] === 'approved' && cfg('facilitators.reapprove_on_pay_change', true)) ? 'applied' : $f['status']);
    q('INSERT INTO facilitators (user_id, status, pay_method, paypal_me, pay_note, intro, applied_at) VALUES (?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE status = VALUES(status), pay_method = VALUES(pay_method), paypal_me = VALUES(paypal_me), pay_note = VALUES(pay_note), intro = VALUES(intro)',
      [$u['id'], $status, $old['pay_method'], $old['paypal_me'] ?: null, $old['pay_note'] ?: null, $old['intro'] ?: null, now()]);
    audit($f ? 'facilitator_edited' : 'facilitator_applied', (int)$u['id'], null, $old['pay_method'] . ($payChanged ? ', payment details changed' : ''));
    if (!$f) notify_email(trim((string)cfg('admin_notify_email', '')), 'Invisible Society: ' . $u['pseudonym'] . ' asks to be a Facilitator', "Please look at it here:\n" . cfg('base_url') . "/admin/facilitators\n");
    flash(!$f ? 'Thank you. Ken will look at your request.' : ($status === 'applied' && $f['status'] === 'approved' ? 'Saved. Because how to pay you changed, Ken will approve it again before members see you.' : 'Saved.'));
    redirect('/facilitator');
}
/** A Facilitator confirms (or says "not received yet") for one of THEIR OWN members only. */
function c_facilitator_confirm_post(): void {
    csrf_check();
    $u = require_login();
    $f = fac_row((int)$u['id']);
    if (!$f || !in_array($f['status'], ['approved', 'paused'], true)) { http_response_code(403); render('error', ['title' => 'Not allowed', 'message' => 'Only approved Facilitators can confirm Part B.']); return; }
    $mid = (int)posted('member'); $n = (int)posted('degree'); $do = posted('do');
    $row = q_one('SELECT p.*, d.part_b_fee FROM progress p JOIN degrees d ON d.num = p.degree_num WHERE p.user_id = ? AND p.degree_num = ?', [$mid, $n]);
    if (!$row || (int)$row['part_b_facilitator_id'] !== (int)$u['id'] || $mid === (int)$u['id']) {
        http_response_code(403); render('error', ['title' => 'Not allowed', 'message' => 'You can only confirm Part B for members who chose you.']); return;
    }
    $m = q_one('SELECT * FROM users WHERE id = ?', [$mid]);
    if ($do === 'confirm') {
        if (confirm_part_b($m, $n, (int)$u['id'], (float)$row['part_b_fee'], $u['pseudonym'])) flash('Part B of ' . $n . '° is confirmed for ' . $m['pseudonym'] . '. Thank you.');
        else flash('That Part B was already confirmed.', 'err');
    } elseif ($do === 'notyet') {
        if (prog_unclaim_b($mid, $n)) {
            audit('part_b_not_received', $mid, $n, 'by ' . $u['pseudonym']);
            notify_member($m, 'Invisible Society: Part B of ' . $n . '° not received yet', $u['pseudonym'] . " has not received your Part B payment for Degree $n yet. Please check it, then press \"I have paid\" again on the Degree page.");
            flash('The member was told you have not received it yet.');
        } else flash('There was nothing to answer.', 'err');
    } else flash('Unknown action.', 'err');
    redirect('/facilitator');
}

// ---- Ken: the Facilitator list ----
function c_admin_facilitators(): void {
    require_admin();
    $rows = q_all("SELECT f.*, u.pseudonym, u.email,
                     (SELECT MAX(p.degree_num) FROM progress p WHERE p.user_id = f.user_id AND p.part_a_status = 'paid' AND p.part_b_status = 'confirmed') AS done,
                     (SELECT COUNT(*) FROM progress p WHERE p.part_b_facilitator_id = f.user_id AND p.part_b_status = 'none') AS waiting,
                     (SELECT COUNT(*) FROM progress p WHERE p.part_b_facilitator_id = f.user_id AND p.part_b_status = 'confirmed') AS confirmed
                   FROM facilitators f JOIN users u ON u.id = f.user_id ORDER BY FIELD(f.status, 'applied', 'approved', 'paused', 'removed'), u.pseudonym");
    adm_render('facilitators', ['title' => 'Facilitators (admin)', 'rows' => $rows]);
}
function c_admin_facilitator_post(int $id): void {
    csrf_check();
    $adm = require_admin();
    $f = fac_row($id);
    if (!$f) { not_found('There is no such Facilitator.'); return; }
    $m = q_one('SELECT * FROM users WHERE id = ?', [$id]);
    $do = posted('do');
    $to = ['approve' => 'approved', 'pause' => 'paused', 'remove' => 'removed'][$do] ?? null;
    if (!$to) { flash('Unknown action.', 'err'); redirect('/admin/facilitators'); }
    if ($to === 'approved' && completed_degree($id) < fac_min_degree()) { flash($m['pseudonym'] . ' has not completed ' . fac_min_degree() . '° yet.', 'err'); redirect('/admin/facilitators'); }
    q('UPDATE facilitators SET status = ?, approved_at = IF(? = \'approved\', ?, approved_at), approved_by = IF(? = \'approved\', ?, approved_by) WHERE user_id = ?', [$to, $to, now(), $to, $adm['id'], $id]);
    $freed = $to === 'removed' ? release_members_of($id) : 0;
    audit('facilitator_' . $to, $id, null, $freed ? "$freed member(s) will choose again" : '');
    if ($to === 'approved') notify_member($m, 'Invisible Society: you are a Member Facilitator', 'Ken approved you as a Member Facilitator. Members can now choose you for Part B. Your Facilitator page shows who chose you.');
    flash($m['pseudonym'] . ' is now ' . $to . '.' . ($freed ? " $freed member(s) will choose another Facilitator." : ''));
    redirect('/admin/facilitators');
}
