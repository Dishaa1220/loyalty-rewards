<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/settings.php';
require_once __DIR__ . '/../app/redeem.php';
require_once __DIR__ . '/../app/wallet.php';
require_login();

// The user ID always comes from the session, never from the URL
$me     = current_user();
$userId = (int) $me['id'];

$perPage = 10;
$total   = count_transactions($userId);
$pages   = max(1, (int) ceil($total / $perPage));
$page    = (int) filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
$page    = min(max(1, $page), $pages);

$rows    = get_history($userId, $perPage, ($page - 1) * $perPage);
$balance = get_balance($userId);
$summary = wallet_summary($userId);
$rate    = (float) get_setting('coin_to_rupee_rate', '1.00');

$pageTitle = 'My wallet';
require __DIR__ . '/../app/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Hello, <?= e($me['name']) ?></h1>
  <div class="d-flex gap-2">
    <a href="<?= e(url('payout.php')) ?>" class="btn btn-outline-success">Cash out</a>
    <a href="<?= e(url('redeem.php')) ?>" class="btn btn-primary">Claim a code</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-muted">Coin balance</div>
      <div class="fs-2 fw-semibold"><?= $balance ?></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-muted">Worth</div>
      <div class="fs-2 fw-semibold">&#8377;<?= e(number_format($balance * $rate, 2)) ?></div>
      <div class="text-muted small">1 coin = &#8377;<?= e(number_format($rate, 2)) ?></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-muted">Boxes claimed</div>
      <div class="fs-2 fw-semibold"><?= $summary['claimed'] ?></div>
      <div class="text-muted small"><?= $summary['earned'] ?> coins earned in total</div>
    </div></div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white fw-semibold">History</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Date</th><th>Details</th><th class="text-end">Coins</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          if ($r['type'] === 'earn') {
              $label = 'Claimed ' . ($r['product_name'] ?? 'a box code');
          } elseif ($r['type'] === 'redeem') {
              $label = $r['note'] ?: 'Cash-out';
          } else {
              $label = $r['note'] ?: 'Adjustment';
          }
          $coins = (int) $r['coins'];
        ?>
        <tr>
          <td class="text-nowrap"><?= e($r['created_at']) ?></td>
          <td><?= e($label) ?></td>
          <td class="text-end fw-semibold <?= $coins >= 0 ? 'text-success' : 'text-danger' ?>">
            <?= $coins >= 0 ? '+' : '' ?><?= $coins ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="3" class="text-center text-muted py-4">
          No coins yet. Scan the QR code inside your next box to earn your first coins.
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pages > 1): ?>
  <nav class="mt-3 d-flex justify-content-between align-items-center">
    <a class="btn btn-sm btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="?page=<?= $page - 1 ?>">Previous</a>
    <span class="text-muted small">Page <?= $page ?> of <?= $pages ?></span>
    <a class="btn btn-sm btn-outline-secondary <?= $page >= $pages ? 'disabled' : '' ?>" href="?page=<?= $page + 1 ?>">Next</a>
  </nav>
<?php endif; ?>
<?php require __DIR__ . '/../app/footer.php'; ?>