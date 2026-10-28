# Anh Gấu Studio — bàn giao cho Claude Code

Đọc file này trước khi sửa. Chủ dự án trao đổi bằng tiếng Việt, ưu tiên giao diện và thao tác trên điện thoại. Đây là ứng dụng nhân sự/chấm công riêng, đặt tại https://anhgaustudio.com/nhansu; website gốc là WordPress độc lập.

## Đúng thư mục cần làm

- **Bản production Hostinger: PHP/MySQL**, không phải server Node ở root.
- Frontend: `php-hostinger/site/public_html/nhansu/assets/`; entrypoint `index.php`, API `api.php?route=/...`.
- Backend: `php-hostinger/site/nhansu-private/lib/`; cron `bin/cron.php`.
- `npm start` / `npm run dev` ở root hiện khởi động PHP mới nhất qua Docker (`scripts/start-current.js`). `public/` và `server.js` là Node cũ, chỉ chạy rõ ràng bằng `npm run start:legacy`. Tài liệu cũ chuyển vào `docs/LEGACY-NODE.md`. Không chỉ sửa Node rồi coi như đã sửa hosting.
- Xem `BAN-GIAO-CLAUDE.md`, `php-hostinger/README.md` và các file `UPDATE-*.md` để biết lịch sử thay đổi.

## Quy tắc vận hành đã chốt

