# Cập nhật giao diện điện thoại — 25/10/2026

## Đúng quy tắc lịch đã chốt

- Lịch chụp mặc định xem Tuần trên máy tính và điện thoại.
- Chọn ngày thứ Hai đến thứ Bảy: hiện tuần đó bình thường, từ thứ Hai đến Chủ nhật.
- Chọn ngày Chủ nhật: hiện từ Chủ nhật đó đến thứ Bảy tuần sau.
- Ví dụ 21/10/2026 → 19–25/10; Chủ nhật 25/10/2026 → 25–31/10.
- Giữ lịch tháng nhỏ để chọn ngày cũ. Trên điện thoại mở **Chọn ngày · Xem lịch cũ**, chọn ngày; lịch nhỏ tự thu lại.
- Ngày có lịch chụp đang hoạt động có dấu chấm xanh, gồm cả các ngày ngoài tuần đang xem. Dấu chấm được lấy cho đủ lịch tháng nhỏ, không chỉ dựa vào các thẻ đang nhìn thấy. Lịch đã hủy không tạo dấu chấm.

## Giao diện trên điện thoại

- Tuần hiển thị dọc theo từng ngày; không cần kéo ngang để tìm ngày khác.
- Thẻ lịch gọn hai dòng, ví dụ:
  12:00 - Gói 699k - Ca 1
  Huy - Cường
- Chỉ ghi tên gọi trên thẻ; popup vẫn hiện tên đầy đủ, khách hàng, gói, ghi chú và thông tin được phép xem theo role.
- Lịch hủy vẫn được đánh dấu và giữ lịch sử.
- Ngày trống thu gọn. Bộ lọc thu vào **Bộ lọc & chọn ngày**; nút Làm mới luôn ở ngoài để dễ bấm.
- Chế độ Tháng trên điện thoại hiển thị ô ngày và số lịch; chạm ngày để xem thẻ đầy đủ.
- Thống kê có **Lịch đang có**, **Lịch hôm nay**, **Lịch sắp tới**, theo thứ tự đó. Trên máy tính nằm dưới lịch nhỏ; trên điện thoại thành ba ô gọn.
- Thống kê lấy toàn bộ lịch đã đồng bộ, độc lập với tuần đang xem. Lịch hôm nay là số lịch chưa hủy trong hôm nay; lịch sắp tới gồm lịch chưa tới giờ hôm nay và các ngày sau. Role Nhân viên chỉ nhận số liệu các lịch được giao cho mình.
- Chấm công vẫn là màn hình mở đầu cho Nhân viên trên điện thoại; nút thao tác chính đủ lớn. Giữ nguyên GPS, camera, ảnh chấm công và quy tắc tính công.

Gói này bao gồm cả các cập nhật trước: Lịch làm việc tự lấy từ lịch khách, mốc muộn sau 30 phút, nhãn gói 699k/1499k, giao diện Event log và quyền xem/chỉnh sửa đã chốt.

## Upload bằng File Manager

Giải nén ZIP trên máy tính, upload **các file bên trong**, không upload nguyên thư mục. Chọn Replace / Ghi đè khi trùng tên.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib |
| 2-public-assets.zip | public_html/nhansu/assets |
| 3-public-root.zip | public_html/nhansu |

1. Có thể tải các file cũ về máy để dự phòng. Thay theo thứ tự PRIVATE → ASSETS → PUBLIC.
2. Giữ nguyên config.php, storage, database và các thư mục sao lưu cũ. Không thay file gốc WordPress.
3. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php kiểm tra file đã đúng gói.
4. Mở lại web, Ctrl + F5 trên máy tính; trên điện thoại đóng tab cũ và mở lại trang. Phiên bản v1.2.2 · 25/10/2026.
5. Không tạo lại khóa Sheet, không cài lại Apps Script, không nhập lại dữ liệu.

Đã kiểm tra cục bộ; chưa tự upload lên hosting. Không đổi schema database hay mật khẩu.
