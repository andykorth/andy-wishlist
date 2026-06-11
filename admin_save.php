<?php
session_start();

if (empty($_SESSION['admin'])) {
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    header('Location: admin.php?err=invalid');
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'create':
        $name       = trim($_POST['name'] ?? '');
        $url        = trim($_POST['url'] ?? '');
        $imageUrl   = trim($_POST['image_url'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $quantity   = max(1, (int) ($_POST['quantity'] ?? 1));
        $sortOrder  = max(0, (int) ($_POST['sort_order'] ?? 0));

        if ($name === '') {
            header('Location: admin.php?err=invalid');
            exit;
        }

        $stmt = $pdo->prepare('
            INSERT INTO gifts (name, url, image_url, description, quantity, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $name,
            $url !== '' ? $url : null,
            $imageUrl !== '' ? $imageUrl : null,
            $desc !== '' ? $desc : null,
            $quantity,
            $sortOrder,
        ]);
        header('Location: admin.php?msg=saved');
        exit;

    case 'update':
        $id         = (int) ($_POST['id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $url        = trim($_POST['url'] ?? '');
        $imageUrl   = trim($_POST['image_url'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $quantity   = max(1, (int) ($_POST['quantity'] ?? 1));
        $sortOrder  = max(0, (int) ($_POST['sort_order'] ?? 0));

        if ($id <= 0 || $name === '') {
            header('Location: admin.php?err=invalid');
            exit;
        }

        $stmt = $pdo->prepare('
            UPDATE gifts
            SET name = ?, url = ?, image_url = ?, description = ?, quantity = ?, sort_order = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $name,
            $url !== '' ? $url : null,
            $imageUrl !== '' ? $imageUrl : null,
            $desc !== '' ? $desc : null,
            $quantity,
            $sortOrder,
            $id,
        ]);
        header('Location: admin.php?msg=saved');
        exit;

    case 'delete':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: admin.php?err=invalid');
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM gifts WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: admin.php?msg=deleted');
        exit;

    case 'unclaim':
        $claimId = (int) ($_POST['claim_id'] ?? 0);
        if ($claimId <= 0) {
            header('Location: admin.php?err=invalid');
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM claims WHERE id = ?');
        $stmt->execute([$claimId]);
        header('Location: admin.php?msg=unclaimed');
        exit;

    default:
        header('Location: admin.php?err=invalid');
        exit;
}
