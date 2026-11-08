<?php
declare(strict_types=1);

// ============================================================================
// Piece 2: the rules for Degrees, Part A, Part B and the waiting time.
// All the "who may open what" decisions live in this one file.
// ============================================================================

const MAX_DEGREE = 32;

/** All progress rows of one member, keyed by Degree number. */
function prog_all(int $uid): array {
    $out = [];
    foreach (q_all('SELECT * FROM progress WHERE user_id = ?', [$uid]) as $r) $out[(int)$r['degree_num']] = $r;
    return $out;
}

function ts(?string $utc): ?int { return $utc ? (int)strtotime($utc . ' UTC') : null; }

/** A date for people, in the display time zone from the config (default New York), e.g. "November 29, 2026". */
function fmt_date(?string $utc, bool $withTime = false): string {
    $t = ts($utc);
    if ($t === null) return '—';
    try { $tz = new DateTimeZone((string)cfg('display_timezone', 'America/New_York')); } catch (Throwable $e) { $tz = new DateTimeZone('UTC'); }
    $d = (new DateTimeImmutable('@' . $t))->setTimezone($tz);
    return $d->format($withTime ? 'F j, Y, g:i a' : 'F j, Y');
}
function fmt_ts(int $t): string { return fmt_date(gmdate('Y-m-d H:i:s', $t)); }

/**
 * When does the waiting time after Degree $prev end for this member?
 * Config 'wait_counts_from': 'opened' (default) = counted from the day Degree $prev opened (Part A confirmed),
 *                            'part_b'           = counted from the day Part B of $prev was confirmed.
 */
function wait_ends(array $prevRow, int $waitDays): ?int {
    $from = cfg('wait_counts_from', 'opened') === 'part_b' ? ($prevRow['part_b_confirmed_at'] ?? null) : ($prevRow['opened_at'] ?? null);
    $t = ts($from);
    return $t === null ? null : $t + $waitDays * 86400;
}

/**
 * The state of Degree $n for a member.
 *   free      the Naught Degree
 *   complete  Part A paid and Part B confirmed
 *   open      Part A paid (lessons open), Part B not confirmed yet
 *   claimed   member said "I paid Part A", waiting for Ken
 *   ready     member may pay Part A now
 *   waiting   the waiting time after the Degree before is not over ('until' = timestamp)
 *   prevb     the Degree before has no confirmed Part B yet ('prev' = its number)
 *   notyet    Ken has not released this Degree
 * $progress = prog_all(), $degrees = degrees keyed by number.
 */
function degree_state(int $n, array $progress, array $degrees): array {
    if ($n === 0) return ['state' => 'free'];
    $row = $progress[$n] ?? null;
    if ($row && $row['part_a_status'] === 'paid') return ['state' => $row['part_b_status'] === 'confirmed' ? 'complete' : 'open', 'row' => $row];
    if ($row && $row['part_a_status'] === 'claimed') return ['state' => 'claimed', 'row' => $row];
    $d = $degrees[$n] ?? null;
    if (!$d || $d['status'] !== 'released') return ['state' => 'notyet'];
    if ($n >= 2) {
        $prev = $progress[$n - 1] ?? null;
        if (!$prev || $prev['part_a_status'] !== 'paid' || $prev['part_b_status'] !== 'confirmed') return ['state' => 'prevb', 'prev' => $n - 1];
        $end = wait_ends($prev, (int)($degrees[$n - 1]['wait_days'] ?? 0));
        if ($end !== null && time() < $end) return ['state' => 'waiting', 'until' => $end, 'prev' => $n - 1];
    }
    return ['state' => 'ready'];
}

function degrees_by_num(): array {
    $o = [];
    foreach (all_degrees() as $d) $o[(int)$d['num']] = $d;
    return $o;
}

/** Highest Degree whose Part A is paid (0 if none). Kept in users.current_degree. */
function recompute_current_degree(int $uid): void {
    $r = q_one("SELECT MAX(degree_num) AS m FROM progress WHERE user_id = ? AND part_a_status = 'paid'", [$uid]);
    q('UPDATE users SET current_degree = ? WHERE id = ?', [(int)($r['m'] ?? 0), $uid]);
}

/** A short code the member writes in the PayPal note so Ken can match the payment. */
function pay_reference(int $uid, int $n): string { return 'IS-' . $uid . '-' . $n . 'A'; }

/** The PayPal link for an amount, or null if Ken's PayPal is not set up in the config. */
function paypal_link(float $amount, string $ref = ''): ?string {
    $cur = preg_replace('/[^A-Z]/', '', strtoupper((string)cfg('paypal.currency', 'USD'))) ?: 'USD';
    $amt = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    $tpl = trim((string)cfg('paypal.link_template', ''));
    if ($tpl !== '') {
        $u = str_replace(['{amount}', '{currency}', '{ref}'], [$amt, $cur, rawurlencode($ref)], $tpl);
    } else {
        $me = trim((string)cfg('paypal.me_name', ''));
        if ($me === '' || !preg_match('/^[A-Za-z0-9._-]{1,60}$/', $me)) return null;
        $u = 'https://www.paypal.com/paypalme/' . $me . '/' . $amt . $cur;
    }
    return preg_match('#^https://#', $u) ? $u : null;
}

