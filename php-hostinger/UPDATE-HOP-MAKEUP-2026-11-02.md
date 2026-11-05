# Cập nhật 02/11/2026 — Biên bản họp, quyền Sửa Biên bản họp, role Makeup

Gói này **cộng dồn** các gói trước (đủ file lib, bin, assets, root). Upload gói này là đủ.

## Thay đổi (v1.3.42)

### 1. Biên bản họp (Studio → tab thứ 3, cạnh Event log và Luật Studio)

Admin, Quản lý và nhân viên được Admin tick **Sửa Biên bản họp** bấm **＋ Lập biên bản** và điền:

- **Thông tin chung**: Tên / Chủ đề cuộc họp, ngày họp, giờ bắt đầu – kết thúc.
- **Thành phần tham gia**: danh sách mọi người, tick người **có mặt**, không tick là **vắng mặt** (có nút Chọn tất cả / Bỏ chọn).
- **Nội dung biên bản**: Mục đích / Vấn đề đưa ra · Ý kiến & Góp ý của nhân viên · Phương án xử lý & Phân công (mỗi dòng: giải pháp, **người phụ trách**, **deadline**; thêm được nhiều dòng) · Kết luận & Quyết định cuối cùng.

Hai nút cuối form:

- **Lưu nháp**: lưu dần trong lúc họp. Nháp chỉ người có quyền sửa thấy, chưa gửi ai.
- **Xác nhận & gửi**: cần đủ người có mặt, Mục đích và Kết luận. Telegram báo **mọi người có mặt** (kèm việc họ phụ trách và hạn), nút trong tin mở thẳng mục Biên bản họp. Người bấm gửi được tính là đã xác nhận.

**Cụm xác nhận**:

- Người có mặt mở biên bản, đọc rồi bấm **Đã đọc và xác nhận hiểu rõ nội dung**. Hệ thống lưu **giờ bấm** của từng người; trong biên bản hiện ✓ giờ xác nhận hoặc "Chưa xác nhận" từng người.
- Biên bản chờ mình xác nhận hiện ở **đầu trang Chấm công** (khung đỏ, nút Đọc & xác nhận) và đầu trang Biên bản họp. Mở từ Telegram mà chỉ có 1 biên bản chờ thì mở luôn biên bản đó.
- **Mọi người có mặt xác nhận xong thì biên bản tự khóa.** Người có quyền cũng bấm **Khóa biên bản** được bất cứ lúc nào. Đã khóa thì **chỉ Admin sửa**; Admin mới **Mở khóa** được.
- Sửa nội dung sau khi đã gửi: mọi người có mặt phải **đọc lại và xác nhận bản mới** (Telegram báo "biên bản vừa được sửa"). Chỉ thêm người có mặt thì chỉ báo người mới, xác nhận cũ giữ nguyên.
- **Nhắc người chưa xác nhận**: gửi lại Telegram cho người chưa bấm (mỗi người tối đa 1 lần / 10 phút).
- **Lịch sử**: ghi đủ ai lập, gửi, sửa (nội dung trước/sau), xác nhận lúc nào, khóa, mở khóa, nhắc, xóa.
- **Xóa**: nháp thì người có quyền xóa; biên bản đã gửi chỉ Admin xóa, phải ghi lý do (xóa mềm, giữ lịch sử).

Mọi tài khoản (trừ Makeup) xem được các biên bản đã gửi.

### 2. Cấp quyền Sửa Biên bản họp

Nhân viên → mở hồ sơ → **Cấp quyền** có thêm ô thứ 3 **Sửa Biên bản họp** (lập, sửa, gửi xác nhận, khóa). Như 2 ô cũ: chỉ Admin tick, chỉ cho role Nhân viên; Admin / Quản lý luôn có quyền nên ô hiện sẵn đã tick và khóa.

### 3. Role Makeup

Hồ sơ nhân viên → **Vai trò** có thêm **Makeup** (chỉ Admin chọn được).

- Tài khoản Makeup đăng nhập là vào thẳng **Lịch chụp** (Toàn studio, xem Ngày / Tuần / Tháng, bấm thẻ xem chi tiết như nhân viên). Không có menu, không chấm công, không Việc, không lương, không Telegram. Góc trên vẫn có ↻ tải lại, bấm avatar để đổi mật khẩu / đăng xuất.
- Máy chủ chặn mọi chức năng khác của tài khoản Makeup (chỉ cho xem lịch). Không thêm / sửa / hủy lịch được.
- Makeup không có trong Bảng lương, danh sách giao Task, Event log, Đăng ký ca, Lịch làm việc, thành phần tham gia họp; không được cấp quyền nào.

