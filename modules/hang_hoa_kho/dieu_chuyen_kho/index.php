<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Điều chuyển kho';
$activeMenu = 'dieuchuyen';

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-amber), #f97316)"><i class="fa-solid fa-right-left"></i></span>
                Phiếu điều chuyển mới
            </h5>
            <div class="panel-subtitle ms-5">Chuyển hàng từ kho này sang kho khác</div>
        </div>
        
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><label class="form-label" for="dcMa">Mã phiếu</label><input type="text" class="form-control" id="dcMa" placeholder="DC0005" readonly></div>
        <div class="col-md-3"><label class="form-label" for="dcNgay">Ngày chuyển</label><input type="date" class="form-control" id="dcNgay" placeholder=""></div>
        <div class="col-md-3"><label class="form-label" for="dcXuat">Kho xuất</label><select class="form-select" id="dcXuat"><option>Circle K Quận 1</option><option>Circle K Quận 3</option></select></div>
        <div class="col-md-3"><label class="form-label" for="dcNhan">Kho nhận</label><select class="form-select" id="dcNhan"><option>Circle K Quận 3</option><option>Circle K Tân Bình</option></select></div>
        <div class="col-md-12"><label class="form-label" for="dcGhiChu">Ghi chú</label><textarea class="form-control" id="dcGhiChu" rows="2" placeholder="Lý do điều chuyển..."></textarea></div>
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th class="text-end">Sản phẩm</th><th class="text-end">Tồn kho xuất</th><th class="text-end">Số lượng chuyển</th></tr></thead>
            <tbody>
                <tr><td class="text-end"><span class="fw-semibold">Cà phê sữa đá lon</span></td><td class="text-end">142</td><td class="text-end">30</td></tr>
                <tr><td class="text-end"><span class="fw-semibold">Nước khoáng Aquafina</span></td><td class="text-end">85</td><td class="text-end">20</td></tr>
            </tbody>
        </table>
    </div>
    <button type="button" class="btn-ck btn-ck-soft mt-3"><i class="fa-solid fa-plus"></i> Thêm dòng sản phẩm</button>
    <div class="mt-3 pt-3" style="border-top: 1px solid var(--border-light);">
        
        <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="fw-bold">Tổng số lượng chuyển</span>
            <span class="num-font fw-bold" style="font-size: 1.6rem; color: var(--ck-red);">50</span>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3"><button type="button" class="btn-ck btn-ck-soft">Hủy</button><button type="button" class="btn-ck btn-ck-primary">Tạo phiếu điều chuyển</button></div>
</div>

<div class="bento-panel fade-up d-1 mt-4">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-truck-fast"></i></span>
                Danh sách phiếu điều chuyển
            </h5>
            <div class="panel-subtitle ms-5">Theo dõi trạng thái từng phiếu</div>
        </div>
        
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Mã phiếu</th><th>Ngày</th><th>Kho xuất</th><th>Kho nhận</th><th class="text-end">Số lượng</th><th>Trạng thái</th></tr></thead>
            <tbody>
                <tr><td><span class="order-code">DC0004</span></td><td>16/10/2026</td><td>Circle K Quận 1</td><td>Circle K Quận 3</td><td class="text-end"><span class="num-font fw-bold ">50</span></td><td><span class="st-badge st-wait"><i class="fa-solid fa-clock"></i> Đang vận chuyển</span></td></tr>
                <tr><td><span class="order-code">DC0003</span></td><td>10/10/2026</td><td>Circle K Quận 3</td><td>Circle K Tân Bình</td><td class="text-end"><span class="num-font fw-bold ">35</span></td><td><span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Hoàn tất</span></td></tr>
                <tr><td><span class="order-code">DC0002</span></td><td>05/10/2026</td><td>Circle K Quận 1</td><td>Circle K Tân Bình</td><td class="text-end"><span class="num-font fw-bold ">20</span></td><td><span class="st-badge st-cancel"><i class="fa-solid fa-circle-xmark"></i> Đã hủy</span></td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
