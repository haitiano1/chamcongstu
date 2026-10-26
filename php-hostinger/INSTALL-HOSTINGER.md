# Cài Anh Gấu Studio tại https://anhgaustudio.com/nhansu/

Bộ này chạy PHP + MySQL độc lập, giữ giao diện và nghiệp vụ chấm công của bản Node.js. Đã kiểm thử bằng Apache/PHP 8.3 + MariaDB 11.4 trong môi trường cục bộ; chưa cài lên Hostinger của bạn. Không dùng WordPress, plugin, tài khoản hay database WordPress. Hai ứng dụng vẫn dùng chung tài nguyên hosting, nên không thể cam kết rằng mọi sự cố tài nguyên/cấu hình hosting đều hoàn toàn tách biệt.

Bạn đã tạo xong:

- Database và user: `u418103456_chamcongstu`.
- Thư mục `public_html/nhansu`.

Không cần VPS, Node.js, đổi DNS, reverse proxy hay nhập SQL thủ công cho bản PHP này.

## 1. Tải hai gói cài về máy

- `nhansu-public.zip`: các file được phục vụ qua web.
- `nhansu-private.zip`: mã xử lý, cấu hình và dữ liệu riêng, đặt ngoài `public_html`.
- `export-from-node.mjs`: công cụ chuyển dữ liệu cũ (bước 7).

Đừng tải bản ZIP Node/local trước đây lên hosting. Các gói mới không kèm mật khẩu, dữ liệu cá nhân hoặc ảnh nhân viên.

## 2. Đặt gói public vào đúng thư mục

Trong Hostinger → Websites → anhgaustudio.com → Files → File Manager:

1. Mở `public_html/nhansu`.
2. Upload `nhansu-public.zip` vào đây, rồi chọn **Extract / Giải nén**, đích là chính thư mục hiện tại.
3. Kiểm tra thấy **ngay** `index.php`, `api.php`, `assets`, `.htaccess`, `.user.ini`, `kiemtra.php`, `chuyen-du-lieu.php` trong `nhansu`. Bật hiển thị file ẩn nếu cần. Không được thành `nhansu/nhansu/index.php` hoặc `nhansu/public_html/nhansu/index.php`.
4. Xóa file ZIP vừa upload sau khi giải nén. Giữ bản ZIP trên máy.

Không đụng vào `public_html/index.php`, `.htaccess` gốc, `wp-config.php`, `wp-admin`, `wp-content` và `wp-includes`.

## 3. Đặt gói private BÊN NGOÀI public_html

1. Từ `public_html`, đi **lên một cấp**: đây là thư mục cha chứa `public_html`.
2. Upload `nhansu-private.zip` ở cấp này, chọn giải nén ở thư mục hiện tại. ZIP tự chứa thư mục `nhansu-private`.
3. Sau giải nén, hai thư mục phải nằm cạnh nhau:

```text
Thư mục của tên miền/
├── public_html/
│   ├── wp-admin/              ← web studio đang có
│   ├── wp-content/            ← giữ nguyên
│   ├── index.php              ← giữ nguyên
│   ├── .htaccess              ← giữ nguyên
│   └── nhansu/
│       ├── index.php
│       ├── api.php
│       ├── chuyen-du-lieu.php
│       ├── kiemtra.php
│       ├── .htaccess
│       ├── .user.ini
│       └── assets/
└── nhansu-private/
    ├── config.example.php
    ├── lib/
    ├── bin/
    └── .htaccess
```

4. Xóa file ZIP private trên hosting sau khi giải nén.

**Không bỏ `nhansu-private` trong `public_html`, kể cả trong `/nhansu`.** Nếu File Manager không cho đi lên, mở mục File Manager có lựa chọn **Access all files / Truy cập tất cả tệp hosting**; nếu vẫn bị khóa, nhờ hỗ trợ Hostinger cho tạo một thư mục riêng nằm ngoài document root. Ứng dụng cố ý từ chối chạy nếu private nằm trong document root.

## 4. Nhập cấu hình database

Trong `nhansu-private`:

