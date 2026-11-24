<?php
require_once __DIR__ . '/db.php';

const MAX_FAILED_ATTEMPTS = 10;
const LOCKOUT_MINUTES     = 15;

function client_ip(): ?string
{
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

// Too many failed scans from this account or this IP recently?
function too_many_failures(int $userId): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM scan_attempts
         WHERE result <> 'success'
           AND created_at > (NOW() - INTERVAL " . LOCKOUT_MINUTES . " MINUTE)
           AND (user_id = ? OR ip_address = ?)"
    );
    $stmt->execute([$userId, client_ip()]);
    return (int) $stmt->fetchColumn() >= MAX_FAILED_ATTEMPTS;
}

// Record a scan attempt (never lets a logging problem break the page)
function log_scan(?int $userId, string $tried, string $result): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO scan_attempts (user_id, code_uid_tried, result, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, substr($tried, 0, 100), $result, client_ip()]);
    } catch (Throwable $t) {
        error_log('log_scan failed: ' . $t->getMessage());
    }
}

// A customer's balance = sum of their ledger
function get_balance(int $userId): int
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(coins), 0) FROM transactions WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function find_code(string $uid): ?array
{
    $stmt = db()->prepare(
        'SELECT c.id, c.status, c.coins_value, c.redeemed_by, c.redeemed_at, p.name AS product_name
         FROM codes c JOIN products p ON p.id = c.product_id
         WHERE c.code_uid = ?'
    );
    $stmt->execute([$uid]);
    return $stmt->fetch() ?: null;
}

/**
 * Try to redeem a code for a user.
 * Returns ['result' => 'success', 'coins' => N]
 *      or ['result' => 'already_used' | 'blocked' | 'invalid']
 */
function redeem_code(string $uid, int $userId): array
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        // The atomic step: only ONE caller can ever change unused -> used
        $stmt = $pdo->prepare(
            "UPDATE codes SET status = 'used', redeemed_by = ?, redeemed_at = NOW()
             WHERE code_uid = ? AND status = 'unused'"
        );
        $stmt->execute([$userId, $uid]);

        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            $code = find_code($uid);
            if ($code === null) {
                return ['result' => 'invalid'];
            }
            return ['result' => $code['status'] === 'blocked' ? 'blocked' : 'already_used'];
        }

        $stmt = $pdo->prepare('SELECT id, coins_value FROM codes WHERE code_uid = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();

        $stmt = $pdo->prepare(
            "INSERT INTO transactions (user_id, type, coins, code_id, note) VALUES (?, 'earn', ?, ?, 'Redeemed box code')"
        );
        $stmt->execute([$userId, $row['coins_value'], $row['id']]);

        $pdo->commit();
        return ['result' => 'success', 'coins' => (int) $row['coins_value']];
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $t;
    }
}