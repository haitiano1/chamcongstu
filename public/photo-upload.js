// Shared upload limits: the JSON body must have room for base64 plus GPS/shift metadata.
export const PHOTO_MAX_BYTES=250000;
export const PHOTO_TARGET_BYTES=180000;
export const ATTENDANCE_BODY_MAX_BYTES=512000;
export const ATTENDANCE_CLIENT_VERSION='2026-10-20-photo2';
export function jpegDataBytes(value){
 if(typeof value!=='string'||!/^data:image\/jpeg;base64,[A-Za-z0-9+/]+={0,2}$/.test(value))return Infinity;
 const content=value.slice(value.indexOf(',')+1);return Math.floor(content.length*3/4)-(content.endsWith('==')?2:content.endsWith('=')?1:0);
}
export function encodeCameraFrame(video,{createCanvas=()=>document.createElement('canvas')}={}){
 const w=video.videoWidth,h=video.videoHeight;if(!Number.isFinite(w)||!Number.isFinite(h)||w<160||h<160)throw new Error('Camera chưa có khung hình đủ rõ. Đợi camera sẵn sàng rồi chụp lại.');
 // Crop only extremely wide/tall sources so both proof dimensions remain readable.
 const sourceW=Math.min(w,h*2),sourceH=Math.min(h,w*2),sx=(w-sourceW)/2,sy=(h-sourceH)/2;
 let edge=Math.min(480,Math.max(sourceW,sourceH));
 const minimumEdge=Math.min(320,Math.max(sourceW,sourceH));
 while(edge>=minimumEdge){const canvas=createCanvas(),scale=edge/Math.max(sourceW,sourceH);canvas.width=Math.round(sourceW*scale);canvas.height=Math.round(sourceH*scale);const context=canvas.getContext('2d');if(!context)throw new Error('Không xử lý được ảnh camera. Hãy tải lại trang và thử lại.');context.drawImage(video,sx,sy,sourceW,sourceH,0,0,canvas.width,canvas.height);
 for(const quality of [.8,.65,.5,.35]){const value=canvas.toDataURL('image/jpeg',quality);if(jpegDataBytes(value)<=PHOTO_TARGET_BYTES)return value;}
 edge=Math.floor(edge*.8);
 }
 throw new Error('Chưa nén được ảnh đủ nhỏ để gửi. Hãy chụp lại hoặc mở web bằng Chrome/Safari mới.');
}
