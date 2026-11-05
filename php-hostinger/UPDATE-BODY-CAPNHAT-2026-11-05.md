# Cập nhật 05/11/2026 — Gói Body 999k + Cập nhật một chạm (v1.3.55)

Gói này **cộng dồn** các gói trước. Đây là lần upload tay cuối cùng; từ bản sau chỉ cần bấm nút trong app.

## Thay đổi

- **Gói Body 999k**: có ở popup **Gói khách chụp**, **＋ Thêm lịch** và **Sửa lịch**. Thẻ lịch ghi "Gói Body 999k", Google Sheet ghi "Body 999".
  Tạm tính như Couple 999: giữ 2 tiếng, hoa hồng ×2 suất.
- **Cập nhật một chạm**: Admin vào **Studio → Cập nhật phần mềm**, bấm một nút. Máy chủ tự tải bản mới, kiểm chữ ký, sao lưu, thay file.
  Bản mới lỗi thì tự trả về bản cũ. Có nút **Quay về** bản trước.

## Làm một lần cuối (theo thứ tự)

Chỉ upload **file bên trong** từng ZIP và chọn Replace:

1. `1-private-lib.zip` → `nhansu-private/lib/` (có file mới `update.php`).
2. `2-private-bin.zip` → `nhansu-private/bin/`.
3. `3-public-assets.zip` → `public_html/nhansu/assets/` (có file mới `update.js`).
4. `4-public-root.zip` → `public_html/nhansu/`.
5. Mở `https://anhgaustudio.com/nhansu/kiemtra-capnhat.php` kiểm tra đủ file (có thêm dòng OpenSSL).
6. **Google Apps Script**: Sheet → Tiện ích mở rộng → Apps Script, dán đè toàn bộ `AnhGauSync.gs`, bấm Lưu. Chưa dán thì lịch Body bị ghi thành Couple 999 trên Sheet.
7. **GitHub Desktop**: bấm Push origin.

Không đổi database, cron.

## Kiểm tra nút Cập nhật

Đăng nhập Admin → Studio → **Cập nhật phần mềm**:

- "Đang dùng bản mới nhất v1.3.55": upload đúng, xong.
- "N file chưa khớp": upload thiếu file, bấm **Sửa cho khớp**.
- "GitHub chưa có bản phát hành nào": chưa Push ở bước 7.
- "PHP không ghi được vào thư mục web": hosting chặn ghi file, báo lại để xử lý; vẫn upload tay được.

## Từ bản sau

1. GitHub Desktop → Push origin.
2. App → Studio → Cập nhật phần mềm → bấm **Cập nhật lên vX.Y.Z**.

Bản nào đổi Apps Script thì trang sẽ ghi rõ "Cần làm tay sau khi cập nhật".
