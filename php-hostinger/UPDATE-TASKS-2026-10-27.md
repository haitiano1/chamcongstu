# Cập nhật 27/10/2026 · Mục Việc (Task / Nhắc nhở) · Event log gọn · Giao diện điện thoại mới · Đăng ký ca · Luật Studio · v1.3.0

## Thay đổi

- Mục mới **Việc** trong menu và thanh nhanh trên điện thoại, có số đỏ đếm việc cần xử lý.
- Admin và Quản lý giao **Task** (người nhận bấm Đã xong, có ghi chú; tick **Bắt buộc chụp ảnh** thì phải chụp ảnh mới báo xong được) hoặc **Nhắc nhở** (người nhận bấm Đã xem). Gửi cho từng người hoặc **@all** (tất cả nhân viên đang làm, trừ người tạo; chốt danh sách lúc tạo).
- Admin và Quản lý sửa / hủy mọi việc; lưu người, giờ, nội dung trước/sau và lý do hủy. Nhân viên chỉ thấy việc của mình; quyền kiểm tra ở backend.
- Ảnh bằng chứng chỉ chụp trực tiếp bằng camera sau, không chọn được ảnh có sẵn trong thư viện. Ảnh lưu private trong database, chỉ người gửi và Admin/Quản lý xem được. Sao lưu tự động có kèm ảnh này.
- F5 / tải lại trang giữ nguyên mục đang xem (địa chỉ có dạng `#tasks`, `#bookings`…).
- Event log: mỗi trang 10 sự việc (chọn 10/20/50), nút số trang, thẻ gọn tối đa 3 dòng; bấm thẻ để xem đầy đủ. Xuất CSV vẫn lấy đủ dữ liệu.
- Giao diện điện thoại mới: thanh điều hướng cố định ở đáy (Chấm công · Lịch chụp · Việc · Bảng công · Thêm), header gọn, bỏ khối tiêu đề trùng, bảng hiển thị dạng thẻ, Lịch làm dạng danh sách theo ngày, popup trượt từ đáy, ô nhập 16px để iPhone không tự phóng to. Đăng xuất nằm trong menu Thêm. Giao diện máy tính giữ nguyên.
- Giao việc từ nhóm Telegram: thêm bot vào nhóm rồi gõ `/task Huy, Cường | Dọn phòng chụp 2 | 18:00` hoặc `/nhacnho all | Mai họp 8h30` (thêm `#anh` để bắt buộc ảnh; `/viec` xem hướng dẫn). Chỉ Telegram đã liên kết tài khoản Admin/Quản lý mới giao được; tên nhận theo tên gọi, tên đầy đủ, mã NV hoặc tên trên Sheet. Bot trả lời trong nhóm trong vòng 1 phút (theo cron), việc hiện trên web và người nhận được nhắn riêng.
- Đăng ký ca theo tuần (menu Thêm → Đăng ký ca): nhân viên tick ca chắc chắn đi làm (thứ 2 – Chủ nhật), tối thiểu 5 ca rồi bấm Chốt. Trước khi chốt đổi thoải mái. Sau khi chốt (hoặc khi tuần đã bắt đầu): bỏ ca còn từ 3 ngày trở lên là báo trước, không bị tính (vẫn giữ tối thiểu 5 ca); bỏ ca còn trong vòng 2 ngày là **nghỉ gấp**: hiện cảnh báo đỏ, bắt buộc ghi lý do, báo Telegram cho Admin/Quản lý. Hệ thống chỉ đề xuất 100 điểm KPI (tái phạm trong tháng 300); người có quyền Event log bấm "Ghi Event log" mới ghi nhận. Thứ 7 và Chủ nhật, ai chưa chốt tuần tới sẽ thấy popup nhắc khi mở web (có ô "Không hiện hôm nay"); trang Chấm công có thẻ nhắc. Ca đã đăng ký tự hiện trong Lịch làm của tôi (chỉ để xem, đối soát chấm công vẫn theo lịch khách).
- Luật Studio (menu Thêm → Luật Studio): mỗi lỗi có mã riêng (VD NG-01), tên lỗi, loại luật tự đặt, nội dung và điểm KPI trừ. Mọi người xem được luật đang áp dụng; chỉ Admin thêm/sửa/ngừng áp dụng, có tìm kiếm, lọc theo loại và lịch sử thay đổi trước/sau. Hosting bắt đầu trống, Admin tự tạo luật.
- Nối Luật Studio với Event log: khi ghi sự việc, xác nhận sự việc chờ duyệt, ghi lỗi chấm công hoặc nghỉ gấp đều có ô "Áp luật Studio". Chọn mã lỗi thì điểm KPI tự lấy theo luật (khóa, không sửa tay), nội dung tự thêm tiền tố [mã] tên lỗi; sự việc hiện mã luật. Mã và tên luật được lưu tại thời điểm ghi, sau này sửa luật không đổi sự việc cũ. Không chọn luật thì vẫn chọn 0/20/100/300 như cũ.
- Telegram: cron mỗi phút nhắn người nhận đã liên kết bot, kèm nút mở mục Việc. Việc tạo quá 24 giờ chưa gửi được thì bỏ qua, không nhắn dồn.
- Database tự tạo các bảng mới `tasks`, `task_recipients`, `task_audit`, `shift_availability`, `shift_cancellations`, `availability_weeks`, `availability_audit`, `studio_rules`, `studio_rule_audit` (tự thêm cột nếu đã có bảng từ bản thử trước) khi mở web lần đầu; không đổi dữ liệu cũ.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (tasks.php, availability.php, studio-rules.php, workflows.php, bookings.php, telegram.php, schema.php, transfer.php, bootstrap.php, people-finance.php) |
| 2-private-bin.zip | nhansu-private/bin |
| 3-public-assets.zip | public_html/nhansu/assets (file mới: mobile.css, availability.js, availability.css, studio-rules.js, studio-rules.css, rule-picker.js; có cập nhật roster.js, bookings.js) |
| 4-public-root.zip | public_html/nhansu |

1. Thay theo thứ tự 1 → 2 → 3 → 4. Giữ nguyên config.php, storage, database và WordPress.
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại đóng tab cũ rồi mở lại; máy tính Ctrl + F5. Góc menu hiện **v1.3.0 · 27/10/2026**.
4. Thử: Admin giao một Nhắc nhở cho chính một nhân viên, nhân viên mở mục Việc bấm Đã xem.

Đã thử cục bộ trên bản dev với dữ liệu sao lưu. Chưa upload lên hosting.
