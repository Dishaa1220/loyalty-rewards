<?php
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/payouts.php';
require_admin();

$messages = [
    'approved' => 'Request approved.',
    'rejected' => 'Request rejected and the coins were refunded.',
    'stale'    => 'That request was already processed (or does not exist). Nothing was changed.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $id     = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    $note   = substr(trim($_POST['note'] ?? ''), 0, 255);
    $note   = $note === '' ? null : $note;
    $admin  = (int) current_user()['id'];

    $ok = false;
    if ($id !== false && $action === 'approve') {
        $ok = approve_payout($id, $admin, $note);
    } elseif ($id !== false && $action === 'reject') {
        $ok = reject_payout($id, $admin, $note);
    }

    $msg = $ok ? ($action === 'approve' ? 'approved' : 'rejected') : 'stale';
    header('Location: payouts.php?msg=' . $msg);
    exit;
}

$pending = list_payout_requests('pending');
$history = list_payout_requests('history', 50);
$flash   = $messages[$_GET['msg'] ?? ''] ?? '';
$badge   = ['approved' => 'success', 'rejected' => 'danger'];

$pageTitle = 'Cash-out requests';
require __DIR__ . '/../../app/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Cash-out requests</h1>
  <a href="<?= e(url('admin/index.php')) ?>" class="btn btn-sm btn-outline-secondary">Back</a>
</div>

<?php if ($flash): ?><div class="alert alert-info py-2"><?= e($flash) ?></div><?php endif; ?>

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold">Pending (<?= count($pending) ?>)</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>#</th><th>Customer</th><th>Coins</th><th>Pay this amount</th><th>UPI ID</th><th>Requested</th><th>Decision</th></tr></thead>
      <tbody>
      <?php foreach ($pending as $r): ?>
        <tr>
          <td><?= (int) $r['id'] ?></td>
          <td><?= e($r['name']) ?><div class="text-muted small"><?= e($r['email']) ?></div></td>
          <td><?= (int) $r['coins'] ?></td>
          <td class="fw-semibold">&#8377;<?= e($r['rupees']) ?></td>
          <td class="font-monospace"><?= e($r['payout_details']) ?></td>
          <td class="text-nowrap"><?= e($r['created_at']) ?></td>
          <td>
            <form method="post" class="d-flex gap-2">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <input type="text" name="note" class="form-control form-control-sm" placeholder="Note (optional)" maxlength="255">
              <button name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
              <button name="action" value="reject" class="btn btn-sm btn-outline-danger"
                      onclick="return confirm('Reject this request and refund the coins?')">Reject</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pending): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No pending requests.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="text-muted small">Pay the customer outside this system (for example by UPI), then press Approve.</p>

<div class="card shadow-sm">
  <div class="card-header bg-white fw-semibold">Reviewed</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>#</th><th>Customer</th><th>Coins</th><th>Amount</th><th>Status</th><th>Reviewed</th><th>Note</th></tr></thead>
      <tbody>
      <?php foreach ($history as $r): ?>
        <tr>
          <td><?= (int) $r['id'] ?></td>
          <td><?= e($r['name']) ?></td>
          <td><?= (int) $r['coins'] ?></td>
          <td>&#8377;<?= e($r['rupees']) ?></td>
          <td><span class="badge text-bg-<?= e($badge[$r['status']] ?? 'secondary') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
          <td class="text-nowrap"><?= e($r['reviewed_at'] ?? '') ?></td>
          <td><?= e($r['admin_note'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$history): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Nothing reviewed yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../../app/footer.php'; ?>