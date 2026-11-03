# Cập nhật 03/11/2026 — Tín có Ăn uống 300k; ô nhập tiền tự thêm dấu chấm

Gói này **cộng dồn** mọi gói ngày 02/11. Chưa upload gói 02/11 thì upload gói này là đủ.

## Thay đổi (v1.3.47)

### 1. Nhóm Trực Fanpage (Tín) có thêm Ăn uống 300.000đ/tháng

- Phiếu lương và Phiếu thưởng của Tín có dòng **Ăn uống** mặc định 300.000đ, giống Huy, Cường.
- Đi ăn thì trừ bớt như Thợ chụp: sửa ô Ăn uống trên phiếu lương, hoặc khung **Nhập thưởng nhân viên** chọn Tín → Ăn uống → Trừ bớt.
- Các khoản khác của Tín giữ nguyên: Chuyên cần, Ca làm không có khách, Tips (chia 3), Trực Fanpage 2.000.000đ.

### 2. Ô nhập tiền tự thêm dấu chấm

Gõ 50000 thì ô tự hiện **50.000**:
- Hồ sơ nhân viên: Lương cứng / tháng, Hoa hồng mỗi khách chụp.
- Ứng lương: Số tiền.
- Lịch chụp → Sửa lịch → Sau khi chụp: Phụ thu, Tip (gõ kiểu "100k" vẫn được, giữ nguyên chữ).
- Phiếu lương và Nhập thưởng vẫn có dấu chấm như trước.

## File cần upload

Như các lần trước, chỉ upload **file bên trong** từng ZIP và chọn Replace:

1. `1-private-lib.zip` → giải nén vào `nhansu-private/lib/` (Replace): `people-finance.php` (cùng các file lib khác trong gói).
2. `2-private-bin.zip` → `nhansu-private/bin/` (Replace): `cron.php` (không đổi, có sẵn để gói đủ bộ).
3. `3-public-assets.zip` → `public_html/nhansu/assets/` (Replace): `app.js`, `bookings.js`, `employees.js`, `payroll.js`, `people-finance.js` và **file mới `money-input.js`** (thiếu file này app sẽ không mở được), cùng các file khác trong gói.
4. `4-public-root.zip` → `public_html/nhansu/` (Replace): `index.php`, `kiemtra-capnhat.php`.

Không đổi Google Apps Script, không đổi cron.

Sau khi upload, mở `https://anhgaustudio.com/nhansu/kiemtra-capnhat.php` để kiểm tra đủ file. Trên điện thoại bấm ↻ để lấy bản mới.
