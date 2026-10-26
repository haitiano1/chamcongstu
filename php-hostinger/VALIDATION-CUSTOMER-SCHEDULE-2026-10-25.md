# Customer schedule release validation

Local PHP/MariaDB and Chromium checks; no production deployment or external Telegram messages.

- 21 rollback-only PHP checks: manual 09:00 roster never overrides 12:00 customer; 12:30 deadline inclusive; 12:30 + 1ms late; PM independent; one derived roster entry/person/day/shift; main+support dedup; merged manual/derived entry; cancellation moves expected start/removes derived entry; no customer means no missing punch inference; post-checkin schedule edits preserve frozen comparison; checkin reminder 12:10, checkout 14:05.
- 95 HR / Sheet integration checks including HMAC/replay/idempotency, role permissions, booking-time freeze and actual 12:31 late arrival.
- 44 calendar/journal browser checks: derived roster card renders, cannot drag as independent copy, opens booking view; package amount shows 1499k; existing calendar pagination, date modes, mobile and log editing retained.
- 10 Telegram worker checks with simulated transport; manual-only roster no longer sends checkin reminders; automatic checkout reminder unchanged.
- 6 package formatting cases: 699 / 1499 / 399 / 699k / 699000 / arbitrary package text.
- 9 startup/API/download checks including independent diagnostic, attendance JPEG, original PDF and gzip backup.
- Existing desktop/mobile smoke tests and PHP/JS syntax checks.

Flat per-target ZIPs include prior calendar/journal release, updated workflows/Telegram/roster logic; no config, storage, database or WordPress files. No schema changes and no automatic money deduction. Legacy manual assignments and historical Event logs preserved.