function audit(string $action, ?int $userId = null, ?int $degree = null, string $detail = ''): void {
    $a = current_user();
    q('INSERT INTO audit_log (at, actor_id, actor_name, action, user_id, degree_num, detail) VALUES (?,?,?,?,?,?,?)',
      [now(), $a['id'] ?? null, $a ? $a['pseudonym'] : '(member)', $action, $userId, $degree, mb_substr($detail, 0, 255)]);
}

// ---- admin actions on one member + one Degree (each one is a single, logged change) ----
function prog_ensure(int $uid, int $n): void {
    q('INSERT IGNORE INTO progress (user_id, degree_num) VALUES (?, ?)', [$uid, $n]);
}
function prog_claim(int $uid, int $n, float $amount, string $ref): void {
    prog_ensure($uid, $n);
    q("UPDATE progress SET part_a_status = 'claimed', part_a_amount = ?, part_a_method = 'paypal', part_a_ref = ?, part_a_claimed_at = ? WHERE user_id = ? AND degree_num = ? AND part_a_status = 'none'",
      [$amount, $ref !== '' ? $ref : null, now(), $uid, $n]);
}
function prog_unclaim(int $uid, int $n): bool {
    return q("UPDATE progress SET part_a_status = 'none', part_a_amount = 0, part_a_method = NULL, part_a_ref = NULL, part_a_claimed_at = NULL WHERE user_id = ? AND degree_num = ? AND part_a_status = 'claimed'", [$uid, $n])->rowCount() > 0;
}
function prog_confirm_a(int $uid, int $n, int $adminId, float $fee): void {
    prog_ensure($uid, $n);
    q("UPDATE progress SET part_a_status = 'paid', part_a_amount = IF(part_a_amount > 0, part_a_amount, ?), part_a_method = COALESCE(part_a_method, 'manual'),
         opened_at = COALESCE(opened_at, ?), part_a_by = ? WHERE user_id = ? AND degree_num = ? AND part_a_status <> 'paid'", [$fee, now(), $adminId, $uid, $n]);
    recompute_current_degree($uid);
}
function prog_undo_a(int $uid, int $n): void {
    q("UPDATE progress SET part_a_status = 'none', part_a_amount = 0, part_a_method = NULL, part_a_ref = NULL, part_a_claimed_at = NULL, opened_at = NULL, part_a_by = NULL,
         part_b_status = 'none', part_b_amount = NULL, part_b_confirmed_at = NULL, part_b_by = NULL,
         part_b_facilitator_id = NULL, part_b_chosen_at = NULL, part_b_claimed_at = NULL, part_b_ref = NULL WHERE user_id = ? AND degree_num = ?", [$uid, $n]);
    recompute_current_degree($uid);
}
function prog_confirm_b(int $uid, int $n, int $adminId, float $fee): bool {
    $st = q("UPDATE progress SET part_b_status = 'confirmed', part_b_amount = ?, part_b_confirmed_at = ?, part_b_by = ? WHERE user_id = ? AND degree_num = ? AND part_a_status = 'paid' AND part_b_status = 'none'",
            [$fee, now(), $adminId, $uid, $n]);
    return $st->rowCount() > 0;
}
function prog_undo_b(int $uid, int $n): void {
    q("UPDATE progress SET part_b_status = 'none', part_b_amount = NULL, part_b_confirmed_at = NULL, part_b_by = NULL WHERE user_id = ? AND degree_num = ?", [$uid, $n]);
}

/** Tell the member by email (the sandbox config writes it to a log file instead of sending). */
function notify_member(array $u, string $subject, string $text): void {
    if (!cfg('notify.member', true)) return;
    send_mail($u['email'], $subject, "Hello {$u['pseudonym']},\n\n$text\n\nOpen your page: " . cfg('base_url') . "/dashboard\n");
}

// ---- lessons ----
/** Lessons of a Degree, in order. $publishedOnly = true for members. */
function lessons_of(int $n, bool $publishedOnly = true): array {
    return q_all('SELECT * FROM lessons WHERE degree_num = ?' . ($publishedOnly ? ' AND published = 1' : '') . ' ORDER BY position, id', [$n]);
}
/**
 * May this person read this lesson now?  Returns ['ok'=>bool, 'why'=>string, 'until'=>?int].
 * why: ok | draft | signin | notopen | closed | wait
 */
function lesson_access(array $l, ?array $user, ?array $progressRow, array $degree): array {
    if ($user && $user['role'] === 'admin') return ['ok' => true, 'why' => 'ok'];
    if (!(int)$l['published']) return ['ok' => false, 'why' => 'draft'];
    if ((int)$degree['is_public'] === 1) return ['ok' => true, 'why' => 'ok'];          // Naught Degree: public, no waiting
    if (!$user) return ['ok' => false, 'why' => 'signin'];
    if (!$progressRow || $progressRow['part_a_status'] !== 'paid') return ['ok' => false, 'why' => 'notopen'];
    if ($degree['status'] !== 'released') return ['ok' => false, 'why' => 'closed'];
    $t = ts($progressRow['opened_at']) ?? time();
    $until = $t + (int)$l['unlock_after_days'] * 86400;
    if (time() < $until) return ['ok' => false, 'why' => 'wait', 'until' => $until];
    return ['ok' => true, 'why' => 'ok'];
}

function pdf_dir(): string { return ROOT_DIR . '/storage/pdfs'; }
function pdf_path(string $file): ?string {
    if (!preg_match('/^[0-9a-f]{32}\.pdf$/', $file)) return null;
    return pdf_dir() . '/' . $file;
}
