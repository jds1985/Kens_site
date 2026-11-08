<h1>Member Facilitator</h1>
<?php if (!$f && !$may): ?>
  <p class="lead">Members who have completed <?= fac_min_degree() ?>° can become Member Facilitators. A Facilitator receives Part B (tuition) from other members, straight into their own PayPal or another way they choose, and confirms it here.</p>
  <p>You have completed <b><?= (int)$done ?>°</b> so far.</p>
<?php else: ?>
  <?php if ($f): $lab = ['applied' => '⏳ Waiting for Ken to approve', 'approved' => '✓ Approved: members can choose you', 'paused' => '‖ Paused: new members do not see you, but you can still confirm your members', 'removed' => '✕ Removed by Ken'][$f['status']]; ?>
    <p class="notice ok" role="status">Your status: <b><?= e($lab) ?></b></p>
  <?php else: ?>
    <p class="lead">You have completed <?= (int)$done ?>°, so you can ask Ken to make you a Member Facilitator. Members will pay Part B <b>straight to you</b>; this site never handles that money. You then confirm here that it arrived.</p>
  <?php endif; ?>

  <?php if ($f): ?>
  <h2>Members who chose you</h2>
  <?php if (!$members): ?><p>Nobody yet.</p><?php else: ?>
  <div class="scroll"><table class="stack">
    <thead><tr><th scope="col">Member</th><th scope="col">Degree</th><th scope="col" class="num">Part B</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
    <tbody>
    <?php foreach ($members as $m): ?>
      <tr>
        <th scope="row" data-label="Member"><?= e($m['pseudonym']) ?></th>
        <td data-label="Degree"><?= (int)$m['degree_num'] ?>°</td>
        <td class="num" data-label="Part B"><?= e(money($m['part_b_fee'])) ?></td>
        <td data-label="Status"><?php if ($m['part_b_status'] === 'confirmed'): ?>✓ Confirmed <?= e(fmt_date($m['part_b_confirmed_at'])) ?>
          <?php elseif ($m['part_b_claimed_at']): ?>⏳ Says paid <?= e(fmt_date($m['part_b_claimed_at'])) ?><br><small>Reference <?= e(part_b_reference((int)$m['user_id'], (int)$m['degree_num'])) ?><?= $m['part_b_ref'] ? ', receipt ' . e($m['part_b_ref']) : '' ?></small>
          <?php else: ?>Chose you, not paid yet<?php endif; ?></td>
        <td data-label="Action" class="acts">
          <?php if ($m['part_b_status'] !== 'confirmed' && $f['status'] !== 'removed'): ?>
            <form method="post" action="<?= e(url('/facilitator/confirm')) ?>"><?= csrf_field() ?>
              <input type="hidden" name="member" value="<?= (int)$m['user_id'] ?>"><input type="hidden" name="degree" value="<?= (int)$m['degree_num'] ?>"><input type="hidden" name="do" value="confirm">
              <button class="btn primary" type="submit" aria-label="I received Part B from <?= e($m['pseudonym']) ?> for <?= (int)$m['degree_num'] ?>°: confirm" data-confirm="Did the money really arrive? Confirming opens the next Degree for <?= e($m['pseudonym']) ?>.">I received it: confirm</button></form>
            <?php if ($m['part_b_claimed_at']): ?>
            <form method="post" action="<?= e(url('/facilitator/confirm')) ?>"><?= csrf_field() ?>
              <input type="hidden" name="member" value="<?= (int)$m['user_id'] ?>"><input type="hidden" name="degree" value="<?= (int)$m['degree_num'] ?>"><input type="hidden" name="do" value="notyet">
              <button class="btn" type="submit" aria-label="Not received yet from <?= e($m['pseudonym']) ?> for <?= (int)$m['degree_num'] ?>°">Not received yet</button></form>
            <?php endif; ?>
          <?php else: ?>—<?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (!$f || $f['status'] !== 'removed'): ?>
  <h2><?= $f ? 'How members pay you' : 'Ask to be a Facilitator' ?></h2>
  <form method="post" action="<?= e(url('/facilitator')) ?>" novalidate><?= csrf_field() ?>
    <fieldset class="choices"><legend>How do members pay you Part B?</legend>
      <div class="choice"><input type="radio" id="pm-paypal" name="pay_method" value="paypal" <?= ($old['pay_method'] ?? 'paypal') === 'paypal' ? 'checked' : '' ?>><label for="pm-paypal">My own PayPal</label></div>
      <div class="choice"><input type="radio" id="pm-other" name="pay_method" value="other" <?= ($old['pay_method'] ?? '') === 'other' ? 'checked' : '' ?>><label for="pm-other">Another way (I describe it below)</label></div>
      <?php if (isset($errors['pay_method'])): ?><p class="error"><?= e($errors['pay_method']) ?></p><?php endif; ?>
    </fieldset>
    <div class="field"><label for="paypal_me">Your PayPal.Me name (the part after paypal.me/)</label>
      <input id="paypal_me" name="paypal_me" type="text" maxlength="200" autocomplete="off" value="<?= e($old['paypal_me'] ?? '') ?>" aria-describedby="h-pm<?= isset($errors['paypal_me']) ? ' e-pm' : '' ?>" <?= isset($errors['paypal_me']) ? 'aria-invalid="true"' : '' ?>>
      <p class="hint" id="h-pm">Only if you chose PayPal. Example: for paypal.me/JaneDoe type JaneDoe.</p>
      <?php if (isset($errors['paypal_me'])): ?><p class="error" id="e-pm"><?= e($errors['paypal_me']) ?></p><?php endif; ?></div>
    <div class="field"><label for="pay_note">How to pay you, or a note for members</label>
      <textarea id="pay_note" name="pay_note" rows="4" maxlength="500" aria-describedby="h-pn<?= isset($errors['pay_note']) ? ' e-pn' : '' ?>" <?= isset($errors['pay_note']) ? 'aria-invalid="true"' : '' ?>><?= e($old['pay_note'] ?? '') ?></textarea>
      <p class="hint" id="h-pn">Needed if you chose "another way". Members see this after they choose you. 500 characters at most.</p>
      <?php if (isset($errors['pay_note'])): ?><p class="error" id="e-pn"><?= e($errors['pay_note']) ?></p><?php endif; ?></div>
    <div class="field"><label for="intro">A few words about you (members see this in the list)</label>
      <textarea id="intro" name="intro" rows="3" maxlength="500"><?= e($old['intro'] ?? '') ?></textarea>
      <?php if (isset($errors['intro'])): ?><p class="error"><?= e($errors['intro']) ?></p><?php endif; ?></div>
    <?php if ($f && $f['status'] === 'approved'): ?><p class="hint">If you change how you are paid, Ken approves it again before members see you.</p><?php endif; ?>
    <p><button class="btn primary" type="submit"><?= $f ? 'Save' : 'Ask Ken to approve me' ?></button></p>
  </form>
  <?php endif; ?>
<?php endif; ?>
