# Andy's Gift Wishlist

A personal gift wishlist web app. Andy (the gift recipient) manages the list via a
password-protected admin panel. Anyone else can view the list and claim gifts with
zero login friction — no accounts, no sign-up.

## Features

- View all wished-for gifts with descriptions, links, and quantities
- Claim a gift (optionally leave your name) with one click
- Per-item claim tracking — quantities > 1 are supported
- Race-condition-safe claiming via database transactions
- Admin panel: add, edit, delete gifts; view and remove individual claims
- Mobile-friendly responsive layout

## Tech stack

PHP 8 + MySQL (MariaDB), vanilla HTML/CSS/JS. No framework, no build step.
Designed for Dreamhost shared hosting.

## Running locally (XAMPP)

1. Start **MySQL** in the XAMPP Control Panel
2. Open phpMyAdmin at `http://localhost/phpmyadmin` and create a database (e.g. `andy_gift`)
3. Copy `config.example.php` → `config.php` and fill in your values:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'andy_gift');
   define('DB_USER', 'root');
   define('DB_PASS', '');          // XAMPP default is no password
   define('ADMIN_PASSWORD_HASH', '...'); // generate this — see below
   define('SITE_TITLE', "Andy's Wishlist");
   ```
4. Generate your admin password hash:
   ```
   "C:\xampp\php\php.exe" -r "echo password_hash('yourpassword', PASSWORD_DEFAULT) . PHP_EOL;"
   ```
   Paste the output string into `config.php` as `ADMIN_PASSWORD_HASH`.
5. Run the dev server using XAMPP's PHP (it has the MySQL driver built in):
   ```
   "C:\xampp\php\php.exe" -S localhost:8080
   ```
6. Visit `http://localhost:8080/setup.php` — you should see "Setup complete."
7. **Delete `setup.php`** — re-running it drops all data.
8. Visit `http://localhost:8080/` to see the wishlist, or `/admin.php` to manage it.

## Deploying to Dreamhost

1. In the Dreamhost panel, create a MySQL database and note the hostname, name, user, and password.
2. Upload all files via SFTP. `config.php` is in `.gitignore` — create it fresh on the server.
3. Copy `config.example.php` → `config.php` on the server with Dreamhost credentials.
4. Visit `https://yourdomain.com/setup.php` once, then **delete it**.

## File reference

| File | Description |
|------|-------------|
| `index.php` | Public wishlist page. Fetches all gifts with claim counts via LEFT JOIN and renders gift cards with an inline claim form. |
| `claim.php` | POST endpoint. Opens a transaction with `SELECT … FOR UPDATE` to prevent double-claiming, inserts a row into `claims`, then redirects back. |
| `admin.php` | Admin panel. Shows a login form to unauthenticated visitors; verifies password with `password_verify()` and stores a session. Authenticated view has add/edit form, gift list with edit/delete controls, and expandable per-gift claim details. |
| `admin_save.php` | POST-only mutation handler (session-gated). Handles `action=create`, `update`, `delete`, and `unclaim` using PDO prepared statements throughout. |
| `logout.php` | Destroys the admin session and redirects to `admin.php`. |
| `setup.php` | One-time database initialiser. Drops and recreates the `gifts` and `claims` tables. **Delete after running.** |
| `config.php` | Local config: DB credentials and admin password hash. Not committed to git. |
| `config.example.php` | Template for `config.php` with instructions. Safe to commit. |
| `assets/style.css` | All styles: CSS custom properties, gift cards, claim form, admin table, responsive layout. |

## Database schema

```sql
gifts  (id, name, description, url, quantity, sort_order, created_at)
claims (id, gift_id → gifts.id CASCADE DELETE, claimer_name, claimed_at)
```

One `claims` row per unit claimed. A gift is fully claimed when `COUNT(claims) >= quantity`.
