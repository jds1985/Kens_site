<h1>Welcome, <?= e($user['pseudonym']) ?></h1>
<?php if (!empty($user['member_id'])): ?><p class="lead">Your Member ID: <b><?= e($user['member_id']) ?></b> · Username: <b><?= e($user['email']) ?></b></p><?php endif; ?>
<?php if (degrees_soon()): ?>
<?= artwork('dashboard') ?>
<?= coming_soon_box() ?>
<p class="actions"><a class="btn" href="<?= e(url('/degree/0')) ?>">Read the Naught Degree</a> <a class="btn" href="<?= e(url('/account')) ?>">My account</a></p>
<?php return; endif; ?>
<?= artwork('dashboard') ?>
<?php if ($admin): ?><p><a class="btn" href="<?= e(url('/admin')) ?>">Open the admin page (Ken)</a></p><?php endif; ?>
<?php $myF = fac_row((int)$user['id']); if ($myF && $myF['status'] !== 'removed'):
  $waitingFor = (int)(q_one("SELECT COUNT(*) AS c FROM progress WHERE part_b_facilitator_id = ? AND part_b_status = 'none' AND part_b_claimed_at IS NOT NULL", [$user['id']])['c'] ?? 0); ?>
  <p class="notice ok">You are a Member Facilitator<?= $myF['status'] === 'applied' ? ' (waiting for Ken to approve)' : '' ?>. <?= $waitingFor ? '<b>' . $waitingFor . ' member(s) say they paid you Part B.</b> ' : '' ?><a href="<?= e(url('/facilitator')) ?>">Open my Facilitator page</a></p>
<?php elseif (!$myF && may_apply_facilitator($user)): ?>
  <p class="notice ok">You have completed <?= fac_min_degree() ?>°: you can now <a href="<?= e(url('/facilitator')) ?>">ask to be a Member Facilitator</a>.</p>
<?php endif; ?>
<div class="box next">
  <h2>Your next step</h2>
  <?php if ($next === null): ?>
    <p>You have completed every Degree. Thank you.</p>
  <?php else: [$nn, $ns] = $next; $nd = $rows[$nn]['d']; $nt = ($nd['title'] ?: 'Degree ' . $nn); ?>
    <?php switch ($ns['state']):
      case 'open': ?>
        <p>You are studying <b><?= $nn ?>° <?= e($nt) ?></b>. Part A is paid.</p>
        <?php $br = $ns['row']; ?>
        <p>Part B, the tuition, is not confirmed yet<?= $br['part_b_claimed_at'] ? ': you said you paid, waiting for the confirmation.' : ($br['part_b_chosen_at'] ? ': you chose who to pay; next, pay and press "I have paid".' : ': first choose a Facilitator to pay.') ?> The amount is in the <?= curriculum_fees_link() ?>. The next Degree stays locked until it is confirmed.</p>
        <p><a class="btn primary" href="<?= e(url('/degree/' . $nn)) ?>">Go to the lessons of <?= $nn ?>°</a> <a class="btn" href="<?= e(url('/degree/' . $nn . '#partb')) ?>">Part B of <?= $nn ?>°</a></p>
      <?php break; case 'claimed': ?>
        <p>You told us you paid Part A for <b><?= $nn ?>°</b>. Ken will check and confirm it. The dues are in the <?= curriculum_fees_link() ?>.</p>
        <p><a class="btn" href="<?= e(url('/degree/' . $nn)) ?>">See the details</a></p>
      <?php break; case 'ready': ?>
        <p><b><?= $nn ?>° <?= e($nt) ?></b> is ready for you. The Part A dues are in the <?= curriculum_fees_link() ?>.</p>
        <p><a class="btn primary" href="<?= e(url('/degree/' . $nn)) ?>">Pay Part A for <?= $nn ?>°</a></p>
      <?php break; case 'waiting': ?>
        <p>Part B of <?= $nn - 1 ?>° is done. Waiting time before <b><?= $nn ?>°</b>: it opens on <b><?= e(fmt_ts((int)$ns['until'])) ?></b>.</p>
      <?php break; case 'prevb': ?>
        <p>The next Degree is <b><?= $nn ?>°</b>. It opens after Part B of <?= $nn - 1 ?>° is confirmed.</p>
        <p><a class="btn" href="<?= e(url('/degree/' . ($nn - 1))) ?>">Go to <?= $nn - 1 ?>°</a></p>
      <?php break; default: ?>
        <p>The next Degree, <b><?= $nn ?>°</b>, has not been released yet. You are on the Naught Degree for now.</p>
        <p><a class="btn primary" href="<?= e(url('/degree/0')) ?>">Read the Naught Degree</a></p>
    <?php endswitch; ?>
  <?php endif; ?>
</div>
<h2>Your Degrees</h2>
<ul class="steps">
<?php foreach ($rows as $n => $r): $d = $r['d']; [$lab, $cls] = state_info($r['st']); ?>
  <li class="<?= e($cls) ?>">
    <span class="n"><?= $n ?>°</span>
    <span class="t"><a href="<?= e(url('/degree/' . $n)) ?>"><?= e($d['title'] ?: ($n === 0 ? 'Naught Degree' : 'Degree ' . $n)) ?></a>
      <span class="tag <?= e($d['status']) ?>"><?= e(status_label($d['status'])) ?></span>
      <?php if ((int)$user['current_degree'] === $n): ?><span class="tag here">◀ You are here</span><?php endif; ?></span>
    <span class="s"><?= e($lab) ?></span>
  </li>
<?php endforeach; ?>
</ul>
