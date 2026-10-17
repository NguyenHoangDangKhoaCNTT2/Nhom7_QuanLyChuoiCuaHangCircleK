<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Quản lý Khuyến mãi';
$activeMenu = 'khuyenmai';

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), var(--ck-orange))"><i class="fa-solid fa-ticket"></i></span>
                Danh sách khuyến mãi
            </h5>
            <div class="panel-subtitle ms-5">Chương trình khuyến mãi và voucher</div>
        </div>
        
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><div class="search-bar"><i class="fa-solid fa-magnifying-glass s-ico"></i><input type="text" placeholder="Tìm theo mã, tên chương trình..."></div><select class="form-select" style="width: auto;"><option>Tất cả trạng thái</option><option>Đang chạy</option><option>Sắp diễn ra</option><option>Đã kết thúc</option></select><button type="button" class="btn-ck btn-ck-primary ms-auto" data-bs-toggle="modal" data-bs-target="#modalKhuyenMai"><i class="fa-solid fa-plus"></i> Thêm khuyến mãi</button></div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Mã KM</th><th>Tên chương trình</th><th>Loại</th><th class="text-end">Giá trị</th><th>Thời gian</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
                <tr><td><span class="order-code">KM001</span></td><td><span class="fw-semibold">Mua 2 tặng 1 nước ngọt</span></td><td>Tặng kèm</td><td class="text-end"><span class="num-font fw-bold ">1 sản phẩm</span></td><td>01/10 – 31/10/2026</td><td><span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Đang chạy</span></td><td><div class="d-flex justify-content-end gap-2"><a href="#" class="action-icon-btn sm" title="Sửa"><i class="fa-solid fa-pen"></i></a><a href="#" class="action-icon-btn sm" title="Xóa"><i class="fa-solid fa-trash"></i></a></div></td></tr>
                <tr><td><span class="order-code">KM002</span></td><td><span class="fw-semibold">Giảm 10% hội viên Vàng</span></td><td>Phần trăm</td><td class="text-end"><span class="num-font fw-bold ">10%</span></td><td>15/11 – 30/11/2026</td><td><span class="st-badge st-wait"><i class="fa-solid fa-clock"></i> Sắp diễn ra</span></td><td><div class="d-flex justify-content-end gap-2"><a href="#" class="action-icon-btn sm" title="Sửa"><i class="fa-solid fa-pen"></i></a><a href="#" class="action-icon-btn sm" title="Xóa"><i class="fa-solid fa-trash"></i></a></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalKhuyenMai" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Thêm / sửa khuyến mãi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="kmMa">Mã khuyến mãi</label><input type="text" class="form-control" id="kmMa" placeholder="KM003"></div>
                    <div class="col-md-6"><label class="form-label" for="kmTen">Tên chương trình</label><input type="text" class="form-control" id="kmTen" placeholder=""></div>
                    <div class="col-md-6"><label class="form-label" for="kmLoai">Loại khuyến mãi</label><select class="form-select" id="kmLoai"><option>Phần trăm</option><option>Giảm tiền</option><option>Tặng kèm</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="kmGt">Giá trị</label><input type="number" class="form-control" id="kmGt" placeholder=""></div>
                    <div class="col-md-6"><label class="form-label" for="kmTu">Từ ngày</label><input type="date" class="form-control" id="kmTu" placeholder=""></div>
                    <div class="col-md-6"><label class="form-label" for="kmDen">Đến ngày</label><input type="date" class="form-control" id="kmDen" placeholder=""></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn-ck btn-ck-primary">Lưu</button>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
