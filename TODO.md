# Andy Gift Wishlist — TODO

AI: mark tasks `[x]` as each one is completed.

---

## Phase 1 — Project Skeleton

- [x] Create `config.example.php` with placeholder DB credentials and admin password hash instructions
- [x] Create `.gitignore` excluding `config.php` and other sensitive/generated files
- [ ] Create `assets/` directory placeholder (`.gitkeep`)
- [ ] Create `setup.php` that creates the `gifts` and `claims` tables via PDO

---

## Phase 2 — Public Wishlist

- [ ] Create `index.php`: query all gifts with claim counts via LEFT JOIN
- [ ] `index.php`: render each gift card (name, link, description, quantity vs claimed)
- [ ] `index.php`: render inline claim form (optional name field + submit button) per unclaimed gift
- [ ] `index.php`: show fully-claimed badge when claimed_count >= quantity
- [ ] `index.php`: display flash messages from query string (`?msg=claimed`, `?err=already_claimed`)
- [ ] Create `claim.php`: validate POST input, check gift exists and has remaining quantity
- [ ] `claim.php`: insert row into `claims`, redirect to `index.php?msg=claimed`
- [ ] `claim.php`: handle edge case where gift is already fully claimed before insert

---

## Phase 3 — Admin Panel

- [ ] Create `admin.php`: show login form when no valid session exists
- [ ] `admin.php`: handle login POST, verify password with `password_verify()`, set session
- [ ] `admin.php`: show gift list table with Edit and Delete buttons when authenticated
- [ ] `admin.php`: show Add Gift form (name, url, description, quantity, sort_order)
- [ ] `admin.php`: pre-populate Edit form when `?edit=ID` query param present
- [ ] `admin.php`: show claim details per gift (who claimed, when) in expandable section
- [ ] Create `admin_save.php`: session gate — redirect to `admin.php` if not logged in
- [ ] `admin_save.php`: handle `action=create` — validate and INSERT new gift
- [ ] `admin_save.php`: handle `action=update` — validate and UPDATE existing gift
- [ ] `admin_save.php`: handle `action=delete` — DELETE gift (cascades to claims)
- [ ] `admin_save.php`: handle `action=unclaim` — DELETE a specific claim row
- [ ] Create `logout.php`: destroy session, redirect to `admin.php`

---

## Phase 4 — Styling & Polish

- [ ] Create `assets/style.css`: base reset, typography, color variables
- [ ] Style gift cards on `index.php`: clean layout, readable on mobile
- [ ] Style claim form: inline, unobtrusive until interacted with
- [ ] Style fully-claimed items: visually distinct (muted / strikethrough)
- [ ] Style admin panel: simple table layout, clearly separated from public view
- [ ] Add empty-state message to `index.php` when no gifts are listed
- [ ] Add `<title>` and meta viewport tag to all pages
- [ ] Add a page header/title to `index.php` (e.g. "Andy's Wishlist")

---

## Phase 5 — Deployment Prep

- [ ] Verify `setup.php` drops and recreates tables cleanly (idempotent)
- [ ] Add a prominent warning comment to `setup.php` so it isn't accidentally re-run
- [ ] Cross-check all user-facing output is wrapped in `htmlspecialchars()`
- [ ] Cross-check all queries use PDO prepared statements (no string interpolation)
- [ ] Add instructions block at top of `config.example.php` for how to generate the password hash
