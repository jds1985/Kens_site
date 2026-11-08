<h1>Choose a new password</h1>
<?php if (!$valid): ?>
  <p class="lead">This link has expired or was already used.</p>
  <p><a class="btn" href="<?= e(url('/forgot')) ?>">Ask for a new link</a></p>
<?php else: ?>
<form method="post" action="<?= e(url('/reset')) ?>">
  <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
  <div class="field"><label for="password">New password</label>
    <input id="password" name="password" type="password" autocomplete="new-password" required <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="e-password"' : '' ?>>
    <p class="hint">At least <?= (int)cfg('min_password_length', 10) ?> characters.</p>
    <?php if (isset($errors['password'])): ?><p class="error" id="e-password"><?= e($errors['password']) ?></p><?php endif; ?></div>
  <div class="field"><label for="password2">Type it again</label>
    <input id="password2" name="password2" type="password" autocomplete="new-password" required>
    <?php if (isset($errors['password2'])): ?><p class="error"><?= e($errors['password2']) ?></p><?php endif; ?></div>
  <p><button class="btn primary" type="submit">Save my new password</button></p>
</form>
<?php endif; ?>
