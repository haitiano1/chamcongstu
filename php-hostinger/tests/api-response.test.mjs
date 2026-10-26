import test from 'node:test';import assert from 'node:assert/strict';
import {readApiResponse} from '../site/public_html/nhansu/assets/api-response.js';
test('JSON API success retains fields',async()=>{assert.deepEqual(await readApiResponse(new Response('{"setup":false}',{status:200})),{setup:false});});
test('empty server responses have actionable Vietnamese error and HTTP status',async()=>{await assert.rejects(readApiResponse(new Response('',{status:500})),/nội dung trống \(HTTP 500\).*kiemtra-capnhat/);});
test('HTML/WAF response does not expose debug content',async()=>{try{await readApiResponse(new Response('<html>password=DO-NOT-DISPLAY</html>',{status:403}));assert.fail();}catch(e){assert.match(e.message,/HTTP 403/);assert.ok(!e.message.includes('DO-NOT-DISPLAY'));}});
test('PHP JSON errors show safe message and reference',async()=>{await assert.rejects(readApiResponse(new Response('{"error":"Thiếu lib/bookings.php","reference":"abc123"}',{status:503})),/Thiếu lib\/bookings.php.*abc123/);});
test('unexpected JSON shape is reported without type errors',async()=>{await assert.rejects(readApiResponse(new Response('null',{status:200})),/không đúng định dạng/);await assert.rejects(readApiResponse(new Response('[]',{status:200})),/không đúng định dạng/);});
test('binary/non-JSON failure never becomes raw UI content',async()=>{await assert.rejects(readApiResponse(new Response('not-json',{status:502})),/HTTP 502/);});
