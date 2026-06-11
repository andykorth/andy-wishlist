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
require __DIR__ . '/partials/error_page.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    showErrorPage('Could not connect to the database.');
}

$statements = [
    'DROP TABLE IF EXISTS claims',
    'DROP TABLE IF EXISTS gifts',
    '
    CREATE TABLE gifts (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(255) NOT NULL,
        description TEXT,
        url         VARCHAR(1000),
        image_url   VARCHAR(1000),
        quantity    INT NOT NULL DEFAULT 1,
        sort_order  INT NOT NULL DEFAULT 0,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ',
    '
    CREATE TABLE claims (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        gift_id      INT NOT NULL,
        claimer_name VARCHAR(255),
        claimed_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ',
];

try {
    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }
} catch (PDOException $e) {
    showErrorPage('Database reset failed: ' . $e->getMessage());
}

header('Location: admin.php?msg=reset');
exit;
