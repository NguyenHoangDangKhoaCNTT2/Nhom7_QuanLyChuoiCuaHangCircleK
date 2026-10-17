<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Lập hóa đơn';
$activeMenu = 'hoadon';

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                Hóa đơn mới
            </h5>
            <div class="panel-subtitle ms-5">Thông tin hóa đơn bán hàng</div>
        </div>
        
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><label class="form-label" for="hdMa">Số hóa đơn</label><input type="text" class="form-control" id="hdMa" placeholder="HD1026" readonly></div>
        <div class="col-md-3"><label class="form-label" for="hdNgay">Ngày lập</label><input type="date" class="form-control" id="hdNgay" placeholder=""></div>
        <div class="col-md-3"><label class="form-label" for="hdKh">Khách hàng</label><select class="form-select" id="hdKh"><option>Khách lẻ</option><option>Phạm Minh E</option><option>Lê Văn D</option></select></div>
        <div class="col-md-3"><label class="form-label" for="hdTn">Thu ngân</label><select class="form-select" id="hdTn"><option>Trương Thị C</option><option>Nguyễn Văn B</option></select></div>
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Sản phẩm</th><th class="text-end">Số lượng</th><th class="text-end">Đơn giá</th><th class="text-end">Thành tiền</th></tr></thead>
            <tbody>
                <tr><td><span class="fw-semibold">Cà phê sữa đá lon</span></td><td class="text-end">2</td><td class="text-end"><span class="num-font fw-bold ">30,000 ₫</span></td><td class="text-end"><span class="num-font fw-bold ">60,000 ₫</span></td></tr>
                <tr><td><span class="fw-semibold">Mì Hảo Hảo ly</span></td><td class="text-end">1</td><td class="text-end"><span class="num-font fw-bold ">12,000 ₫</span></td><td class="text-end"><span class="num-font fw-bold ">12,000 ₫</span></td></tr>
            </tbody>
        </table>
    </div>
    <button type="button" class="btn-ck btn-ck-soft mt-3"><i class="fa-solid fa-plus"></i> Thêm dòng sản phẩm</button>
    <div class="mt-3 pt-3" style="border-top: 1px solid var(--border-light);">
        
        <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="fw-bold">Tổng hóa đơn</span>
            <span class="num-font fw-bold" style="font-size: 1.6rem; color: var(--ck-red);">77,760 ₫</span>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3"><button type="button" class="btn-ck btn-ck-soft">Lưu nháp</button><button type="button" class="btn-ck btn-ck-primary">Lập hóa đơn</button></div>
</div>

<div class="bento-panel fade-up d-1 mt-4">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--violet), #ec4899)"><i class="fa-solid fa-receipt"></i></span>
                Danh sách hóa đơn
            </h5>
            <div class="panel-subtitle ms-5">Tra cứu hóa đơn đã lập</div>
        </div>
        
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><div class="search-bar"><i class="fa-solid fa-magnifying-glass s-ico"></i><input type="text" placeholder="Tìm theo số hóa đơn, khách hàng..."></div><select class="form-select" style="width: auto;"><option>Tất cả trạng thái</option><option>Hoàn tất</option><option>Đang xử lý</option><option>Đã hủy</option></select></div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Số HĐ</th><th>Điểm bán</th><th>Thu ngân</th><th>Thời gian</th><th>Trạng thái</th><th class="text-end">Giá trị</th></tr></thead>
            <tbody>
                <tr><td><span class="order-code">#HD1024</span></td><td>Circle K Quận 1</td><td>Trương Thị C</td><td>14:32</td><td><span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Hoàn tất</span></td><td class="text-end"><span class="num-font fw-bold ">125,000 ₫</span></td></tr>
                <tr><td><span class="order-code">#HD1022</span></td><td>Circle K Quận 3</td><td>Trần B</td><td>13:50</td><td><span class="st-badge st-cancel"><i class="fa-solid fa-circle-xmark"></i> Đã hủy</span></td><td class="text-end"><span class="num-font fw-bold text-decoration-line-through text-muted">210,000 ₫</span></td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
