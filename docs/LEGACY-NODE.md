# Bản Node/SQLite cũ — chỉ để tham khảo

Các chức năng mới nhất chạy bằng PHP/MySQL. Quay về [README hiện tại](../README.md).

## Bản Node/SQLite cũ — tài liệu được giữ để tham khảo

Phần còn lại bên dưới mô tả bản Node trước khi chuyển sang Hostinger. Khi làm tiếp bản đang chạy trên hosting, ưu tiên tài liệu PHP và CLAUDE.md ở trên.

Website tiếng Việt cho studio, responsive cho điện thoại và máy tính. Node.js >=22.13 (khuyến nghị 24), SQLite tích hợp, không cần dịch vụ cơ sở dữ liệu ngoài.

## Chạy

```sh
npm ci
npm test
npm start
```

Máy chủ mặc định nghe cổng 3000 trên 127.0.0.1. Khi triển khai container, đặt `HOST=0.0.0.0`. Truy cập website để tạo Admin đầu tiên; đọc mã thiết lập trong `.data/setup-token` trên máy chủ và nhập vào biểu mẫu. Không chia sẻ mã này. Sau đó Admin đặt tọa độ studio, bán kính, tạo tài khoản và mức lương. Không có mật khẩu mặc định.

## Phân quyền

- Admin: toàn quyền hồ sơ nhân sự, tạo tài khoản và phân quyền Admin/Quản lý/Nhân viên, cài đặt studio, lương và báo cáo.
- Quản lý: quản lý studio; thêm/sửa/xóa/khôi phục hồ sơ Nhân viên, lương cứng và báo cáo. Với tài khoản Admin/Quản lý chỉ được xem hồ sơ, không sửa, đổi mật khẩu, phân quyền hoặc xóa.
- Nhân viên: chấm vào/ra, gửi yêu cầu bổ sung và xem lịch sử/bảng công của bản thân; không xem số tiền lương/hoa hồng.

Backend kiểm tra quyền cho từng API. Mật khẩu dùng scrypt, phiên đăng nhập dùng cookie HttpOnly, phiên có hạn 7 ngày. Chặn đăng nhập sai quá nhiều lần; mỗi lượt công cách nhau tối thiểu 30 giây.

## Công và lương

Hai ca cố định: 09:00–14:00 và 14:00–19:00, giờ Việt Nam. Chấm vào/ra riêng từng ca, kiểm tra GPS mỗi lần. Một ca hoàn tất có cặp vào/ra = 0,5 ngày công; hai ca = 1 ngày. Đi muộn/về sớm vẫn tính theo ca, không tính lương theo giờ. Ca chưa chấm ra được đánh dấu và không tự tính công. Tự checkout được đến 14:30 cho ca 1 và 19:30 cho ca 2; quá hạn cần yêu cầu bổ sung hoặc quản lý chấm hộ; không phát sinh tiền OT theo giờ. Một ca không thể chấm lại sau khi hoàn tất. Có yêu cầu bổ sung lượt công ngày cũ, quản lý xác nhận trước khi ghi nhận; không ghi đè sự kiện cũ.

Lương cứng mặc định 8.000.000đ/tháng, cấu hình riêng từng nhân viên. Lương theo công = lương cứng / 31 × ngày công, làm tròn một lần ở tổng tháng. Mẫu số luôn 31. Hoa hồng 15.000đ/khách trong 09:00–18:59, từ 19:00–23:59 là OT 30.000đ/khách. Tổng lương = lương theo công + hoa hồng. Không có lương giờ hoặc tiền OT theo giờ. Giờ làm chỉ để tham khảo.

Admin/Quản lý ghi số khách và thời điểm phục vụ (giờ Việt Nam); backend tự chọn đơn giá, không tin đơn giá do client gửi. Nhân viên chỉ xem dữ liệu bản thân. Mã yêu cầu ngăn gửi trùng do retry; các lượt nhập mới riêng biệt vẫn cần quản lý đối soát. Lưu người nhập, lúc nhập và đơn giá tại thời điểm ghi nhận. Lịch sử khách hiển thị 200 lượt gần nhất; báo cáo tháng tính toàn bộ dữ liệu theo tháng Việt Nam. Chưa có sửa/hủy khách hoặc quy trình duyệt hoa hồng.

Tháng chưa chốt dùng lương cứng đang cấu hình. Tháng đã chốt giữ nguyên mức lương, công, hoa hồng và tổng lương đã lưu; có lịch sử chốt/mở lại. Chưa có phụ cấp, thuế và khấu trừ. Dữ liệu chấm công thuộc lịch 3 ca cũ được giữ nguyên để đối soát, không tự chuyển sang công mới; báo cáo cảnh báo các ca cũ cần đối soát. Database cũ tự thêm bảng salaries/commissions, giữ nguyên bảng lương giờ cũ nhưng không dùng để tính tiền. Lịch sử cá nhân và báo cáo toàn studio có lọc theo tháng; CSV lương có lương theo công, số khách thường/OT, hoa hồng và tổng lương.

## Vị trí và triển khai

Cần HTTPS để dùng GPS trên điện thoại (localhost được trình duyệt ngoại lệ). Đặt `SECURE_COOKIES=true` khi dùng HTTPS. Reverse proxy phải bảo toàn Host, tự đặt X-Forwarded-Proto và không cho người dùng truy cập trực tiếp backend. Backend kiểm tra tọa độ mới trong 60 giây, sai số không quá min(100 m, bán kính), khoảng cách trong bán kính. Trình duyệt có thể giả GPS; đây không phải cơ chế chống gian lận tuyệt đối. Chỉ lấy vị trí khi chấm; database lưu khoảng cách và sai số, không lưu tọa độ nhân viên.

Dữ liệu nằm trong `.data/` (được Git bỏ qua). Có thể đổi qua `DATA_DIR`. Dùng ổ đĩa bền vững; hệ thống tự sao lưu SQLite nhất quán, gồm cả ảnh (xem hướng dẫn bên dưới). Không công khai dữ liệu, mã thiết lập, hoặc cơ sở dữ liệu. Font Google là tùy chọn, có font hệ thống dự phòng.

## Kiểm tra

