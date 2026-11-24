<?php
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/settings.php';
require_admin();

$errors = [];
$saved  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $rate = $_POST['rate'] ?? '';
    $min  = filter_var($_POST['min_payout'] ?? '', FILTER_VALIDATE_INT,
                       ['options' => ['min_range' => 1, 'max_range' => 1000000]]);

    if (!is_numeric($rate) || (float) $rate <= 0 || (float) $rate > 10000) {
        $errors[] = 'Rate must be a number greater than 0 and at most 10000.';
    }
    if ($min === false) {
        $errors[] = 'Minimum cash-out must be a whole number of coins (1 or more).';
    }

    if (!$errors) {
        set_setting('coin_to_rupee_rate', number_format((float) $rate, 2, '.', ''));
        set_setting('min_payout_coins', (string) $min);
        $saved = true;
    }
}

$currentRate = get_setting('coin_to_rupee_rate', '1.00');
$currentMin  = get_setting('min_payout_coins', '100');

$pageTitle = 'Coin settings';
require __DIR__ . '/../../app/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Coin settings</h1>
  <a href="<?= e(url('admin/index.php')) ?>" class="btn btn-sm btn-outline-secondary">Back</a>
</div>

<?php if ($saved): ?><div class="alert alert-success py-2">Settings saved.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>

<div class="card shadow-sm" style="max-width:480px">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <label class="form-label">Value of 1 coin in rupees (&#8377;)</label>
      <input type="number" step="0.01" min="0.01" name="rate" class="form-control mb-3"
             value="<?= e($currentRate) ?>" required>

      <label class="form-label">Minimum cash-out (coins)</label>
      <input type="number" min="1" name="min_payout" class="form-control mb-3"
             value="<?= e($currentMin) ?>" required>

      <button class="btn btn-primary">Save</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../app/footer.php'; ?>