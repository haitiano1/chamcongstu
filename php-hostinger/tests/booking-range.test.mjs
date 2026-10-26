import test from 'node:test';import assert from 'node:assert/strict';import {bookingRange} from '../site/public_html/nhansu/assets/bookings.js';
for(const d of ['2026-10-19','2026-10-20','2026-10-21','2026-10-22','2026-10-23','2026-10-24'])test(`${d}: normal Monday–Sunday week`,()=>assert.deepEqual(bookingRange(d,'week'),{start:'2026-10-19',end:'2026-10-25'}));
test('Sunday shows that Sunday through next Saturday',()=>assert.deepEqual(bookingRange('2026-10-25','week'),{start:'2026-10-25',end:'2026-10-31'}));
test('Weekday crossing month boundary',()=>assert.deepEqual(bookingRange('2026-09-30','week'),{start:'2026-09-28',end:'2026-10-04'}));
test('Sunday crossing year boundary',()=>assert.deepEqual(bookingRange('2026-12-27','week'),{start:'2026-12-27',end:'2027-01-02'}));
