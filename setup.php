<?php
// =============================================================================
// WARNING: Run this script ONCE to initialize the database, then DELETE IT.
// Re-running it will DROP and recreate both tables, erasing all data.
// =============================================================================

if (!file_exists(__DIR__ . '/config.php')) {
    die('config.php not found. Copy config.example.php to config.php and fill in your values.');
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
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

$statements = [
    'Drop claims'  => 'DROP TABLE IF EXISTS claims',
    'Drop gifts'   => 'DROP TABLE IF EXISTS gifts',

    'Create gifts' => '
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

    'Create claims' => '
        CREATE TABLE claims (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            gift_id      INT NOT NULL,
            claimer_name VARCHAR(255),
            claimed_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ',
];

foreach ($statements as $label => $sql) {
    try {
        $pdo->exec($sql);
        echo htmlspecialchars($label) . ": OK<br>\n";
    } catch (PDOException $e) {
        die(htmlspecialchars($label) . ' FAILED: ' . htmlspecialchars($e->getMessage()));
    }
}

echo "<br><strong>Setup complete.</strong> Delete this file before going live.\n";