1. Sao chép `config.example.php` thành **`config.php`**.
2. Mở `config.php` bằng Edit, thay nội dung bằng mẫu dưới. Điền mật khẩu database bạn đã lưu, không phải mật khẩu WordPress hay tài khoản Hostinger:

```php
<?php
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'u418103456_chamcongstu',
    'db_user' => 'u418103456_chamcongstu',
    'db_password' => 'DIEN_MAT_KHAU_DATABASE_O_DAY',
    'table_prefix' => 'agns_',
    'app_url' => 'https://anhgaustudio.com/nhansu',
    'telegram_token' => '',
];
```

3. Bấm **Save**. Nếu mật khẩu chứa `'` hoặc `\`, dùng `\'` hoặc `\\` tương ứng trong chuỗi PHP. Mật khẩu ngẫu nhiên dài bằng chữ, số và dấu khác sẽ dễ nhập hơn.
4. Host MySQL thường là `localhost`. Nếu hPanel ghi host khác, dùng đúng host được hPanel cấp.
5. Để trống Telegram ở giai đoạn đầu. Không chụp/gửi ảnh file chứa mật khẩu hoặc token.

Ứng dụng tự tạo bảng `agns_*`. Nếu database có bảng của ứng dụng khác, nó sẽ dừng thay vì ghi vào database đó. Không dùng thông tin database trong `wp-config.php`.

## 5. Kiểm tra PHP và tạo Admin đầu tiên

1. Mở bằng trình duyệt: **https://anhgaustudio.com/nhansu/kiemtra.php**.
2. Các mục cần đạt: PHP >=8.2, PDO MySQL, GD/JPEG, cURL và Gzip. Sodium cần để giữ khả năng đăng nhập bằng mật khẩu cũ từ bản Node; nếu thiếu, phải bật Sodium hoặc quản lý đặt lại mật khẩu nhân viên.
3. Nếu hosting đang dùng PHP phù hợp, **giữ nguyên phiên bản**. Không tự đổi phiên bản PHP toàn website hoặc tắt extension đang dùng của WordPress. Nhờ Hostinger hỗ trợ cấu hình theo thư mục nếu cần nâng riêng phần `/nhansu`.
4. Mở **https://anhgaustudio.com/nhansu/**. Lần đầu sẽ hiện “Thiết lập studio”, đồng thời tự tạo `nhansu-private/storage/setup-token`.
5. Quay lại File Manager, mở file **`nhansu-private/storage/setup-token`**, sao chép mã. Đây là mã tạo Admin riêng, không phải mật khẩu database. Chỉ đọc trong File Manager, không truy cập file này bằng URL.
6. Trên trang thiết lập, nhập mã, họ tên, email và mật khẩu Admin dài ít nhất 10 ký tự; nhấn **Tạo studio**. Bạn được đăng nhập vào ứng dụng. Mã thiết lập sẽ bị xóa sau khi dùng.

Nếu thông báo thiếu quyền ghi: kiểm tra thư mục `nhansu-private` thuộc đúng tài khoản hosting; tạo `storage` thủ công và cấp quyền ghi cho PHP của chính tài khoản. Thường thư mục 755, file 644 là đủ trên shared hosting; file cấu hình có thể 600 nếu PHP chạy cùng chủ sở hữu. Không cấp 777. Các thư mục con backup/cache được ứng dụng tự tạo.

## 6. Chọn dùng dữ liệu mới hay chuyển dữ liệu cũ

- Nếu bắt đầu mới: vào **Quản lý → Quản lý studio**, lưu vị trí và bán kính; vào **Quản lý → Quản lý nhân viên**, thêm nhân viên. Sau đó sang bước 8.
- Nếu muốn giữ tài khoản, phân ca, công, lương và ảnh trước đây: **chưa thêm nhân viên, chưa chấm công, chưa phân ca trên hosting**. Chuyển dữ liệu ở bước 7 trước. Trang nhận dữ liệu chỉ chấp nhận bản cài mới còn một Admin tạm.

