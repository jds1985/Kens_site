<?php /* Piece 3: Part B on the Degree page. Vars: $pb, $d, $n. The site never takes this money. */
$fee = money($d['part_b_fee']); $p = $pb['payee']; ?>
<section class="box next" id="partb" aria-labelledby="partb-h">
  <h2 id="partb-h">Part B (tuition): <?= e($fee) ?></h2>
  <p>Part B is paid <b>straight to a Member Facilitator</b>, not through this site. When it is confirmed, the next Degree can open<?= (int)$d['wait_days'] > 0 ? ' (after the waiting time of ' . (int)$d['wait_days'] . ' days)' : '' ?>.</p>

  <?php if ($pb['claimed']): ?>
    <h3>Step 3 of 3: waiting for <?= e($p['who']) ?> to confirm</h3>
    <p role="status">You said you paid <?= e($fee) ?> to <b><?= e($p['who']) ?></b> on <?= e(fmt_date($pb['row']['part_b_claimed_at'])) ?>.
      <?php if ($pb['row']['part_b_ref']): ?>Receipt number: <b><?= e($pb['row']['part_b_ref']) ?></b>.<?php endif; ?>
      When they confirm, you get an email. If it takes long, Ken can confirm it too.</p>
    <form method="post" action="<?= e(url('/degree/' . $n . '/bunclaim')) ?>"><?= csrf_field() ?>
      <p><button class="btn" type="submit">I made a mistake, withdraw this note</button></p></form>

  <?php elseif ($pb['chosen']): ?>
    <h3>Step 2 of 3: pay <?= e($p['who']) ?></h3>
    <?php if ($p['link']): ?>
      <p><a class="btn primary" href="<?= e($p['link']) ?>" target="_blank" rel="noopener noreferrer">Pay <?= e($fee) ?> to <?= e($p['who']) ?> with PayPal</a> <span class="hint">(opens PayPal in a new tab; the money goes to <?= e($p['who']) ?>, not to this site)</span></p>
    <?php endif; ?>
    <?php if ($p['note']): ?>
      <div class="paynote"><p><b><?= $p['link'] ? 'Note from ' . e($p['who']) : 'How to pay ' . e($p['who']) ?>:</b></p><?= paragraphs($p['note']) ?></div>
    <?php endif; ?>
    <?php if (!$p['link'] && !$p['note']): ?>
      <p class="notice err">How to pay <?= e($p['who']) ?> is not set up yet. Please choose someone else, or ask Ken.</p>
    <?php endif; ?>
    <p>Please write this in the payment note: <b class="ref"><?= e($pb['ref']) ?></b></p>
    <h3>Step 3 of 3: tell us you paid</h3>
    <form method="post" action="<?= e(url('/degree/' . $n . '/bclaim')) ?>"><?= csrf_field() ?>
      <div class="field"><label for="bref">Receipt or transaction number (if you have it)</label>
        <input id="bref" name="ref" type="text" maxlength="64" autocomplete="off" aria-describedby="h-bref"><p class="hint" id="h-bref">Optional. It helps <?= e($p['who']) ?> find your payment.</p></div>
      <p><button class="btn primary" type="submit">I have paid Part B (<?= e($fee) ?>) to <?= e($p['who']) ?></button></p>
    </form>
    <details><summary>Choose someone else</summary><?php $again = true; require __DIR__ . '/choose.php'; ?></details>

  <?php else: ?>
    <h3>Step 1 of 3: choose who you pay</h3>
    <?php $again = false; require __DIR__ . '/choose.php'; ?>
  <?php endif; ?>
</section>
