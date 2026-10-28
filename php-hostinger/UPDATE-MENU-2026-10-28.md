# Cập nhật 28/10/2026 — Gộp menu

Gói này cộng dồn: gồm cả **Luật Studio** và **Nối Luật Studio với Event log** (27/10, chưa có trong gói đăng ký ca) cùng bản gộp menu. Upload gói này là đủ, không cần gói Luật Studio riêng.

## Thay đổi

- Menu còn 6 mục cho mọi role: Tổng quan · Chấm công · **Lịch** · Việc · **Công & lương** · **Studio** (Admin/Quản lý có thêm Quản lý).
  - Lịch: tab Lịch chụp · Ca của tôi (Quản lý/Admin: Lịch làm việc) · Đăng ký ca. Mở mục Lịch vào thẳng Lịch chụp.
  - Công & lương: tab Bảng công (Bảng lương) · Ứng lương.
  - Studio: tab Event log · Luật Studio.
- Thanh dưới điện thoại: Chấm công · Lịch · Việc · Bảng công (Lương) · Thêm. Tab con nằm ở đầu trang, dính dưới header khi cuộn.
- Bấm lại mục nhóm sẽ mở tab xem gần nhất trong lượt đó. Địa chỉ vẫn giữ `#bookings`, `#availability`, `#rules`… nên link Telegram và nút nhắc đăng ký ca cũ vẫn mở đúng tab.
- "Lịch làm của tôi" đổi tên thành "Ca của tôi". Không đổi quyền, không đổi API.
- Luật Studio, Áp luật Studio trong Event log: xem `UPDATE-TASKS-2026-10-27.md`.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (availability.php, bookings.php, bootstrap.php, people-finance.php, schema.php, studio-rules.php) |
| 2-public-assets.zip | public_html/nhansu/assets (app.js, availability.js, bookings.js, mobile.css, people-finance.js, roster.js, rule-picker.js, studio-rules.css, studio-rules.js) |
| 3-public-root.zip | public_html/nhansu (api.php, index.php, kiemtra-capnhat.php) |

1. Thay theo thứ tự 1 → 2 → 3. Giữ nguyên config.php, storage, database và WordPress.
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại đóng tab cũ rồi mở lại; máy tính Ctrl + F5. Góc menu hiện **v1.3.1 · 28/10/2026**.
4. Thử bằng tài khoản nhân viên: menu còn 6 mục; mở Lịch → bấm tab Đăng ký ca.

Đã thử cục bộ trên bản dev (nhân viên trên điện thoại 390px, quản lý trên máy tính). Chưa upload lên hosting.
