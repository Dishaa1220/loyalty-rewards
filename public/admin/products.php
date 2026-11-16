<?php
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/settings.php';
require_admin();

$messages = [
    'added'   => 'Product added.',
    'updated' => 'Product updated.',
    'toggled' => 'Product status changed.',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name  = trim($_POST['name'] ?? '');
        $coins = filter_var($_POST['coins_per_unit'] ?? '', FILTER_VALIDATE_INT,
                            ['options' => ['min_range' => 1, 'max_range' => 100000]]);

        if ($name === '' || strlen($name) > 150) {
            $errors[] = 'Product name is required (max 150 characters).';
        }
        if ($coins === false) {
            $errors[] = 'Coins per box must be a whole number from 1 to 100000.';
        }

        if (!$errors) {
            if ($action === 'add') {
                $stmt = db()->prepare('INSERT INTO products (name, coins_per_unit) VALUES (?, ?)');
                $stmt->execute([$name, $coins]);
                header('Location: products.php?msg=added');
            } else {
                $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
                $stmt = db()->prepare('UPDATE products SET name = ?, coins_per_unit = ? WHERE id = ?');
                $stmt->execute([$name, $coins, $id]);
                header('Location: products.php?msg=updated');
            }
            exit;
        }
    } elseif ($action === 'toggle') {
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $stmt = db()->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: products.php?msg=toggled');
        exit;
    }
}

$products = db()->query('SELECT id, name, coins_per_unit, is_active FROM products ORDER BY id DESC')->fetchAll();
$rate = (float) get_setting('coin_to_rupee_rate', '1.00');
$flash = $messages[$_GET['msg'] ?? ''] ?? '';

$pageTitle = 'Products';
require __DIR__ . '/../../app/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Products</h1>
  <a href="<?= e(url('admin/index.php')) ?>" class="btn btn-sm btn-outline-secondary">Back</a>
</div>

<?php if ($flash): ?>
  <div class="alert alert-success py-2"><?= e($flash) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <h2 class="h6">Add a product</h2>
    <form method="post" class="row g-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="col-md-6">
        <input type="text" name="name" class="form-control" placeholder="Product name" required>
      </div>
      <div class="col-md-4">
        <input type="number" name="coins_per_unit" class="form-control" placeholder="Coins per box" min="1" required>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100">Add</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr><th>Name</th><th>Coins per box</th><th>Worth</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <td><input type="text" name="name" class="form-control form-control-sm" value="<?= e($p['name']) ?>"></td>
            <td style="width:140px"><input type="number" name="coins_per_unit" class="form-control form-control-sm" min="1" value="<?= (int) $p['coins_per_unit'] ?>"></td>
            <td>&#8377;<?= e(number_format($p['coins_per_unit'] * $rate, 2)) ?></td>
            <td>
              <?php if ($p['is_active']): ?>
                <span class="badge text-bg-success">Active</span>
              <?php else: ?>
                <span class="badge text-bg-secondary">Inactive</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary">Save</button>
          </form>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="btn btn-sm btn-outline-secondary"><?= $p['is_active'] ? 'Deactivate' : 'Activate' ?></button>
              </form>
            </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">No products yet. Add your first one above.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="text-muted small mt-2">Changing coins per box affects only future batches. Boxes already printed keep their original value.</p>
<?php require __DIR__ . '/../../app/footer.php'; ?>