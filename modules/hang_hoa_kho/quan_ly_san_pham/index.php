<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Quản lý Sản phẩm';
$activeMenu = 'sanpham';

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-amber), #f97316)"><i class="fa-solid fa-boxes-stacked"></i></span>
                Danh sách sản phẩm
            </h5>
            <div class="panel-subtitle ms-5">Danh mục hàng hóa, giá bán và tồn kho</div>
        </div>
        
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><div class="search-bar"><i class="fa-solid fa-magnifying-glass s-ico"></i><input type="text" placeholder="Tìm theo mã SKU, tên..."></div><select class="form-select" style="width: auto;"><option>Tất cả danh mục</option><option>Đồ uống</option><option>Đồ ăn</option><option>Snack</option></select><button type="button" class="btn-ck btn-ck-primary ms-auto" data-bs-toggle="modal" data-bs-target="#modalSanPham"><i class="fa-solid fa-plus"></i> Thêm sản phẩm</button></div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Mã SKU</th><th>Tên sản phẩm</th><th>Danh mục</th><th class="text-end">Giá bán</th><th class="text-end">Tồn kho</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
                <tr><td><span class="order-code">SKU001</span></td><td><span class="fw-semibold">Cà phê sữa đá lon</span></td><td>Đồ uống</td><td class="text-end"><span class="num-font fw-bold ">30,000 ₫</span></td><td class="text-end"><span class="num-font fw-bold ">142</span></td><td><span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Đang bán</span></td><td><div class="d-flex justify-content-end gap-2"><a href="#" class="action-icon-btn sm" title="Sửa"><i class="fa-solid fa-pen"></i></a><a href="#" class="action-icon-btn sm" title="Xóa"><i class="fa-solid fa-trash"></i></a></div></td></tr>
                <tr><td><span class="order-code">SKU002</span></td><td><span class="fw-semibold">Mì Hảo Hảo ly</span></td><td>Đồ ăn</td><td class="text-end"><span class="num-font fw-bold ">12,000 ₫</span></td><td class="text-end"><span class="num-font fw-bold ">9</span></td><td><span class="st-badge st-cancel"><i class="fa-solid fa-circle-xmark"></i> Sắp hết</span></td><td><div class="d-flex justify-content-end gap-2"><a href="#" class="action-icon-btn sm" title="Sửa"><i class="fa-solid fa-pen"></i></a><a href="#" class="action-icon-btn sm" title="Xóa"><i class="fa-solid fa-trash"></i></a></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalSanPham" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Thêm / sửa sản phẩm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="spMa">Mã SKU</label><input type="text" class="form-control" id="spMa" placeholder="SKU003"></div>
                    <div class="col-md-6"><label class="form-label" for="spTen">Tên sản phẩm</label><input type="text" class="form-control" id="spTen" placeholder=""></div>
                    <div class="col-md-6"><label class="form-label" for="spDm">Danh mục</label><select class="form-select" id="spDm"><option>Đồ uống</option><option>Đồ ăn</option><option>Snack</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="spGia">Giá bán (₫)</label><input type="number" class="form-control" id="spGia" placeholder=""></div>
                    <div class="col-md-6"><label class="form-label" for="spDvt">Đơn vị tính</label><select class="form-select" id="spDvt"><option>Lon</option><option>Chai</option><option>Gói</option><option>Ly</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="spTt">Trạng thái</label><select class="form-select" id="spTt"><option>Đang bán</option><option>Ngừng bán</option></select></div>
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
