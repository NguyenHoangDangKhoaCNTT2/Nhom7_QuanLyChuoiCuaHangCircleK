<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Quản lý Nhân viên';
$activeMenu = 'nhanvien';

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--emerald), #059669)"><i class="fa-solid fa-user-clock"></i></span>
                Danh sách nhân viên
            </h5>
            <div class="panel-subtitle ms-5">Hồ sơ nhân viên toàn chuỗi</div>
        </div>
        
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><div class="search-bar"><i class="fa-solid fa-magnifying-glass s-ico"></i><input type="text" placeholder="Tìm theo mã, họ tên..."></div><select class="form-select" style="width: auto;"><option>Tất cả cửa hàng</option><option>Circle K Quận 1</option><option>Circle K Quận 3</option></select><select class="form-select" style="width: auto;"><option>Tất cả ca</option><option>Ca 1</option><option>Ca 2</option><option>Ca 3</option></select><button type="button" class="btn-ck btn-ck-primary ms-auto" data-bs-toggle="modal" data-bs-target="#modalNhanVien"><i class="fa-solid fa-plus"></i> Thêm nhân viên</button></div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Mã NV</th><th>Họ tên</th><th>Chức vụ</th><th>Cửa hàng</th><th>Ca làm</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
                <tr><td><span class="order-code">NV001</span></td><td><span class="fw-semibold">Nguyễn Văn B</span></td><td>Thu ngân</td><td>Circle K Quận 1</td><td>Ca 2</td><td><span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Đang làm</span></td><td><div class="d-flex justify-content-end gap-2"><a href="#" class="action-icon-btn sm" title="Sửa"><i class="fa-solid fa-pen"></i></a><a href="#" class="action-icon-btn sm" title="Xóa"><i class="fa-solid fa-trash"></i></a></div></td></tr>
                <tr><td><span class="order-code">NV002</span></td><td><span class="fw-semibold">Trương Thị C</span></td><td>Quản lý ca</td><td>Circle K Quận 3</td><td>Ca 1</td><td><span class="st-badge st-wait"><i class="fa-solid fa-clock"></i> Nghỉ phép</span></td><td><div class="d-flex justify-content-end gap-2"><a href="#" class="action-icon-btn sm" title="Sửa"><i class="fa-solid fa-pen"></i></a><a href="#" class="action-icon-btn sm" title="Xóa"><i class="fa-solid fa-trash"></i></a></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalNhanVien" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Thêm / sửa nhân viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="nvMa">Mã nhân viên</label><input type="text" class="form-control" id="nvMa" placeholder="NV003"></div>
                    <div class="col-md-6"><label class="form-label" for="nvTen">Họ và tên</label><input type="text" class="form-control" id="nvTen" placeholder=""></div>
                    <div class="col-md-6"><label class="form-label" for="nvCv">Chức vụ</label><select class="form-select" id="nvCv"><option>Thu ngân</option><option>Quản lý ca</option><option>Thủ kho</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="nvCh">Cửa hàng</label><select class="form-select" id="nvCh"><option>Circle K Quận 1</option><option>Circle K Quận 3</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="nvCa">Ca làm</label><select class="form-select" id="nvCa"><option>Ca 1</option><option>Ca 2</option><option>Ca 3</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="nvSdt">Số điện thoại</label><input type="text" class="form-control" id="nvSdt" placeholder=""></div>
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
