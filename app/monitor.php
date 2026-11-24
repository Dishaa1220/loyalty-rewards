<?php
require_once __DIR__ . '/db.php';


const FLAG_FAILED_SCANS_ACCOUNT = 5;    // failed scans by one account
const FLAG_FAILED_SCANS_IP      = 5;    // failed scans from one IP address
const FLAG_ACCOUNTS_PER_IP      = 3;    // different accounts using one IP address
const FLAG_REDEMPTIONS_24H      = 10;   // successful redemptions by one account

function monitor_kpis(): array
{
    $pdo = db();

    $codes = $pdo->query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status = 'unused'), 0)  AS unused,
                COALESCE(SUM(status = 'used'), 0)    AS used,
                COALESCE(SUM(status = 'blocked'), 0) AS blocked
         FROM codes"
    )->fetch();

    $payout = function (string $status) use ($pdo): array {
        $row = $pdo->query(
            "SELECT COUNT(*) AS n, COALESCE(SUM(coins), 0) AS coins, COALESCE(SUM(rupees), 0) AS rupees
             FROM payout_requests WHERE status = '$status'"
        )->fetch();
        return ['n' => (int) $row['n'], 'coins' => (int) $row['coins'], 'rupees' => (float) $row['rupees']];
    };

    $scans = $pdo->query(
        "SELECT COALESCE(SUM(result = 'success'), 0) AS ok, COALESCE(SUM(result <> 'success'), 0) AS failed
         FROM scan_attempts WHERE created_at >= NOW() - INTERVAL 24 HOUR"
    )->fetch();

    return [
        'codes' => [
            'total'   => (int) $codes['total'],
            'unused'  => (int) $codes['unused'],
            'used'    => (int) $codes['used'],
            'blocked' => (int) $codes['blocked'],
        ],
        'coins_issued'     => (int) $pdo->query("SELECT COALESCE(SUM(coins), 0) FROM transactions WHERE type = 'earn'")->fetchColumn(),
        'coins_in_wallets' => (int) $pdo->query('SELECT COALESCE(SUM(coins), 0) FROM transactions')->fetchColumn(),
        'paid'             => $payout('approved'),
        'pending'          => $payout('pending'),
        'scans_ok'         => (int) $scans['ok'],
        'scans_failed'     => (int) $scans['failed'],
    ];
}

// Redemptions per day for the last $days days, oldest first, zero days included
function redemptions_by_day(int $days = 14): array
{
    $pdo   = db();
    $today = $pdo->query('SELECT CURDATE()')->fetchColumn();

    $rows = $pdo->query(
        "SELECT DATE(redeemed_at) AS d, COUNT(*) AS n
         FROM codes
         WHERE status = 'used' AND redeemed_at >= (CURDATE() - INTERVAL " . ($days - 1) . " DAY)
         GROUP BY DATE(redeemed_at)"
    )->fetchAll();

    $counts = [];
    foreach ($rows as $r) {
        $counts[$r['d']] = (int) $r['n'];
    }

    $result = [];
    $base   = new DateTimeImmutable($today);
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = $base->modify("-$i day")->format('Y-m-d');
        $result[$d] = $counts[$d] ?? 0;
    }
    return $result;
}

function product_stats(): array
{
    return db()->query(
        "SELECT p.id, p.name, p.is_active,
                COUNT(c.id) AS generated,
                COALESCE(SUM(c.status = 'used'), 0)    AS used,
                COALESCE(SUM(c.status = 'blocked'), 0) AS blocked,
                COALESCE(SUM(CASE WHEN c.status = 'used' THEN c.coins_value END), 0) AS coins_issued
         FROM products p LEFT JOIN codes c ON c.product_id = p.id
         GROUP BY p.id, p.name, p.is_active
         ORDER BY p.name"
    )->fetchAll();
}