## 7. Chuyển toàn bộ dữ liệu từ máy Windows đang chạy local

1. Trên máy Windows chứa dữ liệu cũ, giữ lại cả thư mục dự án làm bản sao. Nhấn **Ctrl+C** ở cửa sổ `npm start` để dừng app/bot cũ trước lần chuyển chính thức. Không xóa `.data`.
2. Tải `export-from-node.mjs` được gửi kèm, đặt vào thư mục dự án cũ, ví dụ `D:\stu\chamcongstu` (cùng cấp `package.json`).
3. Mở PowerShell tại thư mục đó, chạy:

```powershell
node .\export-from-node.mjs ".\.data\studio.sqlite" ".\nhansu-transfer.json.gz"
```

Nếu đã cấu hình `DATA_DIR` riêng, thay đường dẫn SQLite cho đúng dữ liệu đang dùng. Nếu tệp đích đã tồn tại, đổi tên tệp đích để giữ bản trước. Node cần từ 22.13 (bản 24 bạn đang dùng phù hợp).

4. Chờ dòng “Đã xuất … tài khoản, … lượt chấm công …”. Tệp `nhansu-transfer.json.gz` chứa thông tin nhạy cảm và ảnh; chỉ giữ riêng trên máy, **không upload vào File Manager/public_html, không gửi nhóm nhân viên**.
5. Đăng nhập Admin tạm trên hosting, mở **https://anhgaustudio.com/nhansu/chuyen-du-lieu.php**.
6. Chọn tệp `nhansu-transfer.json.gz`; nhập mật khẩu Admin **đang đăng nhập**; nhập **email Admin trong dữ liệu cũ** và đặt mật khẩu mới cho Admin cũ. Tích xác nhận rồi nhấn **Nhập dữ liệu**.
7. Chờ thông báo số tài khoản/lượt công/phân ca đã nhập. Nếu lỗi, các thay đổi database của lần nhập được hoàn tác. Công cụ không nhận tệp SQLite trực tiếp. Tệp nén tối đa 32 MB; nếu lớn hơn cần chuyển bằng CLI có hỗ trợ kỹ thuật, không giảm/xóa ảnh để ép qua giới hạn.
8. Về trang `/nhansu/`, đăng nhập bằng **email Admin cũ + mật khẩu mới vừa đặt**. Tài khoản Admin tạm được thay thế khi nhập thành công. Phiên đăng nhập cũ trên hosting hết hiệu lực.
9. Đối chiếu danh sách nhân viên, phân ca, công từng tháng, một vài ảnh và tổng lương với bản cũ. Mật khẩu nhân viên cũ giữ được nếu có Sodium; nếu không, vào sửa hồ sơ nhân viên để đặt mật khẩu mới. Không gửi mật khẩu trong nhóm chung.

Các bản chốt lương cũ cũng được giữ; không tự đổi sang công thức khác. Dữ liệu ca cũ cần đối soát như trên bản Node. Sau chuyển, chỉ dùng một nơi chính để chấm công; không tiếp tục cho nhân viên ghi song song ở local và hosting.

## 8. Cài vị trí và thử trên điện thoại

1. Admin/Quản lý vào **Quản lý → Quản lý studio**, kiểm tra địa chỉ, ghim và bán kính thực tế. Không dùng tọa độ thử nghiệm. Có thể chọn tỉnh/phường, tìm địa chỉ, kéo ghim hoặc lấy vị trí hiện tại, rồi **Lưu vị trí studio**.
2. Trên điện thoại, mở bằng Chrome/Safari: **https://anhgaustudio.com/nhansu/** (phải là HTTPS, không qua trình duyệt trong Zalo/Facebook nếu camera/GPS không hoạt động).
3. Đăng nhập tài khoản Nhân viên, cấp quyền camera và vị trí chính xác. Chấm trong ca phù hợp, chụp ảnh, nhấn **Dùng ảnh & chấm công**.
4. Kiểm tra lại bằng Admin: vào **Chấm công**, xem lịch sử toàn studio và ảnh của lượt vừa chấm. Nhân viên chỉ thấy dữ liệu của mình, không thấy lương/hoa hồng tạm tính.

