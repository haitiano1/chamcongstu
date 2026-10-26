# Cập nhật: lịch làm tự lấy từ khách — 25/10/2026

## Quy tắc áp dụng

- Gán Thợ Chính / Thợ Phụ trên Google Sheet, ghép tên với hồ sơ nhân viên rồi đồng bộ như hiện tại. Lịch chụp tự hiển thị người đó trong **Lịch làm việc** (tên mới của Phân ca). Không nhập lại lần hai.
- Gộp một thẻ cho mỗi nhân viên / ngày / ca; nhiều khách trong cùng ca vẫn chỉ một thẻ. Một người vừa là Thợ Chính vừa là Thợ Phụ không bị đếm hai lần.
- Trong mỗi ca chính, khách đầu tiên đang hoạt động được giao cho người đó là mốc đến. Khách 12:00 → sau 12:30 mới tính checkin muộn. Đúng 12:30 chưa muộn. Ca chiều đối chiếu khách đầu tiên của ca chiều, riêng với ca sáng.
- Bot nhắc checkin sau 10 phút từ giờ khách đầu tiên; đây là lời nhắc trước hạn muộn, không phải tự ghi lỗi. Nhắc checkout vẫn sau 5 phút kết thúc ca có checkin. Không gửi nhắc checkin từ các ca nhập tay cũ.
- Hiện chỉ đối soát theo lịch khách. Ca nhập tay cũ giữ làm tham khảo, không tạo mốc 9h/14h để tính trễ, không tự suy ra nghỉ/quên chấm khi không có khách được giao.
- Chưa ghép tên: chưa có căn cứ giao ca cho nhân viên; web không đoán danh tính.
- Sửa/hủy lịch trên Sheet rồi đồng bộ: lịch làm cập nhật theo. Hủy khách đầu tiên thì dùng khách còn lại tiếp theo nếu chưa checkin. Không tạo hàng phân ca vật lý, nên không có bản sao lịch bị bỏ lại sau khi hủy.
- Mốc đối soát đã lưu lúc checkin vẫn được giữ để tránh thay lịch sau đó làm đổi kết luận cũ. Lịch hiển thị theo thông tin khách hiện tại; đối soát có nhãn đã giữ mốc lúc checkin.
- Log KPI và dữ liệu chấm công cũ không bị xóa hoặc tự sửa; điểm cần được người có quyền xác nhận. Không tự quy đổi điểm ra tiền, trừ lương hoặc mất công.
- Khung ca/checkout và OT giữ luồng hiện có. Lịch làm tự gộp khách thuộc Ca 1 (9–14h) / Ca 2 (14–19h); lịch OT vẫn ở Lịch chụp và chức năng OT riêng.
- Các gói hiển thị ngắn: **699k / 1499k / 399k** trên lịch và chi tiết. Bạn vẫn nhập 699 / 1499 / 399 trên Sheet.
- Bao gồm giao diện lịch và Event log, quyền xem toàn bộ log và chỉ Admin/người được Admin chỉ định được sửa như bản vừa gửi trước đó.

## Cập nhật trên Hostinger

Giải nén các ZIP trên **máy tính**. Mỗi ZIP chỉ có file, không có thư mục con.

| Gói | Mở thư mục này trên hosting | Upload các file đã giải nén |
| --- | --- | --- |
| 1-private-lib.zip | nhansu-private/lib | people-finance.php, bookings.php, workflows.php, telegram.php |
| 2-public-assets.zip | public_html/nhansu/assets | app.js, bookings.js, roster.js, people-finance.js, studio-journal.css |
| 3-public-root.zip | public_html/nhansu | index.php, kiemtra-capnhat.php |

1. Có thể tải các file cũ về máy để dự phòng, rồi upload theo thứ tự PRIVATE → ASSETS → PUBLIC.
2. Chọn Replace / Ghi đè file trùng. Chỉ upload **các file bên trong**, không upload nguyên thư mục vừa giải nén.
3. Giữ nguyên config.php, storage, database và các thư mục sao lưu cũ. Không sửa thư mục gốc WordPress.
4. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php để kiểm tra file đã đúng gói.
5. Mở lại web, Ctrl + F5. Giao diện có phiên bản **v1.2.1 · 25/10/2026**.
6. Không tạo lại khóa, không cài lại Apps Script, không nhập lại dữ liệu. Đồng bộ Sheet như hiện tại; mở **Lịch làm việc** để xem các ca tự lấy từ lịch khách. Đổi dữ liệu trên Sheet cần đồng bộ trước khi bấm Tải lại hoặc mở lại Lịch làm việc.

Gói đã kiểm tra cục bộ, chưa tự được đưa lên hosting. Không thay đổi schema database.
