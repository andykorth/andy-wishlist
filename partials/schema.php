<?php
/**
 * Non-destructive schema upgrades for databases created before a column or table existed.
 * Safe to call on every request: once up to date, each check is a cheap SHOW query.
 */
function ensureSchema(PDO $pdo): void
{
    $hasArchived = $pdo->query("SHOW COLUMNS FROM gifts LIKE 'archived_at'")->fetch();
    if (!$hasArchived) {
        $pdo->exec('ALTER TABLE gifts ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL');
    }

    $hasPeople = $pdo->query("SHOW TABLES LIKE 'people'")->fetch();
    if (!$hasPeople) {
        $pdo->exec(PEOPLE_TABLE_SQL);
    }

    $hasPersonId = $pdo->query("SHOW COLUMNS FROM gifts LIKE 'person_id'")->fetch();
    if (!$hasPersonId) {
        // Single-recipient database from before multiple lists existed: everything belongs to Andy.
        $andyId = $pdo->query("SELECT id FROM people WHERE slug = 'andy'")->fetchColumn();
        if (!$andyId) {
            $stmt = $pdo->prepare('INSERT INTO people (name, slug, intro) VALUES (?, ?, ?)');
            $stmt->execute([
                'Andy',
                'andy',
                "This is Andy's rolling wishlist for birthdays, holidays, etc. They are roughly prioritized. "
                . 'Most of them will include links where you can buy them online, '
                . 'but you can mark them off no matter where you buy them.',
            ]);
            $andyId = $pdo->lastInsertId();
        }

        $pdo->exec('ALTER TABLE gifts ADD COLUMN person_id INT NULL AFTER id');
        $pdo->prepare('UPDATE gifts SET person_id = ? WHERE person_id IS NULL')->execute([$andyId]);
        $pdo->exec('ALTER TABLE gifts MODIFY person_id INT NOT NULL');
        $pdo->exec('ALTER TABLE gifts ADD CONSTRAINT fk_gifts_person FOREIGN KEY (person_id) REFERENCES people(id)');
    }
}

const PEOPLE_TABLE_SQL = '
    CREATE TABLE people (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL,
        slug        VARCHAR(100) NOT NULL UNIQUE,
        intro       TEXT,
        sort_order  INT NOT NULL DEFAULT 0,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
';

/** Turn a display name into a URL-safe slug that isn't already taken by another person. */
function uniqueSlug(PDO $pdo, string $source, int $excludeId = 0): string
{
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($source)), '-');
    if ($base === '') {
        $base = 'list';
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM people WHERE slug = ? AND id <> ?');
    $slug = $base;
    for ($n = 2; ; $n++) {
        $stmt->execute([$slug, $excludeId]);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . $n;
    }
}

/** "Andy" -> "Andy's", "James" -> "James'" */
function possessive(string $name): string
{
    return $name . (str_ends_with(strtolower($name), 's') ? "'" : "'s");
}
