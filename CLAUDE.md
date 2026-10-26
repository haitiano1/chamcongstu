# Anh Gấu Studio — bàn giao cho Claude Code

Đọc file này trước khi sửa. Chủ dự án trao đổi bằng tiếng Việt, ưu tiên giao diện và thao tác trên điện thoại. Đây là ứng dụng nhân sự/chấm công riêng, đặt tại https://anhgaustudio.com/nhansu; website gốc là WordPress độc lập.

## Đúng thư mục cần làm

- **Bản production Hostinger: PHP/MySQL**, không phải server Node ở root.
- Frontend: `php-hostinger/site/public_html/nhansu/assets/`; entrypoint `index.php`, API `api.php?route=/...`.
- Backend: `php-hostinger/site/nhansu-private/lib/`; cron `bin/cron.php`.
- `public/` và `server.js` ở root là bản Node/SQLite trước đây. Không chỉ sửa bản Node rồi coi như đã sửa bản trên hosting.
- Xem `BAN-GIAO-CLAUDE.md`, `php-hostinger/README.md` và các file `UPDATE-*.md` để biết lịch sử thay đổi.

## Quy tắc vận hành đã chốt

- Điện thoại là thiết bị chính. Nhân viên mở vào Chấm công, nút thao tác dễ bấm, giữ camera chụp mặt và GPS; ảnh lưu private, không cần quản lý duyệt từng lượt.
- Ca 1 09:00–14:00, ca 2 14:00–19:00. Cho checkout đến đúng 14:30 / 19:30; sau đó dùng yêu cầu bổ sung. OT tách riêng, bắt đầu từ 19h, có cảnh báo xác nhận; không tự cộng tiền OT theo giờ vào lương cứng.
- Hiện làm theo lịch khách: mốc đến là **khách đầu tiên được giao cho nhân viên trong từng ca**, không mặc định 9h/14h. Khách 12:00: đúng 12:30 chưa muộn; sau 12:30 mới muộn. Ca nhập tay chỉ tham khảo; không có khách được giao thì không tự ghi vắng/quên checkin.
- Gán thợ trên Sheet một lần, Lịch làm việc (trước gọi Phân ca) tự lấy từ lịch khách; không tạo bản sao phân ca vật lý. Mốc đối soát được giữ lúc checkin, sửa Sheet sau đó không tự viết lại kết luận cũ.
- Lịch chụp mặc định Tuần. Thứ Hai–thứ Bảy: tuần bình thường từ thứ Hai đến Chủ nhật; riêng ngày Chủ nhật: từ Chủ nhật đó đến thứ Bảy kế tiếp. Lịch nhỏ chọn lại ngày cũ, đánh dấu tất cả ngày có lịch trong cả khoảng lịch nhỏ, không chỉ tuần đang xem.
- Thẻ gọn: `12:00 - Gói 699k - Ca 1` / `Huy - Cường`; chỉ tên gọi trên thẻ, popup giữ thông tin đầy đủ theo quyền.
- Mọi role mặc định **xem lịch Toàn studio**, kể cả lịch chưa gán thợ. Có `Lịch của tôi` (`scope=mine`). Danh sách, dấu chấm và thống kê cùng phạm vi. Nhân viên vẫn không được sửa lịch và không được API trả SĐT khách/ghi chú nội bộ/nguồn Sheet. Quyền xem chung không thay đổi phân công để chấm công.
- Event log: mọi role xem được; chỉ Admin và người được Admin chỉ định được ghi/sửa/xử lý. Manager không mặc định được sửa. Sửa lưu người, giờ, lý do và nội dung trước/sau. Giữ tài liệu/log cũ và lịch sử.
- 20 / 100 / 300 là **điểm KPI**, không phải tiền. Không tự quy đổi thành tiền/phạt lương. Lương cứng và hoa hồng không hiện cho nhân viên; bảo vệ quyền ở backend.
- Telegram: nhắc checkin từ lịch khách +10 phút; nhắc checkout kết thúc ca +5 phút từ lượt checkin thực tế, không cần phân ca tay. Cron trên hosting chạy mỗi phút.
- Google Sheet dùng Apps Script `php-hostinger/google-sheets/AnhGauSync.gs`, gửi một chiều với HMAC. Không cần công khai Sheet. Dòng bị xóa không tự xóa lịch; dùng Hủy. Tên thợ cần ghép với hồ sơ để tạo phân công hợp lệ.

## Dữ liệu và triển khai

- Không sửa root WordPress, DB WordPress, `.htaccess` gốc website; không ghi đè `nhansu-private/config.php`, `storage/`, `.env` hoặc `.data/` khi cập nhật mã.
- Không đưa mật khẩu, token, database, ảnh chấm công, PDF/JSON dữ liệu lịch sử thật lên Git hoặc vào log công khai.
- Các ZIP cập nhật trước chia ba nhóm file phẳng: private `lib`, public `assets`, public root. Chỉ upload file bên trong và Replace; không Rename thư mục. Người dùng đã từng gặp lỗi 500 vì các thư mục lib/assets mới chỉ có một phần file.
- Đổi version URL trong index.php và import app.js khi thay frontend để tránh cache điện thoại. Chạy `php-hostinger/tools/build-diagnostic.py` sau khi chốt file để cập nhật manifest `kiemtra-capnhat.php`.
- Không nói đã cập nhật hosting nếu mới sửa local/đóng ZIP. Chưa có quyền SSH/hPanel trong gói bàn giao.