Database tự thêm 4 bảng mới (`meetings`, `meeting_attendees`, `meeting_editors`, `meeting_audit`) khi mở app lần đầu, không đổi dữ liệu cũ. Không đổi Google Sheet script.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (13 file .php, **có file mới `meetings.php`**) |
| 2-private-bin.zip | nhansu-private/bin (cron.php), **có đổi**, cần thay để gửi Telegram biên bản họp |
| 3-public-assets.zip | public_html/nhansu/assets (toàn bộ file trong gói, **có file mới `meetings.js`, `meetings.css`**) |
| 4-public-root.zip | public_html/nhansu (api.php, index.php, kiemtra-capnhat.php), **api.php có đổi** |

1. Thay theo thứ tự 1 → 2 → 3 → 4. Giữ nguyên config.php, storage, database và WordPress. Cron trên hosting giữ như cũ (mỗi phút).
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại bấm ↻ (hoặc đóng app rồi mở lại); máy tính Ctrl + F5. Góc menu hiện **v1.3.42 · 02/11/2026**.
4. Thử: Studio → **Biên bản họp** → Lập biên bản → tick người có mặt → Xác nhận & gửi; người có mặt nhận Telegram, vào bấm xác nhận. Quản lý nhân viên → mở hồ sơ → Cấp quyền có ô **Sửa Biên bản họp**; Vai trò có **Makeup**.

## Đã thử trên bản dev

- Biên bản họp + role Makeup, 45 kiểm thử máy chủ đạt (trong giao dịch rồi hoàn tác, Telegram giả lập): nhân viên chưa tick không lập được, tick thì được, bỏ tick thì mất; giao diện cũ chỉ gửi 2 ô quyền không làm mất quyền Biên bản họp; Makeup không được cấp quyền, không có trong thành phần tham gia; giờ kết thúc trước giờ bắt đầu bị chặn; nháp thiếu nội dung vẫn lưu, nhân viên không thấy nháp, không gửi Telegram; gửi thiếu Kết luận bị chặn; gửi → người lập tự xác nhận, Telegram chỉ báo người có mặt (không báo người vắng), có việc phụ trách + hạn và nút mở `#meetings`; Chấm công hiện biên bản chờ; xác nhận bản cũ bị chặn, người vắng không xác nhận được; đủ người → tự khóa, lưu giờ và lịch sử xác nhận; khóa rồi Quản lý không sửa; Admin sửa nội dung → mọi người xác nhận lại, Telegram báo đã sửa; không đổi gì báo chưa có thay đổi; nhắc lại cách 10 phút, tin "Nhắc lại"; chỉ Admin mở khóa; thêm người có mặt chỉ báo người mới, giữ xác nhận cũ; chỉ Admin xóa biên bản đã gửi, cần lý do, xóa xong không gửi tin chờ; Makeup chỉ gọi được API xem Lịch chụp, không thêm lịch, không ghi log.
- Gọi API thật trên bản dev (tài khoản test, dữ liệu thử đã xóa): đủ luồng nháp → gửi → xác nhận → tự khóa → Admin sửa → xác nhận lại → mở khóa → xóa; tài khoản Makeup: `/me` rút gọn, xem `/bookings`, `/bookings/days`, `/bookings/summary` được, các mục Biên bản họp, Việc, Bảng lương, Event log, chấm công, Telegram, thêm lịch đều bị chặn 403; Quản lý không sửa được hồ sơ Makeup. Cron gửi Telegram thật qua bot test @testagsbot.
- Giao diện điện thoại 390px và máy tính 1280px: khung đỏ ở Chấm công, danh sách, chi tiết biên bản có nút xác nhận và giờ xác nhận từng người, form 3 bước (thông tin chung, thành phần tham gia, nội dung + phân công), ô quyền thứ 3 trong hồ sơ, chọn Makeup thì 3 ô bỏ tick và khóa; tài khoản Makeup chỉ thấy Lịch chụp, không có menu; không lỗi JS.
- Kiểm thử cũ chạy lại đạt: availability-telegram 20, booking-makeup 10, booking-money 17, booking-presence 7, booking-summary 13, customer-schedule 21, late-checkin 35, legacy-edit 7, resume-early 32, rules 32, studio-credit 5, task-decline 32, task-followup 43, telegram-log 6, upgrade-preservation 10. (`shared-calendar.php` và `telegram.php` vốn đã lỗi từ trước do test cũ chưa cập nhật theo quy tắc mới, không liên quan gói này.)
