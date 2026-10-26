# Bàn giao mã nguồn — 26/10/2026

## Trạng thái

- Mã nguồn đã được commit tại local. Commit ban đầu: `0565649`; commit tiếp theo bổ sung hai file bàn giao này.
- Remote: https://github.com/haitiano1/chamcongstu.git. Nhánh làm việc local: `work`; dự định push lên `main`.
- **Chưa push thành công**: phiên GitHub trong môi trường Codex báo GH_TOKEN không hợp lệ. Người dùng đã cho phép push tới repository trên; cần xác thực GitHub trên máy mới trước khi push. Không yêu cầu người dùng gửi token trong chat.
- Bản PHP gần nhất v1.2.3 (25/10/2026): đã sửa lỗi nhân viên không thấy lịch 22/10 vì ngày đó chưa gán thợ. Nay mọi role xem lịch chung, có bộ lọc lịch của tôi; danh sách/dấu chấm/thống kê dùng cùng scope.
- Không mặc định Hostinger đã chạy đúng phiên bản local: các bản trước được chủ dự án upload thủ công qua File Manager.

## Gói này có / không có gì

Có toàn bộ file mã nguồn đang được Git theo dõi, frontend Node cũ + PHP mới, Apps Script, tests, vendor Leaflet và hướng dẫn. File `CLAUDE.md` mô tả các quyết định quan trọng để tiếp tục.

Không chứa cấu hình thật, dữ liệu nhân sự, mật khẩu, database, ảnh chấm công, storage hoặc tài liệu Event log lịch sử. Các dữ liệu đó vẫn trên hosting / workspace cũ và bản sao lưu riêng. Hai file optional `private/lib/legacy-event-log.pdf` và `.json` không nằm trong Git; giữ nguyên chúng trên hosting. Không dùng bản clone thiếu dữ liệu để ghi đè hoặc kết luận dữ liệu trên hosting bị mất.

Lịch sử chat không tự chuyển sang Claude; đọc hai file bàn giao này trước.

## Mở trong Claude Code

1. Giải nén gói mã nguồn vào một thư mục mới, ví dụ `D:\stu\chamcongstu-claude`. Không giải nén đè lên bản đang chạy có dữ liệu.
2. Mở thư mục đó trong VS Code và mở Claude Code, hoặc mở terminal tại thư mục rồi chạy `claude` nếu đã cài CLI.
3. Gửi prompt mẫu dưới đây. Claude Code sẽ đọc thêm `CLAUDE.md`.

> Đây là dự án Anh Gấu Studio tôi chuyển từ Codex. Hãy đọc CLAUDE.md và BAN-GIAO-CLAUDE.md, kiểm tra Git và cấu trúc dự án trước. Bản đang chạy trên Hostinger là PHP/MySQL trong php-hostinger/, không phải Node ở root. Giữ nguyên WordPress và dữ liệu thật. Trước hết hãy xác nhận bạn hiểu trạng thái hiện tại rồi chờ yêu cầu tiếp theo của tôi.

## Tiếp tục Git trên máy mới

File `.bundle` đi kèm là bản Git offline chứa các commit và toàn bộ file đã theo dõi, không chứa dữ liệu riêng. Nếu đã cài Git, có thể clone từ bundle để giữ nguyên lịch sử:

```powershell
git clone chamcongstu.bundle chamcongstu-claude
cd chamcongstu-claude
git remote set-url origin https://github.com/haitiano1/chamcongstu.git
```

Sau khi đăng nhập GitHub trên máy mới và kiểm tra remote, có thể push nhánh đang đứng lên `main` bằng `git push -u origin HEAD:main`. Không force-push. Nếu repository đã có commit mới, fetch và đối chiếu trước.

ZIP mã nguồn là lựa chọn dễ mở; bundle là lựa chọn giữ lịch sử Git. Không cần dùng cả hai vào cùng một thư mục.

## Chạy thử

Xem `php-hostinger/README.md`. Node >=22.13 (khuyến nghị 24), Docker + Bash cho môi trường PHP cục bộ. Máy Windows có thể dùng WSL / Git Bash và Docker Desktop; Claude có thể kiểm tra môi trường rồi thiết lập theo README.

- Bản Node cũ: `npm ci` và `npm test`.
- Bản PHP: `bash php-hostinger/dev.sh start`, cổng local 8184; dùng database Docker riêng, không kết nối database production để test.
- Một số script đóng gói/kiểm thử dùng đường dẫn `/workspace` và `/tmp` của môi trường Codex, cần chỉnh đường dẫn fixture/runtime khi chạy máy khác.
- Các kiểm thử log/PDF lịch sử cần fixture riêng không có trong Git. Không tự tạo dữ liệu giả rồi đưa lên hosting để làm tests đạt.

## Kiểm tra đã có

Đợt trước push: 50 kiểm thử Node đạt. Đợt gần nhất của bản PHP: 20 kiểm tra quyền xem lịch chung, 22 kiểm tra desktop Admin / mobile Employee cùng xem ngày 22/10, 13 kiểm tra thống kê/dấu ngày, 95 kiểm tra HR/Sheet, 9 kiểm tra API/download và 141 kiểm tra giao diện nhiều kích thước đã đạt trong môi trường cục bộ. Chi tiết trong các file `VALIDATION-*.md`.
