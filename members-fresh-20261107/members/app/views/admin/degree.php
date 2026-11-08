<?php require __DIR__ . '/_nav.php'; $n = (int)$d['num']; $o = $old; ?>
<h1>Edit <?= $n ?>° <?= e($d['title'] ?: '') ?></h1>
<?php if ($errors): ?><div class="notice err" role="alert"><b>Please fix the <?= count($errors) ?> item<?= count($errors) > 1 ? 's' : '' ?> marked below.</b></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/degree/' . $n)) ?>">
  <?= csrf_field() ?>
  <div class="field"><label for="title">Title</label>
    <input id="title" name="title" type="text" maxlength="120" value="<?= e($o['title'] ?? '') ?>" <?= isset($errors['title']) ? 'aria-invalid="true"' : '' ?>>
    <?php if (isset($errors['title'])): ?><p class="error"><?= e($errors['title']) ?></p><?php endif; ?></div>
  <div class="field"><label for="status">Status</label>
    <select id="status" name="status" aria-describedby="h-status">
      <?php foreach (['planned' => 'Planned (not announced)', 'available' => 'Available (announced, members cannot pay yet)', 'released' => 'Released (members can pay Part A and open it)'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= ($o['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="hint" id="h-status">Members can only pay for a Degree that is <b>Released</b>.</p>
    <?php if (isset($errors['status'])): ?><p class="error"><?= e($errors['status']) ?></p><?php endif; ?></div>
  <div class="field"><label for="summary">Short description (everyone can read this)</label>
    <textarea id="summary" name="summary" rows="3" maxlength="500"><?= e($o['summary'] ?? '') ?></textarea>
    <?php if (isset($errors['summary'])): ?><p class="error"><?= e($errors['summary']) ?></p><?php endif; ?></div>
  <div class="field"><label for="intro">Introduction text (<?= $n === 0 ? 'public, for the Naught Degree' : 'only members who opened this Degree can read it' ?>)</label>
    <textarea id="intro" name="intro" rows="10"><?= e($o['intro'] ?? '') ?></textarea>
    <p class="hint">Plain text. A blank line starts a new paragraph.</p>
    <?php if (isset($errors['intro'])): ?><p class="error"><?= e($errors['intro']) ?></p><?php endif; ?></div>
  <fieldset><legend>Money and waiting time</legend>
    <div class="field"><label for="part_a_fee">Part A fee (dues), dollars</label>
      <input id="part_a_fee" name="part_a_fee" type="text" inputmode="decimal" value="<?= e($o['part_a_fee'] ?? '') ?>" <?= isset($errors['part_a_fee']) ? 'aria-invalid="true"' : '' ?>>
      <?php if (isset($errors['part_a_fee'])): ?><p class="error"><?= e($errors['part_a_fee']) ?></p><?php endif; ?></div>
    <div class="field"><label for="part_b_fee">Part B fee (tuition), dollars</label>
      <input id="part_b_fee" name="part_b_fee" type="text" inputmode="decimal" value="<?= e($o['part_b_fee'] ?? '') ?>" <?= isset($errors['part_b_fee']) ? 'aria-invalid="true"' : '' ?>>
      <p class="hint">Changing a fee does not change what members already paid or said they paid.</p>
      <?php if (isset($errors['part_b_fee'])): ?><p class="error"><?= e($errors['part_b_fee']) ?></p><?php endif; ?></div>
    <div class="field"><label for="wait_days">Waiting time after this Degree, in days</label>
      <input id="wait_days" name="wait_days" type="text" inputmode="numeric" value="<?= e($o['wait_days'] ?? '') ?>" <?= isset($errors['wait_days']) ? 'aria-invalid="true"' : '' ?>>
      <p class="hint">How long a member waits before the next Degree opens. Counted from the day <?= e(cfg('wait_counts_from', 'opened') === 'part_b' ? 'Part B of this Degree was confirmed' : 'this Degree opened for the member') ?> (set in the config file).</p>
      <?php if (isset($errors['wait_days'])): ?><p class="error"><?= e($errors['wait_days']) ?></p><?php endif; ?></div>
  </fieldset>
  <p><button class="btn primary" type="submit">Save <?= $n ?>°</button></p>
</form>

<h2>Lessons in <?= $n ?>°</h2>
<?php if (!$lessons): ?><p>No lessons yet.</p><?php else: ?>
<div class="scroll"><table class="stack">
  <thead><tr><th scope="col">Order</th><th scope="col">Title</th><th scope="col">Has</th><th scope="col">Opens after</th><th scope="col">Published</th><th scope="col">Edit</th></tr></thead>
  <tbody>
  <?php foreach ($lessons as $l): ?>
    <tr>
      <td data-label="Order"><?= (int)$l['position'] ?></td>
      <th scope="row" data-label="Title"><?= e($l['title']) ?></th>
      <td data-label="Has"><?= implode(', ', array_filter([$l['body'] ? 'text' : '', $l['pdf_file'] ? 'PDF' : '', $l['link_url'] ? 'link' : ''])) ?: '—' ?></td>
      <td data-label="Opens after"><?= (int)$l['unlock_after_days'] ?> days</td>
      <td data-label="Published"><?= (int)$l['published'] ? '✓ Yes' : '✕ Draft' ?></td>
      <td data-label="Edit"><a class="btn" href="<?= e(url('/admin/lesson/' . $l['id'])) ?>" aria-label="Edit lesson: <?= e($l['title']) ?>">Edit</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<p class="actions"><a class="btn primary" href="<?= e(url('/admin/lesson/new?degree=' . $n)) ?>">Add a lesson to <?= $n ?>°</a>
  <a class="btn" href="<?= e(url('/degree/' . $n)) ?>">See it as a member sees it</a></p>
