<?php /* The list of Facilitators eligible for this Degree. Vars: $pb, $n, $d, $again. */ ?>
<?php if (!$pb['elig'] && !$pb['ken']): ?>
  <p class="notice err">No Facilitator can take Part B of <?= $n ?>° yet. Ken has been told; please check back soon.</p>
<?php else: ?>
<form method="post" action="<?= e(url('/degree/' . $n . '/facilitator')) ?>"><?= csrf_field() ?>
  <fieldset class="choices">
    <legend><?= count($pb['elig']) + ($pb['ken'] ? 1 : 0) ?> <?= count($pb['elig']) + ($pb['ken'] ? 1 : 0) === 1 ? 'person can' : 'people can' ?> take Part B of <?= $n ?>°. Choose one:</legend>
    <?php if ($pb['ken']): $id = 'fac-ken' . ($again ? '-2' : ''); ?>
      <div class="choice"><input type="radio" name="facilitator" value="ken" id="<?= $id ?>" <?= $pb['chosen'] && $pb['fid'] === null ? 'checked' : '' ?> aria-describedby="<?= $id ?>-d">
        <label for="<?= $id ?>"><b><?= e(cfg('facilitators.ken_name', 'Ken (the Invisible Society)')) ?></b></label>
        <p class="hint" id="<?= $id ?>-d"><?= $n <= fac_ken_up_to() ? 'For the first Degrees, Ken receives Part B himself.' : 'There is no other Facilitator for this Degree yet.' ?> Paid with PayPal.</p></div>
    <?php endif; ?>
    <?php foreach ($pb['elig'] as $f): $id = 'fac-' . (int)$f['user_id'] . ($again ? '-2' : ''); ?>
      <div class="choice"><input type="radio" name="facilitator" value="<?= (int)$f['user_id'] ?>" id="<?= $id ?>" <?= $pb['fid'] === (int)$f['user_id'] ? 'checked' : '' ?> aria-describedby="<?= $id ?>-d">
        <label for="<?= $id ?>"><b><?= e($f['pseudonym']) ?></b>, Member Facilitator (completed <?= (int)$f['done'] ?>°)</label>
        <p class="hint" id="<?= $id ?>-d"><?= $f['intro'] ? e($f['intro']) . ' · ' : '' ?>Paid with <?= $f['pay_method'] === 'paypal' ? 'PayPal' : 'another way (shown after you choose)' ?>.</p></div>
    <?php endforeach; ?>
  </fieldset>
  <p><button class="btn<?= $again ? '' : ' primary' ?>" type="submit"><?= $again ? 'Change my choice' : 'Choose this person' ?></button></p>
</form>
<?php endif; ?>
