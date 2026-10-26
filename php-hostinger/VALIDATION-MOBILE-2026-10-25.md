# Mobile release validation

Local disposable PHP/MariaDB fixture and Chromium; no deployment to Hostinger.

- 141 viewport/browser checks at 320, 360, 390, 430, 768 and 1440px. No page-wide or calendar-wide horizontal overflow. Two-line cards have compact touch targets; short names on cards, full names/customer in popup; summary visible on phones; past-day selection retained; mini calendar dots include dates outside displayed week; cancelled dates excluded from dots by API; phone mini calendar collapses after selection; Sunday and Wednesday periods verified; employee phone lands on attendance and primary action remains >=44px tall and within screen.
- Nine week-range cases: Monday–Saturday use normal Monday–Sunday week; Sunday uses Sunday–following Saturday. Month/year boundaries verified.
- 13 rollback-only PHP summary/calendar-day tests. Today/upcoming/active/cancelled/unassigned counts correct; active main+support assignment to same person counted once; mini-day results span full requested calendar, not current week only; cancelled excluded; role Employee aggregates only their assigned bookings; another employee's counters unchanged. Oversized date ranges rejected.
- 44 calendar/journal browser checks retained, updated to use collapsible filters and compact cards.
- 11 HR browser regressions and 95 HR/booking integration checks passed. Booking customer identity is verified in the popup because cards intentionally omit customer names.
- 9 API/startup checks passed: diagnostic manifest, JSON, private JPEG, original PDF and gzip backup.
- 60 log authorization checks passed: all-role viewing, Admin-only grants, designated editor writes, immediate revocation, API bypass denial, before/after audit.
- JavaScript syntax and PHP lint checks passed. Flat ZIP files use 0644, preserve config/storage/database and do not contain WordPress files or fixture data.

User decisions: default week on all devices; phone week rendered vertically; retain mini calendar/history; dots for active bookings in full mini-calendar date range; short labels 699k/1499k; summary independent of selected week, clearly labelled as all synced bookings. Prior customer-first attendance rules and Event log permissions unchanged. No schema migration.
