# Cập nhật 28/10/2026 — Chạm avatar để đổi mật khẩu

## Thay đổi

- Chạm avatar (góc phải header; máy tính có thêm avatar ở thanh menu) mở bảng **Tài khoản**: tên, vai trò, email, form **Đổi mật khẩu** và nút **Đăng xuất**.
- Mọi role tự đổi mật khẩu của chính mình: nhập mật khẩu hiện tại, mật khẩu mới (10–128 ký tự, khác mật khẩu cũ) và nhập lại. Có ô Hiện mật khẩu.
- Nhập sai mật khẩu hiện tại 10 lần thì khóa đổi mật khẩu 15 phút.
- Đổi xong giữ phiên đang dùng, các thiết bị khác phải đăng nhập lại. Nhật ký nhân sự ghi `password_self`.
- Admin/Quản lý vẫn đặt lại mật khẩu cho nhân viên trong Quản lý như cũ (khi nhân viên quên mật khẩu).
- API mới: `POST api.php?route=/me/password` với `current_password`, `new_password`. Không đổi database.
- v1.3.3.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (api.php) |
| 2-public-assets.zip | public_html/nhansu/assets (app.js, style.css) |
| 3-public-root.zip | public_html/nhansu (index.php, kiemtra-capnhat.php) |

1. Thay theo thứ tự 1 → 2 → 3. Giữ nguyên config.php, storage, database và WordPress.
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại đóng tab cũ rồi mở lại; máy tính Ctrl + F5. Góc menu hiện **v1.3.3 · 28/10/2026**.
4. Chạm avatar → thử đổi mật khẩu bằng một tài khoản.

Đã thử cục bộ trên bản dev: sai mật khẩu hiện tại, mật khẩu mới quá ngắn/trùng cũ/nhập lại không khớp đều báo lỗi; đổi đúng thì mật khẩu mới dùng được, phiên đang dùng giữ nguyên, thiết bị khác bị đăng xuất; sai 10 lần bị khóa. Giao diện điện thoại 390px.
