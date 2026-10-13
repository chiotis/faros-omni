# Omni

One place to watch many [FarosCMS](https://github.com/chiotis/faroscms) sites: their health checks, storage, backups, analytics and updates, with a link to each site's admin. Only the owner signs in.

The public Omni is `https://omni.faroscms.com`. Anyone can run their own and connect their own sites to it: the Omni address is a setting of each site.

Sites talk to Omni with the protocol in FarosCMS's `docs/omni-protocol.md`. In short:

- The site always calls Omni; Omni never calls the site.
- A site joins with a one-time connection code made in Omni, and gets a secret of its own.
- Every report after that is signed with the secret (HMAC-SHA256, with a timestamp and a nonce).

## Running it

Omni needs PHP 8.1 or later with `pdo_sqlite` and `sodium`. It has no Composer and no build step on the server: upload and run.

1. Point the web server's document root to `public/`. Where that cannot be set, the `.htaccess` at the root sends everything there (Apache).
   - **Nginx:** `root /path/to/omni/public; location / { try_files $uri /index.php?$query_string; }`
2. Make `storage/` writable by PHP. It holds the database (`storage/db/omni.sqlite`) and the key that seals the sites' secrets (`storage/key`). Back both up together: without the key, the connected sites have to connect again.
3. Open the address. The first page asks for the owner's account (name, email, password, time zone). It closes once the account exists.
4. **Add site** gives a connection code. In the site, a super admin enters the Omni address and the code at Admin > Omni.

Omni is meant to run at the root of its own domain (`https://omni.example.com`). Serve it over HTTPS: sites refuse an Omni address that is not HTTPS, except for local ones (`localhost`, `*.test`).

To develop locally:

```bash
php -S 127.0.0.1:8121 -t public public/index.php
```

## What is where

| Folder | |
|---|---|
| `public/` | The only folder the web serves: `index.php`, the compiled CSS, the JavaScript, the icon |
| `system/Core/` | The app, router, database and its tables, views, signatures, connection codes, sealed secrets |
| `system/Models/` | The owner, the sites (codes, reports, nonces), attempts (rate limits), settings, and `SiteHealth` (what the owner is shown about a site) |
| `system/Controllers/` | Setup and sign-in, the owner's screens, the API |
| `system/Views/` | Plain PHP views |
| `storage/` | The database and the key (not in git) |

### The API

| Request | What it does |
|---|---|
| `GET /api/v1/ping` | Says it is an Omni and which protocols it speaks |
| `POST /api/v1/connect` | Takes a code and gives the site its id and secret. Limited to 10 attempts per address per hour |
| `POST /api/v1/report` | A signed report. Omni keeps the latest one and the last 120 per site |
| `POST /api/v1/disconnect` | The site leaves. Its history stays |

A request is refused when:

- its signature does not match, or it comes from an unknown site,
- its timestamp is more than 5 minutes off,
- its nonce was already used,
- it is larger than 64 KB.

A site the owner removed is told so with `410`.

### Security

- **The owner's account.** The password is hashed. Sign-in is limited to 8 failures per address in 15 minutes. The session cookie is `HttpOnly` and `SameSite=Lax`, and `Secure` over HTTPS.
- **Forms.** Every form carries a CSRF token.
- **Pages.** They are sent with a strict Content Security Policy (no inline scripts) and cannot be framed.
- **Secrets.** Each site's secret is sealed with libsodium in the database, under the key in `storage/key`.
- **Connection codes.** Only their hash is kept.
- **What a site sends.** It is shown escaped. Addresses become links only when they are http(s).

## Styles

Tailwind CSS 3.4, compiled into `public/assets/css/app.css` (committed, so the server needs nothing). After changing classes in a view:

```bash
npm install
npm run build:css
```

## Tests

```bash
tests/run.sh
```

- `tests/unit.php` covers signatures, codes, sealed secrets, the sites model, rate limits and `SiteHealth`.
- `tests/http.php` starts PHP's own server with a storage folder of its own and goes through setup, sign-in, adding a site, the whole API with every refusal, and the screens.

To look at the screens with sample data:

```bash
OMNI_STORAGE=/tmp/omni-demo php tests/seed-demo.php
OMNI_STORAGE=/tmp/omni-demo php -S 127.0.0.1:8121 -t public public/index.php
```

Then sign in with the demo account named in `tests/seed-demo.php`.
