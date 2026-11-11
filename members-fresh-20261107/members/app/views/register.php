<h1>Sign up</h1>
<p class="lead">Please give your legal first and last name (kept private), a screen name or alias that other members see, your email (this is your username), and a password.</p>
<?php if ($errors): ?>
  <div class="notice err" role="alert"><b>Please fix the <?= count($errors) ?> item<?= count($errors) > 1 ? 's' : '' ?> marked below.</b></div>
<?php endif; ?>
<form method="post" action="<?= e(url('/register')) ?>" novalidate>
  <?= csrf_field() ?>
  <fieldset>
    <legend>Legal Name</legend>
  <div class="field">
    <label for="first_name">First Name</label>
    <input id="first_name" name="first_name" type="text" maxlength="60" autocomplete="given-name" required value="<?= e($old['first_name'] ?? '') ?>" <?= isset($errors['first_name']) ? 'aria-invalid="true" aria-describedby="e-first_name"' : '' ?>>
    <?php if (isset($errors['first_name'])): ?><p class="error" id="e-first_name"><?= e($errors['first_name']) ?></p><?php endif; ?>
  </div>
  <div class="field">
    <label for="last_name">Last Name</label>
    <input id="last_name" name="last_name" type="text" maxlength="60" autocomplete="family-name" required value="<?= e($old['last_name'] ?? '') ?>" <?= isset($errors['last_name']) ? 'aria-invalid="true" aria-describedby="e-last_name"' : '' ?>>
    <p class="hint">Your legal name is kept private. Other members do not see it. It is not used to make your Member ID.</p>
    <?php if (isset($errors['last_name'])): ?><p class="error" id="e-last_name"><?= e($errors['last_name']) ?></p><?php endif; ?>
  </div>
  </fieldset>
  <div class="field">
    <label for="pseudonym">Screen Name or Alias</label>
    <input id="pseudonym" name="pseudonym" type="text" maxlength="40" autocomplete="nickname" required value="<?= e($old['pseudonym'] ?? '') ?>" <?= isset($errors['pseudonym']) ? 'aria-invalid="true" aria-describedby="e-pseudonym"' : '' ?>>
    <?php if (isset($errors['pseudonym'])): ?><p class="error" id="e-pseudonym"><?= e($errors['pseudonym']) ?></p><?php endif; ?>
  </div>
  <div class="field">
    <label for="email">Email (this will be your Username)</label>
    <input id="email" name="email" type="email" maxlength="190" autocomplete="email" required value="<?= e($old['email'] ?? '') ?>" <?= isset($errors['email']) ? 'aria-invalid="true" aria-describedby="e-email"' : '' ?>>
    <p class="hint">You sign in with this email. It is also used to reset your password.</p>
    <?php if (isset($errors['email'])): ?><p class="error" id="e-email"><?= e($errors['email']) ?></p><?php endif; ?>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="new-password" required <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="e-password"' : 'aria-describedby="h-password"' ?>>
    <p class="hint" id="h-password">At least <?= (int)cfg('min_password_length', 10) ?> characters. A few words in a row work well.</p>
    <?php if (isset($errors['password'])): ?><p class="error" id="e-password"><?= e($errors['password']) ?></p><?php endif; ?>
  </div>
  <div class="field">
    <label for="password2">Type the password again</label>
    <input id="password2" name="password2" type="password" autocomplete="new-password" required <?= isset($errors['password2']) ? 'aria-invalid="true" aria-describedby="e-password2"' : '' ?>>
    <?php if (isset($errors['password2'])): ?><p class="error" id="e-password2"><?= e($errors['password2']) ?></p><?php endif; ?>
  </div>
  <fieldset>
    <legend>Pledge of Word and Name</legend>
    <pre class="pledge" tabindex="0" role="region" aria-label="Text of the Pledge"><?= pledge_html($pledge) ?></pre>
    <div class="check">
      <input id="pledge" name="pledge" type="checkbox" value="1" <?= isset($errors['pledge']) ? 'aria-invalid="true" aria-describedby="e-pledge"' : '' ?>>
      <label for="pledge">I give my Pledge of Word and Name, and I agree to keep the teachings private.</label>
    </div>
    <?php if (isset($errors['pledge'])): ?><p class="error" id="e-pledge"><?= e($errors['pledge']) ?></p><?php endif; ?>
    <div class="field">
      <label for="pledge_name">Type your name as your signature</label>
      <input id="pledge_name" name="pledge_name" type="text" maxlength="120" autocomplete="off" value="<?= e($old['pledge_name'] ?? '') ?>" <?= isset($errors['pledge_name']) ? 'aria-invalid="true" aria-describedby="e-pledge_name"' : '' ?>>
      <?php if (isset($errors['pledge_name'])): ?><p class="error" id="e-pledge_name"><?= e($errors['pledge_name']) ?></p><?php endif; ?>
    </div>
  </fieldset>
  <p><button class="btn primary" type="submit">Create my account</button></p>
</form>
<p>Already a member? <a href="<?= e(url('/login')) ?>">Sign in</a></p>
