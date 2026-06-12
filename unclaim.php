<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./');
    exit;
}

require __DIR__ . '/config.php';

$claimId = isset($_POST['claim_id']) ? (int) $_POST['claim_id'] : 0;

if ($claimId <= 0) {
    header('Location: ./?err=invalid');
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
    header('Location: ./?err=invalid');
    exit;
}

$stmt = $pdo->prepare('DELETE FROM claims WHERE id = ?');
$stmt->execute([$claimId]);

header('Location: ./?msg=unclaimed');
exit;
