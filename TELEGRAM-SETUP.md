# Thiết lập bot Telegram cho Anh Gấu Studio — Windows

Bản này đã tích hợp bot; không cần tự lập trình, nhập chat_id hay cài thêm npm package. Bạn tự tạo và cấu hình bot ở các bước dưới. Mã nguồn không chứa token thật và chưa gửi tin tới nhân viên nào.

## 1. Cập nhật web

- Dừng cửa sổ đang chạy web bằng Ctrl+C.
- Sao lưu thư mục `.data`, chép source mới vào thư mục dự án, giữ nguyên `.data` và `.env` nếu đã có.
- Mở PowerShell:

```powershell
cd D:\stu\chamcongstu
npm ci
npm start
```

Khuyến nghị Node.js 24. Mở `http://localhost:3000` **trong trình duyệt**, không gõ địa chỉ như một lệnh PowerShell. Nếu muốn mở bằng lệnh: `Start-Process "http://localhost:3000"`.

## 2. Tạo bot riêng

- Cài Telegram, đăng nhập tài khoản của bạn.
- Mở BotFather chính thức: https://t.me/BotFather.
- Gửi `/newbot`.
- Đặt tên hiển thị: `Anh Gấu Studio`.
- Đặt username kết thúc bằng `bot`, ví dụ `AnhGauStudioChamCongBot`. Nếu đã có người dùng tên đó, thêm chữ/số để tạo tên riêng.
- BotFather gửi token dạng `123456789:...`. Lưu riêng để điền vào `.env`, **không gửi token trong chat, cho nhân viên hoặc chụp màn hình công khai**.

Có thể dùng `/setuserpic` để đặt logo studio, `/setdescription` để ghi “Bot nhắc chấm công nội bộ Anh Gấu Studio”. Không bật webhook hoặc chạy cùng bot ở phần mềm khác; bản này dùng getUpdates tự động.

Nếu token bị lộ: mở BotFather, dùng `/revoke` cho bot, lấy token mới, sửa `.env` và khởi động lại web.

## 3. Lấy địa chỉ HTTPS cho điện thoại

Nếu đã có tên miền HTTPS hoạt động cho web, dùng tên miền đó và bỏ qua ngrok. Nếu đang chạy local:

- Cài ngrok từ https://ngrok.com/download/windows hoặc Microsoft Store theo dashboard chính thức.
- Đăng nhập ngrok, lấy lệnh cấu hình authtoken trong dashboard và chạy trên máy. **Authtoken ngrok khác với token Telegram**.
- Giữ cửa sổ web ở bước 1 đang chạy. Mở PowerShell thứ hai:

```powershell
ngrok http 3000
```

- Sao chép địa chỉ **HTTPS** ở dòng Forwarding, ví dụ `https://ten-mien-duoc-cap.ngrok-free.app` (dùng chính xác địa chỉ ngrok hiển thị, không dùng ví dụ).
- Mở địa chỉ đó trên điện thoại, bấm Visit Site nếu ngrok hỏi, đăng nhập và kiểm tra GPS/camera. Không dùng `localhost` trên điện thoại.

Ngrok làm web truy cập được từ Internet; chỉ chia sẻ cho người cần dùng. Nhân viên vẫn phải đăng nhập. Luôn giữ cả cửa sổ web và ngrok chạy khi thử.

## 4. Cấu hình tệp .env

