<?php
declare(strict_types=1);

require __DIR__ . '/demo-guard.php';
$cfg = demo_guard();
$pdo = demo_pdo($cfg);
$pdo->exec("SET time_zone = '+00:00'");

$exists = $pdo->query("SELECT id FROM users WHERE email = 'demo-admin@example.test'")->fetch();
if ($exists) {
    fwrite(STDOUT, "synthetic users already present\n");
    exit(0);
}

$now = gmdate('Y-m-d H:i:s');
$older = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
# Fresh capture had no pledge.txt. Do not restore the old local placeholder.
$pledge = '';
$pledgeVersion = substr(hash('sha256', $pledge), 0, 12);
$ins = $pdo->prepare(
    'INSERT INTO users (id, member_id, first_name, last_name, pseudonym, email, password_hash, email_verified_at, pledge_name, pledge_version, pledged_at, role, current_degree, created_at, pw_changed_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);

# The fourth row is an unconfirmed sign-up from 30 days ago. Sign-in stays refused.
# It lets the admin count show email-not-confirmed separately from the last 7 days.
$people = [
    [1, 'DEMADM-0001', 'Demo', 'Admin', 'Demo Admin', 'demo-admin@example.test', 'demo-admin-pass', 'admin', 0, $now, $now],
    [2, 'DEMFAC-0002', 'Demo', 'Facilitator', 'Demo Facilitator', 'demo-facilitator@example.test', 'demo-facilitator-pass', 'member', 1, $now, $now],
    [3, 'DEMMEM-0003', 'Demo', 'Member', 'Demo Member', 'demo-member@example.test', 'demo-member-pass', 'member', 0, $now, $now],
    [4, 'DEMPEN-0004', 'Demo', 'Pending', 'Demo Pending', 'demo-pending@example.test', 'demo-pending-pass', 'member', 0, null, $older],
];
foreach ($people as [$id, $mid, $first, $last, $name, $email, $password, $role, $degree, $verified, $created]) {
    $ins->execute([
        $id, $mid, $first, $last, $name, $email,
        password_hash($password, PASSWORD_DEFAULT),
        $verified, $name, $pledgeVersion, $created, $role, $degree, $created, $created,
    ]);
}

$prog = $pdo->prepare(
    "INSERT INTO progress (user_id, degree_num, part_a_status, part_a_amount, part_a_method, opened_at, part_a_by, part_b_status, part_b_amount, part_b_confirmed_at, part_b_by)
     VALUES (2, 1, 'paid', 1.00, 'manual', ?, 1, 'confirmed', 1.00, ?, 1)"
);
$prog->execute([$now, $now]);

$fac = $pdo->prepare(
    "INSERT INTO facilitators (user_id, status, pay_method, paypal_me, pay_note, intro, applied_at, approved_at, approved_by)
     VALUES (2, 'approved', 'other', NULL, ?, ?, ?, ?, 1)"
);
$fac->execute([
    'Synthetic demo note. No payment is collected and no external message is sent.',
    'Synthetic facilitator for the isolated demo.',
    $now,
    $now,
]);

$pdf = "/var/www/html/storage/pdfs/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.pdf";
if (!is_dir(dirname($pdf)) && !mkdir(dirname($pdf), 0750, true) && !is_dir(dirname($pdf))) {
    fwrite(STDERR, "could not create pdf directory\n");
    exit(1);
}
$bytes = "%PDF-1.1\n1 0 obj<</Type/Catalog>>endobj\ntrailer<<>>\n%%EOF\n";
if (file_put_contents($pdf, $bytes) === false) {
    fwrite(STDERR, "could not write synthetic pdf\n");
    exit(1);
}
chmod($pdf, 0640);
fwrite(STDOUT, "synthetic users ready\n");
