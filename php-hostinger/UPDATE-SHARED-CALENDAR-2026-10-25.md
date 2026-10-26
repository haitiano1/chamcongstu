# Sửa quyền xem lịch chụp — 25/10/2026

## Nguyên nhân

Bản trước chỉ trả lịch có tên nhân viên được ghép đúng hồ sơ cho role Nhân viên. Admin / Quản lý xem toàn bộ. Do đó lịch chưa gán thợ ngày 22/10 có trên máy tính của quản lý, nhưng không có trong tài khoản Nhân viên trên điện thoại; dấu chấm cũng bị lọc theo cùng quyền.

## Bản chỉnh

- Mọi tài khoản đã đăng nhập mặc định xem **Toàn studio**: cả lịch đã giao, lịch của đồng nghiệp và lịch chưa phân công.
- Có lựa chọn **Lịch của tôi** để chỉ xem các lịch được giao cho tài khoản đang đăng nhập.
- Danh sách lịch, dấu chấm lịch nhỏ và thống kê dùng cùng phạm vi đã chọn. Dấu chấm đánh dấu lịch đang hoạt động trong toàn bộ khoảng lịch nhỏ.
- Nhân viên vẫn chỉ được xem; không được sửa lịch, ghép tên, thay khóa Sheet hoặc đối soát thay quản lý.
- Giữ giới hạn các trường riêng tư: API cho Nhân viên không trả số điện thoại khách, ghi chú nội bộ và thông tin nguồn Sheet. Tên khách, gói, giờ và tên thợ được hiển thị phục vụ xem lịch.
- Xem lịch chung không tự gán ca cho người xem. Chấm công, mốc đến, nhắc checkin và Lịch làm của tôi vẫn lấy đúng các lịch được giao cho từng nhân viên.
- Giữ giao diện điện thoại, thẻ gọn, lịch nhỏ chọn ngày cũ và quy tắc tuần Chủ nhật đã chốt. Không thay dữ liệu, mật khẩu, cấu hình Sheet hay schema database.

## Cập nhật

Giải nén ZIP trên máy tính, upload **các file bên trong** vào thư mục tương ứng. Chọn Replace / Ghi đè file trùng; không upload nguyên thư mục vừa giải nén.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib |
| 2-public-assets.zip | public_html/nhansu/assets |
| 3-public-root.zip | public_html/nhansu |

1. Thay theo thứ tự PRIVATE → ASSETS → PUBLIC. Có thể tải file cũ về máy để dự phòng.
2. Giữ nguyên config.php, storage, database và WordPress.
3. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php để kiểm tra đã thay đủ file.
4. Mở lại web bằng Ctrl + F5 trên máy tính; điện thoại đóng tab cũ rồi mở lại. Phiên bản mới **v1.2.3 · 25/10/2026**.
5. Vào Lịch chụp → **Toàn studio** → chọn ngày **22/10/2026** trong lịch nhỏ. Có thể dùng Admin trên máy tính và Nhân viên trên điện thoại để đối chiếu cùng ngày / cùng phạm vi.
6. Không cần tạo lại khóa hay đồng bộ lại Sheet chỉ để đổi quyền xem.

Đã thử cục bộ. Chưa tự upload lên hosting.
