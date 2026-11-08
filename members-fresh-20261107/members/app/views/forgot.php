<h1>Forgot your password?</h1>
<p class="lead">Type your email address. If it has an account, we will send a link to choose a new password.</p>
<form method="post" action="<?= e(url('/forgot')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label for="email">Email address</label>
  <input id="email" name="email" type="email" autocomplete="email" required></div>
  <p><button class="btn primary" type="submit">Send me the link</button></p>
</form>
