<?php require __DIR__ . '/_nav.php'; $mid = (int)$m['id']; ?>
<p><a href="<?= e(url('/admin/members')) ?>">← All members</a></p>
<h1><?= e($m['pseudonym']) ?></h1>
<ul>
  <li>Email: <b><?= e($m['email']) ?></b> <?= $m['email_verified_at'] ? '(✓ confirmed)' : '(✕ not confirmed yet)' ?></li>
  <li>Joined: <?= e(fmt_date($m['created_at'])) ?> · Last sign-in: <?= e(fmt_date($m['last_login_at'])) ?></li>
  <li>Pledge: signed "<?= e($m['pledge_name']) ?>" on <?= e(fmt_date($m['pledged_at'])) ?> (text version <?= e($m['pledge_version']) ?>)</li>
  <li>Highest Degree opened: <b><?= (int)$m['current_degree'] ?>°</b> · Role: <?= e($m['role']) ?></li>
</ul>

<h2>Degrees</h2>
<?php $shown = 0; ?>
<div class="scroll"><table class="stack">
  <thead><tr><th scope="col">Degree</th><th scope="col">Part A (dues)</th><th scope="col">Opened</th><th scope="col">Part B (tuition)</th><th scope="col">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($prog as $n => $r): if ($r['part_a_status'] === 'none') continue; $shown++; $d = $degrees[$n]; ?>
    <tr>
      <th scope="row" data-label="Degree"><?= $n ?>° <?= e($d['title'] ?: '') ?></th>
      <td data-label="Part A">
        <?php if ($r['part_a_status'] === 'paid'): ?>✓ Paid <?= e(money($r['part_a_amount'])) ?> (<?= e($r['part_a_method']) ?>)
        <?php else: ?>⏳ Says paid <?= e(money($r['part_a_amount'])) ?> on <?= e(fmt_date($r['part_a_claimed_at'])) ?><?php endif; ?>
        <?php if ($r['part_a_ref']): ?><br><small>Receipt: <?= e($r['part_a_ref']) ?></small><?php endif; ?>
        <br><small>Reference: <?= e(pay_reference($mid, $n)) ?></small>
      </td>
      <td data-label="Opened"><?= e(fmt_date($r['opened_at'])) ?></td>
      <td data-label="Part B"><?= $r['part_b_status'] === 'confirmed' ? '✓ Confirmed ' . e(fmt_date($r['part_b_confirmed_at'])) : ($r['part_a_status'] === 'paid' ? '⏳ Not confirmed (' . e(money($d['part_b_fee'])) . ')' : '—') ?>
        <?php if ($r['part_b_chosen_at']): $fn = $r['part_b_facilitator_id'] === null ? 'Ken' : (q_one('SELECT pseudonym FROM users WHERE id = ?', [$r['part_b_facilitator_id']])['pseudonym'] ?? '?'); ?><br><small>Pays to: <?= e($fn) ?></small><?php endif; ?>
        <?php if ($r['part_b_claimed_at'] && $r['part_b_status'] !== 'confirmed'): ?><br><small>Says paid <?= e(fmt_date($r['part_b_claimed_at'])) ?><?= $r['part_b_ref'] ? ', receipt ' . e($r['part_b_ref']) : '' ?></small><?php endif; ?>
        <?php if ($r['part_b_status'] === 'confirmed' && $r['part_b_by']): ?><br><small>Confirmed by: <?= e(q_one('SELECT pseudonym FROM users WHERE id = ?', [$r['part_b_by']])['pseudonym'] ?? '?') ?></small><?php endif; ?></td>
      <td data-label="Actions" class="acts">
        <?php foreach ([
            ['a_confirm', 'Confirm Part A paid', $r['part_a_status'] !== 'paid', 'primary'],
            ['b_confirm', 'Confirm Part B paid', $r['part_a_status'] === 'paid' && $r['part_b_status'] === 'none', 'primary'],
            ['b_undo', 'Undo Part B', $r['part_b_status'] === 'confirmed', ''],
            ['a_undo', 'Undo Part A', true, ''],
          ] as [$do, $label, $show, $cls]): if (!$show) continue; ?>
          <form method="post" action="<?= e(url('/admin/member/' . $mid)) ?>"><?= csrf_field() ?>
            <input type="hidden" name="degree" value="<?= $n ?>"><input type="hidden" name="do" value="<?= $do ?>">
            <button class="btn <?= $cls ?>" type="submit"<?= str_contains($do, 'undo') ? ' data-confirm="Are you sure?"' : '' ?>><?= e($label) ?></button></form>
        <?php endforeach; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$shown): ?><tr><td colspan="5">No payments yet.</td></tr><?php endif; ?>
  </tbody>
</table></div>

<h2>Mark a Degree as paid by hand</h2>
<p>Use this when a member paid Part A some other way (cash, a different PayPal, a gift). It opens the Degree at once, even if the usual rules would keep it locked.</p>
<form method="post" action="<?= e(url('/admin/member/' . $mid)) ?>"><?= csrf_field() ?>
  <input type="hidden" name="do" value="a_confirm">
  <div class="field"><label for="degree">Degree</label>
    <select id="degree" name="degree">
      <?php for ($i = 1; $i <= MAX_DEGREE; $i++): ?><option value="<?= $i ?>"><?= $i ?>° <?= e($degrees[$i]['title'] ?? '') ?> — Part A <?= e(money($degrees[$i]['part_a_fee'] ?? $i)) ?></option><?php endfor; ?>
    </select></div>
  <p><button class="btn primary" type="submit">Mark Part A paid and open it</button></p>
</form>

<h2>History</h2>
<?php if (!$log): ?><p>Nothing yet.</p><?php else: ?>
<ul class="plain"><?php foreach ($log as $x): ?><li><?= e(fmt_date($x['at'], true)) ?> — <?= e($x['action']) ?><?= $x['degree_num'] !== null ? ' (' . (int)$x['degree_num'] . '°)' : '' ?> by <?= e($x['actor_name']) ?><?= $x['detail'] ? ': ' . e($x['detail']) : '' ?></li><?php endforeach; ?></ul>
<?php endif; ?>
