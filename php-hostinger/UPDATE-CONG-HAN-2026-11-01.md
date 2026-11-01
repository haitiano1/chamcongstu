# Cập nhật 01/11/2026 (lần 5) — Công theo khách cả studio, Theo dõi hạn việc, Xóa việc hằng ngày, /log gõ ngược

Gói này **cộng dồn** mọi gói hôm nay (web v1.3.34 → v1.3.40) và script Google Sheet. Chưa upload gói nào hôm nay thì **chỉ cần làm theo gói này**.

## Thay đổi mới (v1.3.40)

### Công: tính theo khách của cả studio

- Trước: từ 01/11, ca của nhân viên mà **không có khách giao cho chính người đó** thì 0 công. Sheet không có cột Thợ nên mọi lịch gán Huy - Cường, người khác (VD Tín) luôn 0 công dù đi làm.
- Nay: ca nào **studio có khách** (bất kể ai chụp) thì mọi nhân viên chấm công đủ ca đó đều được tính công. Chỉ ca **cả studio không có khách nào** mới là "ca không khách" (0 công), cuối tháng Admin tự nhập thưởng khoản **Ca không có khách** như cũ.
- VD Tín lương 3.000.000đ, tháng 11 có 30 ngày, hôm nay làm 2 ca có khách → 1 công → lương theo công **100.000đ**.
- Bảng lương tự tính lại ngay khi mở (không cần sửa dữ liệu). Hoa hồng lịch khách vẫn chỉ tính cho thợ được giao (Huy, Cường).

### Việc: Theo dõi hạn sau khi duyệt / không duyệt từ chối

- Admin / Quản lý duyệt hoặc không duyệt **Xin từ chối** xong, việc đó hiện ở khung xanh **Theo dõi hạn sau yêu cầu từ chối** (mục Việc, dưới khung chờ duyệt).
- Mỗi việc ghi: kết quả duyệt (người xin, người duyệt, giờ, ghi chú), **còn chờ ai** báo xong (Nhắc nhở: bấm Đã xem), hạn hiện tại (đỏ khi quá hạn).
- Chọn hạn mới → **Lưu hạn**. **Bỏ hạn** để xóa hạn. Lịch sử của việc ghi "Đổi hạn" cũ → mới.
- Khung tự ẩn khi người còn lại báo xong (Nhắc nhở: đã xem), khi việc bị hủy, hoặc việc hằng ngày đã qua ngày. Duyệt mà không còn ai nhận thì việc tự hủy, không hiện.
- **Nhắc nhở cũng có hạn** (hạn đọc): quá hạn mà chưa xem thì hiện "Quá hạn". Sửa Nhắc nhở đã có hạn thì thấy ô hạn.
- Chỉ Admin / Quản lý thấy và chỉnh hạn.

### Việc hằng ngày: nút Xóa

- Mỗi việc hằng ngày có thêm nút đỏ **Xóa** (cạnh Sửa, Ngừng). Bấm → xác nhận → xóa hẳn khỏi danh sách, từ mai không giao nữa.
- Việc hôm nay chưa xong của mẫu đó bị hủy (ghi "Đã xóa việc hằng ngày"), nên không còn chặn chấm ra Ca 2. Việc hôm nay đã xong và các ngày trước vẫn giữ trong lịch sử.
- Muốn tạm dừng rồi bật lại sau thì vẫn dùng **Ngừng**.

### Telegram /log: gõ tên trước cũng được

- Cú pháp đúng: `/log nội dung | tên | mã luật hoặc điểm` (phần 3 không bắt buộc). VD `/log Đã họp, nhắc Huy lo thiết bị studio | Huy`.
- Tin bị báo "Không tìm thấy nhân viên "Đã họp"" vì gõ ngược `/log Huy | Đã họp…` (kiểu của /task là tên trước). Nay bot **tự nhận ra gõ ngược** và ghi đúng: nội dung là phần không phải tên.
- Đổi email / mật khẩu đăng nhập web **không ảnh hưởng** Telegram đã liên kết (bot nhận theo tài khoản Telegram, không theo email). Liên kết chỉ mất khi bấm Ngắt liên kết trên web, gõ /stop với bot hoặc đổi bot.

