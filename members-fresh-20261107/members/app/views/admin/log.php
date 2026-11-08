<?php require __DIR__ . '/_nav.php'; ?>
<h1>Log (last 300)</h1>
<div class="scroll"><table class="stack">
  <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">What</th><th scope="col">Member</th><th scope="col">Degree</th><th scope="col">Details</th></tr></thead>
  <tbody>
  <?php foreach ($log as $x): ?>
    <tr>
      <td data-label="When"><?= e(fmt_date($x['at'], true)) ?></td>
      <td data-label="Who"><?= e($x['actor_name']) ?></td>
      <th scope="row" data-label="What"><?= e($x['action']) ?></th>
      <td data-label="Member"><?= $x['user_id'] ? '<a href="' . e(url('/admin/member/' . $x['user_id'])) . '">#' . (int)$x['user_id'] . '</a>' : '—' ?></td>
      <td data-label="Degree"><?= $x['degree_num'] !== null ? (int)$x['degree_num'] . '°' : '—' ?></td>
      <td data-label="Details"><?= e($x['detail'] ?: '') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$log): ?><tr><td colspan="6">Nothing yet.</td></tr><?php endif; ?>
  </tbody>
</table></div>
