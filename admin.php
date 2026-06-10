<?php
session_start();
require __DIR__ . '/config.php';

// --- Authentication -----------------------------------------------------------

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    }
    $loginError = 'Incorrect password.';
}

if (empty($_SESSION['admin'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login — <?= htmlspecialchars(SITE_TITLE) ?></title>
        <link rel="stylesheet" href="assets/style.css">
    </head>
    <body>
    <div class="container container--narrow">
        <h1>Admin Login</h1>
        <?php if ($loginError): ?>
            <div class="flash flash--error"><?= htmlspecialchars($loginError) ?></div>
        <?php endif ?>
        <form method="post" class="login-form">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autofocus required>
            <button type="submit" class="btn btn--primary">Log in</button>
        </form>
        <p><a href="index.php">← Back to wishlist</a></p>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// --- Authenticated admin view -------------------------------------------------

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database connection failed.');
}

$gifts = $pdo->query('
    SELECT g.*, COUNT(c.id) AS claimed_count
    FROM gifts g
    LEFT JOIN claims c ON c.gift_id = g.id
    GROUP BY g.id
    ORDER BY g.sort_order ASC, g.created_at ASC
')->fetchAll(PDO::FETCH_ASSOC);

// Load claims per gift for the expandable section
$claimsMap = [];
$claimRows = $pdo->query('SELECT * FROM claims ORDER BY claimed_at ASC')->fetchAll(PDO::FETCH_ASSOC);
foreach ($claimRows as $row) {
    $claimsMap[$row['gift_id']][] = $row;
}

// Load gift being edited (if ?edit=ID)
$editGift = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM gifts WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editGift = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= htmlspecialchars(SITE_TITLE) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <header class="admin-header">
        <h1>Admin — <?= htmlspecialchars(SITE_TITLE) ?></h1>
        <nav>
            <a href="index.php">View wishlist</a>
            <a href="logout.php">Log out</a>
        </nav>
    </header>

    <?php if ($msg === 'saved'): ?>
        <div class="flash flash--success">Gift saved.</div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="flash flash--success">Gift deleted.</div>
    <?php elseif ($msg === 'unclaimed'): ?>
        <div class="flash flash--success">Claim removed.</div>
    <?php elseif ($err === 'invalid'): ?>
        <div class="flash flash--error">Something went wrong. Please try again.</div>
    <?php endif ?>

    <!-- Add / Edit gift form -->
    <section class="admin-section">
        <h2><?= $editGift ? 'Edit Gift' : 'Add Gift' ?></h2>
        <form method="post" action="admin_save.php" class="gift-form">
            <input type="hidden" name="action" value="<?= $editGift ? 'update' : 'create' ?>">
            <?php if ($editGift): ?>
                <input type="hidden" name="id" value="<?= (int) $editGift['id'] ?>">
            <?php endif ?>

            <div class="form-row">
                <label for="name">Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" required maxlength="255"
                       value="<?= htmlspecialchars($editGift['name'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="url">Link (URL)</label>
                <input type="url" id="url" name="url" maxlength="1000"
                       value="<?= htmlspecialchars($editGift['url'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?= htmlspecialchars($editGift['description'] ?? '') ?></textarea>
            </div>
            <div class="form-row form-row--short">
                <label for="quantity">Quantity</label>
                <input type="number" id="quantity" name="quantity" min="1" max="99" required
                       value="<?= htmlspecialchars((string)($editGift['quantity'] ?? 1)) ?>">
            </div>
            <div class="form-row form-row--short">
                <label for="sort_order">Sort order</label>
                <input type="number" id="sort_order" name="sort_order" min="0"
                       value="<?= htmlspecialchars((string)($editGift['sort_order'] ?? 0)) ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><?= $editGift ? 'Save changes' : 'Add gift' ?></button>
                <?php if ($editGift): ?>
                    <a href="admin.php" class="btn btn--cancel">Cancel</a>
                <?php endif ?>
            </div>
        </form>
    </section>

    <!-- Gift list -->
    <section class="admin-section">
        <h2>All Gifts</h2>
        <?php if (empty($gifts)): ?>
            <p class="empty-state">No gifts yet.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Qty</th>
                    <th>Claimed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($gifts as $gift):
                $giftClaims = $claimsMap[$gift['id']] ?? [];
            ?>
                <tr>
                    <td>
                        <?php if ($gift['url']): ?>
                            <a href="<?= htmlspecialchars($gift['url']) ?>" target="_blank" rel="noopener">
                                <?= htmlspecialchars($gift['name']) ?>
                            </a>
                        <?php else: ?>
                            <?= htmlspecialchars($gift['name']) ?>
                        <?php endif ?>
                        <?php if ($gift['description']): ?>
                            <p class="admin-table__desc"><?= htmlspecialchars($gift['description']) ?></p>
                        <?php endif ?>
                    </td>
                    <td><?= (int) $gift['quantity'] ?></td>
                    <td>
                        <?= (int) $gift['claimed_count'] ?>
                        <?php if (!empty($giftClaims)): ?>
                            <button class="btn btn--tiny" onclick="toggleClaims(<?= (int)$gift['id'] ?>)">details</button>
                            <ul class="claim-details" id="claims-<?= (int)$gift['id'] ?>" style="display:none">
                                <?php foreach ($giftClaims as $claim): ?>
                                    <li>
                                        <?= $claim['claimer_name'] ? htmlspecialchars($claim['claimer_name']) : '<em>anonymous</em>' ?>
                                        — <?= htmlspecialchars($claim['claimed_at']) ?>
                                        <form method="post" action="admin_save.php" style="display:inline">
                                            <input type="hidden" name="action" value="unclaim">
                                            <input type="hidden" name="claim_id" value="<?= (int)$claim['id'] ?>">
                                            <button type="submit" class="btn btn--danger btn--tiny"
                                                    onclick="return confirm('Remove this claim?')">remove</button>
                                        </form>
                                    </li>
                                <?php endforeach ?>
                            </ul>
                        <?php endif ?>
                    </td>
                    <td class="admin-table__actions">
                        <a href="admin.php?edit=<?= (int) $gift['id'] ?>" class="btn btn--small">Edit</a>
                        <form method="post" action="admin_save.php" style="display:inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $gift['id'] ?>">
                            <button type="submit" class="btn btn--danger btn--small"
                                    onclick="return confirm('Delete this gift and all its claims?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <?php endif ?>
    </section>
</div>

<script>
function toggleClaims(id) {
    var el = document.getElementById('claims-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
