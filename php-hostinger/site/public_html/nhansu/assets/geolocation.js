// A first desktop/Wi-Fi fix can be coarse. Wait for a better reading, up to 20s.
export function readPosition({maxAccuracy=100,waitMs=20000,geolocation=globalThis.navigator?.geolocation,secure=globalThis.isSecureContext}={}){
 return new Promise((resolve,reject)=>{
  if(!secure)return reject(new Error('Định vị cần kết nối HTTPS hoặc localhost.'));
  if(!geolocation)return reject(new Error('Trình duyệt chưa hỗ trợ định vị.'));
  let best=null,watchId,settled=false;
  const finish=(error,position)=>{if(settled)return;settled=true;clearTimeout(timer);if(watchId!==undefined)geolocation.clearWatch(watchId);error?reject(error):resolve(position);};
  const timer=setTimeout(()=>{if(best)finish(null,best);else finish(new Error('Chưa lấy được vị trí sau 20 giây. Hãy kiểm tra quyền vị trí và thử lại.'));},waitMs);
  try{watchId=geolocation.watchPosition(p=>{
   const position={latitude:p.coords.latitude,longitude:p.coords.longitude,accuracy:p.coords.accuracy,timestamp:p.timestamp};
   if(!Object.values(position).every(Number.isFinite)||position.accuracy<0||Date.now()-position.timestamp>60000)return;
   if(!best||position.accuracy<best.accuracy)best=position;
   if(position.accuracy<=maxAccuracy)finish(null,position);
  },error=>{if(error.code===1)finish(new Error('Hãy cấp quyền vị trí chính xác trong trình duyệt và hệ điều hành rồi thử lại.'));else if(error.code!==3&&!best)finish(new Error('Chưa lấy được vị trí. Hãy bật vị trí chính xác, kiểm tra kết nối và thử lại.'));},{enableHighAccuracy:true,maximumAge:0,timeout:10000});if(settled)geolocation.clearWatch(watchId);}catch(error){finish(error);}
 });
}
