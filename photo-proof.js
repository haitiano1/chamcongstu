import { PHOTO_MAX_BYTES,jpegDataBytes } from './public/photo-upload.js';
import jpeg from 'jpeg-js';
export function normalizeAttendancePhoto(value){
 if(typeof value!=='string'||!value)throw new Error('Chưa nhận được ảnh chấm công. Hãy tải lại trang, mở camera và nhấn Dùng ảnh & chấm công.');
 if(value.length>350000)throw new Error('Ảnh chấm công vượt 250 KB. Hãy tải lại trang để cập nhật bộ nén ảnh rồi chụp lại.');
 const size=jpegDataBytes(value);if(!Number.isFinite(size))throw new Error('Ảnh gửi lên không đúng định dạng JPEG. Hãy mở camera trên web và chụp lại.');
 if(size>PHOTO_MAX_BYTES)throw new Error('Ảnh chấm công vượt 250 KB. Hãy tải lại trang để cập nhật bộ nén ảnh rồi chụp lại.');
 const bytes=Buffer.from(value.slice(value.indexOf(',')+1),'base64');
 try{const decoded=jpeg.decode(bytes,{useTArray:true,maxResolutionInMP:2,maxMemoryUsageInMB:48,tolerantDecoding:false});if(decoded.width<160||decoded.height<160||decoded.width>1280||decoded.height>1280)throw new Error('size');return Buffer.from(jpeg.encode(decoded,80).data);}catch{throw new Error('Ảnh không hợp lệ hoặc quá nhỏ. Hãy chụp lại bằng camera.');}
}
