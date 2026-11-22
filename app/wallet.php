<?php
require_once __DIR__ . '/db.php';

function count_transactions(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

// One page of a user's ledger, newest first
function get_history(int $userId, int $limit, int $offset): array
{
    $stmt = db()->prepare(
        'SELECT t.id, t.type, t.coins, t.note, t.created_at, p.name AS product_name
         FROM transactions t
         LEFT JOIN codes c ON c.id = t.code_id
         LEFT JOIN products p ON p.id = c.product_id
         WHERE t.user_id = :uid
         ORDER BY t.id DESC
         LIMIT :limit OFFSET :offset'
    );
    // LIMIT and OFFSET must be bound as integers
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Total coins earned and number of box codes claimed
function wallet_summary(int $userId): array
{
    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(CASE WHEN type = 'earn' THEN coins ELSE 0 END), 0) AS earned,
                COALESCE(SUM(type = 'earn' AND code_id IS NOT NULL), 0) AS claimed
         FROM transactions WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return ['earned' => (int) $row['earned'], 'claimed' => (int) $row['claimed']];
}