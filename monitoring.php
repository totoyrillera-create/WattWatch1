<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_permission('view_dashboard');
$active_page = 'monitoring';

$equipmentList = $pdo->query(
    "SELECT e.equipment_id, e.equipment_name, r.room_name FROM equipment e
     JOIN rooms r ON r.room_id = e.room_id WHERE e.status='active' ORDER BY r.room_name"
)->fetchAll();

$selectedId = (int) ($_GET['equipment_id'] ?? ($equipmentList[0]['equipment_id'] ?? 0));

$latestReadings = [];
if ($selectedId) {
    $stmt = $pdo->prepare(
        "SELECT * FROM readings WHERE equipment_id = ? ORDER BY recorded_at DESC LIMIT 25"
    );
    $stmt->execute([$selectedId]);
    $latestReadings = $stmt->fetchAll();
}

$page_title = 'Real-time Monitoring';
$page_subtitle = 'Live voltage, current, and power readings per equipment';
require __DIR__ . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head">
    <h2>Select equipment</h2>
  </div>
  <form method="get" style="display:flex;gap:10px;align-items:center;">
    <select class="form-control" name="equipment_id" style="max-width:320px;" onchange="this.form.submit()">
      <?php foreach ($equipmentList as $eq): ?>
        <option value="<?= $eq['equipment_id'] ?>" <?= $eq['equipment_id']==$selectedId?'selected':'' ?>>
          <?= e($eq['room_name']) ?> — <?= e($eq['equipment_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div class="two-col">
  <div class="panel">
    <div class="panel-head"><h2>Live Power (last 25 readings)</h2></div>
    <canvas id="liveChart" height="120"></canvas>
  </div>
  <div class="panel">
    <div class="panel-head"><h2>Latest Readings</h2></div>
    <table>
      <thead><tr><th>Time</th><th>Voltage</th><th>Current</th><th>Power</th></tr></thead>
      <tbody>
      <?php foreach ($latestReadings as $r): ?>
        <tr>
          <td><?= date('g:i:s A', strtotime($r['recorded_at'])) ?></td>
          <td><?= number_format($r['voltage'],1) ?> V</td>
          <td><?= number_format($r['current_amp'],2) ?> A</td>
          <td><?= number_format($r['power_watts'],0) ?> W</td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$latestReadings): ?><tr><td colspan="4" class="stat-sub">No readings yet for this equipment.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const labels = <?= json_encode(array_reverse(array_map(fn($r)=>date('g:i:s A', strtotime($r['recorded_at'])), $latestReadings))) ?>;
const values = <?= json_encode(array_reverse(array_map(fn($r)=>(float)$r['power_watts'], $latestReadings))) ?>;
new Chart(document.getElementById('liveChart'), {
  type: 'line',
  data: { labels, datasets: [{ label: 'Power (W)', data: values, borderColor:'#1f9d5a', backgroundColor:'rgba(31,157,90,.12)', fill:true, tension:.3, pointRadius:2, borderWidth:2 }] },
  options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
