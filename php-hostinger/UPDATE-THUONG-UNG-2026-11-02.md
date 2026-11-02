# Cập nhật 02/11/2026 (lần 3) — Ứng lương báo Telegram, Phiếu thưởng cho nhân viên, quyền Nhập thưởng, sửa lỗi phiếu lương bị tràn, Xóa nhắc nhở ở khung Theo dõi hạn

Gói này **cộng dồn** mọi gói trước trong ngày 02/11 (Biên bản họp, role Makeup, KPI theo số lần, chọn ca bằng 3 nút…). Chưa upload các gói đó thì upload gói này là đủ.

## Thay đổi (v1.3.45)

### 1. Ứng lương: quy định rõ, báo Telegram ngay cho Quản lý và Admin

- Đầu trang **Ứng lương** có khung cam **Quy định ứng lương**:
  - Không được ứng gấp. Có nhu cầu ứng thì tự sắp xếp và báo trước ít nhất 1 ngày.
  - Tạo phiếu ứng lúc nào cũng được, nhưng **không được duyệt liền**: bấm Gửi yêu cầu là Quản lý và Admin nhận thông báo Telegram ngay và sẽ duyệt **trong 24 giờ tới**.
- Hộp **Yêu cầu ứng lương** nhắc lại quy định này ngay trên ô Ngày muốn ứng. Hôm nay vẫn tạo phiếu được, không chặn ngày.
- Nhân viên bấm **Gửi yêu cầu** → bot Telegram nhắn ngay cho mọi Admin và Quản lý đã liên kết Telegram:

  ```
  💰 Anh Gấu Studio · Yêu cầu ứng lương
  Huy xin ứng 500.000đ
  Ngày muốn nhận: T3 03/11/2026
  Lý do: …
  Vui lòng duyệt trong 24 giờ tới (trước 18:25 03/11). Vào mục Ứng lương để Duyệt hoặc Từ chối.
  ```

  kèm nút **Mở Ứng lương**.
- Phiếu đang chờ hiện thêm dòng "Quản lý/Admin duyệt trước …" để nhân viên biết khi nào có kết quả.

### 2. Phiếu thưởng cho nhân viên

Nhân viên vào **Công & lương → Bảng công**, bên dưới bảng công có khung **🎁 Phiếu thưởng của tôi**:

- **Thưởng theo công việc**: chuyên cần (còn mấy ngày), hoa hồng khách chụp (số khách × mức), hoa hồng nhập tay, thưởng OT.
- **Thưởng ghi theo ngày**: Tiền khách Tips, Nóng / Khen, Giới thiệu khách, Ngoại cảnh, Full khách ngày, Chỉnh ảnh, Ca làm không có khách, Ăn uống.
- **Tổng thưởng**, **KPI bị trừ** (điểm và tiền), KPI còn lại, **Thưởng thực nhận** (tổng thưởng − KPI bị trừ).
- **Thưởng được ghi trong tháng**: từng lần được cộng thưởng (ngày, khoản, số tiền, ghi chú, người nhập).

Nhân viên **vẫn không thấy** lương cứng, lương theo công, lương cuối. Mỗi người chỉ thấy phiếu của mình.

### 3. Quyền Nhập thưởng (VD cho Tín nhập mỗi ngày)

**Cấp quyền:** Quản lý → Quản lý nhân viên → mở hồ sơ Tín → mục Cấp quyền → tick **Nhập thưởng** → Lưu. Chỉ Admin tick được. Admin và Quản lý luôn có quyền này.

Người có quyền thấy khung **✍ Nhập thưởng nhân viên** ở trang Bảng công / Bảng lương:

1. Chọn **Ngày** (mặc định hôm nay), **Nhân viên**, **Khoản thưởng**, nhập **Số tiền**.
2. Chọn **＋ Cộng thêm** (mặc định) hoặc **− Trừ bớt** (khi nhập nhầm). Ghi chú không bắt buộc.
3. Bấm **Lưu thưởng**: số tiền được **cộng dồn** vào khoản thưởng tháng của nhân viên. Phiếu lương và Phiếu thưởng của nhân viên cập nhật ngay.

- Bên dưới có tổng thưởng nhập trong tháng của từng nhân viên và **Lịch sử nhập** (ai nhập, lúc nào).
- Nhân viên được cấp quyền **không tự nhập thưởng cho mình** (Quản lý / Admin nhập phần của Tín), không sửa đơn giá OT, không sửa trực tiếp trên phiếu lương.
- Không trừ bớt quá số đã có; tháng đã chốt lương thì không nhập thêm; không nhập cho ngày tương lai.
- Admin / Quản lý vẫn sửa tổng từng khoản trên phiếu lương như cũ; mỗi lần sửa cũng ghi vào lịch sử (VD "Sửa tổng trên phiếu lương: 30.000đ → 80.000đ") để nhân viên xem.

### 4. Sửa lỗi: phiếu lương bị tràn, che lương cơ bản

Mở phiếu lương (VD của Huy), cột **Lương** bị kéo rộng tràn sang trái, đè dưới cột **Thưởng**, che số lương cơ bản; thanh chuyên cần ở dòng tên cũng chạy ra ngoài. Nguyên nhân: chữ trong phiếu bị cấm xuống dòng, dòng ghi chú dài (VD "2 khách lúc đã chấm ra / không chấm công, không tính") đẩy cột rộng ra. Đã cho chữ xuống dòng và giữ 2 cột Lương / Thưởng luôn bằng nhau.

### 5. Xóa nhắc nhở / việc ngay trong khung Theo dõi hạn

