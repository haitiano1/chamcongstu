# Cập nhật 03/11/2026 — Tín có Ăn uống 300k; ô nhập tiền tự thêm dấu chấm; danh sách Việc chia theo ngày; thống kê Lịch chụp theo tháng, số khách lịch Team; mục Hỗ trợ; Task cần duyệt; Tìm hiểu web

Gói này **cộng dồn** mọi gói ngày 02/11. Chưa upload gói 02/11 thì upload gói này là đủ.

## Thay đổi (v1.3.52)

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

### 3. Danh sách Việc / Nhắc nhở gọn hơn, chia theo ngày, có phân trang

- Danh sách chia nhóm theo ngày: **Hôm nay**, **Hôm qua**, rồi các ngày trước. Mỗi ngày ghi số mục và số chưa xong.
- Mỗi trang 12 mục, cuối danh sách có nút **‹ Trước / Sau ›**.
- Thêm lọc **Task / Nhắc nhở**. Tab **Cần làm** có số việc cần làm.
- Thẻ việc gọn hơn. Nút Xóa thành biểu tượng 🗑 ở góc phải. Admin/Quản lý thấy tên người nhận trên từng thẻ.
- Admin/Quản lý: việc hằng ngày của cùng một ngày gộp vào một mục **Việc hằng ngày · x/y xong**, bấm để mở xem từng người.
- Tab Cần làm (Tất cả đã giao) không còn hiện việc hằng ngày của các ngày đã qua, vì không báo xong được nữa. Muốn xem lại thì chọn tab **Tất cả**.

### 4. Thống kê Lịch chụp theo tháng, thêm Số khách đã chụp

- Khối thống kê bên trái Lịch chụp (trên máy tính) giờ tính **trong tháng đang xem**: **Tổng lịch tháng**, **Lịch hôm nay**, **Lịch sắp tới** (chưa tới giờ chụp), **Số khách đã chụp** (lịch đã qua giờ chụp), Đã hủy. Chuyển sang tháng khác thì số đổi theo tháng đó.
- Số khách đã chụp đếm giống mục **Khách chụp → Số khách chụp** trên phiếu lương. Riêng phiếu lương vẫn chỉ tính khách lúc thợ có chấm công trong giờ chụp; khách bị loại ghi ở dòng "khách lúc đã chấm ra / không chấm công".

### 5. Lịch Team: thợ nhập số khách theo gói sau khi chụp

- Mở lịch gói **Team** đã qua giờ chụp, popup có khung **Số khách đã chụp (Team)**: mỗi gói (Team 349k, 399k, 699k, Couple 999k, 1499k, 2999k) có nút − / + để nhập số khách, ghi tổng khách và số suất hoa hồng. Bấm **Lưu số khách**.
- Thợ của lịch, Quản lý, Admin và người được tick Lên lịch chụp nhập được. Tháng đã chốt lương thì không sửa được.
- Nhập xong, số khách này cộng vào **Số khách đã chụp** (thống kê Lịch chụp) và **Số khách chụp** trên phiếu lương. Hoa hồng tính theo từng gói (Couple 999 ×2, 1499 ×4, 2999 ×8). Thẻ lịch ghi thêm "- 10 khách".
- Nếu trên Sheet đã ghi mỗi người một dòng Team cùng giờ thì khi nhập ở web, cả nhóm tính theo số đã nhập (không đếm trùng). Chưa nhập thì mỗi dòng Team vẫn là 1 khách như trước.
- Số khách Team chỉ lưu trên web, không ghi xuống Google Sheet.

### 6. Mục Hỗ trợ: nhân viên gửi yêu cầu, Admin/Quản lý duyệt (bớt nhắn tin riêng)

- Tab **Việc** có 2 tab con: **📋 Việc được giao** | **🆘 Hỗ trợ**.
- Nhân viên bấm **＋ Gửi yêu cầu** (trong tab Hỗ trợ, hoặc khung **🆘 Cần hỗ trợ?** ở trang Chấm công): chọn loại (Thiết bị / cơ sở vật chất, Đổi ca / nghỉ, Lương / thưởng, Khác), ghi tiêu đề, nội dung, thêm tối đa 3 ảnh (chụp mới hoặc chọn ảnh có sẵn).
- Gửi xong, **Telegram báo ngay Admin + Quản lý**, có nút mở thẳng yêu cầu đó.
- Mở yêu cầu có khung **Trao đổi**: hai bên nhắn qua lại ngay trong yêu cầu, mỗi lần trả lời Telegram báo bên kia.
- Admin/Quản lý bấm **✅ Duyệt**, **✔️ Đã xử lý xong** hoặc **Từ chối** (bắt buộc ghi lý do). Mỗi bước Telegram báo nhân viên.
- Nhân viên chỉ thấy yêu cầu của mình, **rút** được khi còn Chờ xử lý.
- Số đỏ trên nút **Việc**: Admin/Quản lý thấy số yêu cầu chưa xong; nhân viên thấy số yêu cầu có trả lời mới.
- Danh sách chia theo ngày, phân trang, lọc Đang mở / Đã xong / Từ chối – rút / Tất cả.
- Ứng lương và Yêu cầu bổ sung công vẫn ở chỗ cũ.