function batch_stats(int $limit = 20): array
{
    $stmt = db()->prepare(
        "SELECT b.id, b.created_at, p.name AS product_name,
                COUNT(c.id) AS generated,
                COALESCE(SUM(c.status = 'unused'), 0)  AS unused,
                COALESCE(SUM(c.status = 'used'), 0)    AS used,
                COALESCE(SUM(c.status = 'blocked'), 0) AS blocked
         FROM batches b
         JOIN products p ON p.id = b.product_id
         LEFT JOIN codes c ON c.batch_id = b.id
         GROUP BY b.id, b.created_at, p.name
         ORDER BY b.id DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function flag_rows(string $sql, int $threshold): array
{
    $stmt = db()->prepare($sql);
    $stmt->bindValue(':t', $threshold, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Rule-based alerts for a human to review. Each flag has: type, severity, title, subject, detail.
function fraud_flags(): array
{
    $flags = [];

    // 1. One account with many failed scans
    $rows = flag_rows(
        "SELECT u.name, u.email, COUNT(*) AS n
         FROM scan_attempts s JOIN users u ON u.id = s.user_id
         WHERE s.result <> 'success' AND s.created_at >= NOW() - INTERVAL 24 HOUR
         GROUP BY s.user_id, u.name, u.email
         HAVING COUNT(*) >= :t
         ORDER BY n DESC",
        FLAG_FAILED_SCANS_ACCOUNT
    );
    foreach ($rows as $r) {
        $flags[] = [
            'type' => 'failed_account', 'severity' => 'high', 'title' => 'Many failed scans (account)',
            'subject' => $r['email'], 'detail' => "{$r['name']}: {$r['n']} failed scans in 24 hours",
        ];
    }

    // 2. One IP address with many failed scans
    $rows = flag_rows(
        "SELECT ip_address, COUNT(*) AS n, COUNT(DISTINCT user_id) AS accounts
         FROM scan_attempts
         WHERE result <> 'success' AND ip_address IS NOT NULL AND created_at >= NOW() - INTERVAL 24 HOUR
         GROUP BY ip_address
         HAVING COUNT(*) >= :t
         ORDER BY n DESC",
        FLAG_FAILED_SCANS_IP
    );
    foreach ($rows as $r) {
        $flags[] = [
            'type' => 'failed_ip', 'severity' => 'high', 'title' => 'Many failed scans (IP address)',
            'subject' => $r['ip_address'], 'detail' => "{$r['n']} failed scans from {$r['accounts']} account(s) in 24 hours",
        ];
    }

    // 3. One IP address used by several accounts
    $rows = flag_rows(
        "SELECT ip_address, COUNT(DISTINCT user_id) AS accounts
         FROM scan_attempts
         WHERE ip_address IS NOT NULL AND user_id IS NOT NULL AND created_at >= NOW() - INTERVAL 24 HOUR
         GROUP BY ip_address
         HAVING COUNT(DISTINCT user_id) >= :t
         ORDER BY accounts DESC",
        FLAG_ACCOUNTS_PER_IP
    );
    foreach ($rows as $r) {
        $flags[] = [
            'type' => 'shared_ip', 'severity' => 'medium', 'title' => 'Several accounts on one IP address',
            'subject' => $r['ip_address'], 'detail' => "{$r['accounts']} different accounts scanned from this address in 24 hours",
        ];
    }

    // 4. One account redeeming unusually many codes
    $rows = flag_rows(
        "SELECT u.name, u.email, COUNT(*) AS n
         FROM codes c JOIN users u ON u.id = c.redeemed_by
         WHERE c.redeemed_at >= NOW() - INTERVAL 24 HOUR
         GROUP BY c.redeemed_by, u.name, u.email
         HAVING COUNT(*) >= :t
         ORDER BY n DESC",
        FLAG_REDEMPTIONS_24H
    );
    foreach ($rows as $r) {
        $flags[] = [
            'type' => 'fast_redeemer', 'severity' => 'medium', 'title' => 'Unusually many redemptions',
            'subject' => $r['email'], 'detail' => "{$r['name']}: {$r['n']} codes redeemed in 24 hours",
        ];
    }

    return $flags;
}

// Latest scan attempts, optionally only one result type
function recent_scans(string $result = '', int $limit = 50): array
{
    $sql = 'SELECT s.id, s.created_at, s.result, s.code_uid_tried, s.ip_address, u.name, u.email
            FROM scan_attempts s LEFT JOIN users u ON u.id = s.user_id';
    if ($result !== '') {
        $sql .= ' WHERE s.result = :result';
    }
    $sql .= ' ORDER BY s.id DESC LIMIT :limit';

    $stmt = db()->prepare($sql);
    if ($result !== '') {
        $stmt->bindValue(':result', $result);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Block every still-unused code of a batch. Returns how many were blocked.
function block_batch(int $batchId): int
{
    $stmt = db()->prepare("UPDATE codes SET status = 'blocked' WHERE batch_id = ? AND status = 'unused'");
    $stmt->execute([$batchId]);
    return $stmt->rowCount();
}

// Restore blocked codes of a batch (they were never redeemed). Returns how many were restored.
function unblock_batch(int $batchId): int
{
    $stmt = db()->prepare(
        "UPDATE codes SET status = 'unused' WHERE batch_id = ? AND status = 'blocked' AND redeemed_by IS NULL"
    );
    $stmt->execute([$batchId]);
    return $stmt->rowCount();
}