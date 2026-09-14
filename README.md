# Testimonials Manager

A full-stack web application for managing customer testimonials on product landing pages. Built with PHP 8, MySQL, and vanilla JavaScript — no frameworks.

## Features

- **Product search** — live fuzzy search with typo tolerance (e.g. "casavela" finds "casavella")
- **API sync** — import landing pages from an external API with upsert (existing testimonials are preserved)
- **Testimonials CRUD** — create, edit, delete testimonials per country landing page
- **Image upload** — JPG / PNG / WebP, max 5 MB, auto-generated thumbnails
- **Drag & drop reorder** — reorder testimonials by dragging rows
- **Bulk actions** — select multiple testimonials to activate, deactivate, or delete
- **AI mock** — generate random author names and mock-translate text (IT / DE / FR)
- **Change log** — every create / update / delete action is logged with user and timestamp
- **Login** — session-based authentication with bcrypt-hashed passwords

## Tech Stack

| Layer    | Technology                    |
|----------|-------------------------------|
| Backend  | PHP 8.2                       |
| Database | MySQL 8.0                     |
| Frontend | Vanilla JS + HTML + CSS       |
| Dev env  | Docker + Docker Compose       |
| Images   | GD (thumbnails)               |

---

## Local Development (Docker)

### Requirements
- [Docker Desktop](https://www.docker.com/products/docker-desktop/)

### Setup

```bash
git clone https://github.com/YOUR_USERNAME/testimonials-manager.git
cd testimonials-manager
docker compose up -d --build
```

Wait ~20 seconds for MySQL to initialize, then open:

| URL | Description |
|-----|-------------|
| http://localhost:8080 | Application |
| http://localhost:8081 | phpMyAdmin  |

**Default login:** `admin` / `password`

### First steps

1. Log in at http://localhost:8080
2. Click **Sync from API** to import products and landing pages
3. Browse products → select a country → manage testimonials

---

## Server Deployment (FTP)

### Requirements on the server
- PHP 8.0+ with extensions: `pdo_mysql`, `gd`, `fileinfo`
- MySQL 5.7+ or MariaDB 10.4+
- Apache with `mod_rewrite` enabled

### Steps

**1. Database setup**

Create a database and user in your hosting control panel (cPanel / phpMyAdmin), then import the schema:

```
db/init.sql
```

**2. Edit configuration**

Open `src/config.php` and update the database credentials:

```php
define('DB_HOST',     'localhost');   // usually localhost on shared hosting
define('DB_NAME',     'your_db_name');
define('DB_USER',     'your_db_user');
define('DB_PASSWORD', 'your_db_password');
```

**3. Upload via FTP**

Upload the following to your server's **web root** (`public_html/` or `www/`):

```
Upload this structure:
  public_html/
    ├── (contents of public/)   ← index.php, api.php, assets/, uploads/, .htaccess
    └── src/                    ← PHP source files (protected by .htaccess)
```

So:
- Contents of `public/` → directly into `public_html/`
- The `src/` folder → into `public_html/src/`

> **Note:** `src/` contains no executable web endpoints. Apache will not serve `.php` files from it unless directly requested, but you can add extra protection (see step 4).

**4. Protect src/ directory (optional)**

Create `public_html/src/.htaccess`:

```apache
Deny from all
```

**5. Fix the src path in index.php and api.php**

On shared hosting, `ROOT_DIR` resolves automatically via `dirname(__DIR__)`. No changes needed if you follow the structure above.

**6. Set upload permissions**

```bash
chmod 755 public_html/uploads/
```

Or via your FTP client, set the `uploads/` folder permissions to `755`.

---

## Configuration Reference

All settings are in `src/config.php`:

| Constant        | Default                        | Description                  |
|-----------------|--------------------------------|------------------------------|
| `DB_HOST`       | `db` (Docker) / `localhost`    | Database host                |
| `DB_NAME`       | `testimonials`                 | Database name                |
| `DB_USER`       | `app_user`                     | Database user                |
| `DB_PASSWORD`   | `secret123`                    | Database password            |
| `API_URL`       | (assignment endpoint)          | External landings API URL    |
| `API_KEY`       | (assignment key)               | API authentication key       |
| `UPLOAD_MAX_SIZE` | `5242880` (5 MB)             | Max image file size in bytes |

---

## Changing the Admin Password

Connect to MySQL and run:

```sql
UPDATE users
SET password_hash = '$2y$10$YOUR_HASH_HERE'
WHERE username = 'admin';
```

Generate a hash with PHP:

```bash
php -r "echo password_hash('your_new_password', PASSWORD_BCRYPT);"
```

Or via Docker:

```bash
docker exec testimonials_app php -r "echo password_hash('your_new_password', PASSWORD_BCRYPT);"
```

---

## Project Structure

```
testimonials-manager/
├── docker-compose.yml
├── docker/php/
│   ├── Dockerfile
│   └── apache.conf
├── db/
│   └── init.sql            ← database schema + default admin user
├── public/                 ← web root
│   ├── index.php           ← router
│   ├── api.php             ← AJAX API endpoint
│   ├── .htaccess
│   ├── uploads/            ← user-uploaded images
│   └── assets/
│       ├── css/style.css
│       └── js/app.js
└── src/                    ← PHP source (not web-accessible)
    ├── config.php
    ├── Database.php
    ├── Auth.php
    ├── helpers.php
    └── pages/
        ├── login.php
        ├── products.php
        ├── landings.php
        └── testimonials.php
```

---

## API Endpoints (internal AJAX)

| Action              | Method | Description                          |
|---------------------|--------|--------------------------------------|
| `sync`              | POST   | Sync landings from external API      |
| `search_products`   | GET    | Fuzzy search products                |
| `save_testimonial`  | POST   | Create or update a testimonial       |
| `delete_testimonial`| POST   | Delete a testimonial                 |
| `reorder`           | POST   | Update sort order                    |
| `toggle_active`     | POST   | Toggle active/inactive               |
| `bulk_action`       | POST   | Bulk activate / deactivate / delete  |
| `ai_generate_name`  | POST   | Mock AI: generate author name        |
| `ai_translate`      | POST   | Mock AI: translate text              |
| `change_log`        | GET    | Fetch change log for a landing       |
| `delete_image`      | POST   | Delete a single testimonial image    |
