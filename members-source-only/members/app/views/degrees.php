<h1>The Degrees</h1>
<p class="lead">Part A is the dues, paid to the Invisible Society. Part B is the tuition, paid to a Member Facilitator. The Naught Degree (0°) is free.</p>
<p>Status: <span class="tag released">Released</span> open to members · <span class="tag available">Available</span> announced · <span class="tag planned">Planned</span> not yet open.</p>
<div class="scroll">
<table>
  <caption class="sr">All Degrees with status and fees</caption>
  <thead><tr><th scope="col">Degree</th><th scope="col">Title</th><th scope="col">Status</th><th scope="col" class="num">Part A (dues)</th><th scope="col" class="num">Part B (tuition)</th></tr></thead>
  <tbody>
  <?php foreach ($degrees as $d): ?>
    <tr>
      <th scope="row"><a href="<?= e(url('/degree/' . $d['num'])) ?>"><?= (int)$d['num'] ?>°</a></th>
      <td><?= e($d['title'] ?: ((int)$d['num'] === 0 ? 'Naught Degree' : '—')) ?></td>
      <td><span class="tag <?= e($d['status']) ?>"><?= e(status_label($d['status'])) ?></span></td>
      <td class="num"><?= (int)$d['num'] === 0 ? 'Free' : e(money($d['part_a_fee'])) ?></td>
      <td class="num"><?= (int)$d['num'] === 0 ? 'Free' : e(money($d['part_b_fee'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