## Thay đổi trước đó hôm nay (v1.3.34 → v1.3.39)

### Định dạng Sheet lịch chụp (không cần cột Thợ)

- Sheet hiện tại của studio đã đúng định dạng: **Ngày · Time · Tên khách · SĐT · Gói · Đặc Biệt · Phụ thu · Makeup · Tip · Ghi chú · Trạng thái**. Không cần cột Thợ: lịch nào cũng mặc định Huy chính - Cường phụ.
- Chỉ thêm (không bắt buộc) cột **Thứ** ngay bên phải cột Ngày, script tự điền T2 … CN.
- Giữ đúng chữ tiêu đề ở dòng tiêu đề. Script tự thêm cột ẩn `_AG_BOOKING_ID` ở cuối để giữ mã lịch: không sửa, không xóa cột này.
- Form Thêm / Sửa lịch trên web bỏ câu "Đổi thợ thì sửa trên Google Sheet", chỉ ghi "Thợ chụp: Huy - Cường (mặc định)".
- Sheet mẫu `Mau-lich-chup-AnhGau.xlsx` làm lại đúng thứ tự cột trên, bỏ cột Thợ chính / Thợ phụ.

### Phụ thu, Tip ở Sửa lịch

- Lịch chụp → bấm thẻ lịch → **Sửa lịch** có thêm mục **4. Sau khi chụp** với 2 ô **Phụ thu** và **Tip**. Thêm lịch thì không có, vì chụp xong mới biết.
- Ghi số tiền: `100000`, `100.000` hoặc `100k`. Để trống là không có; xóa số đi rồi Lưu là xóa.
- Bấm **Lưu thay đổi**: web lưu ngay (popup lịch hiện Phụ thu, Tip), khoảng 1 phút sau script ghi vào cột **Phụ thu** và **Tip** trên Google Sheet, dạng số để cộng tổng được.
  - Ghi vào cột Phụ thu và Tip đang có trên Sheet (tìm theo tên tiêu đề). Sheet nào chưa có cột thì script tự thêm ở cuối bảng.
  - Chỉ ghi 2 ô này, các ô khác trên dòng giữ nguyên. Lịch đã qua cũng sửa được. Chỉ sửa Phụ thu / Tip thì không bị báo trùng giờ.
  - Gõ thẳng trên Sheet cũng được: web đọc về theo cột Phụ thu / Tip.
- Phiếu lương: khoản Tips vẫn nhập tay như cũ (số Tip ở lịch chưa tự cộng vào lương).

### Thẻ lịch chụp: giờ in đậm, hiện lựa chọn Make up (từ v1.3.37)

- **Giờ chụp in đậm, to hơn** (điện thoại 16px, máy tính 14px) cho nhân viên dễ thấy.
- Sau chữ Ca 1 / Ca 2 hiện đúng lựa chọn Make up: **Ko MK**, **Nam MK**, **Nữ MK**, **Làm tóc**. VD `09:00 - Chị Lan - Gói 699k - Ca 1 - Nữ MK`.
- Lấy theo lựa chọn khi Thêm lịch / Sửa lịch trên web, hoặc ô Makeup trên Sheet. Ô Makeup trống hoặc ghi "Không" thì hiện Ko MK.
- Sheet không có cột Makeup thì đọc dòng "Make up: …" trong Ghi chú. Lịch vừa thêm trên web chưa kịp lên Sheet vẫn hiện đúng.
- Ô Makeup trên Sheet ghi nhiều lựa chọn (VD "Nữ, Làm Tóc") thì hiện "Nữ MK, Làm tóc". Ghi chữ khác thì hiện nguyên chữ đó.

### A. Về khi còn khách / Tôi đổi ý, làm tiếp (từ v1.3.35)

