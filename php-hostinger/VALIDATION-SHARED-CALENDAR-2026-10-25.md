# Shared calendar visibility validation

Root cause reproduced: prior Employee role filter excluded unassigned bookings from both list and mini-calendar day counts. Uploaded sample has all four October 22 bookings without main/support assignments. This differs by account permissions, not by viewport size.

Intentional read-scope change: all authenticated roles default to the shared studio calendar; explicit scope=mine retains own-assignment filtering. Employee responses still omit customer phone, internal notes and Sheet source fields. Calendar write/mapping/connector permissions, attendance assignment and payroll rules unchanged.

Local tests:
- 20 rollback PHP checks reproduce October 22 unassigned, colleague-assigned and own bookings. Employee sees matching Admin totals, date markers and shared summary; own scope excludes unrelated work, ignores arbitrary user_id; restricted columns omitted; imports denied; invalid scope rejected; viewing shared data does not assign work to the viewer.
- 22 browser checks with desktop Admin and phone Employee: October 22 appointment and dot visible in studio view; switching to mine removes them, switching back restores them; employee settings absent; no horizontal overflow.
- 13 summary/calendar PHP checks pass with own scope explicitly selected for personal counters.
- 95 HR/booking integration checks pass, including Sheet protocol, existing privacy checks and frozen attendance expectation.
- 9 API/startup/diagnostic/download checks pass after packaging.
- Six-width mobile regression verifies phone layout and week rules after adding scope tabs.

No production deployment or database schema changes. Public website read from this environment was HTTP 403; live Hostinger state could not be inspected. User must upload current package.
