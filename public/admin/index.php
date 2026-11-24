<?php
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/settings.php';
require_once __DIR__ . '/../../app/payouts.php';
require_once __DIR__ . '/../../app/monitor.php';
require_admin();

$productCount  = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
$customerCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$rate          = (float) get_setting('coin_to_rupee_rate', '1.00');
$pendingCount  = count_pending_payouts();
$flagCount     = count(fraud_flags());

$pageTitle = 'Admin';
require __DIR__ . '/../../app/header.php';
?>
<h1 class="h4 mb-3">Admin dashboard</h1>

<div class="row row-cols-2 row-cols-md-5 g-3 mb-4">
  <div class="col">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-muted small">Products</div>
      <div class="fs-3"><?= $productCount ?></div>
    </div></div>
  </div>
  <div class="col">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-muted small">Customers</div>
      <div class="fs-3"><?= $customerCount ?></div>
    </div></div>
  </div>
  <div class="col">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-muted small">Coin rate</div>
      <div class="fs-3">&#8377;<?= e(number_format($rate, 2)) ?></div>
    </div></div>
  </div>
  <div class="col">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-muted small">Pending cash-outs</div>
      <div class="fs-3"><?= $pendingCount ?></div>
    </div></div>
  </div>
  <div class="col">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-muted small">Fraud flags (24 h)</div>
      <div class="fs-3 <?= $flagCount > 0 ? 'text-danger' : '' ?>"><?= $flagCount ?></div>
    </div></div>
  </div>
</div>

<div class="list-group">
  <a class="list-group-item list-group-item-action" href="<?= e(url('admin/monitor.php')) ?>">Monitoring and fraud flags (<?= $flagCount ?> active)</a>
  <a class="list-group-item list-group-item-action" href="<?= e(url('admin/products.php')) ?>">Manage products</a>
  <a class="list-group-item list-group-item-action" href="<?= e(url('admin/batches.php')) ?>">Code batches and QR codes</a>
  <a class="list-group-item list-group-item-action" href="<?= e(url('admin/payouts.php')) ?>">Cash-out requests (<?= $pendingCount ?> pending)</a>
  <a class="list-group-item list-group-item-action" href="<?= e(url('admin/settings.php')) ?>">Coin settings</a>
</div>
<?php require __DIR__ . '/../../app/footer.php'; ?>