`npm test` chạy kiểm thử khoảng cách GPS, dữ liệu vị trí, tính công/lương và HTTP tích hợp: thiết lập, đăng nhập, quyền Admin/Quản lý/Nhân viên, chặn vị trí ngoài vùng, chấm công, chống trùng và đăng xuất. Cần kiểm thử GPS trên thiết bị thật và cấu hình HTTPS trước khi vận hành. Có quy trình yêu cầu bổ sung công; chưa có tự khôi phục mật khẩu qua email. Admin có thể đặt lại mật khẩu, khóa tài khoản qua hồ sơ nhân viên; Quản lý chỉ làm được với tài khoản Nhân viên.


## Quản lý studio và bản đồ

Nhóm menu **Quản lý** chỉ hiện với Admin/Quản lý, có hai mục con: **Quản lý nhân viên** và **Quản lý studio**. Quản lý studio chỉ cấu hình địa chỉ, bản đồ, ghim và bán kính. Chấm công chỉ có ca làm, vào/ra, lịch sử cá nhân và báo cáo toàn studio theo quyền. Tạo/sửa hồ sơ và phân quyền nằm trong Quản lý nhân viên. Nhân viên không có menu hay quyền gọi API quản lý.

Trong Quản lý studio: chọn tỉnh/thành, phường/xã theo API hành chính hai cấp, nhập số nhà/tên đường, nhấn tìm kiếm, chọn kết quả. Bản đồ sẽ chuyển đến kết quả và hiện ghim cùng vòng tròn bán kính. Nhấn bản đồ hoặc kéo ghim để đặt đúng cửa studio; sau đó nhấn Lưu vị trí studio. Tọa độ ở phần nâng cao tự cập nhật theo ghim, không cần nhập tay. Đổi địa chỉ sẽ bỏ ghim cũ để tránh lưu nhầm vị trí. Chọn đơn vị hành chính chưa xác định được vị trí chính xác của studio; tìm địa chỉ cũng có thể chỉ đến đầu đường, nên luôn xác nhận ghim.

Dịch vụ bên ngoài: `provinces.open-api.vn` API v2 cho tỉnh/thành và phường/xã; `nominatim.openstreetmap.org` tìm địa chỉ Việt Nam (chỉ tìm khi bấm nút, cache và giới hạn tần suất toàn server); `tile.openstreetmap.org` cho ảnh bản đồ nền. Cần Internet và cho phép HTTPS đến các tên miền này. Leaflet được phục vụ từ dependency cục bộ; không cần API key. Với quy mô lớn cần chuyển sang nhà cung cấp bản đồ/geocoding có hạn mức phù hợp. Địa chỉ tìm kiếm gửi đến Nominatim; dữ liệu vị trí nhân viên không gửi vào tìm kiếm.

Nếu API hành chính/tìm kiếm mất kết nối, trang hiện lỗi và cho phép tải lại, lấy vị trí hiện tại hoặc chọn ghim trên bản đồ. Nếu ảnh bản đồ bị chặn, không có bản đồ nền; cần khôi phục kết nối hoặc dùng vị trí hiện tại/tọa độ đã biết. Không tự dùng tâm tỉnh làm tọa độ chấm công. GPS nhân viên vẫn có sai số riêng, bản đồ không loại bỏ sai số thiết bị.

Kiểm thử hiện tại: API bên ngoài bị chính sách mạng môi trường cloud chặn (403 CONNECT). Đã kiểm thử module provider bằng dữ liệu mẫu và kiểm thử trình duyệt bằng phản hồi API/ảnh bản đồ mô phỏng: chọn tỉnh/phường, tìm địa chỉ, đặt ghim, đổi ghim, lưu/đọc lại, bố cục mobile. Chưa xác minh dữ liệu và ảnh bản đồ trực tiếp trong mạng cloud này. Cần thử kết nối thật khi chạy local.

### Cập nhật bản tải về trên máy

Dừng máy chủ bằng Ctrl+C. Sao lưu thư mục `.data` của bản đang dùng. Chép đè các tệp mã nguồn từ bản ZIP mới vào thư mục cũ, giữ nguyên `.data`, sau đó chạy `npm ci` và `npm start`. Không thay cơ sở dữ liệu bằng dữ liệu thử nghiệm. Nhấn Ctrl+Shift+R (Mac: Cmd+Shift+R) để tải giao diện mới. Database tự thêm các cột địa chỉ, giữ nguyên người dùng và dữ liệu chấm công.

### Sửa ảnh nền bản đồ báo Access blocked / 403

Bản cũ đặt `Referrer-Policy: same-origin`, khiến trình duyệt không gửi Referer tới OpenStreetMap. Điều này không đáp ứng yêu cầu xác định nguồn trang trong tile usage policy. Bản mới dùng `strict-origin-when-cross-origin` trên HTTP response và trên ảnh bản đồ Leaflet: chỉ gửi origin website, không gửi đường dẫn/query của trang. Giữ attribution, cache trình duyệt và URL tile chính thức; không đổi hostname liên tục, giả mạo nguồn hoặc thêm cache-busting để né chặn.

Nếu ảnh nền lỗi, phần thông báo bản đồ riêng và nút tải lại sẽ hiện; ghim, tọa độ và bán kính đang chọn được giữ nguyên. Cập nhật source rồi khởi động lại Node và hard refresh trình duyệt. Trình chặn theo dõi/tiện ích quyền riêng tư hoặc mạng vẫn có thể chặn yêu cầu bên ngoài. Nếu nhà cung cấp tiếp tục từ chối, cần xử lý quyền truy cập hoặc chuyển sang nhà cung cấp có quyền sử dụng phù hợp trước khi vận hành. Đã kiểm tra bằng trình duyệt rằng request gửi đúng Referer origin, báo lỗi khi mô phỏng 403 và tải lại thành công khi nhà cung cấp phản hồi bình thường. Chưa xác minh trực tiếp việc nhà cung cấp bỏ chặn trong mạng của người dùng.

### Độ chính xác GPS khi chấm công

Lưu ghim studio chỉ xác định tâm phạm vi; mỗi lần chấm vẫn đo vị trí và độ chính xác thiết bị. Trình duyệt máy tính có thể chỉ định vị bằng IP/Wi-Fi, sai số hàng trăm mét dù tọa độ trông gần studio. Bản mới theo dõi vị trí chính xác cao tối đa 20 giây, ưu tiên kết quả tốt hơn thay vì dùng ngay lần đo đầu. Nếu chưa đạt, backend báo sai số cụ thể và mức cho phép (min(bán kính,100 m)). Không tự nới bán kính hoặc bỏ qua kiểm tra. Bật vị trí chính xác trên trình duyệt/hệ điều hành; thử điện thoại tại studio. Kiểm thử từ xa có thể dùng Chrome DevTools Sensors với tọa độ studio; vẫn phải đúng giờ ca. Lỗi chất lượng GPS khác lỗi nằm ngoài bán kính.