- **Cảnh báo khi chấm ra mà còn khách**: bấm chấm ra mà sau giờ đó còn khách được giao cho mình thì app hiện danh sách khách (giờ, tên, gói) và báo "Chấm ra bây giờ thì các khách này không tính hoa hồng cho bạn". Có 2 nút **Ở lại làm tiếp** / **Vẫn chấm ra**. Ra Ca 1 chỉ xét khách trước 14:00 (trừ khi làm cả ngày); ra Ca 2 xét tới hết ngày, kể cả khách 19h.
- **Tôi đổi ý, làm tiếp**: đã chấm ra mà quay lại làm thì ở trang Chấm công có nút xanh **Tôi đổi ý, làm tiếp Ca …**. Bấm thì chấm vào lại cùng ca, vẫn chụp mặt và GPS như thường. Nút có tới hết giờ ca (Ca 1 trước 14:00, Ca 2 trước 19:00); sau 19:00 dùng **Chấm công OT** như cũ.
  - **Công**: ca đó vẫn tính 1 ca, không cộng thêm công dù chấm vào/ra nhiều lần.
  - **Hoa hồng**: khách chụp trong lúc có mặt (đoạn làm tiếp) được tính.
  - **Đi muộn**: chỉ xét lượt vào đầu tiên của ca, lượt vào lại không tính muộn.
  - **Làm cả ngày**: ra Ca 1 rồi làm tiếp thì 14:00 vẫn tự chuyển sang Ca 2.
- **Telegram báo Admin/Quản lý**: khi nhân viên tự chấm ra mà còn khách ("Về khi còn khách", kèm danh sách khách, nhắc kiểm tra người thay) và khi nhân viên chấm vào lại ("Quay lại làm tiếp").
- **Yêu cầu bổ sung công** dùng được cho trường hợp quên bấm Làm tiếp: gửi 2 yêu cầu cùng ca, **Chấm vào** lúc quay lại và **Chấm ra** lúc về. Quản lý duyệt lượt vào trước rồi mới duyệt lượt ra (duyệt ngược thì app báo sai thứ tự). **Chấm hộ** của Quản lý cũng thêm được đoạn vào lại.
- **Ca của Cường hôm qua** (đã xảy ra): sau khi cập nhật gói này, Cường gửi 2 Yêu cầu bổ sung công **Ca 2**: **Chấm vào** lúc quay lại (trước 19:00, VD 18:00) và **Chấm ra** lúc về thật. Quản lý duyệt lượt vào trước rồi lượt ra → khách 18h và 19h đều tính hoa hồng, Ca 2 vẫn tính 1 ca. Không cần gửi thêm OT.

### B. Màu thẻ lịch chụp (từ v1.3.35)

- Mỗi khách trong ngày một màu, xen kẽ 8 màu (xanh dương, xanh lá, cam, tím, xanh ngọc, hồng, vàng, chàm) theo thứ tự giờ, nên 2 thẻ cạnh nhau luôn khác màu. Lịch đã hủy vẫn màu xám, gạch ngang. Áp dụng cho cả chế độ Ngày, Tuần, Tháng.
- Không còn tô màu theo nhân viên (trước đây mọi lịch Huy - Cường cùng một màu nên khó nhìn).

### C. Đi muộn (từ v1.3.34)

**Nội quy đi muộn (DM-01)**, đã ghi sẵn trong Luật Studio:
- Mốc đến của mỗi ca là giờ **khách đầu tiên được giao** cho người đó trong ca, không phải 9:00 / 14:00. Checkin **quá 30 phút** sau mốc là đi muộn. VD khách 9:00: đến 9:30 vẫn đúng giờ, sau 9:30 là muộn. Ca không có khách được giao thì không tính muộn.
- Đếm theo từng tháng, sang tháng mới đếm lại:
  - **Lần 1**: nhắc nhở, miễn trừ điểm
  - **Lần 2**: Lỗi, trừ **20** điểm KPI
  - **Lần 3**: Cảnh cáo, trừ **100** điểm KPI
  - **Từ lần 4**: Kỷ luật, trừ **300** điểm KPI mỗi lần
