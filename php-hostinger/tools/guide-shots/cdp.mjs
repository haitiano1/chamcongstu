import {spawn} from 'node:child_process';import fs from 'node:fs';
export const sleep=ms=>new Promise(r=>setTimeout(r,ms));
export async function launch(port,profile,{width=390,height=844,dpr=2}={}){
 const chrome=spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',['--headless=new','--remote-debugging-port='+port,'--user-data-dir='+process.env.TEMP+'\\'+profile,'--window-size='+width+','+height,'--hide-scrollbars','about:blank']);
 let ws;for(let i=0;i<40;i++){try{const t=await (await fetch('http://127.0.0.1:'+port+'/json')).json();const p=t.find(x=>x.type==='page');if(p){ws=new WebSocket(p.webSocketDebuggerUrl);break;}}catch{}await sleep(300);}
 await new Promise(r=>ws.onopen=r);let id=0;const pend={};ws.onmessage=m=>{const d=JSON.parse(m.data);if(d.id&&pend[d.id]){pend[d.id](d);delete pend[d.id];}};
 const send=(method,params={})=>new Promise(r=>{const i=++id;pend[i]=r;ws.send(JSON.stringify({id:i,method,params}));});
 const ev=async e=>{const r=await send('Runtime.evaluate',{expression:e,awaitPromise:true,returnByValue:true});return r.result?.result?.value??r.result?.exceptionDetails?.exception?.description;};
 await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:dpr,mobile:true});
 const shot=async(file,{format='png',quality}={})=>{const r=await send('Page.captureScreenshot',{format,...(quality?{quality}:{})});fs.writeFileSync(file,Buffer.from(r.result.data,'base64'));return fs.statSync(file).size;};
 const login=async(email,password,hash='attendance')=>{await send('Page.navigate',{url:'http://127.0.0.1:8184/nhansu/#'+hash});await sleep(2500);
  await ev(`document.querySelector('#email').value=${JSON.stringify(email)};document.querySelector('#password').value=${JSON.stringify(password)};document.querySelector('#email').form.requestSubmit();1`);await sleep(5000);};
 return {chrome,send,ev,shot,login,close:()=>{chrome.kill();}};
}
