import test from 'node:test';import assert from 'node:assert/strict';import {groupAttendanceByDay} from '../public/history.js';
test('attendance is separated by Vietnam dates and sorted even for backdated manual entries',()=>{const now=Date.parse('2026-10-20T10:00:00+07:00'),events=[{id:99,time:Date.parse('2026-10-18T09:00:00+07:00'),source:'manual'},{id:1,time:Date.parse('2026-10-19T23:59:00+07:00')},{id:2,time:Date.parse('2026-10-20T00:01:00+07:00'),shift:'ot',work_date:'2026-10-19'},{id:3,time:Date.parse('2026-10-20T09:00:00+07:00')}];const original=events.map(e=>e.id);const groups=groupAttendanceByDay(events,now);assert.equal(groups.length,3);assert.equal(groups[0].title,'Hôm nay · 20/10/2026');assert.deepEqual(groups[0].events.map(e=>e.id),[3,2]);assert.equal(groups[1].title,'Hôm qua · 19/10/2026');assert.equal(groups[2].date,'2026-10-18');assert.deepEqual(events.map(e=>e.id),original);});

test('an ongoing shift is working; only overdue open shifts are flagged missing',async()=>{
 const {markAttendanceStatus}=await import('../public/history.js');const event={id:1,type:'in',time:Date.parse('2026-10-20T09:00:00+07:00'),shift:'day_first',work_date:'2026-10-20'};
 const during=markAttendanceStatus([event],Date.parse('2026-10-20T10:00:00+07:00'))[0];assert.equal(during.working,true);assert.equal(during.missing,false);
 const overdue=markAttendanceStatus([event],Date.parse('2026-10-20T14:01:00+07:00'))[0];assert.equal(overdue.missing,true);assert.equal(overdue.working,false);
 const done=markAttendanceStatus([event,{...event,id:2,type:'out',time:Date.parse('2026-10-20T14:00:00+07:00')}],Date.parse('2026-10-20T15:00:00+07:00'));assert.equal(done.some(e=>e.missing||e.working),false);
});

test('team history keeps attendance pairs separate for employees working the same shift',async()=>{
 const {markTeamAttendanceStatus}=await import('../public/history.js');const time=Date.parse('2026-10-20T09:00:00+07:00');const events=[{id:1,user_id:10,type:'in',time,shift:'day_first',work_date:'2026-10-20'},{id:2,user_id:20,type:'in',time:time+60000,shift:'day_first',work_date:'2026-10-20'},{id:3,user_id:20,type:'out',time:time+5*3600000,shift:'day_first',work_date:'2026-10-20'}];
 const result=markTeamAttendanceStatus(events,time+6*3600000);assert.equal(result.find(e=>e.id===1).missing,true);assert.equal(result.filter(e=>e.user_id===20).some(e=>e.missing||e.working),false);assert.equal(result.length,3);
});
