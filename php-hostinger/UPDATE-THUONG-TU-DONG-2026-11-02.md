# Cập nhật 02/11/2026 (lần 4) — Thưởng tự tính trên phiếu lương: Ca làm không có khách, Tiền khách Tips (kèm nhật ký Tip), Ngoại cảnh, Chỉnh ảnh, Ăn uống, Trực Fanpage

Gói này **cộng dồn** mọi gói trước trong ngày 02/11. Chưa upload các gói đó thì upload gói này là đủ.

## Thay đổi (v1.3.46)

Các khoản dưới đây áp dụng từ **tháng 11/2026**. Tháng đã chốt giữ nguyên số lúc chốt.

### 1. Nhóm thưởng: Thợ chụp và Trực Fanpage

- Hồ sơ nhân viên (Quản lý → Nhân viên → mở hồ sơ) có ô mới **Nhóm thưởng**:
  - **Thợ chụp** (Huy, Cường): nhận đủ các khoản thưởng.
  - **Trực Fanpage** (Tín): chỉ có **Chuyên cần** (đi làm đủ 28 ngày), **Ca làm không có khách**, **Tiền khách Tips** (Tip chia 3) và **Trực Fanpage 2.000.000đ/tháng**. Không có Ngoại cảnh, Chỉnh ảnh, Ăn uống, OT, hoa hồng khách chụp.
- Lần đầu mở, hệ thống tự đặt **Tín** vào nhóm Trực Fanpage, những người còn lại là Thợ chụp. Muốn đổi thì sửa ô này trong hồ sơ.

### 2. Ca làm không có khách = số ca × lương 1/2 ngày (tự tính)

- Phiếu lương ghi **Ca làm không có khách (x ca)**. x là số ca nhân viên **chấm công đủ** mà **cả studio không có khách** theo lịch Sheet (VD khách đầu tiên trong ngày lúc 15h thì Ca 1 9h–14h là ca không khách).
- Tiền = x × lương 1/2 ngày (lương cứng ÷ số ngày của tháng ÷ 2). Khoản này tự tính, không sửa tay.
- Bên dưới có ngày của từng ca (VD "02/11 Ca 1"). Bấm **Lịch Sheet cả tháng** để xem tổng số ca không khách của cả studio trong tháng theo lịch: các ca đã qua và các ca sắp tới còn trống (dùng để đối chiếu với Sheet).

### 3. Tiền khách Tips: tự chia đều, có nhật ký Tip

- Chụp xong, ghi Tip ở **Lịch chụp → Sửa lịch → Sau khi chụp** (hoặc cột Tip trên Sheet).
- Tổng Tip của tháng **chia đều cho 3 nhân viên Huy, Cường, Tín** (chia 3). Phiếu lương và Phiếu thưởng ghi **Tiền khách Tips (tổng/3)**, ô tiền tự điền và **khóa, không sửa tay**.
- **Bấm vào chữ "Tiền khách Tips"** (trên phiếu lương của Admin/Quản lý, hoặc Phiếu thưởng của nhân viên) để mở **nhật ký Tip**:
  - Tổng Tip của tháng, số khách có Tip, mỗi người được bao nhiêu, chia cho những ai.
  - Tip từng khách: ngày, thứ, ca, giờ chụp, tên khách, số tiền, **ai nhập** (tên người sửa trên web, hoặc "Google Sheet") và lúc nhập.
  - Lịch sử mọi lần nhập / sửa Tip (số cũ → số mới).
  - Nút **⬇ Xuất CSV** để tải toàn bộ dữ liệu.
- Số Tip ghi được: 100000, 100.000, 100k; số dưới 1.000 tính là nghìn đồng (VD 50 = 50.000đ).

### 4. Ngoại cảnh: +150.000đ mỗi lịch (tự tính)

- Lịch có chữ **"ngoại cảnh"** (ô Đặc Biệt, Ghi chú, tên khách…) mà **Huy hoặc Cường** (nhóm Thợ chụp) **có chấm công trong giờ chụp** thì +150.000đ mỗi lịch cho người đó.
- Phiếu ghi **Ngoại cảnh (n lịch)**, ngày giờ từng lịch được tính và những lịch không tính vì lúc đó không chấm công. Khoản này khóa, không sửa tay.

### 5. Chỉnh ảnh: nhập số ảnh, tự nhân 2.000đ

- Cuối tháng, trên phiếu lương, Admin/Quản lý nhập **số ảnh đã chỉnh**. Phiếu tự tính số ảnh × 2.000đ, rồi bấm Lưu thưởng.
- Lịch sử thưởng của nhân viên ghi lại số ảnh mỗi lần sửa.

### 6. Ăn uống: mặc định 300.000đ

- Mỗi người nhóm Thợ chụp có sẵn **300.000đ** Ăn uống mỗi tháng.
- Mỗi lần đi ăn thì vào **Nhập thưởng nhân viên → Ăn uống → − Trừ bớt** số tiền đã ăn (hoặc sửa tổng trên phiếu lương). Không trừ quá số còn lại.

### 7. Ghi rõ lịch "gói chưa có mức"

- Trước đây phiếu chỉ ghi "2 khách gói chưa có mức, tính 0đ". Nay ghi rõ từng lịch, VD `01/11 10:30 Chị Lan · Gói trống` hoặc `· Gói "Combo 500"`.
- Lịch bị tính 0đ khi ô **Gói trống** (lịch thêm trên web để trống ô Gói, khách tới mới chọn) hoặc gói không thuộc 349 / 399 / 699 / Couple 999 / 1499 / 2999.
- Cách sửa: vào **Lịch chụp**, mở lịch đó, ở mục **Gói khách chụp** chọn đúng gói rồi bấm **Lưu gói**. Phiếu lương tự tính lại hoa hồng.

### Lưu ý

- Trước bản này, các ô Tips, Ngoại cảnh, Chỉnh ảnh, Ca làm không có khách trên phiếu lương là ô nhập tay. Nếu ai đó đã gõ số vào các ô này cho tháng 11 thì số đó không còn được dùng; phiếu hiện số tự tính. Chưa ai gõ thì không ảnh hưởng gì.
- Tips chia theo số tài khoản có Vai trò **Nhân viên** đang làm (Huy, Cường, Tín). Các tài khoản khác để Vai trò Quản lý / Admin. Danh sách người được chia có trong nhật ký Tip.
- Khung **Nhập thưởng nhân viên** chỉ còn Nóng / Khen, Giới thiệu khách, Full khách ngày, Ăn uống (cho Thợ chụp). Trực Fanpage không có khoản nhập tay.

## File cần upload

1. `1-private-lib.zip` → giải nén vào `nhansu-private/lib/` (Replace): `api.php`, `bookings.php`, `bootstrap.php`, `people-finance.php`, `rules.php`, `schema.php` (cùng các file lib khác trong gói).
2. `2-private-bin.zip` → `nhansu-private/bin/` (Replace): `cron.php` (không đổi, có sẵn để gói đủ bộ).
3. `3-public-assets.zip` → `public_html/nhansu/assets/` (Replace): `app.js`, `employees.js`, `payroll.js`, `style.css` (cùng các file khác trong gói).
4. `4-public-root.zip` → `public_html/nhansu/` (Replace): `index.php`, `kiemtra-capnhat.php`.

Bảng mới (`payroll_profiles`, `booking_tip_log`) tự tạo ở lần mở đầu tiên. Sau khi upload, mở `https://anhgaustudio.com/nhansu/kiemtra-capnhat.php` để kiểm tra đủ file. Điện thoại bấm ↻ để tải bản mới (v1.3.46).
