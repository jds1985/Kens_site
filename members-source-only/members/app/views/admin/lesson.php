<?php require __DIR__ . '/_nav.php'; $o = $old; $dn = (int)$d['num']; ?>
<h1><?= $l ? 'Edit lesson' : 'New lesson' ?> in <?= $dn ?>°</h1>
<?php if ($errors): ?><div class="notice err" role="alert"><b>Please fix the <?= count($errors) ?> item<?= count($errors) > 1 ? 's' : '' ?> marked below.</b></div><?php endif; ?>
<p class="hint">A lesson can have text, one PDF and one link, in any mix. Audio and video are not supported on purpose.</p>
<form method="post" action="<?= e(url('/admin/lesson')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $l ? (int)$l['id'] : 0 ?>">
  <input type="hidden" name="degree_num" value="<?= $dn ?>">
  <div class="field"><label for="title">Title</label>
    <input id="title" name="title" type="text" maxlength="160" required value="<?= e($o['title'] ?? '') ?>" <?= isset($errors['title']) ? 'aria-invalid="true"' : '' ?>>
    <?php if (isset($errors['title'])): ?><p class="error"><?= e($errors['title']) ?></p><?php endif; ?></div>
  <div class="field"><label for="body">Text</label>
    <textarea id="body" name="body" rows="14"><?= e($o['body'] ?? '') ?></textarea>
    <p class="hint">Plain text. A blank line starts a new paragraph.</p>
    <?php if (isset($errors['body'])): ?><p class="error"><?= e($errors['body']) ?></p><?php endif; ?></div>
  <div class="field"><label for="pdf">PDF file (optional, up to <?= (int)cfg('max_pdf_mb', 10) ?> MB)</label>
    <?php if ($l && $l['pdf_file']): ?><p>Now: <b><?= e($l['pdf_name']) ?></b> — upload a new one to replace it, or
      <label class="inl"><input type="checkbox" name="remove_pdf" value="1"> remove it</label></p><?php endif; ?>
    <input id="pdf" name="pdf" type="file" accept="application/pdf,.pdf" <?= isset($errors['pdf']) ? 'aria-invalid="true"' : '' ?>>
    <?php if (isset($errors['pdf'])): ?><p class="error"><?= e($errors['pdf']) ?></p><?php endif; ?></div>
  <div class="field"><label for="link_url">Link (optional)</label>
    <input id="link_url" name="link_url" type="text" inputmode="url" maxlength="500" placeholder="https://" value="<?= e($o['link_url'] ?? '') ?>" <?= isset($errors['link_url']) ? 'aria-invalid="true"' : '' ?>>
    <?php if (isset($errors['link_url'])): ?><p class="error"><?= e($errors['link_url']) ?></p><?php endif; ?></div>
  <div class="field"><label for="link_label">Words for the link (optional)</label>
    <input id="link_label" name="link_label" type="text" maxlength="160" value="<?= e($o['link_label'] ?? '') ?>">
    <?php if (isset($errors['link_label'])): ?><p class="error"><?= e($errors['link_label']) ?></p><?php endif; ?></div>
  <fieldset><legend>When it opens</legend>
    <div class="field"><label for="position">Order in the Degree</label>
      <input id="position" name="position" type="text" inputmode="numeric" value="<?= e($o['position'] ?? 10) ?>" <?= isset($errors['position']) ? 'aria-invalid="true"' : '' ?>>
      <p class="hint">Small numbers come first. Use 10, 20, 30 so you can add one in between later.</p>
      <?php if (isset($errors['position'])): ?><p class="error"><?= e($errors['position']) ?></p><?php endif; ?></div>
    <div class="field"><label for="unlock_after_days">Opens this many days after the member's Degree opened</label>
      <input id="unlock_after_days" name="unlock_after_days" type="text" inputmode="numeric" value="<?= e($o['unlock_after_days'] ?? 0) ?>" <?= isset($errors['unlock_after_days']) ? 'aria-invalid="true"' : '' ?>>
      <p class="hint">0 = open at once. 7 = one week after Part A was confirmed.</p>
      <?php if (isset($errors['unlock_after_days'])): ?><p class="error"><?= e($errors['unlock_after_days']) ?></p><?php endif; ?></div>
    <div class="check"><input id="published" name="published" type="checkbox" value="1" <?= !empty($o['published']) ? 'checked' : '' ?>>
      <label for="published">Published (members can see it). Leave empty to keep it as a draft.</label></div>
  </fieldset>
  <p class="actions"><button class="btn primary" type="submit">Save lesson</button>
    <a class="btn" href="<?= e(url('/admin/degree/' . $dn)) ?>">Cancel</a></p>
</form>
<?php if ($l): ?>
<h2>Delete this lesson</h2>
<form method="post" action="<?= e(url('/admin/lesson/' . (int)$l['id'] . '/delete')) ?>"><?= csrf_field() ?>
  <p>This removes the lesson and its PDF for good.</p>
  <div class="check"><input id="sure" type="checkbox" required><label for="sure">Yes, delete this lesson</label></div>
  <p><button class="btn danger" type="submit">Delete lesson</button></p>
</form>
<p><a href="<?= e(url('/lesson/' . (int)$l['id'])) ?>">See it as a member sees it</a></p>
<?php endif; ?>
