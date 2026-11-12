<?php
declare(strict_types=1);

/**
 * Optional one-time replacement of old name-based Member IDs.
 *
 * New sign-ups already receive QDI- plus the row number. This script does
 * not run by itself, and it does not change an ID Ken has set by hand.
 * It only rewrites an ID that is six letters, a hyphen, and that same row
 * number (the old name-based form, such as JOHSMI-0007).
 *
 * Dry run (prints the plan, writes nothing):
 *   php app/migrate-member-ids.php
 * Write the changes:
 *   php app/migrate-member-ids.php --apply
 *
 * The web server forbids this file. Run it from the command line, on a
 * copy of the database first.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script is for the command line only.\n";
    exit(1);
}

require __DIR__ . '/bootstrap.php';

$apply = in_array('--apply', $argv ?? [], true);
$changes = [];
foreach (q_all('SELECT id, member_id FROM users WHERE member_id IS NOT NULL ORDER BY id') as $row) {
    $current = (string)$row['member_id'];
    $id = (int)$row['id'];
    if (!is_legacy_name_member_id($current, $id)) continue;
    $changes[] = ['id' => $id, 'from' => $current, 'to' => free_placeholder_member_id($id)];
}

if (!$changes) {
    fwrite(STDOUT, "No name-based Member IDs to change. Existing IDs were left as they are.\n");
    exit(0);
}

fwrite(STDOUT, $apply
    ? "Replacing name-based Member IDs with neutral placeholders.\n"
    : "Dry run. No Member IDs were changed.\n");
foreach ($changes as $change) {
    fwrite(STDOUT, 'user ' . $change['id'] . ': ' . $change['from'] . ' -> ' . $change['to'] . "\n");
}
fwrite(STDOUT, "IDs that are not the old six-letter name pattern are left as they are.\n");
if (!$apply) {
    fwrite(STDOUT, "Run again with --apply to make these changes. This does not run by itself.\n");
    exit(0);
}

$updated = 0;
foreach ($changes as $change) {
    $row = q_one('SELECT member_id FROM users WHERE id = ?', [$change['id']]);
    if (!$row || !is_legacy_name_member_id((string)$row['member_id'], $change['id'])) continue;
    $to = free_placeholder_member_id($change['id']);
    q('UPDATE users SET member_id = ? WHERE id = ? AND member_id = ?', [$to, $change['id'], $row['member_id']]);
    $updated++;
}
fwrite(STDOUT, "Updated $updated member IDs.\n");
