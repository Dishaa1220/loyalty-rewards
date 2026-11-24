<?php
require_once __DIR__ . '/../app/auth.php';

if (current_user()) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        session_regenerate_id(true);          // new session ID after login
        $_SESSION['user_id'] = $row['id'];
        header('Location: ' . (!empty($_SESSION['pending_token']) ? 'redeem.php' : 'index.php'));
        exit;
    }

    // Same message for "no such email" and "wrong password" on purpose
    $error = 'Invalid email or password.';
}

$pageTitle = 'Login';
require __DIR__ . '/../app/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h4 mb-3">Login</h1>

        <?php if (!empty($_SESSION['pending_token'])): ?>
          <div class="alert alert-info py-2">Please log in (or register) to claim your coins.</div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button class="btn btn-primary w-100">Login</button>
        </form>

        <p class="mt-3 mb-0 text-center">No account? <a href="register.php">Register</a></p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../app/footer.php'; ?>