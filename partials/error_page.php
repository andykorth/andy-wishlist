<?php
// Outputs a full styled error page and exits.
// Caller must have already called session_start() and require config.php.
// Usage: showErrorPage('Could not connect to database.');
function showErrorPage(string $message): never {
    $title = defined('SITE_TITLE') ? htmlspecialchars(SITE_TITLE) . ' — Error' : 'Error';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $title ?></title>
        <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
    </head>
    <body>
    <?php
    if (defined('SITE_TITLE') && session_status() === PHP_SESSION_ACTIVE) {
        require __DIR__ . '/header.php';
    }
    ?>
    <div class="container">
        <div class="error-page">
            <h2 class="error-page__title">Something went wrong</h2>
            <p class="error-page__msg"><?= htmlspecialchars($message) ?></p>
            <a href="./" class="btn">← Back to wishlist</a>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}
