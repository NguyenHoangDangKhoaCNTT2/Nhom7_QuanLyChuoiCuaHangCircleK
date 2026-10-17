<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Quản lý Cửa hàng';
$activeMenu = 'cuahang';

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--emerald), #059669)"><i class="fa-solid fa-store"></i></span>
                Danh sách cửa hàng
            </h5>
            <div class="panel-subtitle ms-5">Các cửa hàng trong chuỗi Circle K</div>
        </div>
        
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><div class="search-bar"><i class="fa-solid fa-magnifying-glass s-ico"></i><input type="text" placeholder="Tìm theo mã, tên, địa chỉ..."></div><select class="form-select" style="width: auto;"><option>Tất cả trạng thái</option><option>Đang hoạt động</option><option>Tạm đóng</option></select><button type="button" class="btn-ck btn-ck-primary ms-auto" data-bs-toggle="modal" data-bs-target="#modalCuaHang"><i class="fa-solid fa-plus"></i> Thêm cửa hàng</button></div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Mã CH</th><th>Tên cửa hàng</th><th>Địa chỉ</th><th>Quản lý</th><th>Điện thoại</th><th>Trạng thái</th><th class="text-end"></th></tr></thead>
            <tbody>
                <tr><td><span class="order-code">CK001</span></td><td><span class="fw-semibold">Circle K Quận 1</span></td><td>128 Nguyễn Huệ, Q.1</td><td>Trần Văn B</td><td>028 3822 0001</td><td><span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Hoạt động</span></td><td class="text-end"><div class="d-flex justify-content-end gap-2"><a href="#" class="action-icon-btn sm" title="Sửa"><i class="fa-solid fa-pen"></i></a><a href="#" class="action-icon-btn sm" title="Xóa"><i class="fa-solid fa-trash"></i></a></div></td></tr>
                <tr><td><span class="order-code">CK002</span></td><td><span class="fw-semibold">Circle K Quận 3</span></td><td>45 Võ Văn Tần, Q.3</td><td>Lê Thị C</td><td>028 3822 0002</td><td><span class="st-badge st-wait"><i class="fa-solid fa-clock"></i> Tạm đóng</span></td><td class="text-end"><div class="d-flex justify-content-end gap-2"><a href="#" class="action-icon-btn sm" title="Sửa"><i class="fa-solid fa-pen"></i></a><a href="#" class="action-icon-btn sm" title="Xóa"><i class="fa-solid fa-trash"></i></a></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalCuaHang" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Thêm / sửa cửa hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="chMa">Mã cửa hàng</label><input type="text" class="form-control" id="chMa" placeholder="CK004"></div>
                    <div class="col-md-6"><label class="form-label" for="chTen">Tên cửa hàng</label><input type="text" class="form-control" id="chTen" placeholder="Circle K ..."></div>
                    <div class="col-md-12"><label class="form-label" for="chDiaChi">Địa chỉ</label><input type="text" class="form-control" id="chDiaChi" placeholder=""></div>
                    <div class="col-md-6"><label class="form-label" for="chQl">Quản lý</label><select class="form-select" id="chQl"><option>Chọn nhân viên...</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="chSdt">Điện thoại</label><input type="text" class="form-control" id="chSdt" placeholder=""></div>
                    <div class="col-md-6"><label class="form-label" for="chTt">Trạng thái</label><select class="form-select" id="chTt"><option>Hoạt động</option><option>Tạm đóng</option></select></div>
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
