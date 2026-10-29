// Giả lập tối thiểu Google Sheets / Apps Script để chạy AnhGauSync.gs trong Node.
const vm=require('vm'),fs=require('fs'),crypto=require('crypto');
const pad=n=>String(n).padStart(2,'0');
function display(c){
 if(!c||c.v===''||c.v==null)return '';
 if(c.v instanceof Date){if(/HH/.test(c.fmt||''))return pad(c.v.getHours())+':'+pad(c.v.getMinutes());return pad(c.v.getDate())+'/'+pad(c.v.getMonth()+1)+(/yyyy/.test(c.fmt||'')?'/'+c.v.getFullYear():'');}
 if(typeof c.v==='number')return c.v.toLocaleString('de-DE');return String(c.v);
}
function parseInput(v,fmt){
 if(typeof v!=='string'||fmt==='@')return v;
 let m;if(/HH/.test(fmt||'')&&(m=v.match(/^(\d{1,2}):(\d{2})$/)))return new Date(1899,11,30,+m[1],+m[2]);
 if(/^\d+$/.test(v))return Number(v);return v;
}
class Sheet{
 constructor(book,name,rows){this.book=book;this.name=name;this.cells=rows.map(r=>r.map(v=>typeof v==='object'&&v!==null&&!(v instanceof Date)?{...v}:{v,fmt:v instanceof Date?'dd/MM':''}));this.merges=[];this.frozen=0;this.widths={};}
 getName(){return this.name}
 cell(r,c){while(this.cells.length<r)this.cells.push([]);const row=this.cells[r-1];while(row.length<c)row.push({v:'',fmt:''});return row[c-1];}
 getLastColumn(){return Math.max(0,...this.cells.map(r=>{let n=r.length;while(n&&(r[n-1].v===''||r[n-1].v==null)&&!r[n-1].note)n--;return n;}))}
 getLastRow(){let n=this.cells.length;while(n&&this.cells[n-1].every(c=>c.v===''||c.v==null))n--;return n;}
 getDataRange(){return new Range(this,1,1,Math.max(1,this.getLastRow()),Math.max(1,this.getLastColumn()));}
 getRange(r,c,nr=1,nc=1){return new Range(this,r,c,nr,nc);}
 insertRowAfter(r){this.cells.splice(r,0,[]);for(const m of this.merges){if(m.r1>r)m.r1++;if(m.r2>r&&m.r1<=r)m.r2++;else if(m.r2>r)m.r2++;}this.book.log.push(this.name+': insertRowAfter '+r);return this;}
 insertRowBefore(r){this.cells.splice(r-1,0,[]);for(const m of this.merges){if(m.r1>=r)m.r1++;if(m.r2>=r)m.r2++;}this.book.log.push(this.name+': insertRowBefore '+r);return this;}
 getIndex(){return this.book.sheets.indexOf(this)+1}
 setColumnWidth(c,w){this.widths[c]=w}getColumnWidth(c){return this.widths[c]||100}
 setFrozenRows(n){this.frozen=n}getFrozenRows(){return this.frozen}
 rows(){const n=this.getLastRow(),w=this.getLastColumn();return Array.from({length:n},(_,i)=>Array.from({length:w},(_,j)=>display(this.cells[i]&&this.cells[i][j])));}
}
class Range{
 constructor(s,r,c,nr,nc){Object.assign(this,{s,r,c,nr,nc});}
 each(f){const out=[];for(let i=0;i<this.nr;i++){const row=[];for(let j=0;j<this.nc;j++)row.push(f(this.s.cell(this.r+i,this.c+j),i,j));out.push(row);}return out;}
 getDisplayValues(){return this.each(c=>display(c))}
 getValue(){return this.s.cell(this.r,this.c).v}
 getDisplayValue(){return display(this.s.cell(this.r,this.c))}
 setValue(v){const c=this.s.cell(this.r,this.c);c.v=parseInput(v,c.fmt);c.formula='';return this;}
 setNumberFormat(f){this.each(c=>{c.fmt=f});return this}
 getNumberFormat(){return this.s.cell(this.r,this.c).fmt||''}
 getNotes(){return this.each(c=>c.note||'')}
 setNotes(a){this.each((c,i,j)=>{c.note=a[i][j]});return this}
 getBackgrounds(){return this.each(c=>c.bg||'#ffffff')}
 setBackgrounds(a){this.each((c,i,j)=>{c.bg=a[i][j]});return this}
 copyTo(t){const src=this.each(c=>({...c}));t.s.book.log.push('copyTo');for(let i=0;i<this.nr;i++)for(let j=0;j<this.nc;j++)Object.assign(t.s.cell(t.r+i,t.c+j),src[i][j]);}
 clearContent(){this.each(c=>{c.v='';c.formula=''});return this}
 getMergedRanges(){return this.s.merges.filter(m=>m.c===this.c&&m.r1<=this.r&&m.r2>=this.r).map(m=>({getRow:()=>m.r1,getLastRow:()=>m.r2}))}
 merge(){this.s.merges=this.s.merges.filter(m=>!(m.c===this.c&&m.r1>=this.r&&m.r2<=this.r+this.nr-1));this.s.merges.push({c:this.c,r1:this.r,r2:this.r+this.nr-1});return this}
 getFormulasR1C1(){return this.each(c=>c.formula||'')}
 setFormulaR1C1(f){const c=this.s.cell(this.r,this.c);c.formula=f;c.v='=f';return this}
}
class Book{
 constructor(){this.sheets=[];this.log=[];this.toasts=[];}
 add(name,rows){const s=new Sheet(this,name,rows);this.sheets.push(s);return s;}
 getSheets(){return this.sheets}getSheetByName(n){return this.sheets.find(s=>s.name===n)||null}
 insertSheet(name,idx){const s=new Sheet(this,name,[]);this.sheets.splice(idx,0,s);this.log.push('insertSheet '+name);return s;}
 getId(){return 'SHEETID'}toast(m){this.toasts.push(m)}
}
function load(gsPath,book,server,props={}){
 const store={...props};
 const ctx={console,
  SpreadsheetApp:{openById:()=>book,getActive:()=>book},
  PropertiesService:{getScriptProperties:()=>({getProperties:()=>({...store}),getProperty:k=>store[k]??null,setProperty:(k,v)=>{store[k]=String(v)},setProperties:o=>Object.assign(store,o),deleteProperty:k=>{delete store[k]}})},
  LockService:{getScriptLock:()=>({tryLock:()=>true,releaseLock:()=>{}})},
  Utilities:{getUuid:()=>crypto.randomUUID(),Charset:{UTF_8:'utf8'},DigestAlgorithm:{SHA_256:'sha256'},computeDigest:(a,t)=>[...crypto.createHash('sha256').update(t).digest()].map(b=>b>127?b-256:b),computeHmacSha256Signature:(t,k)=>[...crypto.createHmac('sha256',k).update(t).digest()].map(b=>b>127?b-256:b)},
  UrlFetchApp:{fetch:(url,opt)=>{const r=server(JSON.parse(opt.payload),opt);return {getResponseCode:()=>r[0],getContentText:()=>JSON.stringify(r[1])};}},
 };
 vm.createContext(ctx);vm.runInContext(fs.readFileSync(gsPath,'utf8'),ctx);ctx.store=store;return ctx;
}
module.exports={Book,load,display};