### 7. Task cần duyệt

- Khi **Giao việc / Nhắc nhở** (loại Task) có ô **☐ Task cần duyệt**. Sửa việc cũng bật / tắt được.
- **Có tick:** nhân viên bấm **Báo đã xong** (kèm ảnh nếu việc bắt buộc ảnh) → việc chuyển **⏳ Chờ duyệt**, **Telegram báo ngay Admin + Quản lý kèm ảnh bằng chứng**.
  - Trang Việc của Admin/Quản lý có khung **📋 Việc chờ duyệt**: xem ghi chú, ảnh, bấm **✅ Duyệt** → Đã xong.
  - Bấm **Chưa duyệt** → hiện ô ghi **lý do** (bắt buộc) → nhân viên thấy lý do ngay trên việc ("Chưa duyệt · làm lại") và nhận Telegram; việc quay lại Cần làm để làm lại rồi bấm Báo đã xong lần nữa.
  - Đang chờ duyệt thì nhân viên vẫn chấm ra được (đã làm phần của mình); bị Chưa duyệt mà việc hạn hôm nay thì lại khóa chấm ra ca 2 tới khi báo xong lại.
  - Số đỏ trên nút Việc của Admin/Quản lý cộng thêm số việc chờ duyệt.
- **Không tick:** như cũ, nhân viên báo xong là Đã xong.
- Việc hằng ngày chưa có chế độ duyệt.

### 8. Tìm hiểu web: checklist hướng dẫn có ảnh động, nhân viên tick xác nhận

- Trang mới **Thêm → Tìm hiểu web** (nhóm Studio, cạnh Biên bản họp). Có 15 mục: Mở app, Liên kết Telegram, Chấm công vào ca, Làm cả ngày, Chấm ra & làm tiếp, OT, Yêu cầu bổ sung công, Lịch chụp, Đăng ký ca, Việc & Nhắc nhở, Hỗ trợ, Bảng công & Phiếu thưởng, KPI & Luật Studio, Ứng lương, Biên bản họp.
- Mỗi mục: hướng dẫn **từng bước**, **ảnh động** (ảnh chụp app tự chạy qua từng bước, vòng đỏ nhấp nháy đúng chỗ cần bấm, có nút ‹ ⏸ ›) và nút **Mở trang này để thử**.
- Nhân viên phải mở mục và đọc một lúc thì ô **"Tôi đã đọc, đã được hướng dẫn và nắm rõ chức năng này"** mới mở. Tick xong app hỏi lại, rồi lưu **giờ xác nhận**. **Không bỏ tick được** (làm bằng chứng đã được hướng dẫn).
- Trang Chấm công của nhân viên có khung **📚 Tìm hiểu web x/15** cho tới khi xác nhận đủ.
- Admin/Quản lý: xem tiến độ từng người, từng mục, giờ xác nhận; **🔔 Nhắc người chưa xong** qua Telegram; **⬇ Xuất CSV**. Nhân viên xác nhận đủ 15/15 thì Telegram báo Admin/Quản lý.
- Ảnh hướng dẫn chụp bằng dữ liệu thử (khách demo, tài khoản test), không có dữ liệu thật.

## File cần upload

Như các lần trước, chỉ upload **file bên trong** từng ZIP và chọn Replace:

1. `1-private-lib.zip` → giải nén vào `nhansu-private/lib/` (Replace): `people-finance.php`, `tasks.php`, `bookings.php`, `schema.php`, `bootstrap.php`, `telegram.php`, `studio-rules.php` (cùng các file lib khác trong gói).
2. `2-private-bin.zip` → `nhansu-private/bin/` (Replace): `cron.php` (không đổi, có sẵn để gói đủ bộ).
3. `3-public-assets.zip` → `public_html/nhansu/assets/` (Replace): `app.js`, `bookings.js`, `employees.js`, `payroll.js`, `people-finance.js`, `tasks.js`, `tasks.css`, `studio-journal.css` và **các file mới `money-input.js`, `support.js`, `guide.js`, `guide-media.js` và 45 ảnh `guide-….jpg`** (thiếu file .js mới thì app sẽ không mở được; thiếu ảnh thì khung ảnh động trống), cùng các file khác trong gói.
4. `4-public-root.zip` → `public_html/nhansu/` (Replace): `index.php`, `kiemtra-capnhat.php`.

Không đổi Google Apps Script, không đổi cron.

Sau khi upload, mở `https://anhgaustudio.com/nhansu/kiemtra-capnhat.php` để kiểm tra đủ file. Trên điện thoại bấm ↻ để lấy bản mới.
