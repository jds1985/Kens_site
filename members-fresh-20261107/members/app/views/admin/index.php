<?php require __DIR__ . '/_nav.php'; ?>
<h1>Admin</h1>
<p><?= (int)$stats['members'] ?> members, <?= (int)$stats['verified'] ?> with a confirmed email.</p>

<h2>Members who say they paid Part A (<?= count($claims) ?>)</h2>
<?php if (!$claims): ?><p>Nothing waiting. ✓</p><?php else: ?>
<p>Check your PayPal first (look for the reference code), then press Confirm.</p>
<div class="scroll"><table class="stack">
  <thead><tr><th scope="col">Member</th><th scope="col">Degree</th><th scope="col" class="num">Amount</th><th scope="col">Reference they should have used</th><th scope="col">Receipt no. they typed</th><th scope="col">Said paid on</th><th scope="col">Action</th></tr></thead>
  <tbody>
  <?php foreach ($claims as $c): ?>
    <tr>
      <th scope="row" data-label="Member"><a href="<?= e(url('/admin/member/' . $c['user_id'])) ?>"><?= e($c['pseudonym']) ?></a><br><small><?= e($c['email']) ?></small></th>
      <td data-label="Degree"><?= (int)$c['degree_num'] ?>°</td>
      <td class="num" data-label="Amount"><?= e(money($c['part_a_amount'])) ?></td>
      <td data-label="Reference"><?= e(pay_reference((int)$c['user_id'], (int)$c['degree_num'])) ?></td>
      <td data-label="Receipt no."><?= e($c['part_a_ref'] ?: '—') ?></td>
      <td data-label="Said paid on"><?= e(fmt_date($c['part_a_claimed_at'])) ?></td>
      <td data-label="Action">
        <form method="post" action="<?= e(url('/admin/member/' . $c['user_id'])) ?>"><?= csrf_field() ?>
          <input type="hidden" name="degree" value="<?= (int)$c['degree_num'] ?>"><input type="hidden" name="do" value="a_confirm"><input type="hidden" name="back" value="admin">
          <button class="btn primary" type="submit">Confirm paid</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>

<?php if ($facApplied): ?><p class="notice ok"><a href="<?= e(url('/admin/facilitators')) ?>"><?= $facApplied ?> member(s) ask to be a Facilitator</a>: please look and approve.</p><?php endif; ?>

<h2>Part B not confirmed yet (<?= count($partB) ?>)</h2>
<?php if (!$partB): ?><p>Nothing waiting. ✓</p><?php else: ?>
<p>Members who opened a Degree and whose Part B (tuition) is not confirmed. The site never handles this money. When a member pays a Facilitator, the Facilitator confirms it; you only need to act on the ones paid to <b>you</b>, but you can confirm any of them. Confirm only after the tuition really arrived.</p>
<div class="scroll"><table class="stack">
  <thead><tr><th scope="col">Member</th><th scope="col">Degree</th><th scope="col" class="num">Part B</th><th scope="col">Pays to</th><th scope="col">Said paid</th><th scope="col">Action</th></tr></thead>
  <tbody>
  <?php foreach ($partB as $c): $toKen = $c['part_b_chosen_at'] && $c['part_b_facilitator_id'] === null; ?>
    <tr>
      <th scope="row" data-label="Member"><a href="<?= e(url('/admin/member/' . $c['user_id'])) ?>"><?= e($c['pseudonym']) ?></a></th>
      <td data-label="Degree"><?= (int)$c['degree_num'] ?>°</td>
      <td class="num" data-label="Part B"><?= e(money($c['part_b_fee'])) ?></td>
      <td data-label="Pays to"><?= !$c['part_b_chosen_at'] ? '— not chosen yet' : ($toKen ? '<b>You (Ken)</b>' : e($c['fac_name']) . ' (Facilitator)') ?></td>
      <td data-label="Said paid"><?= $c['part_b_claimed_at'] ? '⏳ ' . e(fmt_date($c['part_b_claimed_at'])) . ($c['part_b_ref'] ? '<br><small>Receipt ' . e($c['part_b_ref']) . '</small>' : '') . '<br><small>Ref ' . e(part_b_reference((int)$c['user_id'], (int)$c['degree_num'])) . '</small>' : '—' ?></td>
      <td data-label="Action">
        <form method="post" action="<?= e(url('/admin/member/' . $c['user_id'])) ?>"><?= csrf_field() ?>
          <input type="hidden" name="degree" value="<?= (int)$c['degree_num'] ?>"><input type="hidden" name="do" value="b_confirm"><input type="hidden" name="back" value="admin">
          <button class="btn<?= $toKen && $c['part_b_claimed_at'] ? ' primary' : '' ?>" type="submit" aria-label="Confirm Part B paid: <?= e($c['pseudonym']) ?>, <?= (int)$c['degree_num'] ?>°">Confirm Part B paid</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
