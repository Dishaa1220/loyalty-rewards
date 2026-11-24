<?php
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/settings.php';
require_once __DIR__ . '/../../app/payouts.php';   // rupees_from_coins()
require_once __DIR__ . '/../../app/monitor.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $batchId = filter_var($_POST['batch_id'] ?? '', FILTER_VALIDATE_INT);
    $action  = $_POST['action'] ?? '';
    $msg     = 'none';
    $n       = 0;

    if ($batchId !== false && $action === 'block') {
        $n   = block_batch($batchId);
        $msg = 'blocked';
    } elseif ($batchId !== false && $action === 'unblock') {
        $n   = unblock_batch($batchId);
        $msg = 'unblocked';
    }

    header('Location: monitor.php?msg=' . $msg . '&n=' . $n);
    exit;
}

$k        = monitor_kpis();
$rate     = get_setting('coin_to_rupee_rate', '1.00');
$byDay    = redemptions_by_day(14);
$maxDay   = max(1, max($byDay));
$products = product_stats();
$batches  = batch_stats(20);
$flags    = fraud_flags();

$allowed = ['success', 'invalid', 'already_used', 'blocked'];
$filter  = $_GET['result'] ?? '';
if (!in_array($filter, $allowed, true)) {
    $filter = '';
}
$scans = recent_scans($filter, 50);

$n     = (int) ($_GET['n'] ?? 0);
$flash = '';
if (($_GET['msg'] ?? '') === 'blocked')   { $flash = "$n unused code(s) blocked."; }
if (($_GET['msg'] ?? '') === 'unblocked') { $flash = "$n code(s) unblocked."; }

$pct = $k['codes']['total'] > 0 ? round($k['codes']['used'] / $k['codes']['total'] * 100, 1) : 0;

$cards = [
    ['Codes generated',   $k['codes']['total'],
        $k['codes']['unused'] . ' unused, ' . $k['codes']['used'] . ' used, ' . $k['codes']['blocked'] . ' blocked'],
    ['Redemption rate',   $pct . '%', 'of all generated codes'],
    ['Coins issued',      $k['coins_issued'], 'earned by customers in total'],
    ['Coins in wallets',  $k['coins_in_wallets'],
        '₹' . rupees_from_coins(max(0, $k['coins_in_wallets']), $rate) . ' at today\'s rate'],
    ['Paid out',          '₹' . number_format($k['paid']['rupees'], 2), $k['paid']['n'] . ' approved cash-out(s)'],
    ['Pending cash-outs', '₹' . number_format($k['pending']['rupees'], 2), $k['pending']['n'] . ' waiting for review'],
    ['Scans in 24 hours', $k['scans_ok'], 'successful redemptions'],
    ['Failed scans (24 h)', $k['scans_failed'], 'invalid, reused or blocked codes'],
];

$resultBadge = ['success' => 'success', 'invalid' => 'danger', 'already_used' => 'warning', 'blocked' => 'dark'];
$severityBadge = ['high' => 'danger', 'medium' => 'warning'];

$pageTitle = 'Monitoring';
require __DIR__ . '/../../app/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Monitoring</h1>
  <a href="<?= e(url('admin/index.php')) ?>" class="btn btn-sm btn-outline-secondary">Back</a>
</div>

<?php if ($flash): ?><div class="alert alert-info py-2"><?= e($flash) ?></div><?php endif; ?>

