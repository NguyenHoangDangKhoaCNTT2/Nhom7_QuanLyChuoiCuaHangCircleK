<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Nhập hàng vào kho';
$activeMenu = 'nhapkho';

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-amber), #f97316)"><i class="fa-solid fa-dolly"></i></span>
                Phiếu nhập mới
            </h5>
            <div class="panel-subtitle ms-5">Nhập hàng từ nhà cung cấp vào kho</div>
        </div>
        
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><label class="form-label" for="pnMa">Mã phiếu</label><input type="text" class="form-control" id="pnMa" placeholder="PN0005" readonly></div>
        <div class="col-md-3"><label class="form-label" for="pnNgay">Ngày nhập</label><input type="date" class="form-control" id="pnNgay" placeholder=""></div>
        <div class="col-md-3"><label class="form-label" for="pnKho">Kho nhận</label><select class="form-select" id="pnKho"><option>Circle K Quận 1</option><option>Circle K Quận 3</option></select></div>
        <div class="col-md-3"><label class="form-label" for="pnNcc">Nhà cung cấp</label><select class="form-select" id="pnNcc"><option>Chọn nhà cung cấp...</option></select></div>
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Sản phẩm</th><th class="text-end">Số lượng</th><th class="text-end">Đơn giá nhập</th><th class="text-end">Thành tiền</th></tr></thead>
            <tbody>
                <tr><td><span class="fw-semibold">Cà phê sữa đá lon</span></td><td class="text-end">50</td><td class="text-end"><span class="num-font fw-bold ">22,000 ₫</span></td><td class="text-end"><span class="num-font fw-bold ">1,100,000 ₫</span></td></tr>
                <tr><td><span class="fw-semibold">Mì Hảo Hảo ly</span></td><td class="text-end">100</td><td class="text-end"><span class="num-font fw-bold ">9,000 ₫</span></td><td class="text-end"><span class="num-font fw-bold ">900,000 ₫</span></td></tr>
            </tbody>
        </table>
    </div>
    <button type="button" class="btn-ck btn-ck-soft mt-3"><i class="fa-solid fa-plus"></i> Thêm dòng sản phẩm</button>
    <div class="mt-3 pt-3" style="border-top: 1px solid var(--border-light);">
        
        <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="fw-bold">Tổng tiền nhập</span>
            <span class="num-font fw-bold" style="font-size: 1.6rem; color: var(--ck-red);">2,000,000 ₫</span>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3"><button type="button" class="btn-ck btn-ck-soft">Hủy</button><button type="button" class="btn-ck btn-ck-primary">Lưu phiếu nhập</button></div>
</div>

<div class="bento-panel fade-up d-1 mt-4">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-clock-rotate-left"></i></span>
                Lịch sử nhập kho
            </h5>
            <div class="panel-subtitle ms-5">Các phiếu nhập gần đây</div>
        </div>
        
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Mã phiếu</th><th>Ngày nhập</th><th>Kho nhận</th><th>Nhà cung cấp</th><th>Trạng thái</th><th class="text-end">Tổng tiền</th></tr></thead>
            <tbody>
                <tr><td><span class="order-code">PN0004</span></td><td>15/10/2026</td><td>Circle K Quận 1</td><td>Coca-Cola VN</td><td><span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Đã nhập</span></td><td class="text-end"><span class="num-font fw-bold ">4,500,000 ₫</span></td></tr>
                <tr><td><span class="order-code">PN0003</span></td><td>12/10/2026</td><td>Circle K Quận 3</td><td>Acecook VN</td><td><span class="st-badge st-wait"><i class="fa-solid fa-clock"></i> Chờ duyệt</span></td><td class="text-end"><span class="num-font fw-bold ">3,200,000 ₫</span></td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