Quay lại cửa sổ chạy web, nhấn Ctrl+C. Trong thư mục dự án chạy:

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
notepad .env
```

Điền hai dòng sau bằng thông tin thật của bạn, không có dấu cách quanh dấu `=`:

```dotenv
TELEGRAM_BOT_TOKEN=TOKEN_NHAN_TU_BOTFATHER
PUBLIC_APP_URL=https://DIA_CHI_HTTPS_CUA_WEB
```

Giữ các dòng cấu hình khác nếu tệp đã có. Lưu và đóng Notepad. Chạy:

```powershell
npm start
```

`npm start` tự đọc `.env`. Tệp này không được đưa vào Git hoặc bản ZIP cập nhật. Không chia sẻ `.env`. Nếu đổi token hoặc đường dẫn web thì sửa tệp và khởi động lại máy chủ. Nếu chuyển sang một bot khác hẳn (khác tài khoản bot), nhân viên cần liên kết lại; chỉ thu hồi/cấp lại token của cùng bot không làm mất liên kết. Nếu ngrok cấp đường dẫn khác, phải cập nhật PUBLIC_APP_URL.

## 5. Kiểm tra kết nối bot bằng Admin

- Đăng nhập Admin trên web.
- Vào **Quản lý → Quản lý studio → Bot Telegram**.
- Đợi khoảng 15 giây, nhấn **Kiểm tra bot**.
- Cần thấy username bot, **Đủ cấu hình** và thời điểm kết nối gần đây; không có lỗi Telegram.

Nếu chưa cấu hình: kiểm tra tệp có đúng tên `.env` (không phải `.env.txt`), token và đã khởi động lại bằng `npm start` chưa. Nếu thiếu HTTPS: điền PUBLIC_APP_URL bằng địa chỉ HTTPS đang truy cập được từ điện thoại.

## 6. Cho từng nhân viên liên kết Telegram

Mỗi người phải dùng tài khoản web và Telegram của chính mình:

1. Trên điện thoại, đăng nhập web → **Chấm công**.
2. Mở **Nhắc chấm công qua Telegram** → **Liên kết Telegram**.
3. Nhấn **Mở Telegram và nhấn Start**.
4. Trong Telegram, nhấn **Start / Bắt đầu**. Chờ bot báo “Đã liên kết với Anh Gấu Studio” (tối đa khoảng 15 giây).
5. Quay lại web, nhấn **Kiểm tra đã liên kết**.
6. Nhấn **Gửi tin thử cho tôi**. Kiểm tra nhận được tin kèm nút Mở trang chấm công.

Liên kết có hiệu lực 15 phút, dùng một lần, gắn với tài khoản đang đăng nhập web. Không gửi liên kết của mình cho người khác. Mỗi Telegram liên kết một tài khoản nhân viên. Muốn đổi Telegram: tạo liên kết mới từ cùng tài khoản web rồi Start trên Telegram mới. Muốn ngừng nhận: bấm Ngắt liên kết trên web hoặc gửi `/stop` cho bot.

## 7. Phân ca và tự đồng bộ nhắc checkin

Admin/Quản lý mở mục **Phân ca** trên menu. Quản lý nhân viên cũng có nút Mở lịch phân ca để đi tới cùng lịch này.

- Chọn chế độ Ngày, Tuần, 2 tuần hoặc Tháng. Dùng mũi tên/Hôm nay để di chuyển lịch.
- Nhân viên đã lưu nằm ở cột bên trái. Tìm theo tên/mã, kéo nhân viên vào Ca 1 hoặc Ca 2 của ngày muốn phân.
- Có thể kéo thẻ đã phân sang ngày/ca khác để chuyển. Nhấn vào thẻ để sửa hoặc xóa phân ca.
- Muốn thêm nhiều người: nhấn Phân ca hoặc dấu + trong một ca, chọn nhiều nhân viên rồi Lưu.
- Điện thoại mặc định xem Tuần: chạm chọn nhân viên, rồi chạm dấu + của ca. Không bắt buộc kéo thả trên điện thoại.
- Mọi thay đổi được lưu ngay trên máy chủ. Chỉ khi hiện Đã lưu phân ca thì mới được tính là lưu thành công.

**Nhắc checkin tự lấy đúng người/ngày/ca trên lịch**; không cần bật lịch nhắc lần thứ hai. Không có phân ca thì không nhắc checkin. Xóa/chuyển phân ca sẽ cập nhật người cần nhắc ở lần kiểm tra tiếp theo. Nhân viên phải đã liên kết Telegram và nhấn Start. Lịch nhắc hằng tuần cũ được chuyển một lần sang các ngày tương ứng trong 90 ngày tới để quản lý kiểm tra; sau đó dùng lịch phân ca làm nguồn chính.

**Checkout không cần phân ca**: chỉ cần liên kết Telegram và có lượt checkin thực tế, nếu chưa checkout thì bot nhắc 5 phút sau giờ kết thúc ca. Đã checkout thì không nhắc. Lượt checkin có trước bản cập nhật vẫn được xét.

| Ca | Nhắc chưa checkin theo phân ca | Nhắc thiếu checkout theo công thực tế |
|---|---|---|
| Ca 1: 09:00–14:00 | 09:10 | 14:05 |
| Ca 2: 14:00–19:00 | 14:10 | 19:05 |

Giờ Việt Nam, kiểm tra mỗi 15 giây. Mỗi loại tối đa một lần gửi cho mỗi người/ca/ngày. Không tự chấm công, không nhắc OT, không gửi lương/ảnh/tọa độ qua Telegram. Thay đổi phân ca không thay đổi công hoặc bảng lương.

## 8. Kiểm thử và vận hành

- Kiểm thử nhanh nhất: nhân viên liên kết xong dùng Gửi tin thử cho tôi. Không cần đợi giờ chấm công; không tạo công hay làm đổi lương.
- Để thử nhắc checkout: dùng tài khoản đã liên kết và đã checkin ca thực tế, không cần bật lịch; giữ web chạy và kiểm tra sau 14:05/19:05 nếu chưa checkout. Không sửa giờ máy chủ hoặc tạo công giả trên tài khoản thật.
- Để thử nhắc checkin theo lịch: phân ca đúng ngày cho một tài khoản thử đã liên kết, giữ web chạy và kiểm tra sau giờ nhắc. Nếu đang trong ca và đã qua mốc nhắc checkin, bot kiểm tra trong khoảng 15 giây. Không tạo tài khoản thử rồi chốt lương như dữ liệu thật.
- Admin xem **Quản lý studio → Bot Telegram → Lịch sử gửi nhắc gần đây** để biết kết quả.
- Máy chạy local phải bật, có Internet và không ngủ; ngrok phải hoạt động để nút mở web dùng được. Muốn dùng hằng ngày, nên chạy web trên máy chủ hoạt động liên tục với địa chỉ HTTPS cố định.
- Bot này chỉ hoạt động trong một tiến trình máy chủ. Không chạy nhiều bản web cùng bot hoặc dùng token này cho nền tảng bot khác.

### Khi không nhận được tin

- Chưa bấm Start, bấm liên kết hết hạn, hoặc liên kết nhầm tài khoản: tạo liên kết mới từ tài khoản đúng rồi Start.
- Chặn bot: mở Telegram, bỏ chặn bot rồi liên kết và thử lại.
- Với nhắc checkin: chưa được phân ca đúng ngày hoặc đã vào ca thì bot không nhắc.
- Với nhắc checkout: cần có lượt vào ca chưa có lượt ra và đã đến giờ nhắc; phân ca không ảnh hưởng. Bot chỉ nhắc trong 24 giờ sau giờ kết thúc ca và không nhắc OT.
- Lỗi 401: token sai/bị thu hồi, lấy token hợp lệ và sửa `.env`.
- Lỗi 409: bot có webhook hoặc đang được chạy ở nơi khác; dừng phần mềm kia. Nếu từng đặt webhook, xóa webhook qua Telegram Bot API `deleteWebhook` bằng công cụ quản trị riêng, không đưa token vào URL trình duyệt hay chia sẻ công khai.
- Lỗi mạng: kiểm tra máy chủ kết nối được `api.telegram.org` bằng HTTPS.
- Gửi lỗi hoặc mất kết nối đúng lúc gửi: xem nhật ký; hệ thống không tự gửi lại cùng lời nhắc để tránh gửi trùng khi chưa chắc Telegram đã nhận hay chưa. Có thể dùng tin thử sau khi sửa kết nối. Việc nhắc không thay thế đối soát công.

## Xác minh bản tích hợp

Kiểm thử tự động dùng Telegram API giả lập: hạn liên kết/dùng một lần, chỉ liên kết tin riêng, bảo vệ tài khoản, phân quyền lịch, ngày nghỉ, mốc giờ Việt Nam, chống gửi trùng qua khởi động lại, tài khoản ngừng hoạt động và lỗi gửi tin. Giao diện kiểm tra trên desktop/mobile. Chưa gửi tin Telegram thật trong môi trường phát triển vì chưa có bot/token do chủ studio cấu hình; tin thử ở bước 6 xác minh kết nối thật trên máy của bạn.
