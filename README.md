# Hệ thống quản lý chuỗi cửa hàng tiện lợi Circle K

Website quản lý chuỗi cửa hàng tiện lợi Circle K viết bằng PHP, chạy trên XAMPP. Hệ thống gồm bốn phần: đăng nhập – phân quyền, hàng hóa và kho, bán hàng (POS), thống kê và báo cáo.

## Thành viên thực hiện

| STT | Họ và tên              | MSSV     |
| :-: | ---------------------- | -------- |
|  1  | Nguyễn Hoàng Đăng Khoa | 65131500 |
|  2  | Nguyễn Dương Bảo Phú   | 65132716 |
|  3  | Nguyễn Phương Nam      | 65132069 |
|  4  | Đoàn Anh Thái          | 65133158 |

---

## 1. Công nghệ sử dụng

- **Ngôn ngữ:** PHP 
- **Cơ sở dữ liệu:** MySQL / MariaDB (tên CSDL: `circlek`)
- **Giao diện:** HTML, CSS, JavaScript, Bootstrap 5.3.2, Font Awesome 6.4.2, Google Fonts (Be Vietnam Pro, Plus Jakarta Sans)
- **Biểu đồ:** Chart.js 4.4.1
- **Môi trường chạy:** XAMPP (Apache + MySQL), chạy trên trình duyệt web

---

## 2. Cấu trúc thư mục

```text
Nhom7_QuanLyChuoiCuaHangCircleK/
├── index.php                 # Trang chủ
├── san_pham.php              # Xem sản phẩm (không cần đăng nhập)
├── khuyen_mai.php            # Xem khuyến mãi (không cần đăng nhập)
├── assets/                   # css, js, images (ảnh sản phẩm)
├── database/
│   └── NHOM_7.sql            # Cấu trúc bảng và dữ liệu mẫu
├── includes/                 # Phần dùng chung
│   ├── config.php            # Session, múi giờ, lấy quyền người dùng
│   ├── chucnang.php          # Danh sách chức năng
│   ├── menu.php              # Menu theo quyền
│   └── head / header / sidebar / topbar / footer
└── modules/
    ├── tai_khoan_nhan_su/    # Phần 1: Đăng nhập & Phân quyền
    ├── hang_hoa_kho/         # Phần 2: Hàng hóa và kho
    ├── ban_hang_pos/         # Phần 3: Bán hàng (POS)
    └── thong_ke_bao_cao/     # Phần 4: Thống kê và báo cáo
```

---

## 3. Chức năng theo từng phần

### 3.1. Đăng nhập & Phân quyền (Nguyễn Hoàng Đăng Khoa)

- Đăng nhập, đăng xuất; hệ thống kiểm tra tài khoản rồi lưu phiên làm việc (người dùng, vai trò, cửa hàng, danh sách chức năng được dùng)
- Phân quyền theo 5 vai trò; Giám đốc có thể cấp quyền riêng cho từng tài khoản
- Quản lý cửa hàng: thêm, sửa, xóa
- Quản lý nhân viên: thêm, sửa, xóa (chức vụ, cửa hàng, lương, ngày vào làm)
- Quản lý khách hàng: thêm, sửa, xóa, lọc theo hạng thẻ
- Nhân viên khu vực: Quản lý trưởng xem nhân viên trong khu vực phụ trách và gửi yêu cầu thêm / xóa nhân viên
- Phê duyệt nhân sự: duyệt hoặc từ chối các yêu cầu trên
- Ca trực: thêm, sửa, xóa ca, ghi giờ vào ca và giờ kết thúc ca

### 3.2. Hàng hóa và kho (Nguyễn Dương Bảo Phú)

- Quản lý sản phẩm: thêm, sửa, xóa (mã, tên, danh mục, giá nhập, giá bán, hình ảnh)
- Nhập hàng vào kho: lập phiếu nhập từ nhà cung cấp
- Điều chuyển kho: lập phiếu điều chuyển hàng giữa các cửa hàng
- Xem tồn kho của cửa hàng (dành cho Quản lý cửa hàng)

### 3.3. Bán hàng – POS (Nguyễn Phương Nam)

- Màn hình bán hàng: chọn sản phẩm vào giỏ, chọn hoặc tạo thành viên, áp dụng khuyến mãi / voucher
- Thanh toán đơn hàng: tiền mặt (có tính tiền thối), thẻ ATM / Visa, VNPay QR, ví MoMo
- Lập hóa đơn và xem hóa đơn
- Quản lý khuyến mãi: chương trình giảm theo phần trăm hoặc giảm tiền, có mã voucher, thời gian áp dụng và số lượng