## Quản lý nhân viên

Menu Quản lý → Quản lý nhân viên. Danh sách có mã nhân viên tự sinh, họ tên, email, điện thoại, chức danh, ngày vào làm, vai trò, lương cứng, trạng thái. Tìm kiếm không phân biệt dấu; lọc đang làm/thử việc/nghỉ việc; lựa chọn hiện hồ sơ đã xóa. Xem/sửa mở hộp thoại dùng tốt trên mobile, gồm ngày sinh, địa chỉ liên hệ, ngân hàng, số tài khoản (lưu chuỗi để giữ số 0 đầu), chủ tài khoản và ghi chú. Không tự nhập thông tin cá nhân từ ảnh sheet; ảnh chỉ dùng tham khảo loại trường dữ liệu.

Thêm nhân viên cần tên, email đăng nhập, mật khẩu tối thiểu 10 ký tự, vai trò, trạng thái và lương cứng (mặc định 8 triệu). Chỉ Admin tạo/cập nhật vai trò Admin/Quản lý; Quản lý chỉ tạo/sửa tài khoản Nhân viên. Mật khẩu cũ không hiển thị; để trống trường mật khẩu khi sửa để giữ nguyên. Đổi mật khẩu, vai trò, chuyển nghỉ việc hoặc xóa sẽ thu hồi các phiên liên quan. Không thể tự xóa, khóa hay hạ quyền tài khoản đang sử dụng. Đổi mật khẩu của bản thân sẽ yêu cầu đăng nhập lại.

Xóa có hộp thoại xác nhận và là lưu trữ mềm: ẩn khỏi danh sách mặc định, khóa đăng nhập, giữ nguyên công/lương/hoa hồng. Bật “Hiện đã xóa” để khôi phục; hồ sơ khôi phục ở trạng thái nghỉ việc, cần chủ động sửa sang đang làm/thử việc để mở đăng nhập. Email của hồ sơ lưu trữ vẫn được giữ, không tái sử dụng để tránh nhập nhằng lịch sử. Nhân viên nghỉ việc (chưa xóa) vẫn hiện trong danh sách và có thể lọc. Báo cáo lương giữ cả nhân viên cũ để đối soát, danh sách chọn ghi khách mới chỉ hiện tài khoản đang làm/thử việc.

Lương cứng cập nhật trong hồ sơ dùng chung dữ liệu với bảng lương; không có hai mức lương riêng. Mức lương thay đổi vẫn tính lại các tháng chưa chốt theo giới hạn đã mô tả; chưa có mức lương theo thời điểm, chốt tháng, thưởng/phạt/ứng lương theo sheet cũ. Không tự thay quy tắc hoa hồng đã chốt theo số trong sheet tham khảo. Tạo/sửa/lưu trữ/khôi phục hồ sơ ghi nhật ký actor, nhân viên, hành động và thời gian trong employee_audit; chưa có màn hình tra nhật ký.

Nâng cấp SQLite tự thêm các cột hồ sơ/trạng thái và mã NVxxxx cho tài khoản cũ. Giữ nguyên ID, mật khẩu và dữ liệu lương/chấm công. Nhớ sao lưu `.data` trước khi cập nhật. API thay đổi hồ sơ cập nhật cả lương trong một transaction; lỗi không để lại hồ sơ tạo dở. Chỉ các API quản lý đã xác thực mới trả thông tin ngân hàng, không đưa dữ liệu này vào API cá nhân/thống kê công khai.

## Logo và tên website

Tiêu đề trình duyệt và thương hiệu là **Anh Gấu Studio**. Header dùng đúng ảnh `https://anhgaustudio.com/wp-content/uploads/2024/05/LOGO-AG-GOLDEN-CO-VIEN.png` do chủ studio cung cấp. Ảnh tải trực tiếp từ website studio và cần Internet. Môi trường cloud trả 403 khi tải ảnh này, vì vậy chưa lưu được bản ảnh cục bộ; nếu ảnh không tải được, header vẫn hiện tên studio làm dự phòng. Chưa xác minh hiển thị ảnh thật trên mạng của người dùng.


## Giao diện nhân viên và đăng nhập

Tab Nhân viên có bốn thẻ thống kê theo dữ liệu thật, bảng email/phòng ban/chức vụ/trạng thái, lọc phối hợp và tìm kiếm tên/mã/email/điện thoại không phân biệt dấu. Phòng ban và chức vụ nhập trong hồ sơ; bộ lọc tự lấy các giá trị đang có. Checkbox chọn từng người hoặc toàn bộ kết quả đang hiển thị. Xuất CSV tải danh sách đã chọn, hoặc toàn bộ kết quả đang lọc nếu chưa chọn; bao gồm hồ sơ và thông tin nhận lương theo quyền quản lý. CSV có BOM UTF-8 và escape ô có nguy cơ công thức. Khi mở CSV bằng Excel, đặt cột điện thoại/số tài khoản thành Text để giữ số 0 đầu. Chưa có nhập CSV/Excel.

Trang đăng nhập theo bố cục trắng/xanh, logo và tên Anh Gấu Studio, đồng hồ cập nhật theo Asia/Ho_Chi_Minh, nút hiện/ẩn mật khẩu. Chỉ hỗ trợ email đăng nhập, không có tên đăng nhập riêng. Mobile xếp thành một cột. Tài khoản do Admin/Quản lý tạo theo quyền đã mô tả; chưa có quên mật khẩu qua email. Trang khởi tạo Admin lần đầu giữ cùng bố cục và bổ sung mã thiết lập.

## Ưu tiên chấm công trên điện thoại

Với màn hình tối đa 800px, đăng nhập/mở phiên có sẵn sẽ vào Chấm công; máy tính vẫn mặc định Tổng quan. Thanh chuyển nhanh ở đầu màn hình xếp Chấm công trước, kế đến Bảng lương và Tổng quan. Trong menu mobile, Chấm công cũng đứng đầu. Thu gọn lời chào, đồng hồ và thông tin phụ để ô chọn ca và nút chấm vào/ra nằm trên màn hình đầu tiên; nút chấm cao tối thiểu 54px, ô chọn ca 48px. Các ô nhập quan trọng trên điện thoại dùng font 16px để tránh phóng to khi nhập trên iOS. Đã kiểm tra trình duyệt ở 390×844 và 360×740: mở đúng màn hình mặc định, nút công thấy ngay không phải cuộn, chuyển lương/quay lại, đúng thứ tự menu, không tràn ngang; thực hiện API chấm công thành công với vị trí giả lập tại studio trong giờ ca. Chưa thay thế việc thử GPS trên thiết bị thật.

