# Korth Family Wishlist

A simple wishlist app for tracking gift ideas around birthdays and the holidays. Each family member has their own list. The lists are managed from an admin panel with one shared password; everyone else can browse and claim gifts without creating an account.

## Features

- A list for each person, each with its own link (`/?for=mom`) and intro text. The home page lets visitors pick whose list to look at.
- Browse gifts with descriptions, links, and quantities
- Claim a gift with one click (optionally leave your name so people know who got what)
- Partial fulfillment tracking when a gift has quantity > 1
- Simple admin panel to add, edit, and delete gifts, and to view or remove individual claims. Pick whose list you're editing at the top, and move a gift to another list from its edit form.
- Add, rename, and reorder people from the admin panel
- Archive gifts you've already received so they drop off the public list; unarchive them from the admin panel any time
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
   define('SITE_TITLE', 'Korth Family Wishlist');
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
| `index.php` | Public wishlist. With no `?for=`, shows a chooser of everyone's lists (or goes straight to the list if there's only one person). With `?for=<slug>`, shows that person's active gifts with inline claim forms. |
| `claim.php` | POST endpoint. Uses a transaction with `SELECT ... FOR UPDATE` to prevent double-claiming, inserts a row into `claims`, then redirects back. |
| `unclaim.php` | POST endpoint. Deletes a specific claim row by ID; publicly accessible so anyone can remove a claim they made by accident. |
| `admin.php` | Admin panel. Unauthenticated visitors see a login form; password is verified with `password_verify()` and stored in a session. The authenticated view has a person selector (remembered in the session), an add/edit gift form, active and archived gift tables for the selected person, and a People section for managing lists. |
| `admin_save.php` | POST-only mutation handler, session-gated. Handles gift actions (`create`, `update`, `delete`, `archive`, `unarchive`, `unclaim`) and people actions (`person_create`, `person_update`, `person_delete`) using PDO prepared statements. |
| `logout.php` | Destroys the admin session and redirects to `admin.php`. |
| `setup.php` | One-time database initialiser. Drops and recreates the `people`, `gifts`, and `claims` tables. Delete after running. |
| `config.php` | Local config: DB credentials and admin password hash. Not committed to git. |
| `config.example.php` | Template for `config.php` with setup instructions. Safe to commit. |
| `partials/header.php` | Shared nav bar included by every page. |
| `partials/schema.php` | `ensureSchema()` upgrades older databases in place on the first page load (adds `archived_at`, creates `people`, and moves existing gifts onto Andy's list). Also slug and possessive-name helpers. |
| `partials/error_page.php` | Helper function `showErrorPage()` for styled error pages. |
| `assets/style.css` | All styles: CSS custom properties, gift cards, claim form, admin table, responsive layout. |

## The whole Database schema

```sql
people (id, name, slug, intro, sort_order, created_at)
gifts  (id, person_id -> people.id, name, description, url, image_url, quantity, sort_order, created_at, archived_at)
claims (id, gift_id -> gifts.id CASCADE DELETE, claimer_name, claimed_at)
```

One `claims` row per unit claimed. A gift with a non-null `archived_at` is hidden from the public list. A gift is fully claimed when `COUNT(claims) >= quantity`.
