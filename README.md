# Anh Gấu Studio — Nhân sự & chấm công

**Bản hiện tại: PHP/MySQL v1.2.3**, dùng trên `https://anhgaustudio.com/nhansu`. Repo này có đủ các chức năng mới nhất đã bàn giao: Tổng quan, Chấm công/GPS/camera, Lịch làm việc tự lấy từ lịch khách, Bảng công/lương, Lịch chụp, Ứng lương, Event log và Quản lý theo role.

## Chạy đúng bản hiện tại

Cần Docker đang chạy, Bash và Python 3. Windows có thể dùng WSL2 + Docker Desktop. Chạy từ thư mục gốc:

```sh
npm start
```

Lệnh này khởi động **bản PHP mới nhất**, mở tại **http://127.0.0.1:8184/nhansu/**. Không cần cài PHP/MySQL trực tiếp vì chúng chạy trong Docker. Nếu thiếu môi trường, script báo lỗi; không tự chuyển về Node cũ.

Có thể gọi trực tiếp: `bash php-hostinger/dev.sh start`. Chi tiết: [PHP development](php-hostinger/README.md).

## Mã nguồn đang chạy trên Hostinger

| Phần | Đường dẫn |
| --- | --- |
| Giao diện mới nhất | `php-hostinger/site/public_html/nhansu/assets/` |
| Trang vào / API | `php-hostinger/site/public_html/nhansu/index.php`, `api.php` |
| Backend PHP | `php-hostinger/site/nhansu-private/lib/` |
| Cron | `php-hostinger/site/nhansu-private/bin/cron.php` |
| Đồng bộ Google Sheet | `php-hostinger/google-sheets/AnhGauSync.gs` |

Đọc [CLAUDE.md](CLAUDE.md) và [bàn giao](BAN-GIAO-CLAUDE.md) trước khi sửa. Không sửa `public/` ở root để triển khai các chức năng PHP.

## Dữ liệu và cập nhật

Local dùng database thử riêng. Repo không chứa mật khẩu, database production, ảnh chấm công, tài liệu Event log thật hoặc cấu hình Sheet/token. Code mới và dữ liệu đang chạy là hai phần khác nhau. Không ghi đè `config.php`, `storage`, `.data`, database hoặc WordPress khi cập nhật.

[Hướng dẫn cài Hostinger](php-hostinger/INSTALL-HOSTINGER.md) · [Bản cập nhật chức năng mới nhất](php-hostinger/UPDATE-SHARED-CALENDAR-2026-10-25.md).

## Bản Node cũ

Giữ để tham khảo/chuyển dữ liệu, không phải production hiện tại. Chỉ chạy khi chủ động chọn `npm run start:legacy`. Xem [tài liệu Node cũ](docs/LEGACY-NODE.md). `npm test` kiểm tra phần Node cũ và các unit test được phát hiện; kiểm tra PHP có hướng dẫn riêng trong `php-hostinger/README.md`.
