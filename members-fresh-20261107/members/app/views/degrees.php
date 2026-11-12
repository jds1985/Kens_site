<h1>The Degrees</h1>
<p class="lead">Part A is the dues, paid to the Invisible Society. Part B is the tuition, paid to a Member Facilitator. The dues and fee schedule is in the <?= curriculum_fees_link() ?> on the main site.</p>
<p>Status: <span class="tag released">Released</span> open to members · <span class="tag available">Available</span> announced · <span class="tag planned">Planned</span> not yet open.</p>
<div class="scroll">
<table>
  <caption class="sr">All Degrees</caption>
  <thead><tr><th scope="col">Degree</th><th scope="col">Title</th><th scope="col">Status</th></tr></thead>
  <tbody>
  <?php foreach ($degrees as $d): ?>
    <tr>
      <th scope="row"><a href="<?= e(url('/degree/' . $d['num'])) ?>"><?= (int)$d['num'] ?>°</a></th>
      <td><?= e($d['title'] ?: ((int)$d['num'] === 0 ? 'Naught Degree' : '—')) ?></td>
      <td><span class="tag <?= e($d['status']) ?>"><?= e(status_label($d['status'])) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
