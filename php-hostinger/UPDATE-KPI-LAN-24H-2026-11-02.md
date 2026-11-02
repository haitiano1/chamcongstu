# Cập nhật 02/11/2026 (lần 2) — Mức trừ KPI theo số lần, chỉ Nhân viên, khóa chấm ra theo hạn, /log tên trước, không hiện lịch trùng, giờ 24h, Xóa việc, chọn ca bằng 3 nút

Gói này **cộng dồn** các gói trước, gồm cả Biên bản họp và role Makeup của gói sáng nay. Chưa upload gói sáng nay thì upload gói này là đủ.

## Thay đổi (v1.3.44)

### 1. Luật Studio: khung mức trừ KPI

Đầu trang Luật Studio có khung viền cam nổi bật **Mức trừ KPI theo số lần mắc lỗi trong tháng**:

| Lần | Mức | Trừ |
| --- | --- | --- |
| Lần 1 | Nhắc nhở | −20 điểm KPI |
| Lần 2 | Cảnh cáo | −100 điểm KPI |
| Lần 3 trở đi | Kỷ luật | −300 điểm KPI |

- Đếm riêng từng lỗi, sang tháng mới đếm lại từ đầu.
- **Lỗi nặng** có điểm riêng từ 100 trở lên (VD CC-03 Chấm công hộ −300) thì trừ đúng điểm đó ngay từ lần đầu (bạn đã chọn "Luật nặng giữ điểm riêng").
- Đi muộn quá 30 phút (DM-01) hệ thống vẫn tự tính như cũ: lần 1 miễn, lần 2 −20, lần 3 −100, từ lần 4 −300.

Trong danh sách luật: luật thường hiện nhãn **Theo số lần**, luật nặng hiện số điểm (VD −300 KPI). Bấm vào luật thấy dòng giải thích cách trừ.

### 2. Event log: ô "Lần mắc lỗi này trong tháng"

Form **Ghi Event log** có 4 ô bấm thay cho ô Điểm KPI cũ: **Chỉ ghi nhận** (0 điểm), **Lần 1 · Nhắc nhở −20**, **Lần 2 · Cảnh cáo −100**, **Lần 3 trở đi · Kỷ luật −300**. Bấm ô nào thì trừ đúng điểm đó.

- Chọn nhân viên + luật thì web tự đếm người đó đã bị ghi lỗi này mấy lần trong tháng và chọn sẵn lần tiếp theo (VD "đã bị ghi lỗi CC-01 1 lần → lần này là Lần 2"). Vẫn bấm đổi được.
- Chọn luật nặng thì ô Lần ẩn đi, hiện "trừ cố định … điểm KPI mỗi lần".
- Danh sách Event log ghi "Lần 2 · 100 điểm"; chi tiết ghi "Lần 2 trong tháng (Cảnh cáo)".
- Telegram `/log` kèm mã luật thường cũng tự đếm số lần (trả lời "Lần 2 trong tháng (Cảnh cáo) · Trừ 100 điểm KPI").
- Hộp ghi nhanh từ Nghỉ gấp và Đối soát chấm công giữ điểm đề xuất riêng như cũ.

### 3. Telegram /log: tên trước, giống /task và /nhacnho

```
/log Huy | Quên checkout ca 2
/log all | Họp rút kinh nghiệm
/log Huy, Cường | Quên chấm ra ca 2 | CC-01
```

Gõ kiểu cũ `nội dung | tên` bot vẫn hiểu và tự đổi lại.

### 4. Task / Nhắc nhở có hạn: khóa chấm ra đúng ngày đến hạn

- Hạn **trong hôm nay** mà chưa xong (Nhắc nhở: chưa bấm Đã xem) thì **không chấm ra ca 2** được, giống Việc hằng ngày.
- Hạn **ngày khác** (VD giao 48h): hôm nay và ngày mai **chấm ra bình thường**. Đúng ngày đến hạn mới khóa chấm ra.
- Quá hạn mà vẫn chưa xong thì các ngày sau vẫn khóa, tới khi báo xong hoặc Quản lý hủy / đổi hạn.
- Đang **Xin từ chối** chờ duyệt thì không khóa. Ca 1 không khóa.
- Đầu trang Chấm công có khung **Việc phải xong hôm nay** liệt kê việc hằng ngày và việc đến hạn hôm nay. Ô Hạn khi giao việc có dòng ghi chú quy tắc này.
- Chỉ áp dụng cho việc có hạn từ 02/11/2026. Việc cũ quá hạn từ trước không khóa.

### 5. Mọi chức năng chỉ nhắm tới Nhân viên