- **Từ lần 2 hệ thống tự ghi Event log** (Đã xác nhận, trừ điểm ngay, ghi "Hệ thống tự ghi"). Có lý do chính đáng thì Quản lý/Admin **hủy hoặc xóa** log đó; lần đó không tính vào số lần, hệ thống không ghi lại.
- **Mỗi lần đi muộn, Telegram báo** cho người đi muộn, Quản lý và Admin (khoảng 1 phút sau khi checkin). Tin nhắn ghi: muộn bao nhiêu phút, ca nào, ngày nào, giờ khách đầu tiên, hạn checkin, giờ checkin, **lần thứ mấy trong tháng**, mức phạt lần này, mức phạt **lần tới** và toàn bộ nội quy.
- **Nhãn đỏ "Muộn X phút · Lần N trong tháng · trừ … điểm KPI / miễn trừ điểm"** trên danh sách chấm công (Lịch sử của nhân viên và Lịch sử chấm công nhân viên của Admin/Quản lý), và dưới "Giờ vào đầu tiên" ở Chấm công hôm nay. Bấm nhãn để xem chi tiết và nội quy.
- Đối soát chấm công (Lịch chụp) dùng cùng cách đếm, có ghi nội quy ở đầu mục.

### D. Xin từ chối Task / Nhắc nhở (từ v1.3.34)

- Ở mục **Việc**, mỗi Task / Nhắc nhở của mình có nút **Xin từ chối** → ghi **Lý do / ý kiến** → Gửi. Admin và Quản lý nhận tin Telegram.
- Admin / Quản lý thấy khung **Yêu cầu từ chối chờ duyệt** ở đầu mục Việc, đọc lý do rồi bấm:
  - **Duyệt**: gỡ người đó khỏi việc, không cần làm nữa. Việc không còn ai nhận thì tự hủy.
  - **Không duyệt** (phải ghi lý do): việc **vẫn treo** cho người đó tới khi báo xong. Nhắc nhở thì quay lại "Chưa xem". Thẻ việc hiện lý do không duyệt.
  - Người gửi nhận Telegram báo kết quả. Quản lý không tự duyệt yêu cầu của chính mình.
- Nhân viên xem lại ở khung **Yêu cầu từ chối của bạn** (14 ngày gần nhất); đang chờ thì **Rút yêu cầu** được. Báo xong việc trong lúc chờ thì yêu cầu tự rút.
- Mọi bước ghi vào Lịch sử thay đổi của việc.

### E. Google Sheet: cột Thứ

- Sheet có thể có thêm cột **Thứ** (đặt bên phải cột Ngày, tiêu đề đúng chữ `Thứ`).
- **Không cần gõ thứ**: mỗi lần đồng bộ (khoảng 1 phút), script tự ghi thứ cho mọi dòng có Ngày, và tự sửa nếu thứ ghi sai hoặc ngày bị đổi.
  - Viết tắt: `T2` `T3` `T4` `T5` `T6` `T7` `CN` (chủ dự án chốt). Ô đã ghi kiểu dài (`Thứ 5`, `Chủ nhật`) cũng được đổi sang kiểu tắt.
  - Ô Thứ ghi chữ khác (không phải thứ) thì script để nguyên.
- Lịch thêm / sửa / xóa trên web: ô Thứ đi cùng ô Ngày.
  - Khách sớm nhất ngày thì ô Ngày và ô Thứ giữ ở dòng đầu.
  - Ô gộp theo ngày thì giãn hoặc gộp lại cả hai cột.
  - Xóa khách đầu ngày thì ô Ngày và ô Thứ chuyển xuống khách kế tiếp.
  - Đổi ngày thì dòng mới có thứ mới.
- Tab tháng mới (VD Tháng 12) script nhân bản từ tab tháng trước nên tự có cột Thứ.
- Web không lấy cột Thứ vào popup lịch, vì đã có ngày.
- Sheet mẫu `Mau-lich-chup-AnhGau.xlsx` đã có cột Thứ.

Database tự thêm 2 bảng mới (`late_checkins`, `task_declines`) và 2 cột Phụ thu, Tip cho lịch chụp khi mở app lần đầu, không đổi dữ liệu cũ.

## Cập nhật

### Bước 1. Web (hosting)

Giải nén từng ZIP trên máy, upload **các file bên trong** vào đúng thư mục, chọn Replace. Không upload nguyên thư mục.

| Gói | Thư mục trên hosting |
| --- | --- |
| 1-private-lib.zip | nhansu-private/lib (12 file .php) |
| 2-private-bin.zip | nhansu-private/bin (cron.php) |
| 3-public-assets.zip | public_html/nhansu/assets (toàn bộ file trong gói, gồm thư mục vendor) |
| 4-public-root.zip | public_html/nhansu (api.php, index.php, kiemtra-capnhat.php) |