### 3.4. Thống kê và báo cáo (Đoàn Anh Thái)

- Dashboard cửa hàng: các thẻ chỉ số hôm nay / hôm qua, biểu đồ doanh thu theo ngày và theo danh mục
- Biểu đồ so sánh toàn chuỗi: so sánh các cửa hàng bằng biểu đồ cột
- Thống kê sản phẩm bán chạy
- Lọc báo cáo theo thời gian, có xuất file CSV

---

## 4. Phân quyền

Chức năng mặc định theo từng vai trò (Giám đốc có thể chỉnh quyền riêng cho từng tài khoản):

| Vai trò | Chức năng mặc định |
| ------- | ------------------ |
| Giám đốc | Toàn bộ chức năng của 4 phần |
| Quản lý trưởng | Dashboard, nhân viên khu vực, ca trực, hóa đơn, nhập kho, quản lý khuyến mãi |
| Quản lý cửa hàng | Dashboard, quản lý nhân viên, ca trực, xem hóa đơn, xem tồn kho, xem khuyến mãi |
| Nhân viên bán hàng | Màn hình bán hàng (POS), thanh toán đơn hàng, xem hóa đơn |
| Nhân viên kho | Điều chuyển kho, nhập hàng vào kho |

Người chưa đăng nhập chỉ xem được Trang chủ, Sản phẩm và Khuyến mãi.

---

## 5. Một số quy tắc trong hệ thống

- Hóa đơn tính thêm VAT 8%
- Thẻ tích điểm có 3 hạng: Bạc (từ 0 điểm, giảm 0%), Vàng (từ 1.000 điểm, giảm 2%), Kim Cương (từ 3.000 điểm, giảm 5%)
- Khi thanh toán thành công, thành viên được cộng 1 điểm cho mỗi 1.000đ và hạng thẻ được cập nhật theo tổng điểm
- Dữ liệu mẫu trong `NHOM_7.sql`: 12 cửa hàng, 88 sản phẩm, 52 tài khoản

---

## 6. Hướng dẫn cài đặt và chạy

### 6.1. Chuẩn bị

1. Cài XAMPP, mở XAMPP Control Panel và bấm **Start** cho **Apache** và **MySQL**.
2. Chép thư mục dự án vào `C:\xampp\htdocs\`.

### 6.2. Tạo cơ sở dữ liệu

1. Mở `http://localhost/phpmyadmin`.
2. Vào tab **Import**, chọn file `database/NHOM_7.sql` rồi bấm **Import**.

> File SQL tự xóa và tạo lại CSDL `circlek`, nên dữ liệu cũ trong CSDL này (nếu có) sẽ mất.

Kết nối CSDL đang dùng cấu hình mặc định của XAMPP: máy chủ `localhost`, tài khoản `root`, không mật khẩu, CSDL `circlek`. Nếu máy khác cấu hình, sửa trong các file `index.php` của từng module.

### 6.3. Chạy web

- Trang chủ: `http://localhost/Nhom7_QuanLyChuoiCuaHangCircleK/`
- Trang đăng nhập: `http://localhost/Nhom7_QuanLyChuoiCuaHangCircleK/modules/tai_khoan_nhan_su/dang_nhap_phan_quyen/dang_nhap.php`

### 6.4. Tài khoản thử nghiệm

| Vai trò | Tên đăng nhập | Mật khẩu |
| ------- | ------------- | -------- |
| Giám đốc | admin_giamdoc | 65131500 |
| Quản lý trưởng | qlt_phu | 65132716 |
| Quản lý cửa hàng | ql_ch1 | 66047258 |
| Nhân viên bán hàng | bh1_ch1 | 66130871 |
| Nhân viên kho | kho_ch1 | 66769647 |

---

## 7. Video demo và thư mục Drive

- Video demo: [Xem video demo](https://drive.google.com/file/d/12xwwIO3UEkd_JhxwoaMyoabxgNF2RsjJ/view?usp=sharing)
- Thư mục Google Drive: [Mở thư mục](https://drive.google.com/drive/folders/1x-I0emLetBgYUKfvGIGgid82Q3UlGvCa?usp=sharing)## 7. Video demo

[Xem video demo](https://drive.google.com/file/d/12xwwIO3UEkd_JhxwoaMyoabxgNF2RsjJ/view?usp=sharing)