Danh sách chọn người chỉ còn tài khoản **Nhân viên** (Huy, Cường, Tín). Admin và Quản lý không còn trong các danh sách. Áp dụng ở: giao Task / Nhắc nhở (kể cả @all và `/task`), Việc hằng ngày, Event log (web và `/log`), Biên bản họp (thành phần tham gia, người phụ trách), Đăng ký ca (Quản lý không còn phải đăng ký, không bị nhắc), Lịch làm việc, Tổng quan, Đối soát chấm công, Chấm hộ, Hoa hồng nhập tay, Ứng lương. Máy chủ cũng chặn chọn người khác.

> Còn tên nào khác ngoài Huy, Cường, Tín hiện trong danh sách (VD tài khoản Tuấn, tài khoản test) thì tài khoản đó đang để Vai trò = Nhân viên. Vào **Quản lý nhân viên** → mở hồ sơ → đổi Vai trò sang Quản lý (nếu là quản lý) hoặc cho nghỉ (nếu không dùng nữa).

### 6. Lịch chụp không còn hiện lịch trùng

**Nguyên nhân 2 thẻ trùng** (như 11:00 Hoai Lam ZALO, 15:00 Tín Hr ZALOA): mỗi dòng trên Google Sheet gắn với web bằng mã ẩn ở cột `_AG_BOOKING_ID`. Trước đây, khi một dòng bị **xóa** trên Sheet, hoặc bị **cắt dán / sao chép rồi xóa dòng cũ** mà ô mã ẩn không đi theo, dòng mới được cấp mã mới. Web vẫn giữ lịch cũ nên hiện 2 thẻ: một thẻ là bản cũ (VD Ko MK), một thẻ là dòng hiện tại (VD Nam MK).

**Từ bản này:**

- Mỗi lần đồng bộ, script gửi kèm danh sách mã các dòng còn trên Sheet. Lịch trên web không còn dòng trên Sheet thì **tự ẩn**, không tính công, không tính hoa hồng. Nếu dòng đó quay lại (VD bấm Hoàn tác trên Sheet) thì lịch tự hiện lại.
- Nếu Sheet **thật sự có 2 dòng cùng giờ**, thẻ trên web có viền đỏ và nhãn **⚠ Trùng giờ · kiểm tra Sheet**. Ô giờ trên Sheet cũng tô đỏ. Xóa bớt dòng thừa trên Sheet là web tự hết trùng.
- Muốn hủy lịch thì vẫn nên ghi **Hủy** ở cột Trạng thái (giữ lại lịch sử).

> **Bắt buộc dán lại Apps Script** (bước 5 bên dưới) thì phần tự ẩn lịch trùng mới chạy. Lịch trùng đang có sẽ tự ẩn trong khoảng 10 phút sau khi dán.

### 7. Ô giờ 24h, Giờ lẻ khác to hơn

- **Thêm lịch → Giờ lẻ khác**: nút mở to hơn. Bên trong là 2 ô chọn to **Giờ (05–23) : Phút** theo 24h, không còn SA/CH như ô giờ của máy để 12h.
- Biên bản họp (giờ bắt đầu / kết thúc) và Chấm hộ (giờ vào / ra) cũng dùng ô giờ 24h này.

### 8. Việc: nút Xóa việc đã giao

Admin và Quản lý thấy nút **🗑 Xóa** ở cuối mỗi thẻ việc (và **🗑 Xóa việc** trong chi tiết). Bấm → hộp xác nhận, lý do không bắt buộc → **Xóa việc**.

- Việc biến mất khỏi danh sách của mọi người: không còn nhắc, không tính vào số việc chờ, không khóa chấm ra. Lịch sử vẫn lưu.
- Xóa được cả việc đang mở và việc đã hủy để dọn danh sách.
- Việc hằng ngày: chỉ xóa việc của ngày đó. Muốn ngừng giao mỗi ngày thì xóa ở mục **Việc hằng ngày**.

### 9. Chấm công: chọn ca bằng 3 nút, mặc định không chọn

Ô thả xuống Ca làm việc được thay bằng 3 nút to: **Ca 1** (09:00 – 14:00), **Ca 2** (14:00 – 19:00), **Làm cả ngày** (09:00 – 19:00 · chấm 1 lần).

- **Mặc định không chọn ca nào.** Nút chấm công ghi "Chọn ca để chấm công" và khóa tới khi nhân viên tự bấm ca.
- Đang làm một ca (đã chấm vào, chưa chấm ra) thì ca đó tự được chọn để chấm ra. Mỗi nút ghi trạng thái hôm nay: **Đang làm** / **Đã chấm ra**.
- Làm cả ngày giữ quy tắc cũ: chỉ chọn được ở lần chấm vào đầu tiên trong Ca 1 (9:00 – 14:00), phải tick xác nhận; ngoài giờ đó bấm vào sẽ hiện thông báo lý do.
- Nút Chấm công OT · Sau 19:00 giữ như cũ.

