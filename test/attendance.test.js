import test from 'node:test';
import assert from 'node:assert/strict';
import { distanceMeters,validatePosition } from '../attendance.js';
const studio={latitude:10.7769,longitude:106.7009,radius:100};
const position=(extra={})=>({...studio,accuracy:10,timestamp:Date.now(),...extra});
test('accepts a fresh position at the studio',()=>assert.equal(validatePosition(position(),studio),0));
test('calculates a real distance and rejects outside radius',()=>{assert.ok(distanceMeters(studio,{...studio,latitude:10.7869})>1000);assert.throws(()=>validatePosition(position({latitude:10.7869}),studio),/ngoài bán kính/);});
test('rejects inaccurate, stale, missing and malformed locations',()=>{assert.throws(()=>validatePosition(position({accuracy:150}),studio),/chính xác/);assert.throws(()=>validatePosition(position({timestamp:Date.now()-120000}),studio),/đã cũ/);assert.throws(()=>validatePosition(null,studio),/không hợp lệ/);assert.throws(()=>validatePosition(position({latitude:200}),studio),/không hợp lệ/);assert.throws(()=>validatePosition(position(),null),/chưa cài đặt/);});
