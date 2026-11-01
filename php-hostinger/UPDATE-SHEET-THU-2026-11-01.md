# Cập nhật 01/11/2026 — Cột "Thứ" trên Google Sheet lịch chụp

Chỉ đổi **script Google Sheet** (`AnhGauSync.gs`) và Sheet mẫu. Web/hosting không đổi, không cần upload gì lên hosting.

## Thay đổi

- Sheet có thể có thêm cột **Thứ** (đặt bên phải cột Ngày, tiêu đề đúng chữ `Thứ`).
- **Không cần gõ thứ**: mỗi lần đồng bộ (khoảng 1 phút), script tự ghi thứ cho mọi dòng có Ngày, và tự sửa nếu thứ ghi sai hoặc ngày bị đổi.
  - Viết tắt: `T2` `T3` `T4` `T5` `T6` `T7` `CN` (chủ dự án chốt). Ô đã ghi kiểu dài (`Thứ 5`, `Chủ nhật`) cũng được đổi sang kiểu tắt.
  - Ô Thứ ghi chữ khác (không phải thứ) thì script để nguyên.
- Lịch thêm / sửa / xóa trên web: ô Thứ đi cùng ô Ngày.
  - Khách sớm nhất ngày thì ô Ngày và ô Thứ giữ ở dòng đầu.
  - Ô gộp theo ngày thì giãn hoặc gộp lại cả hai cột.
  - Xóa khách đầu ngày thì ô Ngày và ô Thứ chuyển xuống khách kế tiếp.
  - Đổi ngày thì dòng mới có thứ mới.
- Tab tháng mới (VD Tháng 12) script nhân bản từ tab tháng trước nên tự có cột Thứ.
- Web không lấy cột Thứ vào popup lịch, vì đã có ngày.
- Sheet mẫu `Mau-lich-chup-AnhGau.xlsx` đã có cột Thứ.

## Cách làm (theo đúng thứ tự)

1. **Thay script trước**: mở Google Sheet lịch → **Tiện ích mở rộng → Apps Script** → mở file script, **xóa hết nội dung**, dán toàn bộ `AnhGauSync.gs` mới → **Lưu** (Ctrl + S). Kết nối, khóa và tự đồng bộ giữ nguyên, không cần cài lại.
   - Phải thay script trước khi thêm cột. Script cũ chưa biết cột Thứ nên khi web thêm lịch, ô Thứ có thể bị đẩy lệch dòng.
2. **Thêm cột** ở các tab tháng đang dùng (Tháng 11, Tháng 12 nếu đã có; tab Tháng 10 không cần):
   - Chuột phải chữ cột của cột **Ngày** (VD cột A) → **Chèn 1 cột bên phải**.
   - Ở dòng tiêu đề của cột mới gõ đúng chữ **Thứ**.
   - Không cần gõ thứ từng dòng.
3. Bấm menu **Anh Gấu Studio → Đồng bộ ngay**, hoặc đợi khoảng 1 phút: cột Thứ tự điền đủ.
4. (Không bắt buộc) Muốn ô Thứ gộp theo ngày giống ô Ngày:
   - Bấm chữ cột Ngày → bấm nút **Sao chép định dạng** (cọ sơn) → bấm chữ cột Thứ. Ô Thứ gộp giống ô Ngày.
   - Bấm lại **Đồng bộ ngay** để script ghi lại chữ thứ vào ô gộp.
5. Thử: trên web thêm 1 lịch vào một ngày mới → khoảng 1 phút sau dòng trên Sheet có Ngày và Thứ đúng.

Đã thử trên Sheet giả lập (script thật chạy trong Node), 28 kiểm thử đạt:
- Tự ghi `T5` cho 05/11; sửa `Thứ 3` sai của 09/11 thành `T2`; `Thứ 5` đúng thứ của 03/12 đổi thành `T5`, `chủ nhật` thành `CN`; dòng có ngày chưa có khách cũng ghi; chữ khác trong ô Thứ để nguyên; dòng không có ngày không ghi.
- 01/12 giữ `T3`, 02/12 ghi `T4`; đọc đúng giờ và tên khách khi có thêm cột; không gửi cột Thứ vào popup; tab không có cột Thứ thì không đổi gì.
- Lịch web 08:00 (sớm nhất ngày, ô gộp): ô Ngày và Thứ giữ dòng đầu, gộp giãn 3 dòng. Lịch web cuối ngày: gộp cả hai cột giãn 4 dòng. Ngày mới 07/11 ghi `T7`.
- Xóa khách đầu ngày (ô gộp và không gộp): khách kế tiếp nhận Ngày và Thứ, gộp lại đúng. Đổi ngày 11/11 → 12/11: dòng mới `T5`, không mang ô Thứ cũ.
- Kiểm thử script cũ: 12 kiểm thử giao thức đạt; bộ 2 chiều 96 đạt. 1 mục (tab "Tháng 5" ngoài khoảng đồng bộ) lỗi giống hệt trước khi sửa: mục đó viết cho tháng 10, sang tháng 11 thì Tháng 5 năm sau đã vào khoảng 6 tháng tới. Không liên quan cột Thứ.
