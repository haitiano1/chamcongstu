# Cập nhật 05/11/2026 — Đặt lịch vào giờ đang vướng lịch khác

Gói này **cộng dồn** các gói trước (Ứng lương 04/11, Hỗ trợ, Task cần duyệt, Tìm hiểu web…). Chưa upload các gói đó thì upload gói này là đủ.

## Thay đổi (v1.3.54)

- **＋ Thêm lịch**: ô giờ đã có khách vẫn bấm chọn được (ô sọc đỏ, chọn xong thành ô đỏ đậm). Giờ lẻ cũng vậy.
- Bấm **Thêm lịch** mà giờ đó vướng lịch khác: hiện popup liệt kê các lịch đang vướng (giờ, tên khách, gói), có 2 nút:
  - **Vẫn đặt lịch**: lịch được đặt bình thường, ghi xuống Google Sheet, tính công, hoa hồng như mọi lịch.
  - **Huỷ**: quay lại form, nội dung đã nhập giữ nguyên.
- **Sửa lịch** cũng vậy, nhưng chỉ hỏi khi đổi ngày, giờ hoặc gói làm dài thêm thời gian. Sửa tên, SĐT, Phụ thu, Tip của lịch đang vướng thì không hỏi lại.

Giữ nguyên:

- Giờ chụp vẫn phải trong khoảng 07:00–22:00.
- 2 lịch cùng giờ bắt đầu vẫn có viền đỏ "⚠ Trùng giờ · kiểm tra Sheet" trên web; lịch chồng giờ vẫn tô đỏ ô giờ trên Google Sheet.

## File cần upload

Chỉ upload **file bên trong** từng ZIP và chọn Replace:

1. `1-private-lib.zip` → `nhansu-private/lib/` (Replace). Lần này đổi `bookings.php`.
2. `2-private-bin.zip` → `nhansu-private/bin/` (Replace): `cron.php` (không đổi, có sẵn để gói đủ bộ).
3. `3-public-assets.zip` → `public_html/nhansu/assets/` (Replace). Lần này đổi `app.js`, `bookings.js`, `api-response.js`, `studio-journal.css`.
4. `4-public-root.zip` → `public_html/nhansu/` (Replace): `index.php`, `kiemtra-capnhat.php`, `api.php`.

Không đổi database, Google Apps Script, cron.

Sau khi upload, mở `https://anhgaustudio.com/nhansu/kiemtra-capnhat.php` để kiểm tra đủ file. Trên điện thoại bấm ↻ để lấy bản mới.
