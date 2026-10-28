# Cập nhật 28/10/2026 — Luật Studio gọn hơn, Xóa luật

Gói này cộng dồn cả **đổi mật khẩu từ avatar** (`UPDATE-ACCOUNT-2026-10-28.md`). Chưa upload gói đổi mật khẩu thì chỉ cần upload gói này.

## Thay đổi

- Danh sách Luật Studio dạng dòng gọn, chia nhóm theo loại luật (tiêu đề nhóm kèm số luật). Mỗi dòng: mã lỗi · tên lỗi · điểm KPI. Luật có nội dung hiện thêm 2 dòng mô tả mờ bên dưới tên.
- Màu điểm theo mức: Nhắc nhở (0) xám, dưới 100 vàng, dưới 300 cam, từ 300 đỏ.
- Nút lọc loại luật có số đếm.
- Chạm một dòng mở chi tiết: loại, điểm, tên, nội dung đầy đủ. Thao tác nằm trong chi tiết theo quyền:
  - Admin: Sửa luật · Lịch sử · Ngừng áp dụng/Áp dụng lại · Xóa luật.
  - Quản lý: Xóa luật.
  - Nhân viên: chỉ xem.
- **Xóa luật** (Admin, Quản lý): lý do không bắt buộc. Luật biến mất khỏi danh sách và ô chọn luật khi ghi Event log của mọi người, không áp dụng lại được. Database vẫn giữ dòng và lịch sử (ghi người xóa, giờ, lý do, nội dung trước khi xóa). Event log đã ghi trước đó giữ nguyên mã, tên và điểm.
- Mã của luật đã xóa được dùng lại cho luật mới; luật cũ trong database đổi sang mã phụ `MÃ~xxxxxxxx`.
- API mới: `POST api.php?route=/studio-rules/{id}/delete` với `version`, `note`. Không đổi cấu trúc database.
- v1.3.4.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (api.php, studio-rules.php) |
| 2-public-assets.zip | public_html/nhansu/assets (app.js, style.css, studio-rules.js, studio-rules.css) |
| 3-public-root.zip | public_html/nhansu (index.php, kiemtra-capnhat.php) |

1. Thay theo thứ tự 1 → 2 → 3. Giữ nguyên config.php, storage, database và WordPress.
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại đóng tab cũ rồi mở lại; máy tính Ctrl + F5. Góc menu hiện **v1.3.4 · 28/10/2026**.
4. Mở Studio → Luật Studio, chạm một luật để xem chi tiết.

Đã thử cục bộ trên bản dev: Quản lý xóa được nhưng không sửa được, Nhân viên không xóa được; luật đã xóa ẩn với mọi role và không chọn được khi ghi Event log; lịch sử ghi lý do; tạo lại mã đã xóa được, trùng mã luật đang dùng vẫn bị chặn. Giao diện điện thoại 390px và máy tính 1280px.
