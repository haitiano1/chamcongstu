# Cập nhật 28/10/2026 — Công theo lịch khách, chia lương theo số ngày của tháng

Gói này cộng dồn mọi thay đổi ngày 28/10 sau bản đã upload (đổi mật khẩu, Luật Studio, KPI trong Bảng lương). Chỉ cần upload gói này.

## Thay đổi

- **Ca không có khách không tính công** (áp dụng từ **01/11/2026**, chỉ cho tài khoản Nhân viên):
  - Ca có ít nhất một khách được giao cho người đó trên lịch chụp (thợ chính hoặc phụ, lịch đang hoạt động, giờ khách nằm trong ca) mới được 0,5 công.
  - Ca không có khách vẫn cho checkin/checkout bình thường (phòng khi Sheet thêm khách muộn), nhưng không cộng công, không cộng lương theo công.
  - Bảng lương ghi dưới cột Ca hoàn tất: "N ca không khách · không tính công". Cuối tháng Admin xem để tự xét thưởng. CSV có thêm cột Ca không khách.
  - Lịch bị hủy/xóa không tính là có khách. Admin/Quản lý không áp quy tắc này.
  - Trước 01/11/2026 giữ cách cũ (ca hoàn tất là có công), vì lịch khách mới có từ khi đồng bộ Sheet.
- **Lương theo công = lương cứng / số ngày của tháng × công**: tháng 30 ngày chia 30, tháng 31 ngày chia 31, tháng 2 chia 28/29. Lương 8 triệu tháng 11: mỗi ca 133.333đ. Tháng 10 có 31 ngày nên số liệu tháng 10 không đổi.
- Trừ KPI giữ nguyên: tối đa bằng thưởng, không trừ vào lương theo công.
- **Đăng ký ca: tối thiểu 10 ca/tuần** (trước là 5). Chọn đủ 10 ca mới bấm được Chốt. Tuần đã chốt từ trước với ít hơn 10 ca vẫn giữ nguyên; muốn bỏ một ca báo trước thì phải chọn thêm ca thay thế cho đủ 10 (nghỉ gấp có lý do vẫn như cũ). (v1.3.9)
- **Lịch trên điện thoại mở thẳng lịch hôm nay**: mặc định chế độ Ngày (máy tính vẫn mặc định Tuần). Ẩn khối Chọn ngày · Xem lịch cũ và thống kê ở đầu trang trên điện thoại; xem ngày khác bằng ‹ › hoặc Bộ lọc & chọn ngày, vẫn đổi được sang Tuần/Tháng. (v1.3.10)
- **Thẻ lịch chụp: Giờ - Tên khách - Gói - Ca** (ví dụ `11:00 - Minh Anh - Gói 1499k - Ca 1`). Dòng dưới vẫn là tên thợ. Lịch chưa có tên khách thì hiện Giờ - Gói - Ca. (v1.3.12)
- **Bỏ tab Ca của tôi của nhân viên**: mục Lịch của nhân viên chỉ còn Lịch chụp và Đăng ký ca. Thợ chính/phụ xem ca ngay trên thẻ lịch chụp. Link cũ `#roster` tự chuyển về Lịch chụp. Admin/Quản lý vẫn có tab Lịch làm việc. (v1.3.13)
- **Nút tải lại ở đầu trang (điện thoại)**: nút tròn ↻ cạnh avatar, bấm để tải lại đúng trang đang xem và lấy bản mới nhất. Dùng khi mở app từ màn hình chính (không có thanh trình duyệt). Máy tính không hiện nút này. (v1.3.14)
- **Icon màn hình chính**: thêm icon bảng việc + đồng hồ chấm công (nền xanh than, vàng theo logo) cho iPhone/Android và tab trình duyệt. Icon cũ chữ "A" đã thêm vào màn hình chính trước đây không tự đổi: xóa icon cũ rồi Thêm vào MH chính lại. (v1.3.14)
- Không đổi database. v1.3.14.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (api.php, availability.php, bookings.php, bootstrap.php, people-finance.php, rules.php, studio-rules.php, workflows.php) |
| 2-public-assets.zip | public_html/nhansu/assets (app.js, availability.js, bookings.js, mobile.css, people-finance.js, rule-picker.js, style.css, studio-journal.css, studio-rules.js, studio-rules.css, app-icon.svg, app-icon-180.png, app-icon-192.png, app-icon-512.png, app.webmanifest) |
| 3-public-root.zip | public_html/nhansu (index.php, kiemtra-capnhat.php) |

1. Thay theo thứ tự 1 → 2 → 3. Giữ nguyên config.php, storage, database và WordPress.
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại đóng tab cũ rồi mở lại; máy tính Ctrl + F5. Góc menu hiện **v1.3.14 · 28/10/2026**.
4. Trước 01/11: kiểm tra tên thợ trên Sheet đã ghép đúng hồ sơ nhân viên (Quản lý → đồng bộ Sheet). Tên chưa ghép thì ca của người đó sẽ bị tính là không khách.

Đã thử cục bộ trên bản dev: 4 ca hoàn tất trong đó 1 ca không khách → 1,5 công, 400.000đ với lương 8 triệu tháng 11; lịch đã hủy không tính; quản lý không bị áp; tháng 10 không đổi; nhân viên thấy số ca không khách nhưng không thấy tiền. Kiểm thử quy tắc nghiệp vụ `tests/rules.php`: 32 đạt.
