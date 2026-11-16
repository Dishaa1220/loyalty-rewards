<?php
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/settings.php';
require_admin();

$error = '';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $rate = $_POST['rate'] ?? '';

    if (!is_numeric($rate) || (float) $rate <= 0 || (float) $rate > 10000) {
        $error = 'Rate must be a number greater than 0 and at most 10000.';
    } else {
        set_setting('coin_to_rupee_rate', number_format((float) $rate, 2, '.', ''));
        $saved = true;
    }
}

$currentRate = get_setting('coin_to_rupee_rate', '1.00');

$pageTitle = 'Coin settings';
require __DIR__ . '/../../app/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Coin settings</h1>
  <a href="<?= e(url('admin/index.php')) ?>" class="btn btn-sm btn-outline-secondary">Back</a>
</div>

<?php if ($saved): ?><div class="alert alert-success py-2">Rate saved.</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>

<div class="card shadow-sm" style="max-width:480px">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <label class="form-label">Value of 1 coin in rupees (&#8377;)</label>
      <input type="number" step="0.01" min="0.01" name="rate" class="form-control mb-3"
             value="<?= e($currentRate) ?>" required>
      <button class="btn btn-primary">Save</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../app/footer.php'; ?>