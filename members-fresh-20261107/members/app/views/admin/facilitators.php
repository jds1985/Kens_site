<?php require __DIR__ . '/_nav.php'; ?>
<h1>Member Facilitators</h1>
<p>Members who completed <?= fac_min_degree() ?>° can ask to be a Facilitator. Members pay Part B (tuition) <b>straight to the Facilitator</b> they choose; the site never touches that money. You approve who appears in the list. For <?= fac_ken_up_to() >= 1 ? '1° to ' . fac_ken_up_to() . '°' : 'no Degree' ?> you are always a choice yourself<?= fac_ken_when_none() ? ', and for any Degree that has no eligible Facilitator' : '' ?>.</p>
<p>A Facilitator can take Part B of a Degree after completing <?= fac_lead() === 0 ? 'that Degree' : fac_lead() . ' Degree(s) more than it' ?> (and at least <?= fac_min_degree() ?>°). If a Facilitator changes how they are paid, they wait for your approval again.</p>
<?php if (!$rows): ?><p>Nobody has asked yet.</p><?php else: ?>
<div class="scroll"><table class="stack">
  <thead><tr><th scope="col">Facilitator</th><th scope="col">Status</th><th scope="col">Completed</th><th scope="col">How members pay them</th><th scope="col">Members</th><th scope="col">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $f): $lab = ['applied' => '⏳ Asks to be approved', 'approved' => '✓ Approved', 'paused' => '‖ Paused (not shown to new members)', 'removed' => '✕ Removed'][$f['status']]; ?>
    <tr>
      <th scope="row" data-label="Facilitator"><a href="<?= e(url('/admin/member/' . $f['user_id'])) ?>"><?= e($f['pseudonym']) ?></a><br><small><?= e($f['email']) ?></small>
        <?php if ($f['intro']): ?><br><small>“<?= e($f['intro']) ?>”</small><?php endif; ?></th>
      <td data-label="Status"><?= e($lab) ?></td>
      <td data-label="Completed"><?= (int)$f['done'] ?>°</td>
      <td data-label="How paid"><?php if ($f['pay_method'] === 'paypal'): ?>PayPal: paypal.me/<?= e($f['paypal_me']) ?><?php else: ?>Other<?php endif; ?>
        <?php if ($f['pay_note']): ?><br><small><?= e($f['pay_note']) ?></small><?php endif; ?></td>
      <td data-label="Members"><?= (int)$f['waiting'] ?> waiting, <?= (int)$f['confirmed'] ?> confirmed</td>
      <td data-label="Actions" class="acts">
        <?php foreach ([['approve', 'Approve', $f['status'] !== 'approved', 'primary'], ['pause', 'Pause', $f['status'] === 'approved', ''], ['remove', 'Remove', $f['status'] !== 'removed', '']] as [$do, $label, $show, $cls]): if (!$show) continue; ?>
          <form method="post" action="<?= e(url('/admin/facilitator/' . $f['user_id'])) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="<?= $do ?>">
            <button class="btn <?= $cls ?>" type="submit" aria-label="<?= e($label . ': ' . $f['pseudonym']) ?>"<?= $do === 'remove' ? ' data-confirm="Remove this Facilitator? Their members who have not been confirmed will choose again."' : '' ?>><?= e($label) ?></button></form>
        <?php endforeach; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