1. Thay theo thứ tự 1 → 2 → 3 → 4. Giữ nguyên config.php, storage, database và WordPress. Cron giữ như cũ (mỗi phút).
2. Mở https://anhgaustudio.com/nhansu/kiemtra-capnhat.php, tất cả dòng phải xanh.
3. Điện thoại bấm ↻; máy tính Ctrl + F5. Góc menu hiện **v1.3.40 · 01/11/2026**.

### Bước 2. Script Google Sheet

Đã dán script của gói lần 4 (Phụ thu, Tip, cột Thứ) thì **bỏ qua bước này** (script không đổi). Chưa dán thì:

1. Google Sheet lịch → **Tiện ích mở rộng → Apps Script** → mở file script, **xóa hết nội dung**, dán toàn bộ `AnhGauSync.gs` trong gói này → **Lưu** (Ctrl + S). Kết nối và tự đồng bộ giữ nguyên.
2. (Không bắt buộc) Cột Thứ: chuột phải chữ cột của cột **Ngày** → **Chèn 1 cột bên phải** → gõ tiêu đề **Thứ** → menu **Anh Gấu Studio → Đồng bộ ngay**.

### Thử

- Công & lương tháng 11 → Tín: hôm nay làm 2 ca có khách thì hiện **1 công**, lương theo công 100.000đ, không còn chữ "2 ca không khách".
- Mục Việc: không duyệt một yêu cầu từ chối → việc hiện ở khung **Theo dõi hạn**, đổi hạn → Lưu hạn.
- Việc hằng ngày → nút **Xóa**.
- Telegram: `/log Huy | Thử ghi log gõ ngược` → bot báo ✅ Đã ghi Event log (xóa log thử trên web sau đó).

## Đã thử trên bản dev

- Công theo khách cả studio, 5 kiểm thử máy chủ đạt (trong giao dịch rồi hoàn tác, lương 3.000.000đ): studio không có khách → 0 công, 2 ca không khách; khách ca 1 do người khác chụp → 0,5 công = 50.000đ, thợ chụp cũng 0,5 công; thêm khách ca 2 chưa gán thợ → 1 công = **100.000đ**; lịch hủy không tính là có khách.
- Theo dõi hạn + Xóa việc hằng ngày, 33 kiểm thử máy chủ đạt: đang chờ duyệt chưa hiện; không duyệt → hiện, còn chờ đúng người; nhân viên không chỉnh hạn được; đặt / đổi / bỏ hạn, phiên bản cũ bị chặn, hạn đã qua bị chặn, lịch sử Đổi hạn trước/sau; báo xong → ẩn; duyệt gỡ người, người còn lại xong → ẩn; việc tự hủy không hiện; Nhắc nhở đặt hạn, đã xem → ẩn; hủy việc → ẩn, không chỉnh hạn việc đã hủy; nhân viên không nhận danh sách. Xóa việc hằng ngày: nhân viên không xóa được, phiên bản cũ bị chặn, việc hôm nay chưa xong bị hủy, đã xong và ngày trước giữ nguyên, ngày mai không tạo lại, lịch sử ghi Xóa.
- Telegram /log, 6 kiểm thử đạt (Telegram giả lập): đúng thứ tự; gõ ngược `tên | nội dung` ghi đúng người và nội dung; gõ ngược kèm điểm 20; cả hai phần không phải tên vẫn báo lỗi như cũ; `all | nội dung` cũng được.
- Giao diện điện thoại 390px (tài khoản Quản lý test, dữ liệu thử đã xóa): khung Theo dõi hạn có kết quả duyệt, người còn chờ, ô hạn, Lưu hạn, Bỏ hạn; việc hằng ngày có nút Xóa đỏ; không lỗi JS.
- Chạy lại kiểm thử cũ: task-decline 32, rules 32, booking-summary 13, booking-presence 7, resume-early 32, late-checkin 35, customer-schedule 21, availability-telegram 20, legacy-edit 7, upgrade-preservation 10, booking-money 17, booking-makeup 10 đạt.

### Kiểm thử của các gói trước hôm nay