Quy tắc hiện tại: Ca 1 09–14h, Ca 2 14–19h, 0,5 công/ca hoàn tất. Checkout được phép tới **14:30:00 / 19:30:00**; quá hạn gửi bổ sung công. OT bắt đầu từ 19h, có xác nhận cảnh báo và chụp ảnh, không cộng lương theo giờ; khách thuộc lượt OT được nhân đôi hoa hồng. Tự chấm cần GPS thật ở trong bán kính và sai số không quá min(100 m, bán kính). Bản hosting không cung cấp nút giả GPS. Khi bất khả kháng, Admin/Quản lý có thể chấm hộ kèm lý do; lượt này được ghi rõ nguồn.

## 9. Bật bot và sao lưu tự động bằng Cron

Trang web vẫn đăng nhập/chấm công được khi chưa cài bot. **Nhắc Telegram và sao lưu tự động cần Cron; chỉ mở web không chạy bot nền.**

1. Nếu dùng lại bot cũ, dừng bản local đang polling trước, để không có hai nơi tranh nhau đọc tin nhắn. Điền token bot vào `telegram_token` trong `nhansu-private/config.php`; token cũ trong `.env` không tự chuyển qua file dữ liệu.
2. Vào Hostinger → Websites → anhgaustudio.com → **Advanced → Cron Jobs**.
3. Chọn loại **PHP**. Nhập **đường dẫn tuyệt đối** tới `nhansu-private/bin/cron.php`. Thư mục phải ở cùng cấp với `public_html`. Ví dụ (phải đối chiếu đường dẫn thật trong File Manager/hPanel):

```text
/home/u418103456/domains/anhgaustudio.com/nhansu-private/bin/cron.php
```

Nếu chọn **Custom / Tùy chỉnh**, lệnh tương đương là:

```sh
/usr/bin/php /home/u418103456/domains/anhgaustudio.com/nhansu-private/bin/cron.php
```

Không nhập URL `https://...` vào ô đường dẫn PHP. Nếu hPanel tự thêm `/usr/bin/php`, chỉ nhập phần đường dẫn tới file; không thêm PHP hai lần. Nếu đường dẫn home khác ví dụ, dùng đường dẫn thật của bạn.

4. Đặt **Every minute / Mỗi phút**: cả 5 ô là `*` (`* * * * *`). Lưu job. Nếu gói của bạn chỉ cho 5 phút/lần, bot có thể trễ tối đa khoảng 5 phút; cần Hostinger cho Cron mỗi phút để gần mốc mong muốn.
5. Sau 1–2 phút, Admin mở **Quản lý studio → trạng thái Telegram**, kiểm tra lần chạy và lỗi. Chạy Cron thành công có dòng `Nhansu cron OK` trong output của hPanel. Nếu thiếu token, sao lưu vẫn chạy nhưng bot chưa chạy.
6. Nhân viên vào **Chấm công → Liên kết Telegram**, mở bot, nhấn **Start**. Nếu đã chuyển liên kết bot cũ, có thể dùng tiếp với cùng bot; kiểm tra bằng **Gửi thử**. Nếu đổi bot khác, liên kết lại. Nhân viên có thể gửi `/stop` để ngừng nhận.
7. Phân ca để nhắc checkin: Ca 1 từ **09:10**, Ca 2 từ **14:10**, chỉ người được phân ca mà chưa chấm vào. Khi đã checkin, bot nhắc checkout từ **14:05 / 19:05** nếu chưa chấm ra, **không cần bật lịch nhắc riêng**. Thực tế gửi ở lần Cron tiếp theo. Không nhắc OT tự động.
8. Sao lưu tích hợp giới hạn bộ dữ liệu nhỏ (tổng ảnh gốc tối đa 8 MB và tổng các bảng nghiệp vụ tối đa 20.000 dòng) để tránh làm cạn bộ nhớ PHP chung với WordPress. Khi vượt giới hạn, ứng dụng báo rõ và giữ nguyên dữ liệu; dùng sao lưu database riêng qua hPanel/phpMyAdmin cùng thư mục private. Ảnh vẫn lưu trong MySQL, việc chấm công không bị giới hạn bởi ngưỡng sao lưu này. Cron tự tạo sao lưu mỗi 24 giờ và giữ 14 bản gần nhất trong private/storage/backups. Admin có thể **Sao lưu ngay → Tải về** ở Quản lý studio. Bản PHP dùng `.json.gz`, không phải `.sqlite`. Tệp chứa ảnh/dữ liệu nhạy cảm; giữ ở nơi riêng. Sao lưu định kỳ cả hai thư mục và database riêng qua hPanel, thử khôi phục trên bản cài thử trước khi cần phục hồi thật.

