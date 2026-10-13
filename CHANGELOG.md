# Changelog

## 0.1.0 — 2026-10-13 — The first Omni
- **Setup.** The first visit asks for the owner's account (name, email, password of at least 12 characters, time zone) and closes once it exists. It is the only account.
- **Sign-in.** Limited to 8 failures per address in 15 minutes. Sign-out ends the session.
- **Sites.** A table of every site, with:
  - its state (healthy, warnings, needs attention, silent, waiting to connect, disconnected),
  - a chip each for health, storage, the last backup, the version and analytics,
  - when it was last heard from,
  - a link to its admin.
- **Filters.** Above the table: all, needs attention, update available, not connected.
- **Adding a site.** It takes a name and gives a connection code. The code is shown once, works once and lasts 24 hours.
- **A site's page.** It shows:
  - what to look at (failed checks, a failed or overdue backup, storage almost full, no report for 48 hours, an update),
  - the health checks, backups and cron job, storage, the version, analytics, the server and its extensions,
  - the latest report as JSON.
  - The owner can rename the site, make a new code to connect it again, or remove it.
- **The API of protocol v1:** ping, connect, signed report and disconnect.
  - The sites' secrets are sealed with libsodium; only the codes' hashes are kept.
  - Requests are refused for a wrong signature, a timestamp more than 5 minutes off, a replayed nonce, more than 64 KB, or a protocol Omni does not speak.
  - A removed site is answered with `410`.
  - Connecting is limited to 10 attempts per address per hour.
- **Tests:** `tests/unit.php` (60 checks), `tests/http.php` (58 checks, a real server), and `tests/seed-demo.php` for sample data.
