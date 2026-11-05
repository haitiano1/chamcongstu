# Cập nhật 04/11/2026 — Ứng lương: bộ lọc dễ nhìn, Sửa ngày (chuyển phiếu về tháng trước), Ứng lương trừ vào phiếu lương

Gói này **cộng dồn** gói 03/11 (Hỗ trợ, Task cần duyệt, Tìm hiểu web…). Chưa upload gói 03/11 thì upload gói này là đủ.

## Thay đổi (v1.3.53)

### 1. Bộ lọc Ứng lương dễ nhìn hơn

- Khung lọc đưa lên ngay dưới Quy định ứng lương, đổi là lọc ngay (không cần bấm Lọc):
  - **‹ Tháng 11/2026 ›**: bấm mũi tên để sang tháng trước / sau, bấm vào chữ tháng để chọn tháng khác.
  - Nút trạng thái có số phiếu: **Tất cả · Chờ duyệt · Đã duyệt · Đã chi · Từ chối · Đã hủy**.
  - **Nhân viên** (Admin/Quản lý) và **Tính theo: Ngày đề nghị | Ngày đã chi**.
  - **Lọc thêm · Xuất CSV**: Ngày cụ thể, Phạm vi (Toàn studio / Của tôi), Xuất CSV.
- Dòng cuối khung ghi đang xem gì (VD "Tháng 11/2026 · Theo ngày đề nghị · 4 phiếu"); đang lọc khác mặc định thì có **✕ Xóa lọc**.

### 2. Sửa ngày phiếu ứng (chuyển về tháng trước)

- Admin/Quản lý: mỗi phiếu có nút **📅 Sửa ngày** (mọi trạng thái).
- Bấm **↩ Chuyển về cuối tháng 10/2026 (31/10)** là điền sẵn ngày cuối tháng trước và lý do; hoặc tự chọn ngày. Phiếu **Đã chi** đổi luôn **Ngày thực tế đã chi**, để tính theo ngày nào cũng về đúng tháng.
- Lưu xong, phiếu rời khỏi tháng này và vào **danh sách + Thống kê ứng lương** (theo nhân viên, theo ngày) của tháng mới. **Lịch sử** của phiếu ghi lại ngày cũ → ngày mới, người sửa, lý do.
- Không tự sửa phiếu của chính mình (giống Duyệt / Xác nhận chi).

### 3. Ứng lương trên phiếu lương (trừ cuối tháng)

- Công & lương → bấm tên nhân viên → phiếu lương có thêm, sau KPI:
  - **Lương sau KPI** (khi có ứng),
  - **Ứng lương đã nhận −X**: từng phiếu (ngày chi · số tiền · lý do),
  - **Tổng tiền cuối cùng = Lương sau KPI − ứng lương** (ứng nhiều hơn lương thì ghi "ứng vượt lương").
- Chỉ trừ phiếu **Đã chi** có **ngày đã chi trong tháng** của phiếu lương. Phiếu còn chờ duyệt / đã duyệt chưa chi thì phiếu lương nhắc "Còn N phiếu … chưa trừ", bấm **Xác nhận đã chi** ở Ứng lương thì mới trừ.
- Bảng lương: cột **Lương cuối** đã trừ ứng (ghi "−Ứng …"), ô tổng có thêm **Đã ứng (trừ)**; **Tổng tiền cuối cùng** đã trừ ứng. Xuất bảng lương CSV có thêm cột Ứng lương đã chi và Tổng tiền cuối cùng.
- Ứng lương tính trực tiếp từ mục Ứng lương (cả tháng đã chốt), nên dùng **Sửa ngày** chuyển phiếu sang tháng nào thì phiếu lương tháng đó đổi theo.
- Nhân viên không thấy phần này trên Bảng công (vẫn xem phiếu ứng của mình ở Ứng lương).

### 4. Tìm hiểu web

- Mục **Ứng lương**: chụp lại ảnh vì trang có khung lọc mới; cách gửi yêu cầu không đổi nên nhân viên không phải xác nhận lại.

## File cần upload

Chỉ upload **file bên trong** từng ZIP và chọn Replace:

1. `1-private-lib.zip` → `nhansu-private/lib/` (Replace). Lần này đổi `people-finance.php`, `bootstrap.php` (cùng các file lib khác trong gói).
2. `2-private-bin.zip` → `nhansu-private/bin/` (Replace): `cron.php` (không đổi, có sẵn để gói đủ bộ).
3. `3-public-assets.zip` → `public_html/nhansu/assets/` (Replace). Lần này đổi `app.js`, `people-finance.js`, `people-finance.css`, `payroll.js`, `style.css`, `guide.js`, 3 ảnh `guide-advances-*.jpg` (cùng các file khác trong gói, gồm cả file mới của gói 03/11: `money-input.js`, `support.js`, `guide.js`, `guide-media.js`, ảnh `guide-….jpg`).
4. `4-public-root.zip` → `public_html/nhansu/` (Replace): `index.php`, `kiemtra-capnhat.php`, `api.php`.

Không đổi database (không thêm bảng), Google Apps Script, cron.

Sau khi upload, mở `https://anhgaustudio.com/nhansu/kiemtra-capnhat.php` để kiểm tra đủ file. Trên điện thoại bấm ↻ để lấy bản mới.
