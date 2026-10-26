export function distanceMeters(a, b) {
  const rad = n => n * Math.PI / 180;
  const dLat = rad(b.latitude-a.latitude), dLon = rad(b.longitude-a.longitude);
  const h = Math.sin(dLat/2)**2 + Math.cos(rad(a.latitude))*Math.cos(rad(b.latitude))*Math.sin(dLon/2)**2;
  return 6371000 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1-h));
}
export function validatePosition(position, studio, now = Date.now()) {
  if (!studio) throw new Error('Quản lý chưa cài đặt vị trí studio.');
  const {latitude, longitude, accuracy, timestamp} = position || {};
  if (![latitude, longitude, accuracy, timestamp].every(Number.isFinite) || Math.abs(latitude)>90 || Math.abs(longitude)>180 || accuracy<0) throw new Error('Dữ liệu vị trí không hợp lệ.');
  if (now-timestamp>60000 || timestamp>now+10000) throw new Error('Vị trí đã cũ. Vui lòng lấy lại vị trí.');
  if (accuracy>Math.min(100, studio.radius)) throw new Error(`GPS chưa đủ chính xác: sai số khoảng ${Math.ceil(accuracy)} m, mức cho phép tối đa ${Math.min(100,studio.radius)} m. Hãy bật vị trí chính xác hoặc thử trên điện thoại ở nơi thoáng. Nếu đang kiểm thử trên máy tính, có thể dùng Chrome Sensors để giả lập đúng vị trí studio.`);
  const distance = distanceMeters(studio, position);
  if (distance>studio.radius) throw new Error(`Bạn cách studio ${Math.round(distance)} m, ngoài bán kính ${studio.radius} m.`);
  return Math.round(distance);
}
