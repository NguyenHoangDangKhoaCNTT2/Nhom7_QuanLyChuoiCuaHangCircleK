<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle  = 'Trang chủ';
$activeMenu = 'home';

require ROOT_PATH . '/includes/header.php';
?>

<section class="hero-banner mb-4 fade-up">
    <div class="hero-content">
        <div class="hero-badge"><span class="pulse-dot" style="width:7px;height:7px;"></span><span>Khung giao diện chung • 4 thành viên • 15 chức năng</span></div>
        <h2 class="hero-title">Circle K — Hệ thống quản trị chuỗi</h2>
        <p class="hero-desc">Mỗi chức năng đã có sẵn trang giao diện mẫu. Mở thư mục chức năng của bạn và phát triển tiếp bên trong.</p>
    </div>
</section>

<div class="row g-4">
    <div class="col-12 col-xl-6">
        <div class="bento-panel fade-up d-1">
            <div class="panel-header">
                <div>
                    <h5 class="panel-title">
                        <span class="title-icon" style="background: linear-gradient(135deg, var(--emerald), #059669)"><i class="fa-solid fa-users-gear"></i></span>
                        Tài khoản & Nhân sự
                    </h5>
                    <div class="panel-subtitle ms-5">modules/tai_khoan_nhan_su/</div>
                </div>
                <span class="badge rounded-pill px-3 py-2" style="background: #ffe4e6; color: var(--ck-red);">Thành viên 1</span>
            </div>
            <a href="<?= e(url('modules/tai_khoan_nhan_su/dang_nhap_phan_quyen/phan_quyen.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #34d399"><i class="fa-solid fa-user-shield"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Đăng nhập & Phân quyền</div><small style="color: var(--ink-500); font-size: 0.78rem;">Màn hình đăng nhập và phân quyền theo vai trò</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
            <a href="<?= e(url('modules/tai_khoan_nhan_su/dang_nhap_phan_quyen/dang_nhap.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #34d399"><i class="fa-solid fa-right-to-bracket"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Màn hình đăng nhập</div><small style="color: var(--ink-500); font-size: 0.78rem;">Trang riêng, không có sidebar</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
            <a href="<?= e(url('modules/tai_khoan_nhan_su/quan_ly_cua_hang/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #34d399"><i class="fa-solid fa-store"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Quản lý Cửa hàng</div><small style="color: var(--ink-500); font-size: 0.78rem;">Danh sách, thêm, sửa cửa hàng trong chuỗi</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
            <a href="<?= e(url('modules/tai_khoan_nhan_su/quan_ly_nhan_vien/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #34d399"><i class="fa-solid fa-user-clock"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Quản lý Nhân viên</div><small style="color: var(--ink-500); font-size: 0.78rem;">Hồ sơ nhân viên, chức vụ, ca làm</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
            <a href="<?= e(url('modules/tai_khoan_nhan_su/quan_ly_khach_hang/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #34d399"><i class="fa-solid fa-id-card-clip"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Quản lý Khách hàng</div><small style="color: var(--ink-500); font-size: 0.78rem;">Hội viên, hạng thẻ, điểm tích lũy</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="bento-panel fade-up d-2">
            <div class="panel-header">
                <div>
                    <h5 class="panel-title">
                        <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-amber), #f97316)"><i class="fa-solid fa-boxes-stacked"></i></span>
                        Hàng hóa & Kho
                    </h5>
                    <div class="panel-subtitle ms-5">modules/hang_hoa_kho/</div>
                </div>
                <span class="badge rounded-pill px-3 py-2" style="background: #ffe4e6; color: var(--ck-red);">Thành viên 2</span>
            </div>
            <a href="<?= e(url('modules/hang_hoa_kho/quan_ly_san_pham/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #fbbf24"><i class="fa-solid fa-boxes-stacked"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Quản lý Sản phẩm</div><small style="color: var(--ink-500); font-size: 0.78rem;">Danh mục, giá bán, tồn kho</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
            <a href="<?= e(url('modules/hang_hoa_kho/nhap_hang_vao_kho/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #fbbf24"><i class="fa-solid fa-dolly"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Nhập hàng vào kho</div><small style="color: var(--ink-500); font-size: 0.78rem;">Lập phiếu nhập kho từ nhà cung cấp</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
            <a href="<?= e(url('modules/hang_hoa_kho/dieu_chuyen_kho/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #fbbf24"><i class="fa-solid fa-right-left"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Điều chuyển kho</div><small style="color: var(--ink-500); font-size: 0.78rem;">Chuyển hàng giữa các cửa hàng / kho</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="bento-panel fade-up d-3">
            <div class="panel-header">
                <div>
                    <h5 class="panel-title">
                        <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-cash-register"></i></span>
                        Bán hàng (POS)
                    </h5>
                    <div class="panel-subtitle ms-5">modules/ban_hang_pos/</div>
                </div>
                <span class="badge rounded-pill px-3 py-2" style="background: #ffe4e6; color: var(--ck-red);">Thành viên 3</span>
            </div>
            <a href="<?= e(url('modules/ban_hang_pos/man_hinh_ban_hang/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #38bdf8"><i class="fa-solid fa-cash-register"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Màn hình bán hàng (POS)</div><small style="color: var(--ink-500); font-size: 0.78rem;">Chọn sản phẩm, giỏ hàng tại quầy</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
            <a href="<?= e(url('modules/ban_hang_pos/lap_hoa_don/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #38bdf8"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Lập hóa đơn</div><small style="color: var(--ink-500); font-size: 0.78rem;">Tạo và tra cứu hóa đơn bán hàng</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
            <a href="<?= e(url('modules/ban_hang_pos/quan_ly_khuyen_mai/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #38bdf8"><i class="fa-solid fa-ticket"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Quản lý Khuyến mãi</div><small style="color: var(--ink-500); font-size: 0.78rem;">Chương trình khuyến mãi, voucher</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
            <a href="<?= e(url('modules/ban_hang_pos/thanh_toan_don_hang/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #38bdf8"><i class="fa-solid fa-credit-card"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Thanh toán đơn hàng</div><small style="color: var(--ink-500); font-size: 0.78rem;">Chọn phương thức, xác nhận thanh toán</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="bento-panel fade-up d-4">
            <div class="panel-header">
                <div>
                    <h5 class="panel-title">
                        <span class="title-icon" style="background: linear-gradient(135deg, var(--violet), #ec4899)"><i class="fa-solid fa-chart-line"></i></span>
                        Thống kê & Báo cáo
                    </h5>
                    <div class="panel-subtitle ms-5">modules/thong_ke_bao_cao/</div>
                </div>
                <span class="badge rounded-pill px-3 py-2" style="background: #ffe4e6; color: var(--ck-red);">Thành viên 4</span>
            </div>
            <a href="<?= e(url('modules/thong_ke_bao_cao/dashboard_cua_hang/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #a78bfa"><i class="fa-solid fa-chart-pie"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Dashboard thống kê cửa hàng</div><small style="color: var(--ink-500); font-size: 0.78rem;">Chỉ số tổng quan của cửa hàng</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
            <a href="<?= e(url('modules/thong_ke_bao_cao/bieu_do_so_sanh_chuoi/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #a78bfa"><i class="fa-solid fa-chart-column"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Biểu đồ so sánh toàn chuỗi</div><small style="color: var(--ink-500); font-size: 0.78rem;">So sánh hiệu suất giữa các cửa hàng</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
            <a href="<?= e(url('modules/thong_ke_bao_cao/thong_ke_san_pham_ban_chay/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #a78bfa"><i class="fa-solid fa-ranking-star"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Thống kê sản phẩm bán chạy</div><small style="color: var(--ink-500); font-size: 0.78rem;">Xếp hạng sản phẩm theo số lượng bán</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
            <a href="<?= e(url('modules/thong_ke_bao_cao/loc_bao_cao_theo_thoi_gian/index.php')) ?>" class="store-item text-decoration-none" style="color: inherit;">
                <span class="store-tag" style="background: #a78bfa"><i class="fa-solid fa-calendar-days"></i></span>
                <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Lọc báo cáo theo thời gian</div><small style="color: var(--ink-500); font-size: 0.78rem;">Bộ lọc ngày / tuần / tháng / quý</small></div>
                <i class="fa-solid fa-chevron-right" style="color: var(--ink-400);"></i>
            </a>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
