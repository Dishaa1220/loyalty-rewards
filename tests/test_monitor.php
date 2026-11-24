<?php
// Run with:  C:\xampp\php\php.exe tests\test_monitor.php
// Uses your local loyalty_db; creates temporary rows and removes them at the end.
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/codes.php';
require_once __DIR__ . '/../app/redeem.php';
require_once __DIR__ . '/../app/monitor.php';

$passed = 0;
$failed = 0;

function check(string $name, bool $condition): void
{
    global $passed, $failed;
    if ($condition) { $passed++; echo "PASS  $name\n"; }
    else            { $failed++; echo "FAIL  $name\n"; }
}

function has_flag(array $flags, string $type, string $subject): bool
{
    foreach ($flags as $f) {
        if ($f['type'] === $type && $f['subject'] === $subject) {
            return true;
        }
    }
    return false;
}

function batch_row(int $batchId): ?array
{
    foreach (batch_stats(50) as $b) {
        if ((int) $b['id'] === $batchId) {
            return $b;
        }
    }
    return null;
}

$pdo = db();
$tag = bin2hex(random_bytes(4));
$users = [];
$productId = $batchId = null;

try {
    // --- temporary data: 3 users, 1 product, 1 batch of 12 codes ---
    $insUser = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, 'x', 'customer')");
    $emails = [];
    foreach (['A', 'B', 'C'] as $letter) {
        $emails[$letter] = "monitor-" . strtolower($letter) . "-$tag@example.test";
        $insUser->execute(["Monitor $letter", $emails[$letter]]);
        $users[$letter] = (int) $pdo->lastInsertId();
    }
    [$A, $B, $C] = [$users['A'], $users['B'], $users['C']];

    $pdo->prepare('INSERT INTO products (name, coins_per_unit) VALUES (?, 10)')->execute(["TEST monitor $tag"]);
    $productId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO batches (product_id, quantity, created_by) VALUES (?, 12, ?)')->execute([$productId, $A]);
    $batchId = (int) $pdo->lastInsertId();

    $insCode = $pdo->prepare(
        "INSERT INTO codes (code_uid, batch_id, product_id, coins_value, status) VALUES (?, ?, ?, 10, 'unused')"
    );
    $uids = [];
    for ($i = 0; $i < 12; $i++) {
        $uids[] = generate_uid();
        $insCode->execute([$uids[$i], $batchId, $productId]);
    }

    // --- batch statistics, blocking and unblocking ---
    $s = batch_row($batchId);
    check('batch shows 12 codes, none used or blocked', $s && (int) $s['generated'] === 12 && (int) $s['used'] === 0 && (int) $s['blocked'] === 0);

    check('blocking the batch blocks all 12 unused codes', block_batch($batchId) === 12);
    check('a blocked code cannot be redeemed', redeem_code($uids[0], $A)['result'] === 'blocked');
    $s = batch_row($batchId);
    check('batch statistics show 12 blocked', (int) $s['blocked'] === 12);

    check('unblocking restores 12 codes', unblock_batch($batchId) === 12);
    check('an unblocked code can be redeemed', redeem_code($uids[0], $A)['result'] === 'success');

    check('blocking again affects only the 11 unused codes', block_batch($batchId) === 11);
    check('unblocking restores 11 codes', unblock_batch($batchId) === 11);
    $s = batch_row($batchId);
    check('the redeemed code stays used', (int) $s['used'] === 1 && (int) $s['blocked'] === 0);

    // --- redemption velocity flag ---
    for ($i = 1; $i <= 10; $i++) {
        redeem_code($uids[$i], $A);                  // A now has 11 redemptions
    }
    $flags = fraud_flags();
    check('11 redemptions in 24 hours raise a fast-redeemer flag', has_flag($flags, 'fast_redeemer', $emails['A']));
    check('a user with no redemptions is not flagged', !has_flag($flags, 'fast_redeemer', $emails['B']));

    // --- failed-scan and shared-IP flags (a test IP address that no real user has) ---
    $ip = '203.0.113.77';
    $insScan = $pdo->prepare('INSERT INTO scan_attempts (user_id, code_uid_tried, result, ip_address) VALUES (?, ?, ?, ?)');
    for ($i = 0; $i < 6; $i++) {
        $insScan->execute([$B, 'BADCODE' . $i, 'invalid', $ip]);
    }
    $insScan->execute([$A, 'BADCODEX', 'invalid', $ip]);
    $insScan->execute([$C, 'BADCODEY', 'invalid', $ip]);

    $flags = fraud_flags();
    check('6 failed scans raise a failed-account flag', has_flag($flags, 'failed_account', $emails['B']));
    check('an account with 1 failed scan is not flagged', !has_flag($flags, 'failed_account', $emails['C']));
    check('8 failed scans from one IP raise a failed-IP flag', has_flag($flags, 'failed_ip', $ip));
    check('3 accounts on one IP raise a shared-IP flag', has_flag($flags, 'shared_ip', $ip));

    // --- log filter, trend and headline numbers ---
    $rows = recent_scans('invalid', 50);
    $others = array_filter($rows, fn($r) => $r['result'] !== 'invalid');
    check('scan log filter returns only the chosen result', $rows !== [] && count($others) === 0);

    $byDay = redemptions_by_day(14);
    check('the trend covers 14 days', count($byDay) === 14);
    check("today's count includes the 11 test redemptions", end($byDay) >= 11);

    $k = monitor_kpis();
    check('headline numbers include the test codes', $k['codes']['total'] >= 12 && $k['codes']['used'] >= 11);
} finally {
    if ($users) {
        $list = implode(',', $users);
        $pdo->exec("DELETE FROM scan_attempts WHERE user_id IN ($list)");
        $pdo->exec("DELETE FROM transactions WHERE user_id IN ($list)");
    }
    if ($batchId)   { $pdo->exec("DELETE FROM codes WHERE batch_id = $batchId"); $pdo->exec("DELETE FROM batches WHERE id = $batchId"); }
    if ($productId) { $pdo->exec("DELETE FROM products WHERE id = $productId"); }
    if ($users)     { $pdo->exec('DELETE FROM users WHERE id IN (' . implode(',', $users) . ')'); }
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);