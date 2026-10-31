<?php
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/partials/error_page.php';
require __DIR__ . '/partials/schema.php';

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
        <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
    </head>
    <body>
    <?php require __DIR__ . '/partials/header.php' ?>
    <div class="container container--narrow">
        <h2 class="page-title">Admin Login</h2>
        <?php if ($loginError): ?>
            <div class="flash flash--error"><?= htmlspecialchars($loginError) ?></div>
        <?php endif ?>
        <form method="post" class="login-form">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autofocus required>
            <button type="submit" class="btn btn--primary">Log in</button>
        </form>
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
    showErrorPage('Could not connect to the database. Please try again later.');
}

ensureSchema($pdo);

$people = $pdo->query('
    SELECT p.*, COUNT(g.id) AS gift_count
    FROM people p
    LEFT JOIN gifts g ON g.person_id = p.id
    GROUP BY p.id
    ORDER BY p.sort_order ASC, p.name ASC
')->fetchAll(PDO::FETCH_ASSOC);
$peopleById = array_column($people, null, 'id');

// Load gift being edited (if ?edit=ID)
$editGift = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM gifts WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editGift = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Load person being edited (if ?edit_person=ID)
$editPerson = $peopleById[(int) ($_GET['edit_person'] ?? 0)] ?? null;

// Whose list is being managed. Remembered in the session so it survives saves.
if (isset($_GET['person'])) {
    $_SESSION['admin_person'] = (int) $_GET['person'];
} elseif ($editGift) {
    $_SESSION['admin_person'] = (int) $editGift['person_id'];
}
$person = $peopleById[(int) ($_SESSION['admin_person'] ?? 0)] ?? ($people[0] ?? null);

$gifts         = [];
$archivedGifts = [];
$claimsMap     = [];
if ($person) {
    $stmt = $pdo->prepare('
        SELECT g.*, COUNT(c.id) AS claimed_count
        FROM gifts g
        LEFT JOIN claims c ON c.gift_id = g.id
        WHERE g.person_id = ?
        GROUP BY g.id
        ORDER BY g.sort_order ASC, g.created_at ASC
    ');
    $stmt->execute([$person['id']]);
    $personGifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $gifts         = array_filter($personGifts, fn($g) => $g['archived_at'] === null);
    $archivedGifts = array_filter($personGifts, fn($g) => $g['archived_at'] !== null);
    usort($archivedGifts, fn($a, $b) => strcmp($b['archived_at'], $a['archived_at']));

    // Load claims per gift for the expandable section
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
$personLabel = $person ? htmlspecialchars(possessive($person['name'])) : '';

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= htmlspecialchars(SITE_TITLE) ?></title>
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>
<?php require __DIR__ . '/partials/header.php' ?>
<div class="container">

    <?php if ($msg === 'saved'): ?>
        <div class="flash flash--success">Gift saved.</div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="flash flash--success">Gift deleted.</div>
    <?php elseif ($msg === 'archived'): ?>
        <div class="flash flash--success">Gift archived. It's hidden from the public list.</div>
    <?php elseif ($msg === 'unarchived'): ?>
        <div class="flash flash--success">Gift restored to the public list.</div>
    <?php elseif ($msg === 'unclaimed'): ?>
        <div class="flash flash--success">Claim removed.</div>
    <?php elseif ($msg === 'person_saved'): ?>
        <div class="flash flash--success">Person saved.</div>
    <?php elseif ($msg === 'person_deleted'): ?>
        <div class="flash flash--success">Person deleted.</div>
    <?php elseif ($err === 'person_not_empty'): ?>
        <div class="flash flash--error">That person still has gifts. Delete or move them first.</div>
    <?php elseif ($err === 'invalid'): ?>
        <div class="flash flash--error">Something went wrong. Please try again.</div>
    <?php endif ?>

    <?php if (!$person): ?>
        <div class="flash flash--error">There are no people yet. Add someone below to start a list.</div>
    <?php else: ?>

    <!-- Person selector -->
    <nav class="person-switcher person-switcher--admin" aria-label="Choose whose list to edit">
        <span class="person-switcher__label">Editing:</span>
        <?php foreach ($people as $p): ?>
            <a href="admin.php?person=<?= (int) $p['id'] ?>"<?= $p['id'] === $person['id'] ? ' class="active" aria-current="page"' : '' ?>><?= htmlspecialchars($p['name']) ?></a>
        <?php endforeach ?>
    </nav>

    <!-- Add / Edit gift form -->
    <section class="admin-section">
        <h2><?= $editGift ? 'Edit Gift' : 'Add Gift to ' . $personLabel . ' List' ?></h2>
        <form method="post" action="admin_save.php" class="gift-form">
            <input type="hidden" name="action" value="<?= $editGift ? 'update' : 'create' ?>">
            <?php if ($editGift): ?>
                <input type="hidden" name="id" value="<?= (int) $editGift['id'] ?>">
            <?php endif ?>

            <div class="form-row form-row--short">
                <label for="person_id">Whose list</label>
                <select id="person_id" name="person_id">
                    <?php foreach ($people as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === (int) ($editGift['person_id'] ?? $person['id']) ? ' selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
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
                <label for="image_url">Image URL <span class="form-hint">(hotlinked thumbnail, optional) You can copy a link or do your own upload. I don't manage uploaded images.</span></label>
                <input type="url" id="image_url" name="image_url" maxlength="1000"
                       value="<?= htmlspecialchars($editGift['image_url'] ?? '') ?>">
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
        <h2><?= $personLabel ?> Active Gifts</h2>
        <?php if (empty($gifts)): ?>
            <p class="empty-state">No gifts on <?= $personLabel ?> list yet.</p>
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
                            <input type="hidden" name="action" value="archive">
                            <input type="hidden" name="id" value="<?= (int) $gift['id'] ?>">
                            <button type="submit" class="btn btn--cancel btn--small"
                                    title="Hide from the public list (e.g. already received)">Archive</button>
                        </form>
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

    <!-- Archived gifts -->
    <section class="admin-section">
        <h2><?= $personLabel ?> Archived Gifts</h2>
        <p class="admin-section__hint">Gifts already received. These are hidden from the public list.</p>
        <?php if (empty($archivedGifts)): ?>
            <p class="empty-state">Nothing archived.</p>
        <?php else: ?>
        <table class="admin-table admin-table--archived">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Claimed by</th>
                    <th>Archived</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($archivedGifts as $gift):
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
                    </td>
                    <td>
                        <?php if (empty($giftClaims)): ?>
                            <em>nobody</em>
                        <?php else: ?>
                            <?= htmlspecialchars(implode(', ', array_map(
                                fn($c) => $c['claimer_name'] ?: 'anonymous', $giftClaims
                            ))) ?>
                        <?php endif ?>
                    </td>
                    <td><?= htmlspecialchars(date('M j, Y', strtotime($gift['archived_at']))) ?></td>
                    <td class="admin-table__actions">
                        <form method="post" action="admin_save.php" style="display:inline">
                            <input type="hidden" name="action" value="unarchive">
                            <input type="hidden" name="id" value="<?= (int) $gift['id'] ?>">
                            <button type="submit" class="btn btn--small">Unarchive</button>
                        </form>
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

    <?php endif // $person ?>

    <!-- People -->
    <section class="admin-section">
        <h2>People</h2>
        <?php if (!empty($people)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Link</th>
                    <th>Gifts</th>
                    <th>Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($people as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><a href="./?for=<?= urlencode($p['slug']) ?>">?for=<?= htmlspecialchars($p['slug']) ?></a></td>
                    <td><?= (int) $p['gift_count'] ?></td>
                    <td><?= (int) $p['sort_order'] ?></td>
                    <td class="admin-table__actions">
                        <a href="admin.php?edit_person=<?= (int) $p['id'] ?>#person-form" class="btn btn--small">Edit</a>
                        <form method="post" action="admin_save.php" style="display:inline">
                            <input type="hidden" name="action" value="person_delete">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <?php if ((int) $p['gift_count'] > 0): ?>
                                <button type="submit" class="btn btn--danger btn--small" disabled
                                        title="Delete or move this person's gifts first (including archived ones)">Delete</button>
                            <?php else: ?>
                                <button type="submit" class="btn btn--danger btn--small"
                                        onclick="return confirm('Delete this person?')">Delete</button>
                            <?php endif ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <?php endif ?>

        <h3 class="admin-subheading" id="person-form"><?= $editPerson ? 'Edit ' . htmlspecialchars($editPerson['name']) : 'Add Person' ?></h3>
        <form method="post" action="admin_save.php" class="gift-form">
            <input type="hidden" name="action" value="<?= $editPerson ? 'person_update' : 'person_create' ?>">
            <?php if ($editPerson): ?>
                <input type="hidden" name="id" value="<?= (int) $editPerson['id'] ?>">
            <?php endif ?>

            <div class="form-row">
                <label for="person_name">Name <span class="required">*</span></label>
                <input type="text" id="person_name" name="name" required maxlength="100"
                       value="<?= htmlspecialchars($editPerson['name'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="person_slug">Link name <span class="form-hint">(the list's address is ./?for=this. Leave blank to make one from the name.)</span></label>
                <input type="text" id="person_slug" name="slug" maxlength="100"
                       value="<?= htmlspecialchars($editPerson['slug'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="person_intro">Intro <span class="form-hint">(shown at the top of their list)</span></label>
                <textarea id="person_intro" name="intro" rows="3"><?= htmlspecialchars($editPerson['intro'] ?? '') ?></textarea>
            </div>
            <div class="form-row form-row--short">
                <label for="person_sort_order">Sort order</label>
                <input type="number" id="person_sort_order" name="sort_order" min="0"
                       value="<?= htmlspecialchars((string)($editPerson['sort_order'] ?? 0)) ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><?= $editPerson ? 'Save person' : 'Add person' ?></button>
                <?php if ($editPerson): ?>
                    <a href="admin.php" class="btn btn--cancel">Cancel</a>
                <?php endif ?>
            </div>
        </form>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php' ?>

<script>
function toggleClaims(id) {
    var el = document.getElementById('claims-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
