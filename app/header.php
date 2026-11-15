<?php
// Include after auth.php. Set $pageTitle before including.
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'Loyalty Rewards') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="index.php">Loyalty Rewards</a>
    <div class="ms-auto d-flex align-items-center gap-3 text-white">
      <?php if ($user): ?>
        <span><?= e($user['name']) ?> (<?= e($user['role']) ?>)</span>
        <form method="post" action="logout.php" class="m-0">
          <?= csrf_field() ?>
          <button class="btn btn-outline-light btn-sm">Logout</button>
        </form>
      <?php else: ?>
        <a class="text-white" href="login.php">Login</a>
        <a class="text-white" href="register.php">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<div class="container">