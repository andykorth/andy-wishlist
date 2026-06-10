<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/config.php';

$giftId      = isset($_POST['gift_id']) ? (int) $_POST['gift_id'] : 0;
$claimerName = isset($_POST['claimer_name']) ? trim($_POST['claimer_name']) : '';

if ($giftId <= 0) {
    header('Location: index.php?err=invalid');
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    header('Location: index.php?err=invalid');
    exit;
}

// Lock the row and check remaining quantity atomically
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('
        SELECT g.quantity, COUNT(c.id) AS claimed_count
        FROM gifts g
        LEFT JOIN claims c ON c.gift_id = g.id
        WHERE g.id = ?
        GROUP BY g.id
        FOR UPDATE
    ');
    $stmt->execute([$giftId]);
    $gift = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gift) {
        $pdo->rollBack();
        header('Location: index.php?err=invalid');
        exit;
    }

    if ((int) $gift['claimed_count'] >= (int) $gift['quantity']) {
        $pdo->rollBack();
        header('Location: index.php?err=already_claimed');
        exit;
    }

    $insert = $pdo->prepare('INSERT INTO claims (gift_id, claimer_name) VALUES (?, ?)');
    $insert->execute([
        $giftId,
        $claimerName !== '' ? $claimerName : null,
    ]);

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    header('Location: index.php?err=invalid');
    exit;
}

header('Location: index.php?msg=claimed');
exit;
