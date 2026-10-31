# Cập nhật 31/10/2026 (lần 2) — Hoa hồng lịch khách chỉ tính khi thợ có mặt

Gói này **cộng dồn** gói 31/10 lần 1 (đủ file lib, assets, root). Upload gói này là đủ.

## Thay đổi (v1.3.31)

- **Hoa hồng lịch khách chỉ tính khi thợ đang chấm công lúc khách chụp.**
  - Ví dụ: Cường có tên thợ phụ cả ngày, làm cả ngày nhưng chấm ra lúc 17:00 → khách 18:00, 19:00 **không tính** hoa hồng cho Cường. Huy còn ở lại chụp thì Huy vẫn được tính.
  - Hôm đó không chấm công thì các khách có tên mình hôm đó cũng không tính.
  - Vào muộn mà khách chưa chụp xong (VD khách 12:00, chấm vào 12:20) vẫn tính.
  - Quên chấm ra: coi như có mặt tới hạn checkout (14:30 / 19:30). Khách trong giờ OT có chấm OT vẫn tính.
  - Không cần xóa tên thợ trên Sheet nữa, hệ thống tự loại theo giờ chấm công.
- **Bảng công (nhân viên) và phiếu lương (Admin/Quản lý)** hiện thêm dòng đỏ "N khách ngoài giờ chấm công · không tính" để biết khách nào bị loại.
- File **Xuất bảng lương CSV** thêm cột "Khách ngoài giờ chấm công".
- Áp dụng cho hoa hồng lịch khách từ tháng 11/2026 (như cũ). Không đổi database, không đổi Google Sheet script.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (api.php, availability.php, bookings.php, bootstrap.php, people-finance.php, rules.php, schema.php, studio-rules.php, tasks.php, telegram.php, transfer.php, workflows.php) |
| 2-public-assets.zip | public_html/nhansu/assets (toàn bộ file trong gói, gồm thư mục vendor) |
| 3-public-root.zip | public_html/nhansu (api.php, index.php, kiemtra-capnhat.php) |

Google Apps Script không đổi. `nhansu-private/bin` không đổi.

1. Thay theo thứ tự 1 → 2 → 3. Giữ nguyên config.php, storage, database và WordPress.
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại bấm ↻ (hoặc đóng app rồi mở lại); máy tính Ctrl + F5. Góc menu hiện **v1.3.31 · 31/10/2026**.
4. Từ tháng 11, Công & lương → bấm tên nhân viên → phiếu lương: dưới dòng "Số khách chụp" có dòng đỏ nếu có khách bị loại.

Đã thử trên bản dev (trong giao dịch rồi hoàn tác, không để lại dữ liệu):
- Máy chủ 7 kiểm thử đạt: làm cả ngày chấm ra 17:00 → khách 10:00, 16:30 tính, khách 18:00 và Couple 19:00 không tính; ngày không chấm công không tính; quên chấm ra tính tới 19:30, khách 19:40 không tính; vào muộn 12:20 cho khách 12:00 vẫn tính; khách 19:30 trong lượt OT tính; chấm ra đúng 12:00 thì khách 12:00 không tính; Team giữ 3 tiếng từ 15:00, thợ vào 17:30 vẫn tính; khách chưa tới giờ không tính và không bị đếm là vắng; nhân viên thấy số khách bị loại, không thấy mức/lương.
- Giao diện: bảng công nhân viên, bảng Admin và phiếu lương hiện đúng dòng "khách ngoài giờ chấm công", không có khách bị loại thì không hiện.
- Kiểm thử cũ `rules` 32, `booking-summary` 13, `customer-schedule` 21 đạt.
