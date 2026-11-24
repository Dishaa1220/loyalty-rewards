<?php
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/settings.php';
require_admin();

$productCount  = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
$customerCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$rate          = (float) get_setting('coin_to_rupee_rate', '1.00');

$pageTitle = 'Admin';
require __DIR__ . '/../../app/header.php';
?>
<h1 class="h4 mb-3">Admin dashboard</h1>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted">Products</div>
      <div class="fs-3"><?= $productCount ?></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted">Customers</div>
      <div class="fs-3"><?= $customerCount ?></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted">Coin rate</div>
      <div class="fs-3">1 coin = &#8377;<?= e(number_format($rate, 2)) ?></div>
    </div></div>
  </div>
</div>

<div class="list-group">
  <a class="list-group-item list-group-item-action" href="<?= e(url('admin/products.php')) ?>">Manage products</a>
  <a class="list-group-item list-group-item-action" href="<?= e(url('admin/batches.php')) ?>">Code batches and QR codes</a>
  <a class="list-group-item list-group-item-action" href="<?= e(url('admin/settings.php')) ?>">Coin settings</a>
</div>
<?php require __DIR__ . '/../../app/footer.php'; ?>