Database tự thêm cột `offense` vào bảng `event_log` khi mở app lần đầu, không đổi dữ liệu cũ.

## Cập nhật

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (13 file .php) |
| 2-private-bin.zip | nhansu-private/bin (cron.php) |
| 3-public-assets.zip | public_html/nhansu/assets (**có file mới `time24.js`**). Gói này **không kèm thư mục `vendor`** (thư viện bản đồ, không đổi); giữ nguyên `vendor` đang có trên hosting |
| 4-public-root.zip | public_html/nhansu (api.php, index.php, kiemtra-capnhat.php) |
| AnhGauSync.gs / AnhGauSync.txt | Dán vào Apps Script của Google Sheet lịch chụp (bước 5) |

1. Thay theo thứ tự 1 → 2 → 3 → 4. Giữ nguyên config.php, storage, database và WordPress. Cron trên hosting giữ như cũ (mỗi phút).
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại bấm ↻ (hoặc đóng app rồi mở lại); máy tính Ctrl + F5. Góc menu hiện **v1.3.44 · 02/11/2026**.
4. Thử: Chấm công thấy 3 nút Ca 1 / Ca 2 / Làm cả ngày, chưa chọn sẵn; Việc thấy nút 🗑 Xóa; Studio → Luật Studio thấy khung mức trừ KPI; Event log → Ghi log → chọn nhân viên + luật thấy ô Lần mắc lỗi; Lịch → Thêm lịch → Giờ lẻ khác thấy ô Giờ : Phút 24h.
5. **Dán lại Apps Script:** mở Google Sheet lịch chụp → **Tiện ích mở rộng → Apps Script** → mở file script đang dùng → bấm vào khung code, **Ctrl + A** rồi **Delete** → mở `AnhGauSync.txt` bằng Notepad, **Ctrl + A**, **Ctrl + C** → dán vào Apps Script (**Ctrl + V**) → **Ctrl + S** để lưu. Không cần cài lại kết nối, không cần bật lại tự đồng bộ. Quay lại Sheet → menu **Anh Gấu Studio → Đồng bộ ngay** để chạy thử (góc dưới hiện "Gửi … lịch").

## Đã thử trên bản dev

- Kiểm thử máy chủ mới đạt: KPI theo lần + luật nặng + khóa chấm ra theo hạn + Xóa việc + chỉ Nhân viên 48 mục (nhân viên không xóa được việc; việc đã xóa không còn trong danh sách, không tính việc chờ, không khóa chấm ra; xóa được việc đã hủy); Telegram `/log` 16 mục (tên trước, kiểu cũ, lần 1 → 2 → 3 → vẫn 300, đếm riêng từng người và từng luật, log đã hủy không đếm, luật nặng giữ điểm, không ghi cho Quản lý); lịch mất dòng trên Sheet 9 mục (ẩn đúng lịch mất dòng, giữ lịch đang chờ ghi Sheet, hết trùng trên Lịch chụp, tab không gửi thì không đụng, dòng quay lại thì hiện lại).
- Kiểm thử cũ chạy lại đạt: Biên bản họp 47, xin từ chối việc 33, theo dõi hạn 43, đăng ký ca 23, đi muộn 35, về sớm / làm tiếp 32, quy tắc 32, tiền lịch 17, tóm tắt lịch 13, lịch khách 21, có mặt tính hoa hồng 7, make up 10, công theo khách 5, sửa log cũ 7, nâng cấp giữ dữ liệu 10. Apps Script: 12 + 19 + 28 mục đạt; bộ hai chiều 96/97 (1 mục lỗi sẵn từ bản trước do phụ thuộc tháng hiện tại, không liên quan).
- Giao diện điện thoại 390px và máy tính 1280px, không lỗi JS: khung mức trừ KPI ở Luật Studio; form Event log gợi ý Lần 1 và ẩn ô Lần khi chọn luật nặng; danh sách chọn người chỉ còn Nhân viên; Chấm công của nhân viên hiện việc hạn hôm nay, không hiện việc hạn 2 ngày nữa; 2 lịch cùng giờ có viền đỏ "Trùng giờ"; Giờ lẻ khác chọn 20:00 báo "20:00–21:00 còn trống"; Biên bản họp giờ 24h; Chấm công mặc định chưa chọn ca (nút ghi "Chọn ca để chấm công"), bấm Ca 2 lúc 16:00 thì chấm vào được, bấm Làm cả ngày sau 14:00 hiện thông báo lý do; mỗi thẻ việc có nút Xóa kèm hộp xác nhận. Dữ liệu thử đã xóa.
