<?php
session_start();
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
    showErrorPage('Could not connect to the database. Please try again later.');
}

$stmt = $pdo->query('
    SELECT g.*, COUNT(c.id) AS claimed_count
    FROM gifts g
    LEFT JOIN claims c ON c.gift_id = g.id
    GROUP BY g.id
    ORDER BY g.sort_order ASC, g.created_at ASC
');
$gifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch individual claims so we can render the public unclaim UI
$claimsMap = [];
$claimRows = $pdo->query('SELECT * FROM claims ORDER BY claimed_at ASC')->fetchAll(PDO::FETCH_ASSOC);
foreach ($claimRows as $row) {
    $claimsMap[$row['gift_id']][] = $row;
}

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
<?php require __DIR__ . '/partials/header.php' ?>
<div class="container">
    <p class="subtitle">See something you'd like to get me? Click "I'll get this" to claim it.</p>

    <?php if ($msg === 'claimed'): ?>
        <div class="flash flash--success">Gift claimed — thank you!</div>
    <?php elseif ($msg === 'unclaimed'): ?>
        <div class="flash flash--success">Claim removed.</div>
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
            $giftId    = (int) $gift['id'];
            $qty       = (int) $gift['quantity'];
            $claimed   = (int) $gift['claimed_count'];
            $remaining = $qty - $claimed;
            $full      = $remaining <= 0;
            $giftClaims = $claimsMap[$giftId] ?? [];
            $domain = '';
            if ($gift['url']) {
                $host = parse_url($gift['url'], PHP_URL_HOST) ?: '';
                $domain = preg_replace('/^www\./', '', $host);
            }
        ?>
            <li class="gift-card <?= $full ? 'gift-card--claimed' : '' ?>">
                <div class="gift-card__body">
                    <?php if ($gift['image_url']): ?>
                    <img class="gift-card__image"
                         src="<?= htmlspecialchars($gift['image_url']) ?>"
                         alt="<?= htmlspecialchars($gift['name']) ?>"
                         loading="lazy">
                    <?php endif ?>
                    <div class="gift-card__info">
                        <h2 class="gift-card__name">
                            <?php if ($gift['url']): ?>
                                <a href="<?= htmlspecialchars($gift['url']) ?>" target="_blank" rel="noopener">
                                    <?= htmlspecialchars($gift['name']) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($gift['name']) ?>
                            <?php endif ?>
                        </h2>
                        <?php if ($domain): ?>
                            <a class="badge badge--domain" href="<?= htmlspecialchars($gift['url']) ?>" target="_blank" rel="noopener">
                                <?= htmlspecialchars($domain) ?> ↗
                            </a>
                        <?php endif ?>

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
                </div>

                <div class="gift-card__actions">
                    <?php if (!$full): ?>
                    <div class="gift-card__claim">
                        <button class="btn btn--claim" onclick="toggleClaim(this)">I'll get this</button>
                        <form class="claim-form" action="claim.php" method="post" style="display:none">
                            <input type="hidden" name="gift_id" value="<?= $giftId ?>">
                            <input type="text" name="claimer_name" placeholder="Your name (optional)" maxlength="255" autocomplete="name">
                            <button type="submit" class="btn btn--confirm">Confirm</button>
                            <button type="button" class="btn btn--cancel" onclick="toggleClaim(this.closest('.gift-card__claim').querySelector('.btn--claim'), true)">Cancel</button>
                        </form>
                    </div>
                    <?php endif ?>

                    <?php if (!empty($giftClaims)): ?>
                    <div class="gift-card__unclaim">
                        <button class="btn--link" onclick="toggleUnclaim(<?= $giftId ?>)">
                            Someone claimed this by mistake? Remove a claim ↓
                        </button>
                        <ul class="unclaim-list" id="unclaim-<?= $giftId ?>" style="display:none">
                            <?php foreach ($giftClaims as $claim): ?>
                            <li>
                                <span class="unclaim-list__name">
                                    <?= $claim['claimer_name'] ? htmlspecialchars($claim['claimer_name']) : '<em>Anonymous</em>' ?>
                                </span>
                                <form method="post" action="unclaim.php" style="display:inline">
                                    <input type="hidden" name="claim_id" value="<?= (int)$claim['id'] ?>">
                                    <button type="submit" class="btn btn--tiny btn--danger"
                                            onclick="return confirm('Remove this claim?')">Remove</button>
                                </form>
                            </li>
                            <?php endforeach ?>
                        </ul>
                    </div>
                    <?php endif ?>
                </div>
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

function toggleUnclaim(id) {
    var el = document.getElementById('unclaim-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
