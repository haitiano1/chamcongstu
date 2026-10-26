# Validation — calendar, journal and editor authorization

Verified locally with PHP 8.3 / MariaDB and Chromium, not on the live Hostinger account.

- Calendar/journal browser suite: 39 checks, desktop and 390px mobile. Real PHP reads plus mocked multi-page calendar reads covering 260 bookings across a month boundary; no truncation of weekly data; search, employee/status filters, day/week/month navigation, month overflow opens complete day, detail dialog and escaped markup, all 50 legacy PDF entries, date groups, audit dialog, UI create/edit and before/after audit, no page-wide horizontal overflow, calendar-contained swipe, read-only employee controls.
- Event log authorization API suite: 60 checks. All authenticated roles read others’ logs, audit, PDF; anonymous denied. Admin alone grants/revokes editor access to employees or managers. Default manager cannot mutate. Direct POST create/edit/classify/resolve, permission-grant and attendance-issue bypasses denied. Designated editor may edit and resolve; stale versions denied. Revocation takes effect immediately; audit keeps before/after content. Original 50 PDF entries remain present.
- HR/booking integration: 95 checks. Advances, point aggregates, historical PDF dates, signed Sheet HMAC / replay / idempotency, employee booking privacy, alias mapping and frozen booking-time attendance comparison.
- Interactive HR browser regression: 11 checks including advance request/CSV, log creation and booking attendance review.
- Existing desktop/mobile regression: 9 checks. External location service unavailable under test network policy; application fallback remains available.
- API/startup and download regression: 9 checks for authenticated JSON, original attendance JPEG bytes, original PDF bytes, web-generated gzip backup and independent diagnostic.
- PHP lint and JavaScript syntax checks passed.
- Advance API and UI implementation sections byte-identical to previous release.
- ZIP entry validation: flat per-target archives, all entries 0644; no config.php, storage, database, credentials, WordPress files or source customer data included.

Deployment: UPDATE-2026-10-25.md. No schema change or external messages. Existing Admin-designated log recorders are retained as editors. All-role read access and delegated write access are intentional user requirements for this release.
