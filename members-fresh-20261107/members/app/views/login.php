<h1>Sign in</h1>
<?php if ($error): ?><div class="notice err" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/login')) ?>">
  <?= csrf_field() ?>
  <div class="field">
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" autocomplete="username" required value="<?= e($old['email'] ?? '') ?>">
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
  </div>
  <p><button class="btn primary" type="submit">Sign in</button></p>
</form>
<p><a href="<?= e(url('/forgot')) ?>">Forgot your password?</a> · <a href="<?= e(url('/register')) ?>">Sign up</a></p>
<?php if ($error && strpos($error, 'not confirmed') !== false): ?>
<div class="box">
  <h2>Send a new confirmation link</h2>
  <form method="post" action="<?= e(url('/resend')) ?>">
    <?= csrf_field() ?>
    <div class="field"><label for="email2">Email address</label>
    <input id="email2" name="email" type="email" required value="<?= e($old['email'] ?? '') ?>"></div>
    <p><button class="btn" type="submit">Send the link again</button></p>
  </form>
</div>
<?php endif; ?>
