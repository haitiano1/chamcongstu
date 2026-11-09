export const SHIFTS=[{id:'day_first',name:'Ca 1',start:9,end:14},{id:'day_second',name:'Ca 2',start:14,end:19}];
export const CHECKOUT_GRACE_MINUTES=30;
export const OT_SHIFT={id:'ot',name:'OT',start:19};
// Mở chấm OT từ 18:50 (chủ dự án yêu cầu 09/11/2026); Ca 2 còn mở thì cùng lượt vào OT tự chấm ra Ca 2 (close).
export const OT_OPEN_MINUTES=18*60+50,OT_OPEN_LABEL='18:50';
export const ATTENDANCE_OPTIONS=[...SHIFTS,OT_SHIFT];
const LEGACY=[{id:'morning',start:9,end:12},{id:'midday',start:12,end:15},{id:'afternoon',start:15,end:18}];
export const localDay=t=>new Date(t+7*3600000).toISOString().slice(0,10);
export function bounds(day,id){const s=[...SHIFTS,...LEGACY].find(s=>s.id===id);if(!s)return null;const midnight=Date.parse(day+'T00:00:00+07:00');return {start:midnight+s.start*3600000,end:midnight+s.end*3600000};}
export function completedPairs(events){const pending=new Map(),pairs=[];for(const e of [...events].sort((a,b)=>a.time-b.time||(a.id||0)-(b.id||0))){const key=e.shift?`${e.work_date}:${e.shift}`:'legacy';if(e.type==='in')pending.set(key,e);else if(pending.has(key)){const entry=pending.get(key);pending.delete(key);const window=entry.shift?bounds(entry.work_date,entry.shift):null;const start=Math.max(entry.time,window?.start??entry.time),end=Math.min(e.time,window?.end??e.time);pairs.push({entry,exit:e,start,end});}}return {pairs,pending:[...pending.values()]};}
// allowResume: đã chấm ra ca này mà quay lại làm tiếp (chủ dự án yêu cầu 01/11/2026), chấm vào lại trong giờ ca; công vẫn tính 1 lần.
export function attendanceDecision(events,id,now=Date.now(),{allowLateCheckout=false,allowResume=false}={}){
 const day=localDay(now),{pending}=completedPairs(events),openOT=pending.find(e=>e.shift==='ot');
 if(id==='ot'){
  if(openOT)return {type:'out',workDate:openOT.work_date};
  const t=new Date(now+7*3600000);if(t.getUTCHours()*60+t.getUTCMinutes()<OT_OPEN_MINUTES)throw new Error(`Chỉ được bắt đầu OT từ ${OT_OPEN_LABEL}. OT đang mở vẫn có thể kết thúc sau nửa đêm.`);
  const open=pending.filter(e=>e.shift!=='ot'&&e.work_date===day);if(open.some(e=>e.shift!=='day_second'))throw new Error('Hãy chấm ra ca chính trước khi bắt đầu OT.');
  return {type:'in',workDate:day,close:open.map(e=>e.shift)};
 }
 if(openOT)throw new Error('Hãy kết thúc lượt OT đang mở trước khi chấm ca chính.');
 const window=bounds(day,id);if(!SHIFTS.some(s=>s.id===id)||!window)throw new Error('Ca làm không hợp lệ.');
 const own=events.filter(e=>e.shift===id&&e.work_date===day).sort((a,b)=>a.time-b.time||(a.id||0)-(b.id||0)),last=own.at(-1);
 if(last&&last.type==='out'){if(!allowResume)throw new Error('Bạn đã hoàn tất ca này hôm nay.');if(now<window.start||now>=window.end)throw new Error(id==='day_first'?'Đã hết giờ Ca 1 (14:00), không làm tiếp Ca 1 được. Hãy chấm vào Ca 2.':'Đã hết giờ Ca 2 (19:00), không làm tiếp Ca 2 được. Sau 19:00 hãy chấm OT.');return {type:'in',workDate:day};}
 if(last){if(!allowLateCheckout&&now>window.end+CHECKOUT_GRACE_MINUTES*60000)throw new Error(`Đã quá hạn checkout ${id==='day_first'?'14:30':'19:30'}. Hãy gửi yêu cầu bổ sung công để quản lý xác nhận.`);return {type:'out',workDate:day};}
 if(now<window.start||now>=window.end)throw new Error('Chỉ được chấm vào trong khung giờ của ca đã chọn.');
 return {type:'in',workDate:day};
}
export function shiftAction(events,id,now=Date.now()){return attendanceDecision(events,id,now).type;}
// Đã chấm ra ca này hôm nay và vẫn còn trong giờ ca: hiện nút "Tôi đổi ý, làm tiếp".
export function canResume(events,id,now=Date.now()){if(!SHIFTS.some(s=>s.id===id))return false;const own=events.filter(e=>e.shift===id&&e.work_date===localDay(now)).sort((a,b)=>a.time-b.time||(a.id||0)-(b.id||0));if(own.at(-1)?.type!=='out')return false;try{return attendanceDecision(events,id,now,{allowResume:true}).type==='in';}catch{return false;}}
export function suggestedShift(events,now=Date.now()){
 const {pending}=completedPairs(events);if(pending.some(e=>e.shift==='ot'))return 'ot';
 const regular=pending.filter(e=>e.work_date===localDay(now)&&SHIFTS.some(s=>s.id===e.shift)&&now<=bounds(e.work_date,e.shift).end+CHECKOUT_GRACE_MINUTES*60000).sort((a,b)=>bounds(a.work_date,a.shift).end-bounds(b.work_date,b.shift).end)[0];if(regular)return regular.shift;
 const hour=new Date(now+7*3600000).getUTCHours();
 if(hour>=19)return 'ot';
 return SHIFTS.find(s=>hour>=s.start&&hour<s.end)?.id||'day_first';
}
export function hasOvertimeAt(events,servedAt,now=Date.now()){
 const {pairs,pending}=completedPairs(events);
 return pairs.some(p=>p.entry.shift==='ot'&&servedAt>=p.start&&servedAt<=p.end)||pending.some(e=>e.shift==='ot'&&servedAt>=e.time&&servedAt<=now);
}
