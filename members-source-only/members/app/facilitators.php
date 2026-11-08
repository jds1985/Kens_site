<?php
declare(strict_types=1);

// ============================================================================
// Piece 3: Member Facilitators and Part B (tuition).
// The site NEVER handles Part B money. It only shows how to pay the chosen Facilitator (their own PayPal link or
// their own instructions), records "the member says they paid", and lets that Facilitator (or Ken) confirm it.
// All the rules live in this file; every setting has a default (see DEFAULTS.md, 'facilitators' in the config).
// ============================================================================

function fac_min_degree(): int { return max(1, (int)cfg('facilitators.min_degree', 3)); }   // a member may apply after completing this Degree
function fac_ken_up_to(): int { return (int)cfg('facilitators.ken_up_to', 3); }            // Ken is always a choice for Degrees 1..this
function fac_ken_when_none(): bool { return (bool)cfg('facilitators.ken_when_none', true); } // Ken is the choice when nobody else is eligible
function fac_lead(): int { return max(0, (int)cfg('facilitators.lead', 0)); }               // a Facilitator must have completed Degree n + lead

/** Highest Degree whose Part B is confirmed for this member (0 if none). */
function completed_degree(int $uid): int {
    $r = q_one("SELECT MAX(degree_num) AS m FROM progress WHERE user_id = ? AND part_a_status = 'paid' AND part_b_status = 'confirmed'", [$uid]);
    return (int)($r['m'] ?? 0);
}
function fac_row(int $uid): ?array { return q_one('SELECT * FROM facilitators WHERE user_id = ?', [$uid]); }
function may_apply_facilitator(array $u): bool { return completed_degree((int)$u['id']) >= fac_min_degree(); }
/** The lowest completed Degree a Facilitator needs to take Part B of Degree $n. */
function fac_needed_for(int $n): int { return max(fac_min_degree(), $n + fac_lead()); }

/** Approved Facilitators who may take Part B of Degree $n (never the member themself). */
function eligible_facilitators(int $n, int $excludeUid): array {
    $rows = q_all("SELECT f.*, u.pseudonym,
                     (SELECT MAX(p.degree_num) FROM progress p WHERE p.user_id = f.user_id AND p.part_a_status = 'paid' AND p.part_b_status = 'confirmed') AS done
                   FROM facilitators f JOIN users u ON u.id = f.user_id
                   WHERE f.status = 'approved' AND f.user_id <> ? AND u.email_verified_at IS NOT NULL ORDER BY u.pseudonym", [$excludeUid]);
    $need = fac_needed_for($n);
    $rows = array_values(array_filter($rows, fn($r) => (int)$r['done'] >= $need));
    if (cfg('facilitators.order', 'name') === 'shuffle') {   // fair order, stable for one member + Degree for a day
        mt_srand(crc32($excludeUid . '|' . $n . '|' . gmdate('Y-m-d'))); shuffle($rows); mt_srand();
    }
    return $rows;
}
/** Is Ken offered as the Part B choice for Degree $n? */
function ken_offered(int $n, array $eligible): bool { return $n <= fac_ken_up_to() || ($eligible === [] && fac_ken_when_none()); }

function part_b_reference(int $uid, int $n): string { return 'IS-' . $uid . '-' . $n . 'B'; }

/** A PayPal.Me link to a Facilitator's own PayPal, or null. */
function fac_paypal_link(?string $me, float $amount): ?string {
    $me = trim((string)$me);
    if ($me === '' || !preg_match('/^[A-Za-z0-9._-]{1,60}$/', $me)) return null;
    $cur = preg_replace('/[^A-Z]/', '', strtoupper((string)cfg('paypal.currency', 'USD'))) ?: 'USD';
    $amt = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    return 'https://www.paypal.com/paypalme/' . $me . '/' . $amt . $cur;
}

/** How to pay Part B: ['who' => name, 'ken' => bool, 'link' => ?url, 'note' => ?text]. $fid null = Ken. */
function part_b_payee(?int $fid, float $amount, string $ref): array {
    if ($fid === null) {
        return ['who' => (string)cfg('facilitators.ken_name', 'Ken (the Invisible Society)'), 'ken' => true,
                'link' => paypal_link($amount, $ref), 'note' => (string)cfg('facilitators.ken_note', '')];
    }
    $f = q_one('SELECT f.*, u.pseudonym FROM facilitators f JOIN users u ON u.id = f.user_id WHERE f.user_id = ?', [$fid]);
    if (!$f) return ['who' => '(unknown)', 'ken' => false, 'link' => null, 'note' => null];
    return ['who' => $f['pseudonym'], 'ken' => false, 'status' => $f['status'],
            'link' => $f['pay_method'] === 'paypal' ? fac_paypal_link($f['paypal_me'], $amount) : null,
            'note' => $f['pay_note']];
}

// ---- Part B steps (single, guarded UPDATEs) ----
function prog_choose_b(int $uid, int $n, ?int $fid): bool {
    return q("UPDATE progress SET part_b_facilitator_id = ?, part_b_chosen_at = ? WHERE user_id = ? AND degree_num = ? AND part_a_status = 'paid' AND part_b_status = 'none' AND part_b_claimed_at IS NULL",
             [$fid, now(), $uid, $n])->rowCount() > 0;
}
function prog_claim_b(int $uid, int $n, string $ref): bool {
    return q("UPDATE progress SET part_b_claimed_at = ?, part_b_ref = ? WHERE user_id = ? AND degree_num = ? AND part_a_status = 'paid' AND part_b_status = 'none' AND part_b_chosen_at IS NOT NULL AND part_b_claimed_at IS NULL",
             [now(), $ref !== '' ? $ref : null, $uid, $n])->rowCount() > 0;
}
function prog_unclaim_b(int $uid, int $n): bool {
    return q("UPDATE progress SET part_b_claimed_at = NULL, part_b_ref = NULL WHERE user_id = ? AND degree_num = ? AND part_b_status = 'none' AND part_b_claimed_at IS NOT NULL", [$uid, $n])->rowCount() > 0;
}
/** Members of a removed Facilitator choose again (only where Part B is not confirmed). */
function release_members_of(int $fid): int {
    return q("UPDATE progress SET part_b_facilitator_id = NULL, part_b_chosen_at = NULL, part_b_claimed_at = NULL, part_b_ref = NULL WHERE part_b_facilitator_id = ? AND part_b_status = 'none'", [$fid])->rowCount();
}

function notify_email(string $to, string $subject, string $text): void {
    if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) send_mail($to, $subject, $text);
}
/** Tell whoever confirms (the Facilitator, or Ken's notify address) that a member says Part B is paid. */
function notify_confirmer(?int $fid, array $u, int $n, float $amount, string $ref): void {
    $text = "{$u['pseudonym']} says they paid Part B (" . money($amount) . ") for Degree $n.\nReference: " . part_b_reference((int)$u['id'], $n) . "\nTheir receipt number: " . ($ref ?: '(none given)') .
            "\n\nPlease check that the money really arrived, then confirm here:\n" . cfg('base_url') . ($fid === null ? '/admin' : '/facilitator') . "\n";
    if ($fid === null) { notify_email(trim((string)cfg('admin_notify_email', '')), 'Invisible Society: Part B of ' . $n . '° to confirm', $text); return; }
    if (!cfg('notify.facilitator', true)) return;
    $f = q_one('SELECT email, pseudonym FROM users WHERE id = ?', [$fid]);
    if ($f) notify_email($f['email'], 'Invisible Society: a member says they paid you Part B', "Hello {$f['pseudonym']},\n\n" . $text);
}

