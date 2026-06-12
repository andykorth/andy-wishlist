# Andy's Gift Wishlist

A simple wishlist app for tracking gift ideas around birthdays and the holidays. Andy manages the list from a password-protected admin panel; everyone else can browse and claim gifts without creating an account.

## Features

- Browse gifts with descriptions, links, and quantities
- Claim a gift with one click (optionally leave your name so people know who got what)
- Partial fulfillment tracking when a gift has quantity > 1
- Simple admin panel to add, edit, and delete gifts, and to view or remove individual claims
- Amazon links get a price history badge linking to CamelCamelCamel
- Works on mobile

## Tech stack

PHP 8, MySQL (MariaDB), plain HTML/CSS/JS. Built for my Dreamhost shared hosting, which eliminates a lot of server side tech.

## Running locally (XAMPP)

1. Start **MySQL** in the XAMPP Control Panel
2. Open phpMyAdmin at `http://localhost/phpmyadmin` and create a database (e.g. `andy_gift`)
3. Copy `config.example.php` to `config.php` and fill in your values:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'andy_gift');
   define('DB_USER', 'root');
   define('DB_PASS', '');          // XAMPP default is no password
   define('ADMIN_PASSWORD_HASH', '...'); // generate this -- see below
   define('SITE_TITLE', "Andy's Wishlist");
   ```
4. Generate your admin password hash:
   ```
   "C:\xampp\php\php.exe" -r "echo password_hash('yourpassword', PASSWORD_DEFAULT) . PHP_EOL;"
   ```
   Paste the output into `config.php` as `ADMIN_PASSWORD_HASH`.
5. Start the dev server using XAMPP's PHP (it has the MySQL driver built in):
   ```
   "C:\xampp\php\php.exe" -S localhost:8080
   ```
6. Visit `http://localhost:8080/setup.php` and you should see "Setup complete."
7. **Delete `setup.php`** after running it, since re-running it drops all data.
8. The wishlist is at `http://localhost:8080/` and the admin panel is at `/admin.php`.

## Deploying to Dreamhost

1. In the Dreamhost panel, create a MySQL database and note the hostname, name, user, and password.
2. Upload all files via SFTP. `config.php` is excluded from git, so create it fresh on the server.
3. Copy `config.example.php` to `config.php` on the server and fill in your Dreamhost credentials.
4. Visit `https://yourdomain.com/setup.php` once to create the tables, then delete the file.

## File reference

| File | Description |
|------|-------------|
| `index.php` | Public wishlist page. Fetches all gifts with claim counts via LEFT JOIN and renders gift cards with inline claim forms. |
| `claim.php` | POST endpoint. Uses a transaction with `SELECT ... FOR UPDATE` to prevent double-claiming, inserts a row into `claims`, then redirects back. |
| `unclaim.php` | POST endpoint. Deletes a specific claim row by ID; publicly accessible so anyone can remove a claim they made by accident. |
| `admin.php` | Admin panel. Unauthenticated visitors see a login form; password is verified with `password_verify()` and stored in a session. The authenticated view has an add/edit form, a gift list with edit/delete controls, and expandable per-gift claim details. |
| `admin_save.php` | POST-only mutation handler, session-gated. Handles `action=create`, `update`, `delete`, and `unclaim` using PDO prepared statements. |
| `admin_reset.php` | POST-only, session-gated. Drops and recreates both tables (same as `setup.php`). Useful when the schema changes. |
| `logout.php` | Destroys the admin session and redirects to `admin.php`. |
| `setup.php` | One-time database initialiser. Drops and recreates the `gifts` and `claims` tables. Delete after running. |
| `config.php` | Local config: DB credentials and admin password hash. Not committed to git. |
| `config.example.php` | Template for `config.php` with setup instructions. Safe to commit. |
| `partials/header.php` | Shared nav bar included by every page. |
| `partials/error_page.php` | Helper function `showErrorPage()` for styled error pages. |
| `assets/style.css` | All styles: CSS custom properties, gift cards, claim form, admin table, responsive layout. |

## The whole Database schema

```sql
gifts  (id, name, description, url, image_url, quantity, sort_order, created_at)
claims (id, gift_id -> gifts.id CASCADE DELETE, claimer_name, claimed_at)
```

One `claims` row per unit claimed. A gift is fully claimed when `COUNT(claims) >= quantity`.
