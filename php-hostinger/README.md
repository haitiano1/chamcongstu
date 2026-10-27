# Anh Gấu Studio — PHP/MySQL host tại /nhansu

Xem [INSTALL-HOSTINGER.md](INSTALL-HOSTINGER.md) để cài trên shared hosting. Code Node/local cũ được giữ nguyên. Backend PHP độc lập, API physical file `api.php?route=/...`, không cần sửa WordPress rewrite, không dùng database WordPress. Assets riêng đã kèm Leaflet 1.9.4 và license.

## Development

Node >=22.13 (recommended 24), Docker daemon, PHP image `php:8.3-apache`, MariaDB 11.4. Không cần PHP cài trực tiếp trên máy. Từ root repo:

```sh
npm ci --ignore-scripts
npm test
docker build -t anhgau-php-test:local php-hostinger
bash php-hostinger/dev.sh start
```

Script development dùng cổng 8184, database Docker volume `anhgau-dev-mysql`, config/mật khẩu thử tự sinh trong `~/.local/state/anhgau-php-development` (đổi bằng `NHANSU_DEV_DIR`; không in ra; không dùng `/tmp` vì WSL xóa khi khởi động lại và Docker tạo lại thư mục mount dưới quyền root). Script chạy lại được và giữ volume. Chỉ dùng localhost để xác minh nội bộ, không coi đó là link preview công khai. Giữ runtime dir khi restart; không xóa secret env rồi gắn lại volume DB cũ vì sẽ lệch mật khẩu. Không dùng dữ liệu nhân viên thật trong test. Sau khi sửa lib, chạy lại dev.sh để đồng bộ code private. Assets mount từ source.

Kiểm tra nội bộ `GET /nhansu/api.php?route=%2Fstatus`. Dùng mã riêng trong private/storage/setup-token cho Admin development, không in vào log. Có thể dừng bằng `docker stop anhgau-dev-web anhgau-dev-db`. Không dùng lệnh xóa volume để restart.

Business rules:

```sh
docker run --rm -v "$PWD:/app:ro" anhgau-php-test:local php /app/php-hostinger/tests/rules.php
```

Chuẩn bị fixture bằng `bash php-hostinger/tests/start-fixture.sh` (chỉ development). Script giữ database test, thay container web test để mount mẫu rewrite WordPress nằm riêng trong tests/fixtures; không sinh file ở thư mục gốc public của bộ cài. `tests/integration.mjs`, `tests/migration.mjs` dùng một fixture Docker khác (`nhansu-php-web`, `nhansu-php-db`, cổng 8183, `/tmp/nhansu-test.env`) và **migration test xóa dữ liệu fixture trước khi nhập**. Không chạy hai script này trên DB thật hoặc development chứa dữ liệu cần giữ. Browser test dùng Playwright + Chromium đã cài trong môi trường thử. Test Telegram dùng transport mô phỏng; không gửi tin ra ngoài.

## Production boundaries

- PHP 8.3 + MariaDB 11.4 đã kiểm thử cục bộ; Hostinger thực tế cần xác minh PHP >=8.2 và extension theo trang kiemtra.php.
- Lưu photo BLOB trong MySQL riêng; ảnh chỉ qua API có kiểm tra quyền. Private nằm ngoài document root. Cookie riêng AG_NHANSU_SESSION path /nhansu; CSRF và Origin check cho POST.
- Tất cả write nghiệp vụ được serialize bằng transactional row lock để bảo vệ cùng một lượt công, chốt lương và phân ca. Cron dùng file lock + unique delivery để không gửi trùng.
- Dữ liệu tháng chốt lưu snapshot; nhân viên không nhận tiền lương/hoa hồng qua API monthly/dashboard.
- Node scrypt được kiểm chứng tương thích Sodium và đổi sang PHP hash khi login. Transfer bỏ mọi session/token bot. Token Telegram phải cấu hình riêng. Export không sửa DB Node.
- API tỉnh/phường, tìm địa chỉ và Telegram phụ thuộc dịch vụ bên ngoài. API tỉnh/phường bị chính sách mạng môi trường thử chặn; đã kiểm tra fallback, chưa xác minh dịch vụ từ Hostinger. Bot được test bằng mock transport, không có token production.
- Sao lưu tích hợp dùng JSON.gz cho dữ liệu nhỏ, tối đa 8 MB tổng ảnh gốc và 20.000 dòng nghiệp vụ để bảo vệ bộ nhớ shared hosting. Khi vượt ngưỡng, dùng backup MySQL riêng qua hPanel/phpMyAdmin + thư mục private; app báo rõ, không xóa dữ liệu. Upload chuyển dữ liệu tối đa 32 MB nén, giải nén tối đa 128 MB. Dữ liệu lớn cần quy trình chuyển CLI phù hợp, không giảm bảo vệ PHP để cố tải qua web.
- Chưa triển khai trên hosting thực của người dùng. Không có chứng cứ để cam kết WordPress production hoàn toàn không chịu tác động từ tài nguyên hosting dùng chung. Đã kiểm tra route WordPress mô phỏng không thay đổi.

## Packaging

```sh
python3 php-hostinger/tools/package.py
```

Chỉ đóng thư mục public `/nhansu` và whitelist code private; không kèm config.php, storage, credentials, dữ liệu thật, test hay file WordPress gốc. Hai ZIP public/private giải nén ở hai vị trí khác nhau như hướng dẫn.

## Update 24/10/2026

See UPDATE-2026-10-24.md and VALIDATION-2026-10-24.md. PHP schema v5 adds salary advances, KPI Event log, staff delegation and signed Google Sheet booking sync. `tools/package-update.py` builds the user-specific update and deliberately includes the uploaded historical PDF and its 50-item seed ONLY in the private ZIP. Generic `tools/package.py` excludes that user-specific source data. Never place the private ZIP under public_html. The live Google Sheet connection still requires the owner to authorize the supplied bound Apps Script; HTTP 403 prevented direct access in this environment.

## Mã nguồn và dữ liệu riêng

Git lưu mã nguồn Node/PHP, giao diện, Apps Script, kiểm thử và hướng dẫn triển khai. Không đưa `config.php`, `.env`, `.data`, private `storage`, database, ảnh chấm công hay dữ liệu nhân sự lên Git.

Hai file `site/nhansu-private/lib/legacy-event-log.pdf` và `legacy-event-log.json` là tài liệu nghiệp vụ riêng, được giữ ngoài Git. Hosting đang chạy vẫn giữ nguyên hai file này khi cập nhật mã nguồn. Nếu cài mới và cần khôi phục log cũ, lấy chúng từ bản sao lưu riêng của chủ studio rồi đặt lại trong thư mục private `lib`; không đưa vào public_html. Chức năng ghi Event log mới vẫn hoạt động khi không có bộ dữ liệu lịch sử. Những kiểm thử đối chiếu 50 log PDF cần bộ fixture riêng này.
