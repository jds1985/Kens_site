<?php
// Rendered only by admin pages. signup_counts() calls require_admin() before this view is reached.
?>
<section class="signup-count" aria-label="Sign-up count">
  <p class="signup-total">Total sign-ups: <strong><?= (int)$signups['total'] ?></strong></p>
  <p class="signup-line">Email confirmed: <strong><?= (int)$signups['confirmed'] ?></strong></p>
  <p class="signup-line">Email not confirmed yet: <strong><?= (int)$signups['unconfirmed'] ?></strong></p>
  <p class="signup-line">Signed up in the last 7 days: <strong><?= (int)$signups['recent'] ?></strong></p>
  <p class="signup-note">This counts every account, including the administrator. There is no separate approval step for a new member. Email confirmation is the only waiting state.</p>
</section>
