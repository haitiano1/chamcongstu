# Validation for 24 October 2026 update

Prepared and tested locally, not deployed to the user's Hostinger account.

- Existing Node regression suite: 50 tests passed.
- PHP business rules: 27 checks passed.
- Telegram with simulated transport: 10 checks passed; no external messages sent.
- New HR/booking integration over actual Apache/PHP 8.3 + MariaDB 11.4: 96 checks passed. Covers advance validation, idempotency, self-approval/role boundaries, actual paid-date aggregates, append-only audits, delegated Event log/KPI confirmation, revocation, all 50 original PDF records, original PDF privacy, HMAC verification/replay, staff alias matching, employee customer-phone privacy, 12:00/12:30 lateness, frozen expectations after booking changes and cancellation/history retention.
- Google Apps Script parser/protocol with simulated Google services: 13 checks passed; header normalization, merged dates, configured year, stable UUIDs, leading-zero phones, cancellation and HMAC byte compatibility.
- Existing desktop/mobile browser checks: 9 passed.
- New interactive browser checks: 11 passed; employee mobile advance submission and filtered CSV, access boundaries, historical PDF search, new Event log creation and booking-based lateness.
- Schema upgrade preservation: 10 checks passed; row counts/photo hashes unchanged and legacy source keys unique.
- Cron entry point: returned `Nhansu cron OK` without real Telegram token.

Google Sheet/Drive URLs supplied in chat returned HTTP 403 from this environment. No live Sheet contents were read or changed. Google synchronization was tested with representative fixture data and signed HTTP requests into the local PHP receiver; installing/authorizing the supplied bound Apps Script in the user's Google account and sending a real test batch remain deployment checks.

The uploaded PDF was read using pdftotext. All 50 numbered items were preserved as source records, without guessing employee IDs, assigning disciplinary findings, inferring KPI penalties or creating financial advances from narrative mentions of money. Dates in day/month form use 2025 from document context as directed by the user. Month-only dates and the 15–16 July interval remain distinct from exact dates; two entries with no explicit date remain unassigned until the user supplies it.

Known existing backup size limits remain documented. New tables are included in the application's backup export; public/private update archives exclude config.php, storage, database files, test credentials and WordPress root files. Historical PDF and seed JSON are private files in the update archive.