/** Shared by Ken's admin page and the Facilitator page: confirm Part B and tell the member. */
function confirm_part_b(array $m, int $n, int $byId, float $fee, string $who): bool {
    if (!prog_confirm_b((int)$m['id'], $n, $byId, $fee)) return false;
    audit('part_b_confirmed', (int)$m['id'], $n, money($fee) . ' by ' . $who);
    $nx = degree_state($n + 1, prog_all((int)$m['id']), degrees_by_num());
    $line = $n >= MAX_DEGREE ? 'That completes all the Degrees.'
        : ($nx['state'] === 'ready' ? 'Degree ' . ($n + 1) . ' is ready: you can pay its Part A now.'
        : ($nx['state'] === 'waiting' ? 'Degree ' . ($n + 1) . ' opens for Part A on ' . fmt_ts((int)$nx['until']) . '.'
        : 'Degree ' . ($n + 1) . ' has not been released yet. We will tell you.'));
    notify_member($m, 'Invisible Society: ' . $n . '° is complete', "Part B for Degree $n is confirmed by $who. $line");
    return true;
}

// ---- Liz's artwork: a named slot. Real file = members/assets/art/<slot>.(webp|jpg|png). Jim only places it. ----
function artwork(string $slot, ?string $fallback = null): string {
    $slot = preg_replace('/[^a-z0-9-]/', '', strtolower($slot));
    foreach (array_filter([$slot, $fallback]) as $s) {
        foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
            if (is_file(ROOT_DIR . "/assets/art/$s.$ext")) {
                $alt = (string)cfg("artwork.alt.$s", cfg('artwork.alt.default', 'Artwork by Liz'));
                return '<figure class="art"><img src="' . e(url("/assets/art/$s.$ext")) . '" alt="' . e($alt) . '" loading="lazy"></figure>';
            }
        }
    }
    $show = (string)cfg('artwork.placeholder', 'admin');    // 'admin' = only Ken sees empty slots, 'all', or 'none'
    $u = current_user();
    if ($show === 'all' || ($show === 'admin' && $u && $u['role'] === 'admin')) {
        return '<div class="art-slot" role="note" aria-label="Empty space for artwork"><b>Space for Liz\'s artwork</b><br>Slot “' . e($slot) . '”: put the picture at <code>members/assets/art/' . e($slot) . '.jpg</code></div>';
    }
    return '';
}
