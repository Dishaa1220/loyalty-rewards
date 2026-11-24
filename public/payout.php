<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/settings.php';
require_once __DIR__ . '/../app/redeem.php';
require_once __DIR__ . '/../app/payouts.php';
require_login();

$me     = current_user();
$userId = (int) $me['id'];

$errors  = [];
$coinsIn = '';
$upiIn   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $coinsIn = trim($_POST['coins'] ?? '');
    $upiIn   = trim($_POST['upi'] ?? '');
    $coins   = filter_var($coinsIn, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);

    if ($coins === false) {
        $errors[] = 'Enter a whole number of coins.';
    } else {
        $result = request_payout($userId, $coins, $upiIn);
        if ($result['ok']) {
            header('Location: payout.php?msg=requested');
            exit;
        }
        $errors[] = $result['error'];
    }
}

$balance  = get_balance($userId);
$rate     = get_setting('coin_to_rupee_rate', '1.00');
$min      = min_payout_coins();
$requests = list_user_requests($userId);
$flash    = ($_GET['msg'] ?? '') === 'requested' ? 'Cash-out request sent. The owner will review it.' : '';

$badge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];

$pageTitle = 'Cash out';
require __DIR__ . '/../app/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Cash out</h1>
  <a href="<?= e(url('index.php')) ?>" class="btn btn-sm btn-outline-secondary">Back to wallet</a>
</div>

<?php if ($flash): ?><div class="alert alert-success py-2"><?= e($flash) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <p class="mb-1">Your balance: <strong><?= $balance ?> coins</strong>
       (&#8377;<?= e(rupees_from_coins($balance, $rate)) ?>)</p>
    <p class="text-muted small">1 coin = &#8377;<?= e(number_format((float) $rate, 2)) ?>. Minimum cash-out: <?= $min ?> coins.
       The coins are taken from your balance now, and the owner pays you by UPI after reviewing.
       If a request is rejected, the coins are returned.</p>

    <form method="post" class="row g-2">
      <?= csrf_field() ?>
      <div class="col-md-4">
        <input type="number" name="coins" class="form-control" placeholder="Coins to cash out" min="<?= $min ?>" value="<?= e($coinsIn) ?>" required>
      </div>
      <div class="col-md-5">
        <input type="text" name="upi" class="form-control" placeholder="Your UPI ID (name@bank)" value="<?= e($upiIn) ?>" required>
      </div>
      <div class="col-md-3">
        <button class="btn btn-success w-100">Request cash-out</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white fw-semibold">Your requests</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>#</th><th>Requested</th><th>Coins</th><th>Amount</th><th>Status</th><th>Owner's note</th></tr></thead>
      <tbody>
      <?php foreach ($requests as $r): ?>
        <tr>
          <td><?= (int) $r['id'] ?></td>
          <td class="text-nowrap"><?= e($r['created_at']) ?></td>
          <td><?= (int) $r['coins'] ?></td>
          <td>&#8377;<?= e($r['rupees']) ?></td>
          <td><span class="badge text-bg-<?= e($badge[$r['status']] ?? 'secondary') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
          <td><?= e($r['admin_note'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$requests): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No cash-out requests yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../app/footer.php'; ?>