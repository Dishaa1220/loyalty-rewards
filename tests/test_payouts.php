<?php

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/settings.php';
require_once __DIR__ . '/../app/redeem.php';
require_once __DIR__ . '/../app/payouts.php';

$passed = 0;
$failed = 0;

function check(string $name, bool $condition): void
{
    global $passed, $failed;
    if ($condition) { $passed++; echo "PASS  $name\n"; }
    else            { $failed++; echo "FAIL  $name\n"; }
}

$pdo  = db();
$tag  = bin2hex(random_bytes(4));
$user = null;

try {
    // --- pure functions ---
    check('15 coins at 0.50 = 7.50', rupees_from_coins(15, '0.50') === '7.50');
    check('100 coins at 1.00 = 100.00', rupees_from_coins(100, '1.00') === '100.00');
    check('3 coins at 0.33 = 0.99 (no float error)', rupees_from_coins(3, '0.33') === '0.99');
    check('valid UPI accepted', valid_upi('ravi@okbank') === true);
    check('UPI without @ rejected', valid_upi('ravi') === false);
    check('UPI with spaces rejected', valid_upi('ra vi@okbank') === false);
    check('injection text rejected as UPI', valid_upi("x'; DROP TABLE users; --@bank") === false);

    // --- temporary user with 1000 coins ---
    $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, 'x', 'customer')")
        ->execute(['Payout Test', "payout-$tag@example.test"]);
    $user = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO transactions (user_id, type, coins, note) VALUES (?, 'adjust', 1000, 'test balance')")
        ->execute([$user]);

    $min  = min_payout_coins();
    $rate = get_setting('coin_to_rupee_rate', '1.00');

    // --- rejected requests change nothing ---
    check('below the minimum is rejected', request_payout($user, $min - 1, 'test@okbank')['ok'] === false);
    check('more than the balance is rejected', request_payout($user, 1001, 'test@okbank')['ok'] === false);
    check('invalid UPI is rejected', request_payout($user, $min, 'not-a-upi')['ok'] === false);
    check('balance still 1000 after rejected requests', get_balance($user) === 1000);

    // --- a valid request ---
    $r1 = request_payout($user, $min, 'test@okbank');
    check('valid request is accepted', $r1['ok'] === true);
    $id1 = $r1['id'] ?? 0;
    check("coins are deducted at once (1000 - $min)", get_balance($user) === 1000 - $min);

    $stmt = $pdo->prepare('SELECT status, coins, rupees FROM payout_requests WHERE id = ?');
    $stmt->execute([$id1]);
    $row = $stmt->fetch();
    check('request is pending with the locked rupee amount',
        $row['status'] === 'pending' && (int) $row['coins'] === $min && $row['rupees'] === rupees_from_coins($min, $rate));

    $stmt = $pdo->prepare('SELECT type, coins FROM transactions WHERE payout_request_id = ?');
    $stmt->execute([$id1]);
    $tx = $stmt->fetchAll();
    check('one linked ledger row of -coins', count($tx) === 1 && $tx[0]['type'] === 'redeem' && (int) $tx[0]['coins'] === -$min);

    check('a second request while pending is rejected', request_payout($user, $min, 'test@okbank')['ok'] === false);

    // --- approve ---
    check('owner can approve a pending request', approve_payout($id1, $user, 'paid') === true);
    check('approving does not change the balance', get_balance($user) === 1000 - $min);
    check('approving twice is refused', approve_payout($id1, $user, null) === false);
    check('rejecting an approved request is refused', reject_payout($id1, $user, null) === false);
    check('no refund was made for the approved request', get_balance($user) === 1000 - $min);

    // --- reject and refund ---
    $r2 = request_payout($user, $min, 'test@okbank');
    check('a new request is allowed after the first was handled', $r2['ok'] === true);
    $id2 = $r2['id'] ?? 0;
    check('balance drops again', get_balance($user) === 1000 - 2 * $min);

    check('owner can reject a pending request', reject_payout($id2, $user, 'wrong UPI') === true);
    check('rejecting refunds the coins', get_balance($user) === 1000 - $min);
    check('rejecting twice is refused', reject_payout($id2, $user, null) === false);
    check('approving a rejected request is refused', approve_payout($id2, $user, null) === false);
    check('balance is not refunded a second time', get_balance($user) === 1000 - $min);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE payout_request_id = ? AND type = 'adjust'");
    $stmt->execute([$id2]);
    check('exactly one refund row exists', (int) $stmt->fetchColumn() === 1);

    // --- database safety net against a double refund ---
    $refused = false;
    try {
        $pdo->prepare("INSERT INTO transactions (user_id, type, coins, payout_request_id) VALUES (?, 'adjust', ?, ?)")
            ->execute([$user, $min, $id2]);
    } catch (PDOException $ex) {
        $refused = (($ex->errorInfo[1] ?? 0) === 1062);
    }
    check('database refuses a second refund row for the same request', $refused);
} finally {
    if ($user) {
        $pdo->exec("DELETE FROM transactions WHERE user_id = $user");
        $pdo->exec("DELETE FROM payout_requests WHERE user_id = $user");
        $pdo->exec("DELETE FROM users WHERE id = $user");
    }
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);