## Chấm OT, ảnh camera và chấm công hộ

### OT độc lập

Chọn “OT · Sau 19:00” trong màn hình Chấm công, dùng nút Bắt đầu OT / Kết thúc OT. Từ 19h hệ thống ưu tiên OT nếu ca chính đã chấm ra; ca chính còn mở cần kết thúc trước. Có thể nhiều lượt OT trong một tối (mỗi lượt vào/ra cách nhau tối thiểu 30 giây). OT đang mở có thể kết thúc sau nửa đêm, giữ ngày bắt đầu, và phải kết thúc trước khi chấm ca chính tiếp theo. Giờ OT qua tháng được tách đúng theo tháng Việt Nam. Không tự chấm ra, không tự kết thúc OT hoặc ghi đè các lượt cũ.

Trước khi bắt đầu OT, nhân viên thấy cảnh báo chấm đúng thực tế, phải tích xác nhận và nhấn “Tôi đang OT – bắt đầu”. Hủy không tạo sự kiện. API cũng yêu cầu xác nhận và lưu thời điểm xác nhận máy chủ; chưa có mức phạt/trừ tiền tự động. Lịch sử OT ghi giờ vào/ra, đang OT/đã kết thúc, thời lượng, xác nhận và ảnh nếu có. Báo cáo lương/CSV tách lượt/giờ OT và OT chưa kết thúc; OT không cộng công, ngày công hoặc lương cứng.

Khách OT mới ghi nhận phải có thời điểm nằm trong lượt OT đã chấm (đã kết thúc hoặc đang mở) để nhận 30.000đ/khách. OT qua nửa đêm vẫn được x2 khi có lượt OT bao phủ; ngoài OT và ngoài giờ làm không được tự ghi. Các khoản hoa hồng lịch sử giữ nguyên đơn giá đã lưu, không tự hồi tố hoặc hủy. Giờ OT không có tiền theo giờ.

### Ảnh chấm công tự động

Mọi lần tự chấm vào/ra, cả ca chính và OT, đều phải mở camera, chụp ảnh rồi nhấn Dùng ảnh & chấm công; sau đó lấy GPS mới và gửi. Camera ưu tiên phía trước, không thu âm, có chụp lại và hủy; các track camera được dừng khi đóng hoặc dùng ảnh. Cần HTTPS hoặc localhost và quyền camera/vị trí trên thiết bị. Chưa hỗ trợ chọn ảnh thư viện qua giao diện. Đây là ảnh chứng từ để đối soát, không nhận diện danh tính, phát hiện khuôn mặt hoặc kiểm tra người thật tự động; không bảo đảm chống giả mạo ở client.

Không có bước duyệt: ảnh, thời gian máy chủ, vị trí hợp lệ và sự kiện được lưu cùng lúc. Nếu hủy/chưa có ảnh hoặc GPS không hợp lệ thì không tạo lượt chấm. Backend kiểm tra JPEG, kích thước/dung lượng, giải mã và mã hóa lại để loại metadata. Ảnh lưu BLOB trong SQLite riêng `.data/studio.sqlite`, không nằm trong public. API lịch sử chỉ trả cờ có ảnh, không trả bytes trong JSON. Endpoint ảnh yêu cầu phiên đăng nhập: nhân viên xem ảnh bản thân; Admin/Quản lý xem ảnh toàn studio. Phản hồi ảnh không cache, không tạo link công khai.

Trong Chấm công, từng dòng lịch sử có Xem ảnh; Admin/Quản lý có thư viện ảnh theo ngày (60 ảnh/trang), tên nhân viên, giờ, loại vào/ra và ca/OT; bấm để xem lớn. Dữ liệu cũ chưa có ảnh vẫn được giữ và gắn nhãn dữ liệu cũ. Ảnh chưa có cơ chế tự xóa/lưu trữ ngoài; dung lượng database/sao lưu tăng theo số lượt chấm, cần quản lý thời gian lưu trước khi vận hành lâu dài.

### Chấm hộ ngoại lệ

Admin/Quản lý vào Chấm công → Lịch sử toàn studio → Chấm công hộ. Chọn nhân viên đang làm/thử việc, ca/OT, vào/ra, thời điểm làm việc theo giờ Việt Nam và lý do 10–500 ký tự. Không cho ghi giờ tương lai, lặp ca đã hoàn tất hoặc chấm ra khi chưa vào. Quản lý chỉ chấm hộ tài khoản Nhân viên; Admin được chấm hộ các tài khoản theo quyền. Chấm hộ OT vào cũng cần xác nhận người đó thực sự OT.

Lượt chấm hộ không yêu cầu GPS hoặc camera, được tính công theo cùng quy tắc ca, nhưng ghi rõ source=manual, người thực hiện, thời điểm thao tác máy chủ và lý do; vị trí/ảnh là trống, không giả ảnh hoặc tọa độ. Không cần duyệt. Chấm hộ được đánh dấu trong lịch sử cá nhân/toàn studio, báo cáo OT và CSV chấm công; không xuất hiện thành ảnh giả trong thư viện. Ghi nhân sự nào được chấm hộ vào employee_audit. API tự chấm không thể dùng source=manual để bỏ qua GPS/camera.

Để giữ lịch sử nhất quán, chấm bổ sung theo thứ tự thời gian trong cùng ca; OT phải theo thứ tự thời gian toàn bộ sự kiện. Không hỗ trợ sửa/xóa sự kiện hoặc chèn lại OT trước các sự kiện mới hơn. Cần quy trình điều chỉnh công riêng nếu đã ghi sai.

### Kiểm thử và cập nhật

