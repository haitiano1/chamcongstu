# Cập nhật 27/10/2026 · Mục Việc (Task / Nhắc nhở) · Event log gọn · Giao diện điện thoại mới · v1.3.0

## Thay đổi

- Mục mới **Việc** trong menu và thanh nhanh trên điện thoại, có số đỏ đếm việc cần xử lý.
- Admin và Quản lý giao **Task** (người nhận bấm Đã xong, có ghi chú; tick **Bắt buộc chụp ảnh** thì phải chụp ảnh mới báo xong được) hoặc **Nhắc nhở** (người nhận bấm Đã xem). Gửi cho từng người hoặc **@all** (tất cả nhân viên đang làm, trừ người tạo; chốt danh sách lúc tạo).
- Admin và Quản lý sửa / hủy mọi việc; lưu người, giờ, nội dung trước/sau và lý do hủy. Nhân viên chỉ thấy việc của mình; quyền kiểm tra ở backend.
- Ảnh bằng chứng chỉ chụp trực tiếp bằng camera sau, không chọn được ảnh có sẵn trong thư viện. Ảnh lưu private trong database, chỉ người gửi và Admin/Quản lý xem được. Sao lưu tự động có kèm ảnh này.
- F5 / tải lại trang giữ nguyên mục đang xem (địa chỉ có dạng `#tasks`, `#bookings`…).
- Event log: mỗi trang 10 sự việc (chọn 10/20/50), nút số trang, thẻ gọn tối đa 3 dòng; bấm thẻ để xem đầy đủ. Xuất CSV vẫn lấy đủ dữ liệu.
- Giao diện điện thoại mới: thanh điều hướng cố định ở đáy (Chấm công · Lịch chụp · Việc · Bảng công · Thêm), header gọn, bỏ khối tiêu đề trùng, bảng hiển thị dạng thẻ, Lịch làm dạng danh sách theo ngày, popup trượt từ đáy, ô nhập 16px để iPhone không tự phóng to. Đăng xuất nằm trong menu Thêm. Giao diện máy tính giữ nguyên.
- Giao việc từ nhóm Telegram: thêm bot vào nhóm rồi gõ `/task Huy, Cường | Dọn phòng chụp 2 | 18:00` hoặc `/nhacnho all | Mai họp 8h30` (thêm `#anh` để bắt buộc ảnh; `/viec` xem hướng dẫn). Chỉ Telegram đã liên kết tài khoản Admin/Quản lý mới giao được; tên nhận theo tên gọi, tên đầy đủ, mã NV hoặc tên trên Sheet. Bot trả lời trong nhóm trong vòng 1 phút (theo cron), việc hiện trên web và người nhận được nhắn riêng.
- Telegram: cron mỗi phút nhắn người nhận đã liên kết bot, kèm nút mở mục Việc. Việc tạo quá 24 giờ chưa gửi được thì bỏ qua, không nhắn dồn.
- Database tự tạo 3 bảng mới (và tự thêm cột nếu đã có bảng từ bản thử trước) `tasks`, `task_recipients`, `task_audit` khi mở web lần đầu; không đổi dữ liệu cũ.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (tasks.php, telegram.php, schema.php, transfer.php, bootstrap.php, people-finance.php) |
| 2-private-bin.zip | nhansu-private/bin |
| 3-public-assets.zip | public_html/nhansu/assets (có file mới mobile.css) |
| 4-public-root.zip | public_html/nhansu |

1. Thay theo thứ tự 1 → 2 → 3 → 4. Giữ nguyên config.php, storage, database và WordPress.
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại đóng tab cũ rồi mở lại; máy tính Ctrl + F5. Góc menu hiện **v1.3.0 · 27/10/2026**.
4. Thử: Admin giao một Nhắc nhở cho chính một nhân viên, nhân viên mở mục Việc bấm Đã xem.

Đã thử cục bộ trên bản dev với dữ liệu sao lưu. Chưa upload lên hosting.
