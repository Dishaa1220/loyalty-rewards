<?php
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/codes.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $productId = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
    $quantity  = filter_var($_POST['quantity'] ?? '', FILTER_VALIDATE_INT,
                            ['options' => ['min_range' => 1, 'max_range' => 500]]);

    if ($quantity === false) {
        $errors[] = 'Quantity must be a whole number from 1 to 500.';
    }

    $product = null;
    if ($productId !== false) {
        $stmt = db()->prepare('SELECT id, name, coins_per_unit FROM products WHERE id = ? AND is_active = 1');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
    }
    if (!$product) {
        $errors[] = 'Please choose an active product.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO batches (product_id, quantity, created_by) VALUES (?, ?, ?)');
            $stmt->execute([$product['id'], $quantity, current_user()['id']]);
            $batchId = (int) $pdo->lastInsertId();

            $insert = $pdo->prepare(
                'INSERT INTO codes (code_uid, batch_id, product_id, coins_value) VALUES (?, ?, ?, ?)'
            );

            $made = 0;
            $attempts = 0;
            while ($made < $quantity) {
                if (++$attempts > $quantity * 5) {
                    throw new RuntimeException('Could not generate enough unique codes.');
                }
                try {
                    $insert->execute([generate_uid(), $batchId, $product['id'], $product['coins_per_unit']]);
                    $made++;
                } catch (PDOException $ex) {
                    // 1062 = duplicate uid (astronomically rare): just try a new one.
                    // Any other error is a real problem, so we re-throw it.
                    if (($ex->errorInfo[1] ?? 0) !== 1062) {
                        throw $ex;
                    }
                }
            }

            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            throw $t;
        }

        header('Location: batch.php?id=' . $batchId);
        exit;
    }
}

$products = db()->query('SELECT id, name, coins_per_unit FROM products WHERE is_active = 1 ORDER BY name')->fetchAll();

$batches = db()->query(
    'SELECT b.id, b.quantity, b.created_at, p.name AS product_name,
            SUM(c.status = \'used\') AS used_count
     FROM batches b
     JOIN products p ON p.id = b.product_id
     LEFT JOIN codes c ON c.batch_id = b.id
     GROUP BY b.id, p.name, b.quantity, b.created_at
     ORDER BY b.id DESC'
)->fetchAll();

$pageTitle = 'Batches';
require __DIR__ . '/../../app/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Code batches</h1>
  <a href="<?= e(url('admin/index.php')) ?>" class="btn btn-sm btn-outline-secondary">Back</a>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <h2 class="h6">Generate a new batch</h2>
    <?php if (!$products): ?>
      <p class="text-muted mb-0">Create an active product first.</p>
    <?php else: ?>
    <form method="post" class="row g-2">
      <?= csrf_field() ?>
      <div class="col-md-6">
        <select name="product_id" class="form-select" required>
          <?php foreach ($products as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> (<?= (int) $p['coins_per_unit'] ?> coins)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <input type="number" name="quantity" class="form-control" placeholder="How many codes (1-500)" min="1" max="500" required>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100">Generate</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>#</th><th>Product</th><th>Codes</th><th>Used</th><th>Created</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($batches as $b): ?>
        <tr>
          <td><?= (int) $b['id'] ?></td>
          <td><?= e($b['product_name']) ?></td>
          <td><?= (int) $b['quantity'] ?></td>
          <td><?= (int) $b['used_count'] ?></td>
          <td><?= e($b['created_at']) ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="batch.php?id=<?= (int) $b['id'] ?>">View / print</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$batches): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No batches yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../../app/footer.php'; ?>