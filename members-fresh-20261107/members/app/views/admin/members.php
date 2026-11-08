<?php require __DIR__ . '/_nav.php'; ?>
<h1>Members (<?= (int)$total ?>)</h1>
<form method="get" action="<?= e(url('/admin/members')) ?>" class="searchform" role="search">
  <label for="q">Find a member (name or email)</label>
  <input id="q" name="q" type="text" value="<?= e($qs) ?>" maxlength="80">
  <button class="btn" type="submit">Search</button>
</form>
<div class="scroll"><table class="stack">
  <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Email confirmed</th><th scope="col">Highest Degree opened</th><th scope="col">Waiting for you</th><th scope="col">Joined</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <th scope="row" data-label="Name"><a href="<?= e(url('/admin/member/' . $r['id'])) ?>"><?= e($r['pseudonym']) ?></a><?= !empty($r['member_id']) ? '<br><small>' . e($r['member_id']) . ' · ' . e(trim($r['first_name'] . ' ' . $r['last_name'])) . '</small>' : '' ?><?= $r['role'] === 'admin' ? ' <span class="tag released">admin</span>' : '' ?></th>
      <td data-label="Email"><?= e($r['email']) ?></td>
      <td data-label="Email confirmed"><?= $r['email_verified_at'] ? '✓ Yes' : '✕ No' ?></td>
      <td data-label="Highest Degree"><?= (int)$r['current_degree'] ?>°</td>
      <td data-label="Waiting for you"><?= (int)$r['pending'] ? '⏳ ' . (int)$r['pending'] . ' payment' : '—' ?></td>
      <td data-label="Joined"><?= e(fmt_date($r['created_at'])) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6">No members found.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Pages">
  <?php if ($page > 1): ?><a class="btn" href="<?= e(url('/admin/members?q=' . rawurlencode($qs) . '&p=' . ($page - 1))) ?>">← Earlier</a><?php endif; ?>
  <span>Page <?= $page ?> of <?= $pages ?></span>
  <?php if ($page < $pages): ?><a class="btn" href="<?= e(url('/admin/members?q=' . rawurlencode($qs) . '&p=' . ($page + 1))) ?>">Later →</a><?php endif; ?>
</nav>
<?php endif; ?>
