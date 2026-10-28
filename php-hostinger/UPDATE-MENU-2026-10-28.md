# Cập nhật 28/10/2026 — Gộp menu, xóa lịch, Đã cọc/Đã nhận

Gói này cộng dồn: gồm cả **Luật Studio** và **Nối Luật Studio với Event log** (27/10, chưa có trong gói đăng ký ca), bản gộp menu và phần lịch chụp bên dưới. Upload gói này là đủ, không cần gói Luật Studio riêng.

## Thay đổi

- Menu còn 6 mục cho mọi role: Tổng quan · Chấm công · **Lịch** · Việc · **Công & lương** · **Studio** (Admin/Quản lý có thêm Quản lý).
  - Lịch: tab Lịch chụp · Ca của tôi (Quản lý/Admin: Lịch làm việc) · Đăng ký ca. Mở mục Lịch vào thẳng Lịch chụp.
  - Công & lương: tab Bảng công (Bảng lương) · Ứng lương.
  - Studio: tab Event log · Luật Studio.
- Thanh dưới điện thoại: Chấm công · Lịch · Việc · Bảng công (Lương) · Thêm. Tab con nằm ở đầu trang, dính dưới header khi cuộn.
- Bấm lại mục nhóm sẽ mở tab xem gần nhất trong lượt đó. Địa chỉ vẫn giữ `#bookings`, `#availability`, `#rules`… nên link Telegram và nút nhắc đăng ký ca cũ vẫn mở đúng tab.
- "Lịch làm của tôi" đổi tên thành "Ca của tôi". Không đổi quyền, không đổi API.
- Popup lịch chụp: Quản lý/Admin có nút **Xóa lịch** (lý do không bắt buộc). Lịch xóa biến mất khỏi Lịch chụp, thống kê, đối soát chấm công và nhắc Telegram; vẫn giữ trong database và nhật ký trước/sau. Sheet đồng bộ lại không làm lịch đã xóa hiện lại. Ca đã checkin giữ nguyên kết luận cũ.
- Ghi chú trong popup (chỉ Quản lý/Admin) hiện **Đã cọc** và **Đã nhận** lấy từ cột cùng tên trên Sheet; ô trống hiện 0. Cần dán lại `google-sheets/AnhGauSync.gs` mới vào Apps Script của Sheet (Lưu → Đồng bộ ngay); không cần cài lại kết nối.
- Database tự thêm cột `deposit_label`, `received_label` vào bảng lịch chụp khi mở web lần đầu.
- Luật Studio, Áp luật Studio trong Event log: xem `UPDATE-TASKS-2026-10-27.md`.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (availability.php, bookings.php, bootstrap.php, people-finance.php, schema.php, studio-rules.php) |
| 2-public-assets.zip | public_html/nhansu/assets (app.js, availability.js, bookings.js, mobile.css, people-finance.js, roster.js, rule-picker.js, studio-journal.css, studio-rules.css, studio-rules.js) |
| 3-public-root.zip | public_html/nhansu (api.php, index.php, kiemtra-capnhat.php) |

1. Thay theo thứ tự 1 → 2 → 3. Giữ nguyên config.php, storage, database và WordPress.
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại đóng tab cũ rồi mở lại; máy tính Ctrl + F5. Góc menu hiện **v1.3.2 · 28/10/2026**.
4. Thử bằng tài khoản nhân viên: menu còn 6 mục; mở Lịch → bấm tab Đăng ký ca.
5. Mở Sheet → Tiện ích mở rộng → Apps Script, thay toàn bộ nội dung bằng file `AnhGauSync.gs` trong gói, Lưu, rồi menu Anh Gấu Studio → Đồng bộ ngay. Mở một lịch trên web để thấy Đã cọc / Đã nhận.
6. Xóa lịch demo: Admin/Quản lý mở từng lịch demo → Xóa lịch.

Đã thử cục bộ trên bản dev: menu (nhân viên điện thoại 390px, quản lý máy tính); xóa lịch qua popup, xóa trùng bị chặn, nhân viên không xóa được, Sheet gửi lại không làm lịch hiện lại, Đã cọc/Đã nhận lưu và hiện đúng. Chưa upload lên hosting.
