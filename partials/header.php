<?php
// Requires session_start() and config.php to have been called by the including page.
$_hCurrentPage = basename($_SERVER['PHP_SELF']);
$_hIsAdmin     = !empty($_SESSION['admin']);
?>
<header class="site-header">
    <a class="site-header__title" href="index.php"><?= htmlspecialchars(SITE_TITLE) ?></a>
    <nav class="site-header__nav">
        <a href="index.php"<?= $_hCurrentPage === 'index.php' ? ' class="active"' : '' ?>>Wishlist</a>
        <?php if ($_hIsAdmin): ?>
            <a href="logout.php">Log out</a>
        <?php else: ?>
            <a href="admin.php"<?= $_hCurrentPage === 'admin.php' ? ' class="active"' : '' ?>>Admin</a>
        <?php endif ?>
    </nav>
</header>
