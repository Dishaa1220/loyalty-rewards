<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/codes.php';
require_once __DIR__ . '/../app/redeem.php';
require_once __DIR__ . '/../app/settings.php';

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$raw = trim((string) ($isPost ? ($_POST['c'] ?? '') : ($_GET['c'] ?? '')));

// Not logged in: remember the code, send them to login
if (!current_user()) {
    if ($raw !== '') {
        $_SESSION['pending_token'] = substr($raw, 0, 100);
    }
    header('Location: ' . url('login.php'));
    exit;
}
$user = current_user();

if ($isPost) {
    csrf_check();
}

// Came back from login: use the remembered code
if ($raw === '' && !empty($_SESSION['pending_token'])) {
    $raw = $_SESSION['pending_token'];
}

$state   = 'enter';
$code    = null;
$earned  = 0;
$balance = 0;

if ($raw !== '') {
    if (too_many_failures((int) $user['id'])) {
        $state = 'locked';
    } else {
        $uid  = parse_token($raw);                       // signature check, no database
        $code = $uid !== null ? find_code($uid) : null;  // then the database

        if ($code === null) {
            log_scan((int) $user['id'], $raw, 'invalid');
            $state = 'invalid';
        } elseif ($isPost) {
            $outcome = redeem_code($uid, (int) $user['id']);
            log_scan((int) $user['id'], $uid, $outcome['result']);
            $code = find_code($uid);

            if ($outcome['result'] === 'success') {
                $state   = 'done';
                $earned  = $outcome['coins'];
                $balance = get_balance((int) $user['id']);
            } else {
                $state = $outcome['result'];             // already_used, blocked or invalid
            }
        } elseif ($code['status'] === 'unused') {
            $state = 'confirm';                          // GET only shows info
        } else {
            $state = $code['status'] === 'blocked' ? 'blocked' : 'already_used';
            log_scan((int) $user['id'], $uid, $state);
        }
    }
}

if ($state !== 'confirm') {
    unset($_SESSION['pending_token']);
}

$rate = (float) get_setting('coin_to_rupee_rate', '1.00');

$pageTitle = 'Claim coins';
require __DIR__ . '/../app/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h4 mb-3">Claim your coins</h1>

        <?php if ($state === 'enter'): ?>
          <p>Scan the QR code inside your box, or type the code printed under it.</p>
          <form method="get" class="d-flex gap-2">
            <input type="text" name="c" class="form-control font-monospace" placeholder="XXXXXXXXXXXX-xxxxxxxxxx" required>
            <button class="btn btn-primary">Check</button>
          </form>

        <?php elseif ($state === 'confirm'): ?>
          <p class="mb-1">Product: <strong><?= e($code['product_name']) ?></strong></p>
          <p>This code is worth <strong><?= (int) $code['coins_value'] ?> coins</strong>
             (about &#8377;<?= e(number_format($code['coins_value'] * $rate, 2)) ?>).</p>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="c" value="<?= e($raw) ?>">
            <button class="btn btn-success w-100">Claim coins</button>
          </form>

        <?php elseif ($state === 'done'): ?>
          <div class="alert alert-success">
            You earned <strong><?= (int) $earned ?> coins</strong>
            (about &#8377;<?= e(number_format($earned * $rate, 2)) ?>).
          </div>
          <p class="mb-3">Your balance: <strong><?= (int) $balance ?> coins</strong>
             (&#8377;<?= e(number_format($balance * $rate, 2)) ?>)</p>
          <a href="<?= e(url('redeem.php')) ?>" class="btn btn-outline-primary">Enter another code</a>

        <?php elseif ($state === 'already_used'): ?>
          <div class="alert alert-warning">
            <?php if ($code && (int) $code['redeemed_by'] === (int) $user['id']): ?>
              You already claimed this code on <?= e($code['redeemed_at']) ?>.
            <?php else: ?>
              This code has already been used.
            <?php endif; ?>
          </div>

        <?php elseif ($state === 'blocked'): ?>
          <div class="alert alert-danger">This code cannot be redeemed. Please contact the company.</div>

        <?php elseif ($state === 'locked'): ?>
          <div class="alert alert-danger">
            Too many failed attempts. Please try again in <?= (int) LOCKOUT_MINUTES ?> minutes.
          </div>

        <?php else: ?>
          <div class="alert alert-danger">This code is not valid. Check it and try again.</div>
          <a href="<?= e(url('redeem.php')) ?>" class="btn btn-outline-primary">Try again</a>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../app/footer.php'; ?>