- Sheet mẫu đúng bố cục studio (không cột Thợ) chạy qua script: đọc đủ 8 lịch, đúng ngày giờ, gói, Phụ thu 100000, Tip 50000, dòng Hủy; thợ để trống → web gán Huy - Cường mặc định; cột Thứ tự điền T5, T6.
- Phụ thu, Tip, 17 kiểm thử máy chủ đạt (trong giao dịch rồi hoàn tác): form điền sẵn; chỉ đổi 2 ô thì việc ghi Sheet chỉ gồm 2 ô, gửi đúng số cho script; popup hiện ngay; không đổi thì không ghi; bản web cũ không gửi 2 ô thì giữ nguyên; chữ không phải số tiền bị chặn; xóa được; lịch chồng giờ vẫn nhập được; lịch chưa đồng bộ vẫn lưu; Sheet cũ không đè lúc đang chờ ghi; Sheet không có cột / script cũ thì giữ số web; sửa trên Sheet thì web cập nhật; đổi gói không mất số.
- Script Sheet (Sheet giả lập) 19 kiểm thử đạt: đọc cột Tip có sẵn, chưa có cột Phụ thu thì không gửi; nhập Phụ thu 100k / Tip 50.000 → ghi số 100000 / 50000 đúng định dạng cột, thêm cột Phụ thu ở cuối, ô khác giữ nguyên; web đọc lại đúng; xóa Tip; đổi giờ chuyển dòng thì Tip đi theo; đổi giờ và nhập Tip cùng lúc ghi đúng dòng mới.
- Giao diện điện thoại 390px (tài khoản Quản lý test, dữ liệu thử đã xóa): Sửa lịch có mục 4 Sau khi chụp với 2 ô; Lưu thay đổi → web lưu Phụ thu 100k, Tip 50000, việc ghi Sheet chỉ gồm 2 ô.
- Make up trên thẻ, 10 kiểm thử máy chủ đạt: ô Makeup Nữ → Nữ MK, Làm Tóc → Làm tóc; ô trống, ô ghi "Không" → Ko MK; lịch web chưa đồng bộ ghi chú "Make up: Nam" → Nam MK; chọn Không → Ko MK; Sheet không có cột Makeup, ghi chú "Make up: Nữ" → Nữ MK; "Nữ, Làm Tóc" → "Nữ MK, Làm tóc"; chữ khác hiện nguyên; ghi chú nhắc "makeup" lẫn trong câu → Ko MK. Điện thoại 390px và máy tính 1300px (dữ liệu thử đã xóa): giờ in đậm to hơn, nhãn Make up đúng từng thẻ, không lỗi JS.
- Về khi còn khách / Làm tiếp, 32 kiểm thử máy chủ đạt (trong giao dịch rồi hoàn tác, Telegram giả lập): không bấm Làm tiếp thì vẫn chặn "Bạn đã hoàn tất ca này"; bấm thì vào lại được trong giờ ca, hết giờ Ca 2 báo dùng OT, hết giờ Ca 1 báo chấm Ca 2; vào lại xong thì lượt kế là chấm ra; 2 đoạn trong cùng ca vẫn 1 ca (0,5 công), giờ làm cộng 2 đoạn; có mặt lúc khách 18h, 19h (đoạn sau), vắng đoạn giữa; chấm hộ thêm đoạn thứ hai; bổ sung công vào lại + ra (duyệt ra trước báo sai thứ tự, gửi trùng lượt vào hoặc chen giữa lịch sử bị chặn); làm cả ngày ra Ca 1 rồi làm tiếp vẫn tự sang Ca 2, ra Ca 1 không làm tiếp thì không sang; khách còn lại tính đúng theo ca; Telegram báo Admin + Quản lý (không báo nhân viên) khi về còn khách 18:00, 19:00, báo quay lại làm tiếp, không gửi lặp, vào lại ngay thì không báo về sớm, lượt cũ hơn 3 tiếng / chấm hộ / không còn khách thì không báo.
- Giao diện điện thoại 390px (tài khoản nhân viên test, dữ liệu thử đã xóa): popup "Bạn còn 2 khách sau giờ này" có giờ, tên, gói và 2 nút; chấm ra xong hiện nút xanh "Tôi đổi ý, làm tiếp Ca 1" và popup xác nhận; Lịch chụp điện thoại 390px và máy tính 1300px: mỗi thẻ một màu, thẻ hủy xám; không lỗi JS.
- Đi muộn, 35 kiểm thử máy chủ đạt (trong giao dịch rồi hoàn tác, Telegram giả lập): khách 9:00 vào 9:56 → muộn 56 phút, lần 1, không ghi log, báo nhân viên + Quản lý + Admin, không báo nhân viên khác; khách 14:00 vào 14:30 không muộn; ca không có khách không tính; lần 2 tự ghi log 20 điểm (Đã xác nhận, luật DM-01, gắn Đối soát); log hủy thì lần sau vẫn là lần 2; lần 3 = 100, lần 4, 5 = 300; tổng KPI đúng; log bị xóa không tạo lại và không đếm; chạy lại cron không gửi lặp; chưa cấu hình Telegram vẫn tự ghi log; luật DM-01 đã xóa không tạo lại; Đối soát đếm giống.
- Xin từ chối, 32 kiểm thử máy chủ đạt: lý do ngắn bị chặn; không gửi trùng; người không nhận việc không gửi được; nhân viên không duyệt được; không duyệt phải có lý do; không duyệt thì việc vẫn treo; duyệt thì gỡ người, việc một người nhận tự hủy, việc nhiều người vẫn mở cho người khác; Nhắc nhở không duyệt quay lại Chưa xem; rút yêu cầu (chỉ người gửi); báo xong tự rút; Quản lý không tự duyệt, Admin duyệt được; việc đã hủy không còn trong danh sách chờ; Telegram báo Admin/Quản lý (trừ người gửi) và báo kết quả cho người gửi.
- Giao diện điện thoại 390px và máy tính 1300px (tài khoản test, dữ liệu thử đã xóa): nhãn đỏ ở Lịch sử nhân viên và Lịch sử Admin/Quản lý, popup có nội quy; mục Việc có nút Xin từ chối, khung chờ duyệt cho Quản lý, khung "Yêu cầu từ chối của bạn", lý do không duyệt trên thẻ việc; không lỗi JS.
- Kiểm thử cũ (chạy lại sau khi thêm Làm tiếp) `availability-telegram` 20, `rules` 32, `customer-schedule` 21, `booking-presence` 7, `booking-summary` 13, `legacy-edit` 7, `upgrade-preservation` 10 đạt. (`shared-calendar.php` và `telegram.php` vốn đã lỗi từ trước do test cũ chưa cập nhật theo quy tắc mới, không liên quan gói này.)
- Cột Thứ: Đã thử trên Sheet giả lập (script thật chạy trong Node), 28 kiểm thử đạt:
- Tự ghi `T5` cho 05/11; sửa `Thứ 3` sai của 09/11 thành `T2`; `Thứ 5` đúng thứ của 03/12 đổi thành `T5`, `chủ nhật` thành `CN`; dòng có ngày chưa có khách cũng ghi; chữ khác trong ô Thứ để nguyên; dòng không có ngày không ghi.
- 01/12 giữ `T3`, 02/12 ghi `T4`; đọc đúng giờ và tên khách khi có thêm cột; không gửi cột Thứ vào popup; tab không có cột Thứ thì không đổi gì.
- Lịch web 08:00 (sớm nhất ngày, ô gộp): ô Ngày và Thứ giữ dòng đầu, gộp giãn 3 dòng. Lịch web cuối ngày: gộp cả hai cột giãn 4 dòng. Ngày mới 07/11 ghi `T7`.
- Xóa khách đầu ngày (ô gộp và không gộp): khách kế tiếp nhận Ngày và Thứ, gộp lại đúng. Đổi ngày 11/11 → 12/11: dòng mới `T5`, không mang ô Thứ cũ.
- Kiểm thử script cũ: 12 kiểm thử giao thức đạt; bộ 2 chiều 96 đạt. 1 mục (tab "Tháng 5" ngoài khoảng đồng bộ) lỗi giống hệt trước khi sửa: mục đó viết cho tháng 10, sang tháng 11 thì Tháng 5 năm sau đã vào khoảng 6 tháng tới. Không liên quan cột Thứ.
