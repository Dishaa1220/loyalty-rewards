<?php
require_once __DIR__ . '/../app/auth.php';

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($name === '' || strlen($name) > 100) {
        $errors[] = 'Please enter your name (max 100 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            $stmt = db()->prepare(
                'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
            );
            // Registration ALWAYS creates a customer. Admins are made manually.
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'customer']);

            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {      // duplicate email (UNIQUE constraint)
                $errors[] = 'That email is already registered.';
            } else {
                throw $ex;
            }
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/../app/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h4 mb-3">Create your account</h1>

        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger py-2"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" value="<?= e($name) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password (min 8 characters)</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm password</label>
            <input type="password" name="confirm" class="form-control" required>
          </div>
          <button class="btn btn-primary w-100">Register</button>
        </form>

        <p class="mt-3 mb-0 text-center">Already have an account? <a href="login.php">Login</a></p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../app/footer.php'; ?>