<h1>My account</h1>
<ul>
  <li>Name: <b><?= e($user['pseudonym']) ?></b></li>
  <li>Email: <b><?= e($user['email']) ?></b></li>
  <li>Pledge given: <b><?= e(substr($user['pledged_at'], 0, 10)) ?></b></li>
</ul>
<h2>Change my password</h2>
<form method="post" action="<?= e(url('/account')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label for="current">Current password</label>
    <input id="current" name="current" type="password" autocomplete="current-password" required>
    <?php if (isset($errors['current'])): ?><p class="error"><?= e($errors['current']) ?></p><?php endif; ?></div>
  <div class="field"><label for="password">New password</label>
    <input id="password" name="password" type="password" autocomplete="new-password" required>
    <?php if (isset($errors['password'])): ?><p class="error"><?= e($errors['password']) ?></p><?php endif; ?></div>
  <div class="field"><label for="password2">Type the new password again</label>
    <input id="password2" name="password2" type="password" autocomplete="new-password" required>
    <?php if (isset($errors['password2'])): ?><p class="error"><?= e($errors['password2']) ?></p><?php endif; ?></div>
  <p><button class="btn primary" type="submit">Change password</button></p>
</form>
