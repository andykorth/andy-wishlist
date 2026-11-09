<?php
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/partials/error_page.php';
require __DIR__ . '/partials/schema.php';

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

ensureSchema($pdo);

$people = $pdo->query('
    SELECT p.*, COUNT(g.id) AS gift_count
    FROM people p
    LEFT JOIN gifts g ON g.person_id = p.id AND g.archived_at IS NULL
    GROUP BY p.id
    ORDER BY p.sort_order ASC, p.name ASC
')->fetchAll(PDO::FETCH_ASSOC);

// Pick whose list to show: ?for=<slug>, or the only person if there is just one.
$person  = null;
$forSlug = trim($_GET['for'] ?? '');
if ($forSlug !== '') {
    foreach ($people as $p) {
        if ($p['slug'] === $forSlug) {
            $person = $p;
            break;
        }
    }
} elseif (count($people) === 1) {
    $person = $people[0];
}

$gifts     = [];
$claimsMap = [];
if ($person) {
    $stmt = $pdo->prepare('
        SELECT g.*, COUNT(c.id) AS claimed_count
        FROM gifts g
        LEFT JOIN claims c ON c.gift_id = g.id
        WHERE g.person_id = ? AND g.archived_at IS NULL
        GROUP BY g.id
        ORDER BY g.sort_order ASC, g.created_at ASC
    ');
    $stmt->execute([$person['id']]);
    $gifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch individual claims so we can render the public unclaim UI
    $stmt = $pdo->prepare('
        SELECT c.*
        FROM claims c
        JOIN gifts g ON g.id = c.gift_id
        WHERE g.person_id = ?
        ORDER BY c.claimed_at ASC
    ');
    $stmt->execute([$person['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $claimsMap[$row['gift_id']][] = $row;
    }
}

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $person ? htmlspecialchars(possessive($person['name'])) . ' list — ' : '' ?><?= htmlspecialchars(SITE_TITLE) ?></title>
    <style><?php readfile(__DIR__ . '/assets/style.css') ?></style>
</head>
<body>
<?php require __DIR__ . '/partials/header.php' ?>
<main class="container">
    <?php if (!$person): ?>
        <h2 class="page-title">Whose list are you looking for?</h2>
        <?php if ($forSlug !== ''): ?>
            <div class="flash flash--error">There's no list called &ldquo;<?= htmlspecialchars($forSlug) ?>&rdquo;. Pick one below.</div>
        <?php endif ?>
        <?php if (empty($people)): ?>
            <p class="empty-state">No lists yet. Check back soon!</p>
        <?php else: ?>
            <ul class="person-list">
            <?php foreach ($people as $p): ?>
                <li>
                    <a class="person-card" href="./?for=<?= urlencode($p['slug']) ?>">
                        <span class="avatar avatar--<?= (int) $p['id'] % 5 ?>" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?></span>
                        <span class="person-card__text">
                            <span class="person-card__name"><?= htmlspecialchars(possessive($p['name'])) ?> list</span>
                            <span class="person-card__count"><?= (int) $p['gift_count'] ?> <?= (int) $p['gift_count'] === 1 ? 'gift' : 'gifts' ?></span>
                        </span>
                        <span class="person-card__arrow" aria-hidden="true">&rarr;</span>
                    </a>
                </li>
            <?php endforeach ?>
            </ul>
        <?php endif ?>
    <?php else: ?>

    <?php if (count($people) > 1): ?>
        <nav class="person-switcher" aria-label="Other lists">
            <span class="person-switcher__label">Lists:</span>
            <?php foreach ($people as $p): ?>
                <a href="./?for=<?= urlencode($p['slug']) ?>"<?= $p['id'] === $person['id'] ? ' class="active" aria-current="page"' : '' ?>><span class="avatar avatar--small avatar--<?= (int) $p['id'] % 5 ?>" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?></span><?= htmlspecialchars($p['name']) ?></a>
            <?php endforeach ?>
        </nav>
    <?php endif ?>

    <h2 class="page-title"><?= htmlspecialchars(possessive($person['name'])) ?> list</h2>
    <?php if ($person['intro']): ?>
        <p class="subtitle"><?= nl2br(htmlspecialchars($person['intro'])) ?></p>
    <?php endif ?>

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
        <p class="empty-state">Nothing on <?= htmlspecialchars(possessive($person['name'])) ?> list yet. Check back soon!</p>
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
            $asin = '';
            if ($gift['url']) {
                $host = parse_url($gift['url'], PHP_URL_HOST) ?: '';
                $domain = preg_replace('/^www\./', '', $host);
                if (preg_match('/amazon\./i', $host) &&
                    preg_match('/(?:dp|gp\/product)\/([A-Z0-9]{10})/i', $gift['url'], $m)) {
                    $asin = $m[1];
                }
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
                        <?php if ($asin): ?>
                            <a class="badge badge--camel" href="https://camelcamelcamel.com/product/<?= htmlspecialchars($asin) ?>" target="_blank" rel="noopener">
                                price history ↗
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
                        <button class="btn btn--claim" onclick="toggleClaim(this)">I bought this</button>
                        <form class="claim-form" action="claim.php" method="post" style="display:none">
                            <input type="hidden" name="gift_id" value="<?= $giftId ?>">
                            <input type="hidden" name="for" value="<?= htmlspecialchars($person['slug']) ?>">
                            <input type="text" name="claimer_name" placeholder="Your name (optional)" maxlength="255" autocomplete="name">
                            <button type="submit" class="btn btn--confirm">Confirm</button>
                            <button type="button" class="btn btn--cancel" onclick="toggleClaim(this.closest('.gift-card__claim').querySelector('.btn--claim'), true)">Cancel</button>
                        </form>
                    </div>
                    <?php endif ?>

                    <?php if (!empty($giftClaims)): ?>
                    <div class="gift-card__unclaim">
                        <button class="btn--link" onclick="toggleUnclaim(<?= $giftId ?>)">
                            See claimed purchases. If it was an accident, remove a claim:
                        </button>
                        <ul class="unclaim-list" id="unclaim-<?= $giftId ?>" style="display:none">
                            <?php foreach ($giftClaims as $claim): ?>
                            <li>
                                <span class="unclaim-list__name">
                                    <?= $claim['claimer_name'] ? htmlspecialchars($claim['claimer_name']) : '<em>Anonymous</em>' ?>
                                </span>
                                <form method="post" action="unclaim.php" style="display:inline">
                                    <input type="hidden" name="claim_id" value="<?= (int)$claim['id'] ?>">
                                    <input type="hidden" name="for" value="<?= htmlspecialchars($person['slug']) ?>">
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

    <?php endif // $person ?>
</main>
<?php require __DIR__ . '/partials/footer.php' ?>

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