`npm test`: 27 kiểm thử, gồm quyền xem ảnh, ảnh bắt buộc, chấm hộ/nguồn/người thực hiện, bảo vệ vai trò, OT/hoa hồng và nâng cấp dữ liệu cũ. Đã chạy E2E trên trình duyệt mobile với đồng hồ, camera và GPS giả lập: cảnh báo OT/hủy/tích xác nhận, camera hủy/chụp lại/gửi, tự chấm vào/ra không chờ duyệt, dừng camera, xem ảnh, chấm hộ có lý do và thư viện quản lý. Không thay thế thử camera/GPS trên điện thoại thật qua HTTPS.

Bản mới thêm dependency `jpeg-js`; khi cập nhật cần giữ và sao lưu `.data`, chép source mới, chạy `npm ci`, rồi `npm start` và tải lại trình duyệt. Migration thêm các cột ảnh/xác nhận OT/nguồn/người ghi/lý do, không xóa tài khoản hoặc công cũ. Không đưa các fixture đồng hồ/camera E2E vào bản phân phối.

## Quyền riêng tư lương và lịch sử theo ngày

Từ bản này, role Nhân viên chỉ có Bảng công: ngày/ca làm, công, giờ và lượt OT, thiếu chấm ra. Không hiển thị banner tổng lương tạm tính, hoa hồng, mức lương, công thức tính lương hoặc bảng khách/hoa hồng. Menu/CTA/CSV đổi thành Bảng công. API dashboard/monthly cho Nhân viên dùng danh sách trường công được phép; không gửi số tiền, đơn giá hoặc số khách dùng tính hoa hồng. API commissions trả 403 cho Nhân viên. Admin/Quản lý vẫn xem và đối soát lương/hoa hồng theo quyền. Các mô tả cũ về nhân viên tự xem lương trong tài liệu trước bản này không còn áp dụng; chưa có quy trình công bố phiếu lương đã chốt.

Lịch sử chấm công cá nhân và bảng công toàn studio chia nhóm theo ngày Việt Nam, có thanh phân cách Hôm nay/Hôm qua hoặc thứ/ngày, cùng số lượt. Sắp xếp theo thời điểm làm việc giảm dần, không theo ID nhập, để chấm hộ bổ sung ngày cũ không làm lẫn ngày. Lượt ra OT sau nửa đêm xuất hiện ở ngày thực sự chấm ra; báo cáo OT vẫn giữ ngày bắt đầu ca.

Đặt lại mật khẩu: Quản lý → Quản lý nhân viên → nút sửa hồ sơ → Đổi mật khẩu → Lưu hồ sơ. Không đọc lại mật khẩu cũ. Mật khẩu mới tối thiểu 10 ký tự; đổi xong thu hồi phiên cũ. Admin đặt lại cho mọi tài khoản theo quyền; Quản lý chỉ đặt lại cho Nhân viên.

Kiểm thử bản này: 28 kiểm thử đạt, có kiểm tra API không lộ tiền lương/hoa hồng và nhóm ngày đúng múi giờ. E2E mobile/desktop xác nhận nhân viên không thấy tiền, CSV chỉ có công, Quản lý còn số tiền, và lịch sử cá nhân/toàn studio có nhóm ngày.

## Bản cập nhật: chấm công mobile, bổ sung công, chốt lương và sao lưu

### Chấm công trên điện thoại

Mở vào mục Chấm công. Chọn ca 1/ca 2; nút OT tách riêng và vẫn yêu cầu xác nhận thực sự làm thêm. Trạng thái ca hiện rõ chưa vào/đang làm/đã kết thúc. Sau khi gửi thành công, màn hình xác nhận hiện giờ, ca và ảnh vừa chụp. Nếu camera bị chặn, mở quyền camera qua cài đặt trang web rồi nhấn Mở lại camera. Nếu GPS lỗi, có hướng dẫn, Lấy lại vị trí và Thử chấm lại; server vẫn kiểm tra GPS và ảnh, không bỏ qua xác minh.

Lịch sử lọc theo tháng, chia ngày, phân biệt vào/tan ca/OT/chấm hộ. Ca đang làm không bị báo thiếu; chỉ đánh dấu Thiếu chấm ra khi đã đến giờ kết thúc ca mà còn mở. Nhân viên tiếp tục không thấy số tiền lương hoặc hoa hồng.

### Yêu cầu bổ sung công

Chấm công → Yêu cầu bổ sung công → Gửi yêu cầu: chọn ca/OT, lượt vào hoặc ra, giờ làm thực tế theo Việt Nam và lý do. Mỗi yêu cầu bổ sung một lượt; nếu thiếu cả vào lẫn ra, gửi hai yêu cầu. Chưa duyệt không làm thay đổi công. Nhân viên xem trạng thái chờ/đã bổ sung/từ chối cùng ghi chú xử lý.

Admin/Quản lý xử lý ở cùng mục, kiểm tra lượt vào trước lượt ra, ghi chú khi xác nhận hoặc từ chối. Không được tự xử lý yêu cầu của mình; Quản lý chỉ xử lý tài khoản Nhân viên. Giờ trong tương lai, lượt trùng, sai thứ tự hoặc tháng đã chốt bị từ chối. Yêu cầu hợp lệ được xác nhận sẽ tạo sự kiện bổ sung mang nguồn chấm hộ, lưu người thực hiện/thời điểm/lý do, không giả GPS hoặc ảnh của nhân viên. Không có sửa/xóa sự kiện cũ.

### Chốt bảng lương

Admin/Quản lý → Bảng lương → chọn **tháng đã kết thúc** → Chốt bảng lương tháng → đối soát và xác nhận. Không cho chốt khi còn ca/OT thiếu chấm ra hoặc yêu cầu bổ sung chưa xử lý trong tháng.

Bản chốt lưu các dòng nhân sự, mức lương cứng, công, hoa hồng, tổng tiền và chi tiết đơn giá hoa hồng đã ghi nhận. API/bảng lương/CSV và tổng lương trên Tổng quan dùng bản chốt, không tính lại theo mức lương mới. Chặn thêm công, bổ sung công và hoa hồng vào tháng đã chốt. Đổi hồ sơ hoặc lương cứng cho tháng sau không làm đổi bản chốt cũ.

Chỉ Admin được Mở lại bảng lương, bắt buộc lý do từ 10 ký tự. Sau khi mở lại, dữ liệu trở về trạng thái cập nhật để đối soát và chốt lại. Các phiên bản chốt cũ và người/thời điểm/lý do mở lại vẫn giữ trong cơ sở dữ liệu; giao diện có lịch sử chốt/mở lại. Nhân viên chỉ xem công; chưa có công bố phiếu lương.

### Sao lưu và khôi phục

