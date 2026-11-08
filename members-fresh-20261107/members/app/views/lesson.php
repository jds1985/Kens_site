<p><a href="<?= e(url('/degree/' . $d['num'])) ?>">← <?= (int)$d['num'] ?>° <?= e($d['title'] ?: ((int)$d['num'] === 0 ? 'Naught Degree' : 'Degree ' . $d['num'])) ?></a></p>
<h1><?= e($l['title']) ?></h1>
<?php if (!(int)$l['published']): ?><p class="notice err">Draft: members cannot see this lesson yet.</p><?php endif; ?>
<?php if ($l['body']): ?><div class="lessontext"><?= paragraphs($l['body']) ?></div><?php endif; ?>
<?php if ($l['pdf_file']): ?>
  <div class="box">
    <h2>PDF</h2>
    <p><?= e($l['pdf_name'] ?: 'lesson.pdf') ?> (<?= e(number_format(max(1, (int)round($l['pdf_size'] / 1024)))) ?> KB)</p>
    <p class="actions"><a class="btn primary" href="<?= e(url('/lesson/' . $l['id'] . '/pdf')) ?>" target="_blank" rel="noopener">Open the PDF</a>
      <a class="btn" href="<?= e(url('/lesson/' . $l['id'] . '/pdf?download=1')) ?>">Download the PDF</a></p>
  </div>
<?php endif; ?>
<?php if ($l['link_url']): ?>
  <div class="box">
    <h2>Link</h2>
    <p><a href="<?= e($l['link_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($l['link_label'] ?: $l['link_url']) ?></a>
      <span class="hint">(opens <?= e(link_host($l['link_url'])) ?> in a new tab)</span></p>
  </div>
<?php endif; ?>
<?php if (!$l['body'] && !$l['pdf_file'] && !$l['link_url']): ?><p>This lesson has no content yet.</p><?php endif; ?>
