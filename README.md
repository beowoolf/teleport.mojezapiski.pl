# teleport.mojezapiski.pl

A simple, self-hosted link shortening service written in plain PHP. It maps short slugs to target URLs, redirects visitors, records every visit, and exposes a small JSON/HTTP API for managing links. Live instance: https://teleport.mojezapiski.pl/

## Table of Contents

- [Project name](#teleportmojezapiskipl)
- [Project description](#project-description)
- [Tech stack](#tech-stack)
- [Getting started locally](#getting-started-locally)
- [Available scripts](#available-scripts)
- [Project scope](#project-scope)
- [Project status](#project-status)
- [License](#license)

## Project description

teleport.mojezapiski.pl turns a slug such as `docs` into a short link such as `https://teleport.mojezapiski.pl/?s=docs`. When a visitor opens the link, the service:

1. Looks up the slug in the MySQL database.
2. Stores a visit record (timestamp and visitor IP address).
3. Responds with an HTTP `302 Found` redirect to the target URL.

Links are created, changed and removed through the same single entry point (`index.php`) using HTTP methods. Write operations and the statistics endpoint are protected by a shared admin password sent in an HTTP header. The project has no user interface; it is an API-only service.

## Tech stack

- **Language:** PHP (procedural code, no framework)
- **Database access:** PHP Data Objects (PDO) with the `pdo_mysql` driver and exception error mode
- **Database:** MySQL or MariaDB with the InnoDB storage engine
- **Other PHP extensions:** `mbstring` (used when formatting database error messages)
- **Dependency management:** none (no Composer, no npm)
- **Web server:** any server that can run PHP with `index.php` as the entry point (for example Apache or Nginx with PHP-FPM)

### Project layout

```text
.
├── index.php                                  # Single entry point: API and redirect logic
├── robots.txt                                 # Disallows all crawlers
├── .gitignore                                 # Excludes real credential files
└── core/
    ├── functions/
    │   └── create_new_pdo.php                 # Creates the PDO connection
    └── configurations/
        ├── dbms/
        │   ├── pdo_options.php                # PDO options (exception error mode)
        │   └── mysql/                         # MySQL host, port, driver and database name
        └── apps/links/
            ├── admin_password.php(.sample)    # Admin password for write/stats requests
            └── db/                            # Database host, port, user and password
```

## Getting started locally

### Prerequisites

- PHP 7.4 or newer with the `PDO`, `pdo_mysql` and `mbstring` extensions. The exact minimum version has not been tested or pinned in this repository.
- MySQL 5.7+ or MariaDB 10.3+ (InnoDB is required for the foreign key).
- Git.

### 1. Clone the repository

```bash
git clone https://github.com/<your-username>/teleport.mojezapiski.pl.git
cd teleport.mojezapiski.pl
```

### 2. Create the configuration files

Real configuration files are excluded from Git (see `.gitignore`). Copy each `.sample` template, remove the `.sample` suffix, and replace the placeholder value (`set_me`, `fill_me`, `3306`, etc.) with your own:

```bash
cp core/configurations/apps/links/admin_password.php.sample core/configurations/apps/links/admin_password.php
cp core/configurations/apps/links/db/user_name_for_links_db.php.sample core/configurations/apps/links/db/user_name_for_links_db.php
cp core/configurations/apps/links/db/password_for_links_db.php.sample core/configurations/apps/links/db/password_for_links_db.php
cp core/configurations/dbms/mysql/host_for_mysql.php.sample core/configurations/dbms/mysql/host_for_mysql.php
cp core/configurations/dbms/mysql/port_for_mysql.php.sample core/configurations/dbms/mysql/port_for_mysql.php
cp core/configurations/dbms/mysql/databases/name_of_the_links_db_in_mysql.php.sample core/configurations/dbms/mysql/databases/name_of_the_links_db_in_mysql.php
```

| File | Purpose |
| --- | --- |
| `apps/links/admin_password.php` | Password required for `POST`, `PATCH`, `DELETE` and `?stats` requests |
| `apps/links/db/user_name_for_links_db.php` | MySQL user name |
| `apps/links/db/password_for_links_db.php` | MySQL password |
| `dbms/mysql/host_for_mysql.php` | MySQL host (e.g. `localhost`) |
| `dbms/mysql/port_for_mysql.php` | MySQL port (default `3306`) |
| `dbms/mysql/databases/name_of_the_links_db_in_mysql.php` | Database name used by the connection |

Do not commit these files.

### 3. Create the database and user

```sql
CREATE DATABASE m1095_links CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'teleport'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT ALL PRIVILEGES ON m1095_links.* TO 'teleport'@'localhost';
FLUSH PRIVILEGES;
```

> **Note:** The `CREATE TABLE` statements in `index.php` hard-code the schema name `m1095_links`. Either name your database `m1095_links`, or change the schema name in `index.php` to match the value in `name_of_the_links_db_in_mysql.php`.

The tables `links` and `visits` are created automatically. The table-creation routine runs when a database error occurs, so the request that first hits a missing table returns an error message. Send the request again and it will succeed.

### 4. Run the application

For local development you can use PHP's built-in web server from the project root:

```bash
php -S localhost:8000
```

Open http://localhost:8000/?stats (with the API header described below) to confirm that the database connection works.

> The built-in server is intended for development only. In production, configure your web server so that `index.php` is the only publicly reachable PHP script (see [Project status](#project-status)).

## Available scripts

The project has no build, test or lint scripts. The application is operated through its HTTP endpoints. Replace `<admin_password>` with the value from `admin_password.php` and `localhost:8000` with your server address.

| Method | Request | Auth header | Description |
| --- | --- | --- | --- |
| `POST` | `/` with JSON body `{"slug": "...", "target": "..."}` | Required | Create a link. Returns the new `link_id`. |
| `GET` | `/?s=<slug>` | Not required | Log a visit and redirect (`302`) to the target URL. |
| `GET` | `/?i=<slug>` | Not required | Log a visit and return the target URL as plain text. |
| `GET` | `/?stats` | Required | Return JSON with each link's visit count and last visit time. |
| `PATCH` | `/?s=<slug>` with JSON body `{"link": "..."}` | Required | Change the target URL of a link. Returns the number of updated rows. |
| `DELETE` | `/?s=<slug>` | Required | Delete a link and its visits. Returns the number of deleted rows. |

The auth header is `API-Key-Password: <admin_password>`.

### Examples

```bash
# Create a link
curl -X POST "http://localhost:8000/" \
  -H "API-Key-Password: <admin_password>" \
  -H "Content-Type: application/json" \
  -d '{"slug":"docs","target":"https://example.com/docs"}'

# Open a link (redirects)
curl -i "http://localhost:8000/?s=docs"

# Get the target URL as text
curl "http://localhost:8000/?i=docs"

# Show statistics
curl "http://localhost:8000/?stats" -H "API-Key-Password: <admin_password>"

# Change the target URL
curl -X PATCH "http://localhost:8000/?s=docs" \
  -H "API-Key-Password: <admin_password>" \
  -H "Content-Type: application/json" \
  -d '{"link":"https://example.com/new-docs"}'

# Delete a link
curl -X DELETE "http://localhost:8000/?s=docs" \
  -H "API-Key-Password: <admin_password>"
```

## Project scope

**Included**

- Creating short links that map a unique slug to a target URL.
- Redirecting visitors with an HTTP `302` response.
- Resolving a slug to its target URL as plain text.
- Recording every visit with a timestamp and the visitor's IP address.
- A statistics endpoint showing visit counts and the last visit per link.
- Updating the target URL and deleting links.
- Protecting write operations and statistics with a shared admin password.
- Creating the database tables automatically.
- `robots.txt` that asks all crawlers not to index the site.

**Not included**

- A web interface or admin panel.
- User accounts, multiple API keys or permission levels.
- Validation of target URLs.
- Link expiry, custom domains or analytics beyond visit counts.
- Rate limiting, automated tests, CI/CD or containerisation.
- Database migrations.

## Project status

The project is a personal, working service and is published at https://teleport.mojezapiski.pl/. The code was recently migrated from a Bitbucket account to this repository. It has no versioned releases, automated tests or CI pipeline.

### Known limitations and planned improvements

- `creator_ip` and `visitor_ip` are `VARCHAR(15)`, which cannot store IPv6 addresses.
- Target URLs are stored without validation.
- Authentication uses one shared password with no rate limiting.
- The `CREATE TABLE` statements hard-code the schema `m1095_links` instead of using the configured database name.
- Some error paths return JSON and others return plain text, and error responses use HTTP status `200`.
- The `core/` directory sits inside the web root. Configure the web server so that only `index.php` is executed and the configuration files are not reachable.

## License

This project does not include a license file yet. Until the author adds one, all rights are reserved by the author, Paweł Wilczek, and no permission to copy, modify or distribute the code is granted.
