<h1>Confirm your email</h1>
<?php if ($valid): ?>
  <p class="lead">One more step. Press the button to confirm your email address.</p>
  <form method="post" action="<?= e(url('/verify')) ?>"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
    <p><button class="btn primary" type="submit">Confirm my email</button></p></form>
<?php else: ?>
  <p class="lead">This link has expired or was already used.</p>
  <p><a class="btn" href="<?= e(url('/login')) ?>">Go to sign in</a></p>
<?php endif; ?>
