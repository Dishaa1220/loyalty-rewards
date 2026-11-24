<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/redeem.php';   // for get_balance()

function min_payout_coins(): int
{
    return max(1, (int) get_setting('min_payout_coins', '100'));
}

// coins x rate, calculated in whole paise to avoid floating-point surprises. 15, '0.50' -> '7.50'
function rupees_from_coins(int $coins, string $rate): string
{
    $ratePaise = (int) round(((float) $rate) * 100);
    $paise     = $coins * $ratePaise;
    return sprintf('%d.%02d', intdiv($paise, 100), $paise % 100);
}

function valid_upi(string $upi): bool
{
    return preg_match('/^[a-zA-Z0-9._-]{2,50}@[a-zA-Z0-9]{2,30}$/', $upi) === 1;
}

/**
 * Customer asks to cash out. Coins are deducted immediately.
 * Returns ['ok' => true, 'id' => N] or ['ok' => false, 'error' => '...'].
 */
function request_payout(int $userId, int $coins, string $upi): array
{
    $min = min_payout_coins();
    if ($coins < $min) {
        return ['ok' => false, 'error' => "The minimum cash-out is $min coins."];
    }
    if (!valid_upi($upi)) {
        return ['ok' => false, 'error' => 'Please enter a valid UPI ID, like name@bank.'];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Lock this user's row: two requests from the same user now run one after the other
        $lock = $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $lock->execute([$userId]);
        $lock->fetchColumn();

        if ($coins > get_balance($userId)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'You do not have enough coins.'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payout_requests WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        if ((int) $stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'You already have a pending request. Please wait for the owner to review it.'];
        }

        $rate   = get_setting('coin_to_rupee_rate', '1.00');
        $rupees = rupees_from_coins($coins, $rate);

        $stmt = $pdo->prepare(
            'INSERT INTO payout_requests (user_id, coins, rate_at_request, rupees, payout_details)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $coins, $rate, $rupees, $upi]);
        $requestId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "INSERT INTO transactions (user_id, type, coins, payout_request_id, note)
             VALUES (?, 'redeem', ?, ?, ?)"
        );
        $stmt->execute([$userId, -$coins, $requestId, "Cash-out request #$requestId"]);

        $pdo->commit();
        return ['ok' => true, 'id' => $requestId];
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $t;
    }
}

// Owner approves (money paid outside the app). Only works on a pending request.
function approve_payout(int $requestId, int $adminId, ?string $note): bool
{
    $stmt = db()->prepare(
        "UPDATE payout_requests
         SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), admin_note = ?
         WHERE id = ? AND status = 'pending'"
    );
    $stmt->execute([$adminId, $note, $requestId]);
    return $stmt->rowCount() === 1;
}

// Owner rejects: the coins are refunded through a new ledger row.
function reject_payout(int $requestId, int $adminId, ?string $note): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE payout_requests
             SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), admin_note = ?
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([$adminId, $note, $requestId]);

        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            return false;                     // already handled by someone else
        }

        $stmt = $pdo->prepare('SELECT user_id, coins FROM payout_requests WHERE id = ?');
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        $stmt = $pdo->prepare(
            "INSERT INTO transactions (user_id, type, coins, payout_request_id, note)
             VALUES (?, 'adjust', ?, ?, ?)"
        );
        $stmt->execute([$req['user_id'], (int) $req['coins'], $requestId, "Refund: cash-out request #$requestId was rejected"]);

        $pdo->commit();
        return true;
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $t;
    }
}

function list_user_requests(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT id, coins, rupees, payout_details, status, admin_note, created_at, reviewed_at
         FROM payout_requests WHERE user_id = ? ORDER BY id DESC LIMIT 20'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// 'pending' = oldest first; anything else = the reviewed history, newest first
function list_payout_requests(string $status, int $limit = 50): array
{
    $base = 'SELECT r.id, r.coins, r.rupees, r.payout_details, r.status, r.admin_note,
                    r.created_at, r.reviewed_at, u.name, u.email
             FROM payout_requests r JOIN users u ON u.id = r.user_id';

    if ($status === 'pending') {
        return db()->query($base . " WHERE r.status = 'pending' ORDER BY r.id ASC")->fetchAll();
    }

    $stmt = db()->prepare($base . " WHERE r.status <> 'pending' ORDER BY r.reviewed_at DESC, r.id DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function count_pending_payouts(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM payout_requests WHERE status = 'pending'")->fetchColumn();
}