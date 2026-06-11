# Andy Gift Wishlist — TODO

AI: mark tasks `[x]` as each one is completed.

---

## Phase 1 — Project Skeleton

- [x] Create `config.example.php` with placeholder DB credentials and admin password hash instructions
- [x] Create `.gitignore` excluding `config.php` and other sensitive/generated files
- [x] Create `assets/` directory placeholder (`.gitkeep`)
- [x] Create `setup.php` that creates the `gifts` and `claims` tables via PDO

---

## Phase 2 — Public Wishlist

- [x] Create `index.php`: query all gifts with claim counts via LEFT JOIN
- [x] `index.php`: render each gift card (name, link, description, quantity vs claimed)
- [x] `index.php`: render inline claim form (optional name field + submit button) per unclaimed gift
- [x] `index.php`: show fully-claimed badge when claimed_count >= quantity
- [x] `index.php`: display flash messages from query string (`?msg=claimed`, `?err=already_claimed`)
- [x] Create `claim.php`: validate POST input, check gift exists and has remaining quantity
- [x] `claim.php`: insert row into `claims`, redirect to `index.php?msg=claimed`
- [x] `claim.php`: handle edge case where gift is already fully claimed before insert

---

## Phase 3 — Admin Panel

- [x] Create `admin.php`: show login form when no valid session exists
- [x] `admin.php`: handle login POST, verify password with `password_verify()`, set session
- [x] `admin.php`: show gift list table with Edit and Delete buttons when authenticated
- [x] `admin.php`: show Add Gift form (name, url, description, quantity, sort_order)
- [x] `admin.php`: pre-populate Edit form when `?edit=ID` query param present
- [x] `admin.php`: show claim details per gift (who claimed, when) in expandable section
- [x] Create `admin_save.php`: session gate — redirect to `admin.php` if not logged in
- [x] `admin_save.php`: handle `action=create` — validate and INSERT new gift
- [x] `admin_save.php`: handle `action=update` — validate and UPDATE existing gift
- [x] `admin_save.php`: handle `action=delete` — DELETE gift (cascades to claims)
- [x] `admin_save.php`: handle `action=unclaim` — DELETE a specific claim row
- [x] Create `logout.php`: destroy session, redirect to `admin.php`

---

## Phase 4 — Styling & Polish

- [x] Create `assets/style.css`: base reset, typography, color variables
- [x] Style gift cards on `index.php`: clean layout, readable on mobile
- [x] Style claim form: inline, unobtrusive until interacted with
- [x] Style fully-claimed items: visually distinct (muted / strikethrough)
- [x] Style admin panel: simple table layout, clearly separated from public view
- [x] Add empty-state message to `index.php` when no gifts are listed
- [x] Add `<title>` and meta viewport tag to all pages
- [x] Add a page header/title to `index.php` (e.g. "Andy's Wishlist")

---

## Phase 5 — Deployment Prep

- [x] Verify `setup.php` drops and recreates tables cleanly (idempotent)
- [x] Add a prominent warning comment to `setup.php` so it isn't accidentally re-run
- [x] Cross-check all user-facing output is wrapped in `htmlspecialchars()`
- [x] Cross-check all queries use PDO prepared statements (no string interpolation)
- [x] Add instructions block at top of `config.example.php` for how to generate the password hash

## Phase 6 — New features

- [x] Update README.md with project details, features, how to run it, plus a description of what each php file does.
- [x] Create a common page header for all pages. Include a link to admin and index pages.
- [x] Format and style the header nicely with css.
- [x] Allow someone to unclaim a gift claimed by themselves or someone else, similar to the ability for admins to do so.
- [x] Add a optional image url. This is for hotlinked images hosted elsewhere. If present, hotlink an icon for them.
- [x] When showing the gift list, if a link is present, make the link more obvious by including the domain of the link, styled as a badge. 
- [x] Make sure error pages are also styled to match the other pages, including errors that occur when it can't connect to the db.
- [x] Add an admin button that can wipe the db and re-run setup. This is an easy way to deal with db format changes.