## 10. Kiểm tra xong và sử dụng

- Mở `https://anhgaustudio.com/`, một trang bài viết cũ và WP Admin: vẫn hoạt động như trước.
- Mở `/nhansu/`, đăng nhập Admin, Quản lý và một Nhân viên: đúng quyền, đúng dữ liệu.
- Thử phân ca, chấm hộ, gửi bổ sung, xem ảnh, đối chiếu lương và tải một bản sao lưu.
- Có thể xóa riêng `public_html/nhansu/kiemtra.php` sau kiểm tra; xóa `chuyen-du-lieu.php` và `assets/transfer.js` sau khi chuyển xong nếu không dùng nữa. Không xóa `api.php`, `index.php`, `nhansu-private`, ảnh hoặc database.
- Nếu có cache/CDN/WordPress cache ở cấp máy chủ, loại trừ **`/nhansu/*`**, đặc biệt `api.php`, để không cache trạng thái đăng nhập và dữ liệu cá nhân. API đã gửi `Cache-Control: no-store`.

## Khi gặp lỗi

- **403 / file không đọc được:** kiểm tra quyền file 644, thư mục 755 và không giải nén lồng thư mục; kiểm tra quy tắc chặn thư mục trên hosting. Không sửa `.htaccess` gốc WordPress để thử.
- **Chưa cấu hình / thiếu nhansu-private:** kiểm tra đúng thư mục cha của public_html và tên config.php, không phải config.php.txt.
- **Chưa kết nối database:** kiểm tra đúng host/tên DB/user/mật khẩu riêng và quyền user; không cần tự tạo bảng bằng phpMyAdmin.
- **500:** kiểm tra PHP error log trong hPanel; chỉ gửi dòng lỗi đã che thông tin bí mật. Nếu lỗi `Options not allowed here` trong `.htaccess` của `/nhansu`, bỏ riêng dòng `Options -Indexes` ở file đó, giữ các dòng bảo vệ file; không sửa file gốc.
- **404 /nhansu hoặc hiện WordPress:** kiểm tra `public_html/nhansu/index.php` tồn tại; thử `/nhansu/index.php`. API dùng file `api.php` thật để không cần sửa rewrite WordPress. Nếu có CDN/rule ép mọi đường dẫn vào WP, cần loại trừ riêng `/nhansu/*` trong cấu hình rule đang có.
- **Yêu cầu quá lớn / ảnh chưa tới:** tải lại trang mới trên điện thoại, kiểm tra post_max_size/upload_max_filesize và giới hạn WAF ở hosting. File .user.ini có thể mất vài phút mới áp dụng.
- **GPS không chính xác:** bật vị trí chính xác, ra nơi thoáng, lấy lại vị trí; không nới bán kính chỉ để cho người ở xa chấm thử. Quản lý có chấm hộ ghi lý do.
- **Bot không nhắc:** kiểm tra đúng token, Cron mỗi phút, đã liên kết, bot không bị chặn, không có bản local/webhook cùng chạy; nhắc checkout chỉ dành cho ca đã checkin nhưng chưa checkout.

Không gửi mật khẩu/token/file chuyển dữ liệu trong chat. Nếu cần trợ giúp, gửi ảnh thông báo lỗi hoặc cây thư mục đã che thông tin bí mật.