Khung **Theo dõi hạn sau yêu cầu từ chối** (mục Việc) trước chỉ có Lưu hạn / Bỏ hạn, không có chỗ xóa. Nay mỗi mục có nút đỏ **🗑 Xóa nhắc nhở** (hoặc **🗑 Xóa việc**) → hộp xác nhận, lý do không bắt buộc → **🗑 Xóa nhắc nhở**. Nhắc nhở biến mất khỏi danh sách của mọi người, không còn nhắc, không tính việc chờ, không khóa chấm ra; lịch sử vẫn lưu.

Mỗi thẻ việc trong danh sách bên dưới cũng có nút **🗑 Xóa** (đã có từ bản v1.3.44, cùng gói này). Chỉ Admin và Quản lý thấy nút Xóa.

Database tự tạo 2 bảng mới `payroll_bonus_editors`, `payroll_bonus_entries` khi mở app lần đầu, không đổi dữ liệu cũ.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (13 file .php) |
| 2-private-bin.zip | nhansu-private/bin (cron.php) |
| 3-public-assets.zip | public_html/nhansu/assets. Gói này **không kèm thư mục `vendor`** (thư viện bản đồ, không đổi); giữ nguyên `vendor` đang có trên hosting |
| 4-public-root.zip | public_html/nhansu (api.php, index.php, kiemtra-capnhat.php) |
| AnhGauSync.gs / AnhGauSync.txt | Apps Script Google Sheet. **Không đổi so với gói 02/11 lần 2.** Đã dán bản đó thì bỏ qua; chưa dán thì làm bước 5 |

1. Thay theo thứ tự 1 → 2 → 3 → 4. Giữ nguyên config.php, storage, database và WordPress. Cron trên hosting giữ như cũ (mỗi phút).
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại bấm ↻ (hoặc đóng app rồi mở lại); máy tính Ctrl + F5. Góc menu hiện **v1.3.45 · 02/11/2026**.
4. Thử:
   - Mở hồ sơ Tín → Cấp quyền thấy ô **Nhập thưởng** → tick → Lưu.
   - Bảng lương → mở phiếu lương Huy: 2 cột Lương / Thưởng không còn đè nhau. Bên dưới bảng có khung **Nhập thưởng nhân viên**.
   - Đăng nhập tài khoản nhân viên → Bảng công thấy **Phiếu thưởng của tôi**.
   - Việc → khung Theo dõi hạn có nút **🗑 Xóa nhắc nhở**; bấm xóa nhắc nhở "họp 12h tại stu".
   - Ứng lương thấy khung cam Quy định ứng lương; gửi thử 1 yêu cầu thì Admin/Quản lý nhận tin Telegram, rồi Từ chối hoặc nhân viên Hủy yêu cầu thử đó.
5. **Chỉ khi chưa dán Apps Script bản 02/11:** mở Google Sheet lịch chụp → **Tiện ích mở rộng → Apps Script** → bấm vào khung code, **Ctrl + A** rồi **Delete** → mở `AnhGauSync.txt` bằng Notepad, **Ctrl + A**, **Ctrl + C** → dán vào Apps Script (**Ctrl + V**) → **Ctrl + S**. Quay lại Sheet → menu **Anh Gấu Studio → Đồng bộ ngay**.

## Đã thử trên bản dev

- Kiểm thử máy chủ mới đạt 28 mục: cấp / giữ quyền Nhập thưởng (Makeup không được cấp, giao diện cũ không làm mất quyền), cộng dồn, gửi lại không cộng 2 lần, trừ bớt không quá tổng, trừ về 0 thì xóa, không tự nhập cho mình, không nhập đơn giá OT / số 0 / ngày tương lai / cho Quản lý, nhân viên không sửa phiếu lương, Quản lý sửa tổng thì ghi chênh lệch vào lịch sử, Tín thấy nhân viên khác còn Huy chỉ thấy phần mình, nhân viên thấy phiếu thưởng nhưng không thấy lương; Ứng lương tạo được ngày hôm nay, Telegram báo đúng Admin + Quản lý (không báo nhân viên, Quản lý tự gửi thì chỉ báo Admin), tin có số tiền / lý do / hạn 24 giờ / nút Mở Ứng lương.
- Xóa ở khung Theo dõi hạn: tạo nhắc nhở thử giống ảnh (1 người xin từ chối đã duyệt, 1 người còn chờ xem), bấm 🗑 Xóa nhắc nhở → hộp "Xóa nhắc nhở" → khung Theo dõi hạn biến mất, nhắc nhở chuyển trạng thái đã xóa. Test việc chạy lại đạt: theo dõi hạn 43, xin từ chối 33, KPI + xóa việc 48.
- Kiểm thử cũ chạy lại đạt: quy tắc 32, Biên bản họp 47, KPI + hạn việc + chỉ Nhân viên 48, xin từ chối việc 33, theo dõi hạn 43, đi muộn 35, về sớm / làm tiếp 32, công theo khách 5, đăng ký ca 23, `/log` 16, tiền lịch 17, lịch mất dòng 9, make up 10, có mặt tính hoa hồng 7 (đã sửa mục cũ "nhân viên không thấy mức hoa hồng" vì nay nhân viên được xem phiếu thưởng).
- Giao diện máy tính 1440px: mở phiếu lương Huy và Cường, 2 cột rộng bằng nhau, bảng không tràn. Điện thoại 390px: Phiếu thưởng của tôi, khung Nhập thưởng, trang Ứng lương + hộp Yêu cầu ứng lương; ô Nhập thưởng hiện trong hồ sơ nhân viên. Trên bản dev, gửi phiếu ứng thì bot Telegram thử nhắn được cho Quản lý. Dữ liệu thử đã xóa.
