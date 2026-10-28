# Cập nhật 28/10/2026 — Bộ đếm điểm KPI trong Bảng lương

Gói này cộng dồn cả **đổi mật khẩu từ avatar** và **Luật Studio gọn + Xóa luật** (`UPDATE-ACCOUNT-2026-10-28.md`, `UPDATE-RULES-2026-10-28.md`). Chưa upload hai gói đó thì chỉ cần upload gói này.

## Thay đổi

- Mỗi nhân viên có **2.000 điểm KPI mỗi tháng**. Bảng lương thêm hai cột:
  - **KPI bị trừ**: tổng điểm trừ trong tháng từ Event log ở trạng thái Đã xác nhận / Đã xử lý (chờ duyệt và đã hủy không tính).
  - **KPI còn lại**: 2.000 − điểm bị trừ, kèm thanh đếm: xanh từ 75%, vàng từ 50%, đỏ dưới 50%. Có thể âm nếu bị trừ quá 2.000.
- Tháng của một lỗi tính giống trang Event log khi lọc theo tháng, nên hai nơi cùng con số. Lỗi ghi theo khoảng ngày vắt qua hai tháng được tính ở cả hai tháng (giống Event log).
- **Quy đổi tiền: 1 điểm bị trừ = 1.000đ**, trừ vào Tổng thưởng. Hiện Tổng thưởng = hoa hồng mỗi khách do hệ thống tính (không có ô nhập thưởng tay). Tiền trừ tối đa bằng Tổng thưởng, không trừ vào lương theo công.
  - Cột tiền của Admin/Quản lý: Lương theo công · Hoa hồng · **Trừ KPI** · **Lương cuối** (= lương theo công + hoa hồng − trừ KPI). Dòng bị chạm mức tối đa ghi "tối đa bằng hoa hồng".
- Nhân viên xem Bảng công của mình chỉ thấy điểm KPI, không thấy tiền trừ, lương cứng hay hoa hồng.
- Chốt bảng lương: lưu luôn điểm KPI và tiền trừ KPI lúc chốt; Event log sửa sau đó không đổi tháng đã chốt. Tháng đã chốt từ trước bản này giữ nguyên tiền đã chốt, chỉ hiện thêm điểm.
- Tạm bỏ cột **Khách / OT** khỏi bảng (cột Lượt / giờ OT vẫn giữ). CSV thêm Điểm KPI bị trừ, Điểm KPI còn lại, Trừ KPI, Lương cuối.
- Câu "chưa quy đổi thành tiền" ở Event log, Luật Studio, đối soát lịch đổi thành "khấu trừ vào thưởng tháng".
- Không đổi database. v1.3.6.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (api.php, bootstrap.php, people-finance.php, rules.php, studio-rules.php, workflows.php) |
| 2-public-assets.zip | public_html/nhansu/assets (app.js, availability.js, bookings.js, people-finance.js, rule-picker.js, style.css, studio-rules.js, studio-rules.css) |
| 3-public-root.zip | public_html/nhansu (index.php, kiemtra-capnhat.php) |

1. Thay theo thứ tự 1 → 2 → 3. Giữ nguyên config.php, storage, database và WordPress.
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại đóng tab cũ rồi mở lại; máy tính Ctrl + F5. Góc menu hiện **v1.3.6 · 28/10/2026**.
4. Mở Công & lương → Bảng lương, xem hai cột KPI.

Đã thử cục bộ trên bản dev: chỉ cộng lỗi Đã xác nhận/Đã xử lý trong đúng tháng, bỏ qua chờ duyệt/đã hủy; 120 điểm trừ 120.000đ vào hoa hồng; bị trừ vượt hoa hồng thì chỉ trừ hết hoa hồng; nhân viên chỉ thấy điểm; tháng đã chốt giữ nguyên. Giao diện điện thoại 390px và máy tính 1366px.
