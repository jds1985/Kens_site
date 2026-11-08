<?php require __DIR__ . '/_nav.php'; ?>
<h1>Degrees and lessons</h1>
<p>Press a Degree to change its title, status and text, and to add lessons.</p>
<div class="scroll"><table class="stack">
  <thead><tr><th scope="col">Degree</th><th scope="col">Title</th><th scope="col">Status</th><th scope="col">Lessons (published / all)</th><th scope="col">Edit</th></tr></thead>
  <tbody>
  <?php foreach ($degrees as $d): $n = (int)$d['num']; $c = $cnt[$n] ?? null; ?>
    <tr>
      <th scope="row" data-label="Degree"><?= $n ?>°</th>
      <td data-label="Title"><?= e($d['title'] ?: ($n === 0 ? 'Naught Degree' : '—')) ?></td>
      <td data-label="Status"><span class="tag <?= e($d['status']) ?>"><?= e(status_label($d['status'])) ?></span></td>
      <td data-label="Lessons"><?= $c ? (int)$c['pub'] . ' / ' . (int)$c['c'] : '0 / 0' ?></td>
      <td data-label="Edit"><a class="btn" href="<?= e(url('/admin/degree/' . $n)) ?>">Edit <?= $n ?>°</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
