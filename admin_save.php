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
require __DIR__ . '/partials/schema.php';

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

ensureSchema($pdo);

$action = $_POST['action'] ?? '';

/** The posted person_id, if it names a real person; otherwise 0. */
function postedPersonId(PDO $pdo): int
{
    $stmt = $pdo->prepare('SELECT id FROM people WHERE id = ?');
    $stmt->execute([(int) ($_POST['person_id'] ?? 0)]);
    return (int) $stmt->fetchColumn();
}

switch ($action) {

    case 'create':
        $name       = trim($_POST['name'] ?? '');
        $url        = trim($_POST['url'] ?? '');
        $imageUrl   = trim($_POST['image_url'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $quantity   = max(1, (int) ($_POST['quantity'] ?? 1));
        $sortOrder  = max(0, (int) ($_POST['sort_order'] ?? 0));
        $personId   = postedPersonId($pdo);

        if ($name === '' || $personId === 0) {
            header('Location: admin.php?err=invalid');
            exit;
        }

        $stmt = $pdo->prepare('
            INSERT INTO gifts (person_id, name, url, image_url, description, quantity, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $personId,
            $name,
            $url !== '' ? $url : null,
            $imageUrl !== '' ? $imageUrl : null,
            $desc !== '' ? $desc : null,
            $quantity,
            $sortOrder,
        ]);
        $_SESSION['admin_person'] = $personId;
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
        $personId   = postedPersonId($pdo);

        if ($id <= 0 || $name === '' || $personId === 0) {
            header('Location: admin.php?err=invalid');
            exit;
        }

        $stmt = $pdo->prepare('
            UPDATE gifts
            SET person_id = ?, name = ?, url = ?, image_url = ?, description = ?, quantity = ?, sort_order = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $personId,
            $name,
            $url !== '' ? $url : null,
            $imageUrl !== '' ? $imageUrl : null,
            $desc !== '' ? $desc : null,
            $quantity,
            $sortOrder,
            $id,
        ]);
        $_SESSION['admin_person'] = $personId;
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

    case 'archive':
    case 'unarchive':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: admin.php?err=invalid');
            exit;
        }
        $sql = $action === 'archive'
            ? 'UPDATE gifts SET archived_at = CURRENT_TIMESTAMP WHERE id = ? AND archived_at IS NULL'
            : 'UPDATE gifts SET archived_at = NULL WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        header('Location: admin.php?msg=' . ($action === 'archive' ? 'archived' : 'unarchived'));
        exit;

    case 'person_create':
    case 'person_update':
        $id        = (int) ($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $slugInput = trim($_POST['slug'] ?? '');
        $intro     = trim($_POST['intro'] ?? '');
        $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));

        if ($name === '' || ($action === 'person_update' && $id <= 0)) {
            header('Location: admin.php?err=invalid');
            exit;
        }

        $slug = uniqueSlug($pdo, $slugInput !== '' ? $slugInput : $name, $id);
        $introValue = $intro !== '' ? $intro : null;

        if ($action === 'person_create') {
            $stmt = $pdo->prepare('INSERT INTO people (name, slug, intro, sort_order) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $slug, $introValue, $sortOrder]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare('UPDATE people SET name = ?, slug = ?, intro = ?, sort_order = ? WHERE id = ?');
            $stmt->execute([$name, $slug, $introValue, $sortOrder, $id]);
        }
        $_SESSION['admin_person'] = $id;
        header('Location: admin.php?msg=person_saved');
        exit;

    case 'person_delete':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: admin.php?err=invalid');
            exit;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM gifts WHERE person_id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            header('Location: admin.php?err=person_not_empty');
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM people WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: admin.php?msg=person_deleted');
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
