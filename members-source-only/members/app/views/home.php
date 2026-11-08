<h1>Members of the Invisible Society</h1>
<p class="lead">This is the members area of the QaBbaLáH Dá‘aTh Institute. The <b>Naught Degree</b> is free and open to everyone. The higher Degrees are for members.</p>
<?= artwork('home') ?>
<?php if ($user): ?>
  <p><a class="btn" href="<?= e(url('/dashboard')) ?>">Go to my page</a></p>
<?php else: ?>
  <p class="actions">
    <a class="btn" href="<?= e(url('/degree/0')) ?>">Read the Naught Degree (free)</a>
    <a class="btn primary" href="<?= e(url('/register')) ?>">Sign up</a>
    <a class="btn" href="<?= e(url('/login')) ?>">Sign in</a>
  </p>
<?php endif; ?>
<?php if (degrees_soon()): ?>
<?= coming_soon_box() ?>
<p><a href="<?= e(url('/degree/0')) ?>">Read the Naught Degree (free)</a></p>
<?php else: ?>
<h2>Degrees available now</h2>
<ul class="plain">
<?php foreach ($degrees as $d): ?>
  <li><a href="<?= e(url('/degree/' . $d['num'])) ?>"><?= (int)$d['num'] ?>° <?= e($d['title'] ?: 'Degree ' . $d['num']) ?></a> <span class="tag <?= e($d['status']) ?>"><?= e(status_label($d['status'])) ?></span></li>
<?php endforeach; ?>
</ul>
<p><a href="<?= e(url('/degrees')) ?>">See the full list of all 32 Degrees and their fees</a></p>
<?php endif; ?>
