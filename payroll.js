import { completedPairs } from './public/shifts.js';
export function monthlySummary(events,month,wage={},commissions=[]) {
 const [year,m]=month.split('-').map(Number);
 const start=Date.UTC(year,m-1,1)-7*3600000,end=Date.UTC(year,m,1)-7*3600000;
 const {pairs,pending}=completedPairs(events);let milliseconds=0,completedShifts=0,otMilliseconds=0,otSessions=0;const days=new Set();
 const credited=new Set();
 for(const pair of pairs){const a=Math.max(start,pair.start),b=Math.min(end,pair.end);if(b>a){if(pair.entry.shift==='ot'){otMilliseconds+=b-a;otSessions++;continue;}milliseconds+=b-a;const key=`${pair.entry.work_date}:${pair.entry.shift}`;if(['day_first','day_second'].includes(pair.entry.shift)&&!credited.has(key)){completedShifts++;credited.add(key);}for(let t=a;t<b;){const local=new Date(t+7*3600000);days.add(local.toISOString().slice(0,10));t=Math.min(b,Date.UTC(local.getUTCFullYear(),local.getUTCMonth(),local.getUTCDate()+1)-7*3600000);}}}
 const incomplete=pending.filter(e=>e.shift!=='ot'&&e.time>=start&&e.time<end).length;
 const otIncomplete=pending.filter(e=>e.shift==='ot'&&e.time<end).length;
 const workdays=completedShifts/2,monthly_salary=wage?.monthly_salary??8000000;
 const base_salary=Math.round(monthly_salary/31*workdays);
 const customers=commissions.filter(c=>c.served_at>=start&&c.served_at<end);
 const regular_customers=customers.filter(c=>c.rate===15000).reduce((n,c)=>n+c.customers,0);
 const ot_customers=customers.filter(c=>c.rate===30000).reduce((n,c)=>n+c.customers,0);
 const commission=customers.reduce((n,c)=>n+c.customers*c.rate,0);
 return {otHours:Math.round(otMilliseconds/3600000*100)/100,otSessions,otIncomplete,completedShifts,days:days.size,hours:Math.round(milliseconds/3600000*100)/100,workdays,monthly_salary,base_salary,regular_customers,ot_customers,commission,salary:base_salary+commission,incomplete,legacyShifts:pairs.filter(p=>p.start>=start&&p.start<end&&!['day_first','day_second','ot'].includes(p.entry.shift)).length};
}
