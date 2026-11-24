<?php
// Run with:  C:\xampp\php\php.exe tests\test_redeem.php
// Uses your local loyalty_db; creates temporary rows and removes them at the end.
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/codes.php';
require_once __DIR__ . '/../app/redeem.php';

$passed = 0;
$failed = 0;

function check(string $name, bool $condition): void
{
    global $passed, $failed;
    if ($condition) { $passed++; echo "PASS  $name\n"; }
    else            { $failed++; echo "FAIL  $name\n"; }
}

$pdo = db();
$tag = bin2hex(random_bytes(4));
$userA = $userB = $productId = $batchId = null;

try {
    // --- set up temporary data ---
    $insUser = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, 'x', 'customer')");
    $insUser->execute(['Test A', "test-a-$tag@example.test"]);
    $userA = (int) $pdo->lastInsertId();
    $insUser->execute(['Test B', "test-b-$tag@example.test"]);
    $userB = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO products (name, coins_per_unit) VALUES (?, 25)')->execute(["TEST product $tag"]);
    $productId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO batches (product_id, quantity, created_by) VALUES (?, 2, ?)')->execute([$productId, $userA]);
    $batchId = (int) $pdo->lastInsertId();

    $uidGood    = generate_uid();
    $uidBlocked = generate_uid();
    $insCode = $pdo->prepare(
        'INSERT INTO codes (code_uid, batch_id, product_id, coins_value, status) VALUES (?, ?, ?, 25, ?)'
    );
    $insCode->execute([$uidGood, $batchId, $productId, 'unused']);
    $insCode->execute([$uidBlocked, $batchId, $productId, 'blocked']);

    // --- tests ---
    $r1 = redeem_code($uidGood, $userA);
    check('first redemption succeeds', $r1['result'] === 'success');
    check('first redemption gives the code value (25)', ($r1['coins'] ?? 0) === 25);
    check('balance of user A is 25', get_balance($userA) === 25);

    $r2 = redeem_code($uidGood, $userA);
    check('same user cannot redeem the code twice', $r2['result'] === 'already_used');
    check('balance is still 25 after the repeat', get_balance($userA) === 25);

    $r3 = redeem_code($uidGood, $userB);
    check('a different user cannot redeem a used code', $r3['result'] === 'already_used');
    check('user B balance is 0', get_balance($userB) === 0);

    $code = find_code($uidGood);
    check('code is marked used by user A', $code['status'] === 'used' && (int) $code['redeemed_by'] === $userA);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE code_id = ?');
    $stmt->execute([$code['id']]);
    check('exactly one ledger row exists for the code', (int) $stmt->fetchColumn() === 1);

    $rb = redeem_code($uidBlocked, $userA);
    check('a blocked code cannot be redeemed', $rb['result'] === 'blocked');
    check('balance unchanged after blocked attempt', get_balance($userA) === 25);

    $ri = redeem_code('ZZZZZZZZZZZZ', $userA);
    check('an unknown code is invalid', $ri['result'] === 'invalid');

    // Database safety net: a second ledger row for the same code must be refused
    $refused = false;
    try {
        $pdo->prepare("INSERT INTO transactions (user_id, type, coins, code_id) VALUES (?, 'earn', 25, ?)")
            ->execute([$userB, $code['id']]);
    } catch (PDOException $ex) {
        $refused = (($ex->errorInfo[1] ?? 0) === 1062);
    }
    check('database refuses a second ledger row for the same code', $refused);

    // Rate limiting
    for ($i = 0; $i < MAX_FAILED_ATTEMPTS; $i++) {
        log_scan($userB, 'BADCODE' . $i, 'invalid');
    }
    check('user B is locked after too many failures', too_many_failures($userB) === true);
    check('user A is not locked', too_many_failures($userA) === false);
} finally {
    // --- clean up ---
    if ($userA && $userB) {
        $pdo->exec("DELETE FROM scan_attempts WHERE user_id IN ($userA, $userB)");
        $pdo->exec("DELETE FROM transactions WHERE user_id IN ($userA, $userB)");
    }
    if ($batchId)   { $pdo->exec("DELETE FROM codes WHERE batch_id = $batchId"); $pdo->exec("DELETE FROM batches WHERE id = $batchId"); }
    if ($productId) { $pdo->exec("DELETE FROM products WHERE id = $productId"); }
    if ($userA && $userB) { $pdo->exec("DELETE FROM users WHERE id IN ($userA, $userB)"); }
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);