- Điện thoại là thiết bị chính. Nhân viên mở vào Chấm công, nút thao tác dễ bấm, giữ camera chụp mặt và GPS; ảnh lưu private, không cần quản lý duyệt từng lượt.
- Ca 1 09:00–14:00, ca 2 14:00–19:00. Cho checkout đến đúng 14:30 / 19:30; sau đó dùng yêu cầu bổ sung. OT tách riêng, bắt đầu từ 19h, có cảnh báo xác nhận; không tự cộng tiền OT theo giờ vào lương cứng.
- Hiện làm theo lịch khách: mốc đến là **khách đầu tiên được giao cho nhân viên trong từng ca**, không mặc định 9h/14h. Khách 12:00: đúng 12:30 chưa muộn; sau 12:30 mới muộn. Ca nhập tay chỉ tham khảo; không có khách được giao thì không tự ghi vắng/quên checkin. Từ 01/11/2026 (`BOOKING_CREDIT_FROM` trong rules.php): ca của **nhân viên** (role employee) không có khách được giao vẫn cho chấm công nhưng **không tính công**; Bảng lương hiện số "ca không khách" để cuối tháng Admin tự xét thưởng (chủ dự án chốt 28/10/2026, bỏ "mặc định 133k/ca"). Admin/Quản lý không áp quy tắc này. Lương theo công = lương cứng / số ngày của tháng (28–31) × công.
- Gán thợ trên Sheet một lần, Lịch làm việc (trước gọi Phân ca) tự lấy từ lịch khách; không tạo bản sao phân ca vật lý. Mốc đối soát được giữ lúc checkin, sửa Sheet sau đó không tự viết lại kết luận cũ.
- Lịch chụp mặc định Tuần trên máy tính; điện thoại (≤800px) mặc định Ngày, mở thẳng hôm nay và ẩn khối lịch nhỏ/thống kê (chủ dự án chốt 28/10/2026). Thứ Hai–thứ Bảy: tuần bình thường từ thứ Hai đến Chủ nhật; riêng ngày Chủ nhật: từ Chủ nhật đó đến thứ Bảy kế tiếp. Lịch nhỏ chọn lại ngày cũ, đánh dấu tất cả ngày có lịch trong cả khoảng lịch nhỏ, không chỉ tuần đang xem.
- Thẻ gọn: `12:00 - Tên khách - Gói 699k` / `Huy - Cường` (bỏ Ca vì đã có giờ, chủ dự án chốt 28/10/2026; không có tên khách thì bỏ phần tên); chỉ tên gọi trên thẻ, popup giữ thông tin đầy đủ theo quyền.
- Mọi role mặc định **xem lịch Toàn studio**, kể cả lịch chưa gán thợ. Có `Lịch của tôi` (`scope=mine`). Danh sách, dấu chấm và thống kê cùng phạm vi. Popup lịch hiện đủ mọi cột Sheet có dữ liệu cho mọi role (chủ dự án chốt 28/10/2026, kể cả SĐT/cọc/ghi chú); nhân viên chỉ không được xóa/sửa lịch. Quyền xem chung không thay đổi phân công để chấm công.
- Việc hằng ngày (`task_templates`): Admin/Quản lý cài cho từng người, bật/tắt bắt buộc ảnh; mỗi ngày tự sinh Task. Checkout **ca 2** bị chặn tới khi xong hết việc hằng ngày của hôm đó; ca 1 không ràng buộc. Chấm hộ có chế độ **cả ca** (ghi vào + ra một lần, có lý do).
- Event log: mọi role xem được; chỉ Admin và người được Admin chỉ định được ghi/sửa/xử lý. Manager không mặc định được sửa. Sửa lưu người, giờ, lý do và nội dung trước/sau. Giữ tài liệu/log cũ và lịch sử.
- 20 / 100 / 300 là **điểm KPI**. Mỗi người 2.000 điểm/tháng (Bảng lương có cột KPI bị trừ / còn lại). Chủ dự án chốt 28/10/2026: 1 điểm bị trừ = 1.000đ (Lỗi 20 = 20.000đ, Cảnh cáo 100 = 100.000đ, Kỷ luật giữ 300 điểm = 300.000đ, không theo mức 150.000đ của bảng lương Excel cũ), trừ vào Tổng thưởng (hiện chỉ có hoa hồng hệ thống tính), tối đa bằng Tổng thưởng, không trừ vào lương theo công (chủ dự án: thực tế luôn có thưởng gần bằng lương cứng nên giữ giới hạn này); Lương cuối = lương theo công + hoa hồng − trừ KPI. Bảng lương không hiện riêng cột số tiền bị trừ, chỉ hiện Lương cuối. Không có ô nhập thưởng tay (chủ dự án không muốn). Nhân viên chỉ thấy điểm KPI, không thấy tiền. Lương cứng và hoa hồng không hiện cho nhân viên; bảo vệ quyền ở backend.
- Telegram: nhắc checkin từ lịch khách +10 phút; nhắc checkout kết thúc ca +5 phút từ lượt checkin thực tế, không cần phân ca tay. Cron trên hosting chạy mỗi phút.
- Google Sheet dùng Apps Script `php-hostinger/google-sheets/AnhGauSync.gs`, gửi một chiều với HMAC. Không cần công khai Sheet. Dòng bị xóa không tự xóa lịch; dùng Hủy. Tên thợ cần ghép với hồ sơ để tạo phân công hợp lệ.

## Dữ liệu và triển khai

- Không sửa root WordPress, DB WordPress, `.htaccess` gốc website; không ghi đè `nhansu-private/config.php`, `storage/`, `.env` hoặc `.data/` khi cập nhật mã.
- Không đưa mật khẩu, token, database, ảnh chấm công, PDF/JSON dữ liệu lịch sử thật lên Git hoặc vào log công khai.
- Các ZIP cập nhật trước chia ba nhóm file phẳng: private `lib`, public `assets`, public root. Chỉ upload file bên trong và Replace; không Rename thư mục. Người dùng đã từng gặp lỗi 500 vì các thư mục lib/assets mới chỉ có một phần file.
- Đổi version URL trong index.php và import app.js khi thay frontend để tránh cache điện thoại. Chạy `php-hostinger/tools/build-diagnostic.py` sau khi chốt file để cập nhật manifest `kiemtra-capnhat.php`.
- Không nói đã cập nhật hosting nếu mới sửa local/đóng ZIP. Chưa có quyền SSH/hPanel trong gói bàn giao.