Máy chủ kiểm tra khi khởi động và mỗi giờ: nếu chưa có bản sao hoặc bản gần nhất đã qua 24 giờ thì tạo bản mới. Giữ 14 bản tự động/thủ công gần nhất trong `.data/backups`. Có thể đặt biến môi trường `BACKUP_DIR` đến ổ đĩa dự phòng khác. Không tự chạy khi máy chủ tắt.

Bản sao SQLite nhất quán chứa tài khoản, công, yêu cầu, lương, lịch sử chốt và ảnh; kiểm tra `integrity_check` trước khi giữ bản sao. Loại bỏ phiên đăng nhập khỏi bản sao. Chỉ Admin thấy mục **Quản lý → Quản lý studio → Sao lưu dữ liệu**, có Sao lưu ngay và Tải về. Không cần dừng web để sao lưu. Lỗi sao lưu hiện tại đây và được ghi ở cửa sổ máy chủ.

**Nên tải bản sao sang máy/ổ đĩa khác thường xuyên**: bản sao cùng ổ với dữ liệu không bảo vệ được khi ổ đó hỏng. Không chia sẻ công khai tệp sao lưu vì có thông tin cá nhân và ảnh.

Khôi phục trên máy chạy web:

1. Dừng máy chủ bằng Ctrl+C. Giữ lại thư mục `.data`.
2. Chạy `npm run restore -- "D:\sao-luu\studio-1234567890123-abcd1234.sqlite"` (thay bằng đường dẫn tệp thực tế).
3. Chạy `npm start` và đăng nhập lại.

Công cụ từ chối khi máy chủ vẫn chạy, kiểm tra tệp SQLite và bảng cần thiết, tạo bản an toàn `before-restore-*.sqlite` của dữ liệu hiện tại, rồi mới thay cơ sở dữ liệu. Bản an toàn này không bị xoay xóa cùng 14 bản thông thường. Nếu dùng `DATA_DIR` tùy chỉnh, đặt cùng biến này cho cả chạy web và lệnh khôi phục. Không chép đè cơ sở dữ liệu khi web đang chạy.

### Cập nhật và xác minh

Dừng bản cũ, sao lưu `.data`, chép mã nguồn mới **giữ nguyên `.data`**, chạy `npm ci`, `npm start`, rồi tải lại trình duyệt bằng Ctrl+Shift+R. Các bảng mới được thêm tự động, không xóa dữ liệu đang dùng.

Kiểm thử hiện tại: **31 bài đạt**. Gồm duyệt/từ chối bổ sung, phân quyền và chống tự duyệt, khóa số liệu/chặn sửa tháng chốt, phiên bản chốt/mở lại, quyền riêng tư của nhân viên, sao lưu hằng ngày/xoay bản, giữ ảnh, loại phiên đăng nhập và khôi phục khi dừng máy chủ. E2E Chromium đã chạy trên giao diện 390×844 và 360×740 với camera/GPS giả lập: nút chấm công nằm trong màn hình đầu, gửi ảnh/GPS và xem ảnh xác nhận, lỗi quyền camera/mở lại camera, GPS ngoài vùng/lấy lại/thử lại thành công, gửi và quản lý duyệt yêu cầu, chốt/mở lương, tải sao lưu; không có lỗi JavaScript. Cần thử quyền camera/GPS trên điện thoại thật qua HTTPS ở môi trường vận hành.

## Bot nhắc chấm công Telegram

Đã tích hợp liên kết riêng từng nhân viên, lịch nhắc cố định theo tuần/ngày nghỉ ngoại lệ, nhắc checkin sau 10 phút và checkout sau 5 phút, chống gửi trùng và nhật ký gửi. Mặc định tắt khi chưa có token và nhắc checkin cần đặt lịch; checkout tự nhắc theo lượt checkin thực tế, không cần bật lịch. Xem **[TELEGRAM-SETUP.md](TELEGRAM-SETUP.md)** để tạo bot, dùng ngrok/HTTPS, cấu hình `.env`, liên kết nhân viên, đặt lịch và kiểm thử từ đầu đến cuối trên Windows. `npm start`/`npm run dev` tự đọc `.env`; chỉ cần `TELEGRAM_BOT_TOKEN` và `PUBLIC_APP_URL`. Không ghi token vào mã nguồn hoặc chia sẻ `.env`.

Bot dùng polling `getUpdates` mỗi 15 giây, không cần webhook hay nhập chat_id. Nhắc gửi tin riêng, không gửi ảnh, tọa độ hay số tiền. Nhân viên phải chủ động bấm Start để nhận tin. Lịch này không thay thế lịch xếp ca xoay và chưa nhắc OT. Cần máy chủ chạy liên tục, Internet đến `api.telegram.org`, và URL HTTPS hoạt động để mở trang chấm công trên điện thoại. Khi máy chủ tắt, không có nhắc. Các bảng liên kết/lịch/lượt gửi được đưa vào sao lưu SQLite hiện có; token vẫn nằm ngoài cơ sở dữ liệu trong biến môi trường.

## Sửa lỗi gửi ảnh chấm công qua điện thoại/ngrok

Bản `2026-10-20-photo2` dùng cùng hợp đồng ảnh cho giao diện và máy chủ. Camera tự thu ảnh về tối đa 480 px, giảm chất lượng/kích thước theo dung lượng thực để ảnh JPEG không quá 180.000 byte trước khi gửi. Ảnh chưa xử lý thành công không được gửi. Máy chủ giữ giới hạn ảnh JPEG 250.000 byte và giới hạn JSON chấm công 512.000 byte (có đủ phần base64/GPS/ca); các API khác vẫn giới hạn 16.384 byte. Body được đếm theo byte và ghép đầy đủ trước khi giải mã UTF-8.

Phân biệt thông báo ảnh chưa nhận được, sai định dạng, ảnh vượt dung lượng và cả yêu cầu quá lớn; không còn dùng chung thông báo cần chụp ảnh cho lỗi dung lượng. Ảnh vẫn bắt buộc, giải mã/re-encode trên máy chủ, lưu riêng cùng lượt công; không bỏ kiểm tra GPS, giới hạn kích thước hay quyền xem ảnh.

HTML/JS/CSS ứng dụng trả `Cache-Control: no-store`; HTML và module camera/bộ nén có mã phiên bản mới để tránh lấy lại tệp cũ. Giao diện kiểm tra phiên bản máy chủ trước khi chụp/gửi. Khi chưa cùng phiên bản, trang hướng dẫn cập nhật đầy đủ thay vì gửi yêu cầu thiếu ảnh.

