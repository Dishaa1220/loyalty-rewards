<?php
// Run with:  C:\xampp\php\php.exe tests\test_wallet.php
// Uses your local loyalty_db; creates temporary rows and removes them at the end.
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/codes.php';
require_once __DIR__ . '/../app/redeem.php';
require_once __DIR__ . '/../app/wallet.php';

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
    $insUser = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, 'x', 'customer')");
    $insUser->execute(['Wallet A', "wallet-a-$tag@example.test"]);
    $userA = (int) $pdo->lastInsertId();
    $insUser->execute(['Wallet B', "wallet-b-$tag@example.test"]);
    $userB = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO products (name, coins_per_unit) VALUES (?, 25)')->execute(["TEST wallet $tag"]);
    $productId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO batches (product_id, quantity, created_by) VALUES (?, 2, ?)')->execute([$productId, $userA]);
    $batchId = (int) $pdo->lastInsertId();

    $uid1 = generate_uid();
    $uid2 = generate_uid();
    $insCode = $pdo->prepare(
        "INSERT INTO codes (code_uid, batch_id, product_id, coins_value, status) VALUES (?, ?, ?, 25, 'unused')"
    );
    $insCode->execute([$uid1, $batchId, $productId]);
    $insCode->execute([$uid2, $batchId, $productId]);

    // User A: two claims (+25 each) and one adjustment (-10)
    redeem_code($uid1, $userA);
    redeem_code($uid2, $userA);
    $pdo->prepare("INSERT INTO transactions (user_id, type, coins, note) VALUES (?, 'adjust', -10, 'test adjustment')")
        ->execute([$userA]);

    check('user A has 3 ledger rows', count_transactions($userA) === 3);
    check('user A balance is 40 (25 + 25 - 10)', get_balance($userA) === 40);

    $page1 = get_history($userA, 2, 0);
    $page2 = get_history($userA, 2, 2);
    check('page 1 holds 2 rows', count($page1) === 2);
    check('page 2 holds the last 1 row', count($page2) === 1);
    check('newest row comes first (the adjustment)', $page1[0]['type'] === 'adjust' && (int) $page1[0]['coins'] === -10);
    check('claim rows show the product name', $page1[1]['product_name'] === "TEST wallet $tag");

    $summary = wallet_summary($userA);
    check('summary: 50 coins earned', $summary['earned'] === 50);
    check('summary: 2 boxes claimed', $summary['claimed'] === 2);

    check('user B has no history', count_transactions($userB) === 0 && get_history($userB, 10, 0) === []);
    check('user B balance is 0', get_balance($userB) === 0);
    $sB = wallet_summary($userB);
    check('user B summary is all zero', $sB['earned'] === 0 && $sB['claimed'] === 0);
} finally {
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