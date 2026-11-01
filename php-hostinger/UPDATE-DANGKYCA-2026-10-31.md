# Cập nhật 31/10/2026 (lần 3) — Đăng ký ca: Lịch của tôi + Telegram, mời liên kết Telegram

Gói này **cộng dồn** gói "Hoa hồng" 31/10 (đủ file lib, bin, assets, root). Upload gói này là đủ, kể cả khi chưa làm gói Hoa hồng.

## Thay đổi (v1.3.33)

- **Mời liên kết Telegram ở đầu trang Chấm công**: ai chưa liên kết sẽ thấy khung xanh có logo Telegram "Liên kết Telegram để không bỏ lỡ thông báo từ AGS".
  - Bấm **Liên kết ngay** → nút đổi thành **Mở Telegram, nhấn Start** → mở bot, nhấn Start → quay lại app là khung tự ẩn, báo "Đã liên kết Telegram". Có nút "Tôi đã nhấn Start" để kiểm tra lại.
  - **Để sau**: ẩn tới hết hôm nay, mai hiện lại. Đã liên kết thì không hiện nữa.
  - Khung "Nhắc chấm công qua Telegram" cuối trang vẫn giữ (gửi tin thử, ngắt liên kết).

- **Hoa hồng lịch khách chỉ tính khi thợ đang chấm công lúc khách chụp** (từ gói Hoa hồng, đã có trong gói này).
- **Lịch chụp → Lịch của tôi** hiện ca mình đã đăng ký trên từng ngày: "Đăng ký **Ca 1** **Ca 2**" (xanh). Ca đã bỏ gấp hiện đỏ "Nghỉ gấp Ca 1". Toàn studio không hiện phần này.
- **Thứ 7 và Chủ nhật** mở Đăng ký ca là vào thẳng **tuần sau** (tuần này đã khóa).
- **Telegram nhắc đăng ký ca**:
  - Thứ 7 và Chủ nhật lúc **9h** và **20h**: bot nhắn riêng người **chưa chốt** tuần tới (Nhân viên và Quản lý), ghi số ca đã chọn / 10, có nút **Mở Đăng ký ca**. Người đã chốt không bị nhắc.
  - **20h Chủ nhật**: Admin và Quản lý nhận danh sách người chưa chốt (ghi rõ ai chưa nối Telegram).
  - Nhân viên bấm **Chốt** → Admin và Quản lý nhận tin "X đã chốt N ca tuần …" kèm ca từng ngày.
  - Nghỉ gấp vẫn báo Admin/Quản lý như cũ, nay có nút mở thẳng Đăng ký ca.
  - Hosting lỡ giờ cron thì gửi bù trong 3 tiếng; mỗi tin chỉ gửi một lần. Trang Studio → Bot Telegram có ghi các lượt "Nhắc đăng ký ca" / "Danh sách chưa chốt ca".
- Không đổi database, không đổi Google Sheet script.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (api.php, availability.php, bookings.php, bootstrap.php, people-finance.php, rules.php, schema.php, studio-rules.php, tasks.php, telegram.php, transfer.php, workflows.php) |
| 2-private-bin.zip | nhansu-private/bin (cron.php) — **có đổi**, cần thay để Telegram nhắc đăng ký ca |
| 3-public-assets.zip | public_html/nhansu/assets (toàn bộ file trong gói, gồm thư mục vendor) |
| 4-public-root.zip | public_html/nhansu (api.php, index.php, kiemtra-capnhat.php) |

1. Thay theo thứ tự 1 → 2 → 3 → 4. Giữ nguyên config.php, storage, database và WordPress. Cron trên hosting giữ như cũ (mỗi phút).
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh (có dòng `bin/cron.php`).
3. Điện thoại bấm ↻ (hoặc đóng app rồi mở lại); máy tính Ctrl + F5. Góc menu hiện **v1.3.33 · 31/10/2026**.
4. Thử: nhân viên vào Đăng ký ca (thứ 7/CN hiện "Tuần sau") → tick vài ca → Lịch chụp → Lịch của tôi → chuyển tới ngày đó thấy "Đăng ký Ca 1 Ca 2".
5. Nếu upload xong trước 20h thứ 7 hoặc Chủ nhật, người chưa chốt sẽ nhận tin Telegram lúc 20h.

Đã thử trên bản dev:
- Mời liên kết Telegram (điện thoại 320/390px, máy tính 1440px, tài khoản nhân viên test): khung hiện trên ô Chấm công, không tràn ngang; Liên kết ngay → nút Mở Telegram dẫn đúng bot; chưa nhấn Start thì khung giữ nguyên; liên kết xong bấm "Tôi đã nhấn Start" → khung ẩn, báo đã liên kết; sang trang khác khung ẩn; không lỗi JS. Dữ liệu thử đã xóa.
- Máy chủ 20 kiểm thử đạt (trong giao dịch rồi hoàn tác, Telegram giả lập): thứ 7 9h nhắc đúng người chưa chốt, đúng tuần 09/11–15/11 và số ca 3/10, nút mở Đăng ký ca; người đã chốt và Admin không bị nhắc; Quản lý chưa chốt được nhắc; cùng khung không gửi lặp; ngoài giờ và thứ 6 không gửi; lỡ 20h gửi bù lúc 22:30; 20h Chủ nhật gửi danh sách cho cả Admin và Quản lý, ghi người chưa nối Telegram, không có người đã chốt; chốt rồi thì thôi nhắc; tin báo chốt liệt kê "T2 09/11: Ca 1, Ca 2 / T3 10/11: Ca 2", không gửi lại cho chính người chốt; chốt vẫn chạy khi chưa cấu hình Telegram; Lịch của tôi có ca đăng ký và nghỉ gấp, Toàn studio thì không.
- Điện thoại 390px và máy tính 1440px (tài khoản nhân viên test, dữ liệu thử đã xóa): thứ 7 mở thẳng "Tuần sau 02/11 – 08/11"; Lịch của tôi ngày 02/11 hiện "Đăng ký Ca 1 Ca 2", 04/11 hiện "Nghỉ gấp Ca 1" màu đỏ; chế độ Tuần hiện đúng từng ngày; Toàn studio không hiện; không lỗi JS, không tràn ngang.
- Kiểm thử cũ `booking-presence` 7, `rules` 32, `booking-summary` 13, `customer-schedule` 21 đạt.
