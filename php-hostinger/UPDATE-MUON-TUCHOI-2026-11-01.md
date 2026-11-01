# Cập nhật 01/11/2026 — Đi muộn tự trừ KPI + báo Telegram, Xin từ chối Task/Nhắc nhở

Gói này **cộng dồn** gói "Đăng ký ca" 31/10 (đủ file lib, bin, assets, root). Upload gói này là đủ.

## Thay đổi (v1.3.34)

### 1. Đi muộn

**Nội quy đi muộn (DM-01)**, đã ghi sẵn trong Luật Studio:
- Mốc đến của mỗi ca là giờ **khách đầu tiên được giao** cho người đó trong ca, không phải 9:00 / 14:00. Checkin **quá 30 phút** sau mốc là đi muộn. VD khách 9:00: đến 9:30 vẫn đúng giờ, sau 9:30 là muộn. Ca không có khách được giao thì không tính muộn.
- Đếm theo từng tháng, sang tháng mới đếm lại:
  - **Lần 1**: nhắc nhở, miễn trừ điểm
  - **Lần 2**: Lỗi, trừ **20** điểm KPI
  - **Lần 3**: Cảnh cáo, trừ **100** điểm KPI
  - **Từ lần 4**: Kỷ luật, trừ **300** điểm KPI mỗi lần
- **Từ lần 2 hệ thống tự ghi Event log** (Đã xác nhận, trừ điểm ngay, ghi "Hệ thống tự ghi"). Có lý do chính đáng thì Quản lý/Admin **hủy hoặc xóa** log đó; lần đó không tính vào số lần, hệ thống không ghi lại.
- **Mỗi lần đi muộn, Telegram báo** cho người đi muộn, Quản lý và Admin (khoảng 1 phút sau khi checkin). Tin nhắn ghi: muộn bao nhiêu phút, ca nào, ngày nào, giờ khách đầu tiên, hạn checkin, giờ checkin, **lần thứ mấy trong tháng**, mức phạt lần này, mức phạt **lần tới** và toàn bộ nội quy.
- **Nhãn đỏ "Muộn X phút · Lần N trong tháng · trừ … điểm KPI / miễn trừ điểm"** trên danh sách chấm công (Lịch sử của nhân viên và Lịch sử chấm công nhân viên của Admin/Quản lý), và dưới "Giờ vào đầu tiên" ở Chấm công hôm nay. Bấm nhãn để xem chi tiết và nội quy.
- Đối soát chấm công (Lịch chụp) dùng cùng cách đếm, có ghi nội quy ở đầu mục.

### 2. Xin từ chối Task / Nhắc nhở

- Ở mục **Việc**, mỗi Task / Nhắc nhở của mình có nút **Xin từ chối** → ghi **Lý do / ý kiến** → Gửi. Admin và Quản lý nhận tin Telegram.
- Admin / Quản lý thấy khung **Yêu cầu từ chối chờ duyệt** ở đầu mục Việc, đọc lý do rồi bấm:
  - **Duyệt**: gỡ người đó khỏi việc, không cần làm nữa. Việc không còn ai nhận thì tự hủy.
  - **Không duyệt** (phải ghi lý do): việc **vẫn treo** cho người đó tới khi báo xong. Nhắc nhở thì quay lại "Chưa xem". Thẻ việc hiện lý do không duyệt.
  - Người gửi nhận Telegram báo kết quả. Quản lý không tự duyệt yêu cầu của chính mình.
- Nhân viên xem lại ở khung **Yêu cầu từ chối của bạn** (14 ngày gần nhất); đang chờ thì **Rút yêu cầu** được. Báo xong việc trong lúc chờ thì yêu cầu tự rút.
- Mọi bước ghi vào Lịch sử thay đổi của việc.

Database tự thêm 2 bảng mới (`late_checkins`, `task_declines`) khi mở app lần đầu, không đổi dữ liệu cũ. Không đổi Google Sheet script.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (12 file .php) |
| 2-private-bin.zip | nhansu-private/bin (cron.php), **có đổi**, cần thay để báo đi muộn và tự ghi Event log |
| 3-public-assets.zip | public_html/nhansu/assets (toàn bộ file trong gói, gồm thư mục vendor) |
| 4-public-root.zip | public_html/nhansu (api.php, index.php, kiemtra-capnhat.php) |

1. Thay theo thứ tự 1 → 2 → 3 → 4. Giữ nguyên config.php, storage, database và WordPress. Cron trên hosting giữ như cũ (mỗi phút).
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại bấm ↻ (hoặc đóng app rồi mở lại); máy tính Ctrl + F5. Góc menu hiện **v1.3.34 · 01/11/2026**.
4. Thử: Chấm công → Lịch sử chấm công nhân viên → lượt vào muộn có nhãn đỏ; Studio → Luật Studio có **DM-01 Đi muộn quá 30 phút**.
5. Upload xong **trong ngày 01/11** thì bot sẽ gửi tin về lượt vào 9:56 Ca 1 của Cường hôm nay (khách đầu 9:00, muộn 56 phút, lần 1, miễn trừ điểm). Các ngày trước khi cập nhật không gửi bù và không tự ghi log.

Đã thử trên bản dev:
- Đi muộn, 35 kiểm thử máy chủ đạt (trong giao dịch rồi hoàn tác, Telegram giả lập): khách 9:00 vào 9:56 → muộn 56 phút, lần 1, không ghi log, báo nhân viên + Quản lý + Admin, không báo nhân viên khác; khách 14:00 vào 14:30 không muộn; ca không có khách không tính; lần 2 tự ghi log 20 điểm (Đã xác nhận, luật DM-01, gắn Đối soát); log hủy thì lần sau vẫn là lần 2; lần 3 = 100, lần 4, 5 = 300; tổng KPI đúng; log bị xóa không tạo lại và không đếm; chạy lại cron không gửi lặp; chưa cấu hình Telegram vẫn tự ghi log; luật DM-01 đã xóa không tạo lại; Đối soát đếm giống.
- Xin từ chối, 32 kiểm thử máy chủ đạt: lý do ngắn bị chặn; không gửi trùng; người không nhận việc không gửi được; nhân viên không duyệt được; không duyệt phải có lý do; không duyệt thì việc vẫn treo; duyệt thì gỡ người, việc một người nhận tự hủy, việc nhiều người vẫn mở cho người khác; Nhắc nhở không duyệt quay lại Chưa xem; rút yêu cầu (chỉ người gửi); báo xong tự rút; Quản lý không tự duyệt, Admin duyệt được; việc đã hủy không còn trong danh sách chờ; Telegram báo Admin/Quản lý (trừ người gửi) và báo kết quả cho người gửi.
- Giao diện điện thoại 390px và máy tính 1300px (tài khoản test, dữ liệu thử đã xóa): nhãn đỏ ở Lịch sử nhân viên và Lịch sử Admin/Quản lý, popup có nội quy; mục Việc có nút Xin từ chối, khung chờ duyệt cho Quản lý, khung "Yêu cầu từ chối của bạn", lý do không duyệt trên thẻ việc; không lỗi JS.
- Kiểm thử cũ `availability-telegram` 20, `rules` 32, `customer-schedule` 21, `booking-presence` 7, `booking-summary` 13, `legacy-edit` 7, `upgrade-preservation` 10 đạt. (`shared-calendar.php` và `telegram.php` vốn đã lỗi từ trước do test cũ chưa cập nhật theo quy tắc mới, không liên quan gói này.)
