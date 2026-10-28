<?php
// Danh sách chức năng theo 4 nhóm - dùng chung cho menu.php và trang phân quyền
return [
    ['label' => 'Tài khoản & Nhân sự', 'nhom' => 'nhan_su', 'items' => [
        ['key' => 'phanquyen', 'label' => 'Đăng nhập & Phân quyền', 'icon' => 'fa-user-shield', 'color' => '#34d399', 'href' => 'modules/tai_khoan_nhan_su/dang_nhap_phan_quyen/phan_quyen.php'],
        ['key' => 'cuahang', 'label' => 'Quản lý Cửa hàng', 'icon' => 'fa-store', 'color' => '#34d399', 'href' => 'modules/tai_khoan_nhan_su/quan_ly_cua_hang/index.php'],
        ['key' => 'nhanvien', 'label' => 'Quản lý Nhân viên', 'icon' => 'fa-user-clock', 'color' => '#34d399', 'href' => 'modules/tai_khoan_nhan_su/quan_ly_nhan_vien/index.php'],
        ['key' => 'khachhang', 'label' => 'Quản lý Khách hàng', 'icon' => 'fa-id-card-clip', 'color' => '#34d399', 'href' => 'modules/tai_khoan_nhan_su/quan_ly_khach_hang/index.php'],
    ]],
    ['label' => 'Hàng hóa & Kho', 'nhom' => 'hang_hoa_kho', 'items' => [
        ['key' => 'sanpham', 'label' => 'Quản lý Sản phẩm', 'icon' => 'fa-boxes-stacked', 'color' => '#fbbf24', 'href' => 'modules/hang_hoa_kho/quan_ly_san_pham/index.php'],
        ['key' => 'nhapkho', 'label' => 'Nhập hàng vào kho', 'icon' => 'fa-dolly', 'color' => '#fbbf24', 'href' => 'modules/hang_hoa_kho/nhap_hang_vao_kho/index.php'],
        ['key' => 'dieuchuyen', 'label' => 'Điều chuyển kho', 'icon' => 'fa-right-left', 'color' => '#fbbf24', 'href' => 'modules/hang_hoa_kho/dieu_chuyen_kho/index.php'],
    ]],
    ['label' => 'Bán hàng (POS)', 'nhom' => 'ban_hang', 'items' => [
        ['key' => 'pos', 'label' => 'Màn hình bán hàng (POS)', 'icon' => 'fa-cash-register', 'color' => '#38bdf8', 'href' => 'modules/ban_hang_pos/man_hinh_ban_hang/index.php'],
        ['key' => 'hoadon', 'label' => 'Lập hóa đơn', 'icon' => 'fa-file-invoice-dollar', 'color' => '#38bdf8', 'href' => 'modules/ban_hang_pos/lap_hoa_don/index.php'],
        ['key' => 'khuyenmai', 'label' => 'Quản lý Khuyến mãi', 'icon' => 'fa-ticket', 'color' => '#38bdf8', 'href' => 'modules/ban_hang_pos/quan_ly_khuyen_mai/index.php'],
        ['key' => 'thanhtoan', 'label' => 'Thanh toán đơn hàng', 'icon' => 'fa-credit-card', 'color' => '#38bdf8', 'href' => 'modules/ban_hang_pos/thanh_toan_don_hang/index.php'],
    ]],
    ['label' => 'Thống kê & Báo cáo', 'nhom' => 'thong_ke', 'items' => [
        ['key' => 'dashboard', 'label' => 'Dashboard thống kê cửa hàng', 'icon' => 'fa-chart-pie', 'color' => '#a78bfa', 'href' => 'modules/thong_ke_bao_cao/dashboard_cua_hang/index.php'],
        ['key' => 'sosanh', 'label' => 'Biểu đồ so sánh toàn chuỗi', 'icon' => 'fa-chart-column', 'color' => '#a78bfa', 'href' => 'modules/thong_ke_bao_cao/bieu_do_so_sanh_chuoi/index.php'],
        ['key' => 'banchay', 'label' => 'Thống kê sản phẩm bán chạy', 'icon' => 'fa-ranking-star', 'color' => '#a78bfa', 'href' => 'modules/thong_ke_bao_cao/thong_ke_san_pham_ban_chay/index.php'],
        ['key' => 'locbaocao', 'label' => 'Lọc báo cáo theo thời gian', 'icon' => 'fa-calendar-days', 'color' => '#a78bfa', 'href' => 'modules/thong_ke_bao_cao/loc_bao_cao_theo_thoi_gian/index.php'],
    ]],
];