<?php
require __DIR__ . '/config.php';

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

$stmt = $pdo->query('
    SELECT g.*, COUNT(c.id) AS claimed_count
    FROM gifts g
    LEFT JOIN claims c ON c.gift_id = g.id
    GROUP BY g.id
    ORDER BY g.sort_order ASC, g.created_at ASC
');
$gifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(SITE_TITLE) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <header>
        <h1><?= htmlspecialchars(SITE_TITLE) ?></h1>
        <p class="subtitle">See something you'd like to get me? Click "I'll get this" to claim it.</p>
    </header>

    <?php if ($msg === 'claimed'): ?>
        <div class="flash flash--success">Gift claimed — thank you!</div>
    <?php elseif ($err === 'already_claimed'): ?>
        <div class="flash flash--error">Sorry, that gift was just fully claimed by someone else.</div>
    <?php elseif ($err === 'invalid'): ?>
        <div class="flash flash--error">Something went wrong. Please try again.</div>
    <?php endif ?>

    <?php if (empty($gifts)): ?>
        <p class="empty-state">No gifts listed yet — check back soon!</p>
    <?php else: ?>
        <ul class="gift-list">
        <?php foreach ($gifts as $gift):
            $qty      = (int) $gift['quantity'];
            $claimed  = (int) $gift['claimed_count'];
            $remaining = $qty - $claimed;
            $full     = $remaining <= 0;
        ?>
            <li class="gift-card <?= $full ? 'gift-card--claimed' : '' ?>">
                <div class="gift-card__body">
                    <h2 class="gift-card__name">
                        <?php if ($gift['url']): ?>
                            <a href="<?= htmlspecialchars($gift['url']) ?>" target="_blank" rel="noopener">
                                <?= htmlspecialchars($gift['name']) ?>
                            </a>
                        <?php else: ?>
                            <?= htmlspecialchars($gift['name']) ?>
                        <?php endif ?>
                    </h2>

                    <?php if ($gift['description']): ?>
                        <p class="gift-card__desc"><?= nl2br(htmlspecialchars($gift['description'])) ?></p>
                    <?php endif ?>

                    <p class="gift-card__qty">
                        <?php if ($qty === 1): ?>
                            <?= $full ? '<span class="badge badge--claimed">Claimed</span>' : '' ?>
                        <?php else: ?>
                            <span class="badge <?= $full ? 'badge--claimed' : 'badge--partial' ?>">
                                <?= $claimed ?> of <?= $qty ?> claimed
                            </span>
                        <?php endif ?>
                    </p>
                </div>

                <?php if (!$full): ?>
                <div class="gift-card__claim">
                    <button class="btn btn--claim" onclick="toggleClaim(this)">I'll get this</button>
                    <form class="claim-form" action="claim.php" method="post" style="display:none">
                        <input type="hidden" name="gift_id" value="<?= (int) $gift['id'] ?>">
                        <input type="text" name="claimer_name" placeholder="Your name (optional)" maxlength="255" autocomplete="name">
                        <button type="submit" class="btn btn--confirm">Confirm</button>
                        <button type="button" class="btn btn--cancel" onclick="toggleClaim(this.closest('.gift-card__claim').querySelector('.btn--claim'), true)">Cancel</button>
                    </form>
                </div>
                <?php endif ?>
            </li>
        <?php endforeach ?>
        </ul>
    <?php endif ?>
</div>

<script>
function toggleClaim(btn, cancel) {
    var card = btn.closest('.gift-card__claim');
    var form = card.querySelector('.claim-form');
    if (cancel) {
        form.style.display = 'none';
        card.querySelector('.btn--claim').style.display = '';
    } else {
        btn.style.display = 'none';
        form.style.display = 'flex';
        form.querySelector('input[name="claimer_name"]').focus();
    }
}
</script>
</body>
</html>
