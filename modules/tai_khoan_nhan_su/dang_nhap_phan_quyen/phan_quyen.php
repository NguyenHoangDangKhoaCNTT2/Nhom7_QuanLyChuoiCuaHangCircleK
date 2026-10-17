<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Đăng nhập & Phân quyền';
$activeMenu = 'phanquyen';

require ROOT_PATH . '/includes/header.php';
?>

<div class="row g-4">
    <div class="col-12 col-xl-4">
<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--emerald), #059669)"><i class="fa-solid fa-user-shield"></i></span>
                Vai trò
            </h5>
            <div class="panel-subtitle ms-5">Chọn vai trò để chỉnh quyền</div>
        </div>
        
    </div>
    <div class="store-item" style="background: #f8fafc;">
        <span class="store-tag" style="background: var(--ck-red)">AD</span>
        <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Admin tổng</div><small style="color: var(--ink-500); font-size: 0.78rem;">Toàn quyền toàn chuỗi</small></div>
    </div>
    <div class="store-item">
        <span class="store-tag" style="background: var(--ck-amber)">QL</span>
        <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Quản lý cửa hàng</div><small style="color: var(--ink-500); font-size: 0.78rem;">Quản lý 1 cửa hàng</small></div>
    </div>
    <div class="store-item">
        <span class="store-tag" style="background: var(--ocean)">TN</span>
        <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Thu ngân</div><small style="color: var(--ink-500); font-size: 0.78rem;">Bán hàng, lập hóa đơn</small></div>
    </div>
    <div class="store-item">
        <span class="store-tag" style="background: var(--emerald)">TK</span>
        <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Thủ kho</div><small style="color: var(--ink-500); font-size: 0.78rem;">Nhập kho, điều chuyển</small></div>
    </div>
    <button type="button" class="btn-ck btn-ck-soft w-100 mt-3"><i class="fa-solid fa-plus"></i> Thêm vai trò</button>
</div>
    </div>
    <div class="col-12 col-xl-8">
<div class="bento-panel fade-up d-1">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-table-cells"></i></span>
                Ma trận phân quyền
            </h5>
            <div class="panel-subtitle ms-5">Vai trò đang chọn: Admin tổng</div>
        </div>
        
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Chức năng</th><th class="text-center">Xem</th><th class="text-center">Thêm</th><th class="text-center">Sửa</th><th class="text-center">Xóa</th></tr></thead>
            <tbody>
                <tr><td class="fw-semibold">Quản lý Cửa hàng</td><td class="text-center"><input class="form-check-input" type="checkbox"></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox"></td></tr>
                <tr><td class="fw-semibold">Quản lý Nhân viên</td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox"></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td></tr>
                <tr><td class="fw-semibold">Quản lý Khách hàng</td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox"></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td></tr>
                <tr><td class="fw-semibold">Quản lý Sản phẩm</td><td class="text-center"><input class="form-check-input" type="checkbox"></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox"></td></tr>
                <tr><td class="fw-semibold">Nhập hàng vào kho</td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox"></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td></tr>
                <tr><td class="fw-semibold">Điều chuyển kho</td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox"></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td></tr>
                <tr><td class="fw-semibold">Bán hàng POS</td><td class="text-center"><input class="form-check-input" type="checkbox"></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox"></td></tr>
                <tr><td class="fw-semibold">Lập hóa đơn</td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox"></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td></tr>
                <tr><td class="fw-semibold">Quản lý Khuyến mãi</td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox"></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td></tr>
                <tr><td class="fw-semibold">Thống kê & Báo cáo</td><td class="text-center"><input class="form-check-input" type="checkbox"></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox" checked></td><td class="text-center"><input class="form-check-input" type="checkbox"></td></tr>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <button type="button" class="btn-ck btn-ck-soft">Hủy</button>
        <button type="button" class="btn-ck btn-ck-primary">Lưu phân quyền</button>
    </div>
</div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
