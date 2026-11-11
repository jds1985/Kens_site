<?php $n = (int)$d['num']; $state = $st['state']; $name = $d['title'] ?: ($n === 0 ? 'Naught Degree' : 'Degree ' . $n); ?>
<h1><?= $n ?>° <?= e($name) ?></h1>
<?= artwork('degree-' . $n, 'degree') ?>
<p><span class="tag <?= e($d['status']) ?>"><?= e(status_label($d['status'])) ?></span>
<?php if ($n === 0): ?> <span class="tag released">Open to everyone</span><?php endif; ?>
<?php if ($user && $n > 0): [$lab] = state_info($st); ?> <span class="tag state"><?= e($lab) ?></span><?php endif; ?></p>
<?php if ($d['summary'] && !$mayRead): ?><p class="lead"><?= e($d['summary']) ?></p><?php endif; ?>

<?php if ($isAdmin): ?>
  <p class="notice ok">You are the site owner: you can read everything here, drafts included. <a href="<?= e(url('/admin/degree/' . $n)) ?>">Edit this Degree</a></p>
<?php endif; ?>

<?php if ($mayRead): ?>
  <?php if ($d['summary']): ?><p class="lead"><?= e($d['summary']) ?></p><?php endif; ?>
  <?= $d['intro'] ? paragraphs($d['intro']) : ($n === 0 ? '<p>Text for this Degree will be added soon.</p>' : '') ?>
  <?php if ($n === 0 && !$user): ?>
    <p class="actions"><a class="btn primary" href="<?= e(url('/register')) ?>">Sign up to continue</a> <a class="btn" href="<?= e(url('/login')) ?>">Sign in</a></p>
  <?php endif; ?>

  <h2>Lessons</h2>
  <?php if (!$lessons): ?>
    <p>No lessons have been added to this Degree yet.</p>
  <?php else: ?>
    <ol class="lessons">
    <?php foreach ($lessons as $l): $a = $l['access']; ?>
      <li class="<?= $a['ok'] ? 'ok' : 'wait' ?>">
        <?php if ($a['ok']): ?>
          <a href="<?= e(url('/lesson/' . $l['id'])) ?>"><?= e($l['title']) ?></a>
          <span class="kinds">
            <?php if ($l['body']): ?><span class="tag">Text</span> <?php endif; ?>
            <?php if ($l['pdf_file']): ?><span class="tag">PDF</span> <?php endif; ?>
            <?php if ($l['link_url']): ?><span class="tag">Link</span> <?php endif; ?>
            <?php if (!(int)$l['published']): ?><span class="tag planned">Draft, only you see it</span><?php endif; ?>
          </span>
        <?php else: ?>
          <b><?= e($l['title']) ?></b>
          <span class="tag planned">⏳ Opens <?= e(fmt_ts((int)($a['until'] ?? time()))) ?></span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <?php if ($n >= 1 && $state === 'open' && $pb): ?>
    <?php require __DIR__ . '/fac/part_b.php'; ?>
  <?php elseif ($n >= 1 && $state === 'complete'): ?>
    <div class="box" id="partb"><h2>Part B</h2><p>✓ Part B of <?= $n ?>° is confirmed (<?= e(fmt_date($st['row']['part_b_confirmed_at'])) ?>).</p></div>
  <?php endif; ?>

<?php else: ?>
  <?php if ($state === 'ready'): ?>
    <div class="box next">
      <h2>Ready to open this Degree</h2>
      <p class="fee-ref">Part A is the dues. The amount is in the <?= curriculum_fees_link() ?> on the main site.</p>
      <?php require __DIR__ . '/_pay_privacy.php'; ?>
      <ol class="stepslist">
        <li><b>Pay with PayPal.</b>
          <?php if ($paypal): ?>
            <br><a class="btn primary" href="<?= e($paypal) ?>" target="_blank" rel="noopener noreferrer">Pay Part A with PayPal</a>
            <span class="hint">(opens PayPal in a new tab)</span>
          <?php else: ?>
            <br><span class="notice err">Ken's PayPal link is not set up yet (site owner: see <code>paypal</code> in the config file).</span>
          <?php endif; ?>
          <br>In the PayPal note, please write: <b class="ref"><?= e($ref) ?></b>
        </li>
        <li><b>Come back here and tell us you paid.</b> Ken checks his PayPal and confirms. You will get an email when the Degree opens.
          <form method="post" action="<?= e(url('/degree/' . $n . '/claim')) ?>">
            <?= csrf_field() ?>
            <div class="field"><label for="ref">PayPal receipt or transaction number (if you have it)</label>
              <input id="ref" name="ref" type="text" maxlength="64" autocomplete="off" aria-describedby="h-ref"><p class="hint" id="h-ref">Optional. It helps Ken find your payment faster.</p></div>
            <p><button class="btn primary" type="submit">I have paid Part A</button></p>
          </form>
        </li>
      </ol>
      <p class="hint">Part B, the tuition, comes later, after you have studied this Degree. That amount is in the same Curriculum section.</p>
    </div>
  <?php elseif ($state === 'claimed'): ?>
    <div class="box next">
      <h2>Waiting for Ken to confirm</h2>
      <p>You said you paid Part A on <?= e(fmt_date($st['row']['part_a_claimed_at'])) ?>.
        <?php if ($st['row']['part_a_ref']): ?>Receipt number: <b><?= e($st['row']['part_a_ref']) ?></b>.<?php endif; ?>
        Ken will check his PayPal and confirm. You will get an email, and the lessons will open here.</p>
      <form method="post" action="<?= e(url('/degree/' . $n . '/unclaim')) ?>"><?= csrf_field() ?>
        <p><button class="btn" type="submit">I made a mistake, withdraw this note</button></p></form>
    </div>
  <?php else: ?>
    <div class="box">
      <h2>Locked</h2>
      <?php if ($state === 'waiting'): ?>
        <p>Waiting time. Part B of <?= $n - 1 ?>° is confirmed. This Degree opens for Part A on <b><?= e(fmt_ts((int)$st['until'])) ?></b>.</p>
      <?php elseif ($state === 'prevb'): ?>
        <p>This Degree opens after Part B of <a href="<?= e(url('/degree/' . ($n - 1))) ?>"><?= $n - 1 ?>°</a> is confirmed.</p>
      <?php elseif ($state === 'notyet'): ?>
        <p>This Degree has not been released yet. You will be able to open it here when it is.</p>
      <?php elseif (!$user): ?>
        <p>This Degree is for members. <a href="<?= e(url('/register')) ?>">Sign up</a> or <a href="<?= e(url('/login')) ?>">sign in</a>, then work through the Degrees in order.</p>
      <?php endif; ?>
      <p>How it works: each Degree has Part A (dues, paid through PayPal) and Part B (tuition, paid to a Member Facilitator). The next Degree opens only after Part B of the one before is confirmed and the waiting time is over.</p>
      <p class="fee-ref">The dues and fee schedule is in the <?= curriculum_fees_link() ?>.</p>
      <ul>
        <li>Waiting time after this Degree: <b><?= (int)$d['wait_days'] ?> days</b></li>
      </ul>
    </div>
  <?php endif; ?>
<?php endif; ?>
<p><a href="<?= e(url('/degrees')) ?>">← All Degrees</a></p>
