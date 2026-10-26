import { completedPairs,bounds } from './shifts.js';
const key=time=>new Date(time+7*3600000).toISOString().slice(0,10);
export function groupAttendanceByDay(events,now=Date.now()){
 const today=key(now),yesterday=key(now-86400000),groups=[];
 for(const event of [...events].sort((a,b)=>b.time-a.time||(b.id||0)-(a.id||0))){const date=key(event.time);let group=groups.at(-1);if(!group||group.date!==date){const pretty=new Intl.DateTimeFormat('vi-VN',{timeZone:'Asia/Ho_Chi_Minh',day:'2-digit',month:'2-digit',year:'numeric'}).format(new Date(event.time));const label=date===today?'Hôm nay':date===yesterday?'Hôm qua':new Intl.DateTimeFormat('vi-VN',{timeZone:'Asia/Ho_Chi_Minh',weekday:'long'}).format(new Date(event.time));group={date,title:label+' · '+pretty,events:[]};groups.push(group);}group.events.push(event);}
 return groups;
}
export function markAttendanceStatus(events,now=Date.now()){
 const pending=new Set(completedPairs(events).pending.map(e=>e.id));
 return events.map(e=>{const window=bounds(e.work_date,e.shift);const open=pending.has(e.id);return {...e,missing:open&&e.shift!=='ot'&&!!window&&now>=window.end,working:open&&(e.shift==='ot'||!!window&&now<window.end)};});
}

export function markTeamAttendanceStatus(events,now=Date.now()){
 const groups=new Map();for(const event of events){if(!groups.has(event.user_id))groups.set(event.user_id,[]);groups.get(event.user_id).push(event);}
 return [...groups.values()].flatMap(group=>markAttendanceStatus(group,now));
}