<div class="row g-3 mb-4">
  <?php foreach ($cards as [$label, $value, $sub]): ?>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small"><?= e($label) ?></div>
        <div class="fs-4 fw-semibold"><?= e((string) $value) ?></div>
        <div class="text-muted small"><?= e($sub) ?></div>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold">Fraud flags (last 24 hours)</div>
  <div class="card-body">
    <?php if (!$flags): ?>
      <div class="alert alert-success mb-0 py-2">No suspicious activity in the last 24 hours.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Severity</th><th>Flag</th><th>Who</th><th>Details</th></tr></thead>
          <tbody>
          <?php foreach ($flags as $f): ?>
            <tr>
              <td><span class="badge text-bg-<?= e($severityBadge[$f['severity']] ?? 'secondary') ?>"><?= e(ucfirst($f['severity'])) ?></span></td>
              <td><?= e($f['title']) ?></td>
              <td class="font-monospace"><?= e($f['subject']) ?></td>
              <td><?= e($f['detail']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <p class="text-muted small mt-3 mb-0">
      Flags are alerts for you to review; nothing is blocked automatically. Rules: <?= FLAG_FAILED_SCANS_ACCOUNT ?>+ failed scans by one account,
      <?= FLAG_FAILED_SCANS_IP ?>+ failed scans from one IP address, <?= FLAG_ACCOUNTS_PER_IP ?>+ accounts on one IP address,
      <?= FLAG_REDEMPTIONS_24H ?>+ redemptions by one account, all within 24 hours.
    </p>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold">Redemptions per day (last 14 days)</div>
  <div class="card-body">
    <table class="table table-sm align-middle mb-0">
      <?php foreach ($byDay as $date => $count): ?>
        <tr>
          <td class="text-nowrap" style="width:120px"><?= e($date) ?></td>
          <td>
            <div class="progress" style="height:16px">
              <div class="progress-bar" style="width: <?= (int) round($count / $maxDay * 100) ?>%"></div>
            </div>
          </td>
          <td class="text-end" style="width:50px"><?= (int) $count ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold">By product</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Product</th><th>Generated</th><th>Used</th><th>Blocked</th><th>Redeemed %</th><th>Coins issued</th></tr></thead>
      <tbody>
      <?php foreach ($products as $p): ?>
        <?php $gen = (int) $p['generated']; ?>
        <tr>
          <td><?= e($p['name']) ?><?= $p['is_active'] ? '' : ' <span class="badge text-bg-secondary">inactive</span>' ?></td>
          <td><?= $gen ?></td>
          <td><?= (int) $p['used'] ?></td>
          <td><?= (int) $p['blocked'] ?></td>
          <td><?= $gen > 0 ? round($p['used'] / $gen * 100, 1) . '%' : '-' ?></td>
          <td><?= (int) $p['coins_issued'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No products yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold">Batches (latest 20)</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>#</th><th>Product</th><th>Created</th><th>Codes</th><th>Unused</th><th>Used</th><th>Blocked</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($batches as $b): ?>
        <tr>
          <td><?= (int) $b['id'] ?></td>
          <td><?= e($b['product_name']) ?></td>
          <td class="text-nowrap"><?= e($b['created_at']) ?></td>
          <td><?= (int) $b['generated'] ?></td>
          <td><?= (int) $b['unused'] ?></td>
          <td><?= (int) $b['used'] ?></td>
          <td><?= (int) $b['blocked'] ?></td>
          <td>
            <form method="post" class="d-flex gap-2">
              <?= csrf_field() ?>
              <input type="hidden" name="batch_id" value="<?= (int) $b['id'] ?>">
              <button name="action" value="block" class="btn btn-sm btn-outline-danger"
                      <?= (int) $b['unused'] === 0 ? 'disabled' : '' ?>
                      onclick="return confirm('Block all unused codes of this batch? Customers will not be able to redeem them.')">Block unused</button>
              <button name="action" value="unblock" class="btn btn-sm btn-outline-secondary"
                      <?= (int) $b['blocked'] === 0 ? 'disabled' : '' ?>>Unblock</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$batches): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No batches yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="fw-semibold">Scan log (latest 50)</span>
    <span class="d-flex gap-1 flex-wrap">
      <a class="btn btn-sm <?= $filter === '' ? 'btn-primary' : 'btn-outline-primary' ?>" href="monitor.php">All</a>
      <?php foreach ($allowed as $r): ?>
        <a class="btn btn-sm <?= $filter === $r ? 'btn-primary' : 'btn-outline-primary' ?>" href="monitor.php?result=<?= e($r) ?>"><?= e($r) ?></a>
      <?php endforeach; ?>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Time</th><th>Account</th><th>Code tried</th><th>Result</th><th>IP address</th></tr></thead>
      <tbody>
      <?php foreach ($scans as $s): ?>
        <tr>
          <td class="text-nowrap"><?= e($s['created_at']) ?></td>
          <td><?= e($s['email'] ?? '(not logged in)') ?></td>
          <td class="font-monospace small"><?= e(substr($s['code_uid_tried'], 0, 30)) ?></td>
          <td><span class="badge text-bg-<?= e($resultBadge[$s['result']] ?? 'secondary') ?>"><?= e($s['result']) ?></span></td>
          <td class="font-monospace small"><?= e($s['ip_address'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$scans): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">No scan attempts to show.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../../app/footer.php'; ?>