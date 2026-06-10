# Andy's Gift Wishlist — Implementation Plan

## Overview

A personal gift wishlist web app. Andy (the gift recipient) manages the list via a
password-protected admin panel. Anyone else can view the list and claim/purchase items
with no login required. Deployed to Dreamhost shared hosting.

---

## Tech Stack

| Layer    | Choice        | Reason |
|----------|---------------|--------|
| Language | PHP 8.x       | Native Dreamhost support, no build step, simple deployment |
| Database | MySQL (MariaDB)| Provided by Dreamhost shared hosting |
| Frontend | Vanilla HTML/CSS/JS | No build tooling needed, easy maintenance |
| Auth     | PHP sessions + bcrypt password | Admin-only, no user accounts needed |

No framework. The app is small enough that raw PHP + PDO is cleaner than adding
framework overhead.

---

## Database Schema

```sql
CREATE TABLE gifts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    description TEXT,
    url         VARCHAR(1000),
    quantity    INT NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE claims (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    gift_id      INT NOT NULL,
    claimer_name VARCHAR(255),   -- optional, entered by claimant
    claimed_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE CASCADE
);
```

`gifts.quantity` — how many of this item Andy wants.  
`claims` rows — one row per unit claimed. claimed_count = COUNT(claims WHERE gift_id = N).  
When claimed_count >= quantity, item shows as fully claimed.

---

## File Structure

```
/
├── index.php          Public wishlist view
├── claim.php          POST endpoint: claim one unit of a gift
├── unclaim.php        POST endpoint: remove a claim (via secret token or admin)
├── admin.php          Admin panel: login form + CRUD for gifts
├── admin_save.php     POST endpoint: create/update/delete gifts (admin only)
├── logout.php         Clear admin session
├── setup.php          One-time DB setup script (delete after running)
├── config.php         DB credentials + admin password hash (not in git)
├── config.example.php Template for config.php
└── assets/
    ├── style.css
    └── favicon.ico
```

---

## Page-by-Page Behavior

### `index.php` — Public Wishlist
- Fetches all gifts ordered by sort_order ASC, created_at ASC
- For each gift shows:
  - Name (linked if url provided)
  - Description (if set)
  - Quantity wanted vs. claimed count (e.g. "2 of 3 claimed")
  - Claim button (disabled/hidden when fully claimed)
- Claim button opens an inline form asking for optional name, then POSTs to `claim.php`
- No login required

### `claim.php` — Claim a Gift
- POST only
- Validates: gift exists, not fully claimed
- Inserts a row into `claims`
- Redirects back to `index.php` with a success message
- No login required

### `admin.php` — Admin Panel
- GET with no session → shows login form (password only, no username)
- POST login → validates password against bcrypt hash in config.php, sets session
- With valid session → shows full gift list with Edit / Delete buttons, plus Add Gift form
- Edit pre-populates form fields
- All mutations go to `admin_save.php`

### `admin_save.php` — Admin Mutations
- Session-gated (redirect to admin.php if not logged in)
- Actions: `create`, `update`, `delete`
- Validates input, runs PDO prepared statements
- Redirects back to admin.php

---

## Security Approach

- **Admin password**: bcrypt hash stored in `config.php`. Hash generated with `password_hash()`.
- **SQL injection**: All queries use PDO prepared statements with bound parameters.
- **XSS**: All output run through `htmlspecialchars()`.
- **CSRF**: Simple same-origin POST check (referer header) — acceptable for low-stakes personal app.
- **config.php**: Excluded from git via `.gitignore`. Contains DB credentials and admin password hash.
- **No hostile-actor protection for claiming**: Per requirements, false claims are not a concern.

---

## Implementation Phases

### Phase 1 — Project skeleton
- [ ] Create directory structure
- [ ] `config.example.php` with placeholder values
- [ ] `.gitignore` (ignores config.php)
- [ ] `setup.php` (creates DB tables)

### Phase 2 — Public wishlist
- [ ] `index.php`: fetch and display gifts with claim counts
- [ ] `claim.php`: POST handler to claim a unit
- [ ] Basic `assets/style.css`

### Phase 3 — Admin panel
- [ ] `admin.php`: login form + authenticated gift management UI
- [ ] `admin_save.php`: create/update/delete gift records
- [ ] `logout.php`

### Phase 4 — Polish
- [ ] Responsive CSS (readable on mobile — family members use phones)
- [ ] Empty state messages ("No gifts listed yet")
- [ ] Success/error flash messages via query params
- [ ] Sort order drag-reorder (optional, low priority)

### Phase 5 — Deployment
- [ ] Dreamhost: create MySQL database, note credentials
- [ ] Upload files via SFTP or git deploy
- [ ] Copy `config.example.php` → `config.php`, fill in credentials
- [ ] Run `setup.php` once, then delete it
- [ ] Verify app works end-to-end

---

## Deployment Notes (Dreamhost)

- Dreamhost shared hosting serves PHP directly from the `public_html` directory (or a
  subdomain directory).
- PHP 8.x can be selected in the Dreamhost panel under Manage Websites → PHP version.
- MySQL database created in the Dreamhost panel; credentials go into `config.php`.
- No `.htaccess` changes needed for basic PHP/MySQL.
- Recommended: deploy to a subdomain like `gifts.andykorth.com` or `wishes.andykorth.com`.

---

## Config File Template

```php
<?php
// Database
define('DB_HOST', 'mysql.example.com');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Admin password hash — generate with: php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
define('ADMIN_PASSWORD_HASH', '$2y$10$...');

// Site title shown in browser / page header
define('SITE_TITLE', "Andy's Wishlist");
```

---

## Out of Scope

- Multiple wishlist users / accounts
- Email notifications
- Payment integration
- Gift reservations with expiry
- Image uploads