Cập nhật trên Windows: Ctrl+C dừng web, sao lưu `.data`, chép **toàn bộ các tệp bên trong thư mục chamcongstu của ZIP** vào `D:\stu\chamcongstu`, giữ nguyên `.data` và `.env`. Không tạo thêm thư mục lồng `chamcongstu\chamcongstu`. Chạy `npm ci` và `npm start` từ đúng thư mục có `server.js`. Giữ ngrok chạy nếu dùng; mở lại link HTTPS đang hoạt động. Trên máy tính Ctrl+Shift+R; trên điện thoại đóng tab cũ và thử một tab ẩn danh đăng nhập lại nếu còn dùng giao diện cũ. Không cần tạo lại tài khoản hoặc bot Telegram.

Đã xác minh 39 bài kiểm thử đạt, có kiểm thử 413 cho body quá lớn, ảnh thiếu có thông báo riêng, nén và dự phòng kích thước, không gửi ảnh không xử lý được. E2E Chromium mobile dùng camera/GPS giả lập: ảnh nhiễu 4K khoảng 24 MB được nén còn khoảng 59 KB, ảnh gửi nằm trong giới hạn, chấm công thành công và tải/giải mã được ảnh đã lưu; trường hợp máy chủ/giao diện lệch phiên bản bị chặn với hướng dẫn cụ thể. Chưa truy cập được máy local hoặc link ngrok thực tế của chủ studio; cần cài bản mới và thử trên điện thoại thực tế.


## Checkout tự nhắc theo ca đã checkin

Bot nhắc checkout độc lập với lịch: tài khoản đang hoạt động, đã liên kết Telegram, có lượt vào Ca 1/Ca 2 chưa chấm ra thì được nhắc lúc 14:05/19:05 (giờ Việt Nam, kiểm tra mỗi 15 giây). Không cần có dòng lịch, không cần bật lịch và không phụ thuộc thứ/ngày nghỉ ngoại lệ hoặc ca được chọn trong lịch. Đã checkout thì không nhắc; chưa checkin thì không phát sinh nhắc checkout. Các lượt checkin đang mở đã tồn tại trước khi cập nhật vẫn được xét, không cần chấm lại. Giới hạn xét đến 24 giờ sau giờ kết thúc ca, mỗi người/ca/ngày một lần gửi; không nhắc OT. Tài khoản ngừng hoạt động hoặc chưa liên kết bot không được gửi tin.

Lịch trong Quản lý nhân viên đổi tên thành Lịch nhắc checkin, chỉ dùng để xác định người chưa vào ca cần nhắc. Ngắt liên kết Telegram hoặc gửi /stop dừng mọi lời nhắc. Máy chủ cần token, URL HTTPS hợp lệ và hoạt động liên tục. Không tự tạo lượt checkout hay thay đổi công/lương.

Đã chạy 41 kiểm thử đạt, bao gồm dịch vụ bot với Telegram giả lập: không có lịch, lịch tắt, sai thứ/ngày nghỉ vẫn nhắc checkout nếu đã vào ca; hai ca có mốc giờ đúng; có lượt ra thì không nhắc; tài khoản không hoạt động/chưa liên kết không nhận; khởi động lại không gửi trùng. Không gửi tin Telegram thật trong môi trường kiểm thử.


## Phân ca bằng lịch và dữ liệu nhân viên nhập một lần

Admin/Quản lý có mục **Phân ca**, lịch tháng/tuần/2 tuần/ngày, lịch nhỏ và danh sách nhân viên đã lưu. Kéo nhân viên vào Ca 1/Ca 2 theo ngày; kéo thẻ đã phân để chuyển; nhấn thẻ để sửa/xóa. Nút Phân ca hoặc dấu + cho phép chọn nhiều người. Mobile mặc định lịch Tuần và hỗ trợ chạm chọn nhân viên rồi chạm dấu + của ca. Lịch rộng cuộn bên trong bảng, không làm tràn toàn trang. Nhân viên có Lịch làm của tôi, chỉ xem ca của mình, không xem hồ sơ người khác hoặc sửa phân ca.

Lịch phân ca là nguồn duy nhất cho nhắc checkin trên máy chủ; mục lịch nhắc cũ trở thành lối tắt tới Phân ca. API sửa lịch nhắc tuần cũ bị chặn với hướng dẫn chuyển sang Phân ca. Nếu có cấu hình nhắc tuần cũ trước khi nâng cấp, hệ thống chuyển một lần các ca hợp lệ trong 90 ngày tới vào lịch, giữ ngày nghỉ ngoại lệ, không tạo lại các ca đã xóa ở lần khởi động sau. Các cấu hình cũ vẫn giữ trong DB để đối chiếu nhưng không điều khiển bot nữa.

Ca phân theo ngày không đồng nghĩa với công đã làm. Xóa/chuyển ca điều chỉnh nhắc checkin, không sửa sự kiện chấm công hoặc lương. Checkout tiếp tục tự nhắc sau 5 phút theo ca thực tế dù không phân ca. Ngày không phân ca sẽ không có nhắc checkin. Backend kiểm tra vai trò (Quản lý chỉ sửa ca của Nhân viên), tài khoản còn hoạt động, ngày/ca hợp lệ và trùng người/ngày/ca. Mỗi thẻ có số phiên bản, sửa/xóa từ lịch cũ bị chặn để tránh ghi đè thay đổi người khác. Lưu dấu vết thêm/chuyển/xóa trong roster_audit; các bảng lịch được sao lưu cùng SQLite.

### Nhập dữ liệu riêng từ ảnh

Cơ chế nhập một lần nhận tệp JSON `employee-import.json` cạnh `server.js` khi chạy thư mục dữ liệu mặc định `.data`. Bản mã nguồn thông thường không chứa thông tin cá nhân; bản ZIP riêng kèm dữ liệu của chủ studio có thêm tệp này. Nếu dùng DATA_DIR tùy chỉnh, đặt tệp thành `pending-employees.json` bên trong thư mục đó. Không đưa tệp dữ liệu riêng vào Git hoặc chia sẻ công khai.

Khi máy chủ khởi động và đã có Admin (hoặc ngay sau khi thiết lập Admin đầu tiên), các hồ sơ hợp lệ được tạo với role Nhân viên, trạng thái đang làm và lương cứng mặc định 8 triệu nếu không cung cấp mức khác. Email đã có được bỏ qua hoàn toàn, không ghi đè hồ sơ. Số điện thoại/số tài khoản được lưu dạng chuỗi để giữ số 0 đầu. Mật khẩu khởi tạo là chuỗi ngẫu nhiên không công bố; Admin/Quản lý vào sửa hồ sơ → Đổi mật khẩu để cấp mật khẩu cho nhân viên. Không gửi email tự động. Ngày vào làm chỉ là thông tin hồ sơ, không tự khóa tài khoản nếu nằm trong tương lai.

Tệp đã xử lý được chuyển vào `.data/employee-import-processed-*.json`, giúp lần khởi động sau không nhập lại. Hệ thống tạo bản sao lưu sau khi nhập. Lỗi nhập in tại cửa sổ máy chủ; không xóa tệp để quản lý có thể sửa và thử lại. Nếu có phần nhập thành công trước lỗi, lần chạy sau bỏ qua email đã có để không tạo trùng.

Ba dòng trong bản dữ liệu riêng được nhập theo ảnh người dùng cung cấp. Hai số điện thoại đầu trùng nhau được giữ nguyên theo yêu cầu. Địa chỉ Cường chỉ có phần nhìn thấy và có ghi chú để sửa sau. Lương, vai trò và trạng thái chưa có trong ảnh dùng mặc định nêu trên, có thể chỉnh trong Quản lý nhân viên. Chưa tự phân ca cho ba người vì chưa có lịch làm thực tế.

Xác minh bản Phân ca: 46 kiểm thử đạt. E2E Chromium đã kiểm tra kéo chuột thực để thêm/chuyển ca, sửa trong hộp thoại, phân nhiều người, lưu và tải lại, mobile chạm chọn/thêm không tràn trang, Nhân viên chỉ xem lịch riêng và bị chặn API sửa. Gói dữ liệu riêng ba nhân viên đã được thử nhập vào DB kiểm thử; chưa truy cập hoặc ghi trực tiếp vào DB trên máy Windows của chủ studio. Người dùng cần chép bản cập nhật kèm employee-import.json và khởi động web để nhập trên máy thật.


Phân ca mở mặc định ở chế độ **Tuần** trên máy tính và điện thoại; có thể chọn Ngày khi cần xem gọn trên màn hình nhỏ. Mỗi nhân viên có màu cố định theo ID, dùng đồng nhất cho danh sách kéo thả và các thẻ trên lịch ở cả hai ca. Đổi ngày, chuyển ca, tìm kiếm hoặc tải lại không đổi màu nhân viên.

## Lịch sử nhân viên dành cho Admin/Quản lý

Trước đây phần Lịch sử chấm công đầu trang là lịch sử của tài khoản đăng nhập; lịch sử toàn studio nằm phía dưới và phải bấm tải. Bản mới đưa **Lịch sử chấm công nhân viên** lên trước lịch sử cá nhân với Admin/Quản lý và tự tải theo tháng khi mở/quay lại Chấm công. Có cột tên, lọc tháng/nhân viên, cập nhật, ảnh/nguồn chấm và Xuất CSV theo cùng bộ lọc. Bảng cá nhân đổi tên rõ thành **Lịch sử chấm công của tôi**. Nhân viên chỉ có bảng cá nhân; API toàn studio vẫn trả 403 với role Nhân viên.

Nhận diện ca đang làm/thiếu checkout được tính riêng từng user_id, tránh ghép lượt vào của người này với lượt ra của người khác cùng ca/ngày. API báo cáo trả user_id và cả sự kiện thuộc work_date của tháng được chọn để giữ đủ cặp OT qua ranh giới tháng. Các dòng vẫn chia theo ngày chấm thực tế. Không sửa hoặc xóa công đã lưu.

Xác minh: 47 kiểm thử đạt, thêm tình huống hai người cùng ca nhưng chỉ một người checkout. E2E desktop/mobile kiểm tra Quản lý tự thấy toàn studio mà không bấm tải, phân biệt lịch sử cá nhân, lọc người/tháng, xem ảnh có kiểm tra quyền, CSV đúng người đang lọc; Nhân viên không thấy hoặc gọi được báo cáo toàn studio. Không có lỗi JavaScript và không tràn trang trên màn hình 390px.


## Checkout muộn tối đa 30 phút

Ca 1 cho tự checkout đến **14:30:00**, ca 2 đến **19:30:00** (giờ Việt Nam). Sau mốc này máy chủ từ chối tự checkout; giao diện khóa nút nếu vẫn chọn ca đó và hướng dẫn gửi yêu cầu bổ sung công. Không cho checkin mới ngoài khung giờ ca chính. GPS và ảnh vẫn bắt buộc khi tự checkout.

Giao diện ưu tiên ca chính đang mở còn trong hạn 30 phút, nên sau 14h không tự chuyển sang ca 2 khi ca 1 cần checkout. Đồng hồ kiểm tra lại trạng thái nút mỗi giây, kể cả khi người dùng đã chọn ca thủ công, để nút không giữ trạng thái cũ sau hạn. Người dùng vẫn có thể chủ động chọn ca khác hoặc OT nếu đáp ứng điều kiện tương ứng.

Lượt ra lưu giờ thực tế, nhưng công/giờ tham khảo của ca chỉ tính đến 14h/19h. Checkout muộn không thêm công, lương hoặc OT. Bot tiếp tục nhắc thiếu checkout lúc 14:05/19:05. Nếu quá hạn, nhân viên gửi yêu cầu bổ sung; Admin/Quản lý có thể xác nhận giờ thực tế qua quy trình có lưu người xử lý/lý do. Ngoại lệ này chỉ được gọi từ API xử lý yêu cầu/chấm hộ có kiểm tra quyền; truyền cờ từ client vào API tự chấm không vượt qua được giới hạn.

Xác minh: 50 kiểm thử đạt, gồm đúng mốc 14:30/19:30 và sau mốc 1ms, cả hai ca, chặn vượt hạn ở API, duyệt bổ sung/chấm hộ sau hạn và không tăng lương/OT. E2E mobile với đồng hồ giả lập xác nhận ca cũ còn được chọn sau hết giờ, nút checkout còn bật đến hạn, tự khóa khi vượt hạn với thông báo rõ ràng; không có lỗi JavaScript.
