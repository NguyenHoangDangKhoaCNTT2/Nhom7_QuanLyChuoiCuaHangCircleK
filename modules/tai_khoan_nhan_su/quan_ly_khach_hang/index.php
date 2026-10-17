<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Quản lý Khách hàng';
$activeMenu = 'khachhang';

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--emerald), #059669)"><i class="fa-solid fa-id-card-clip"></i></span>
                Danh sách khách hàng
            </h5>
            <div class="panel-subtitle ms-5">Hội viên và điểm tích lũy</div>
        </div>
        
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><div class="search-bar"><i class="fa-solid fa-magnifying-glass s-ico"></i><input type="text" placeholder="Tìm theo tên, số điện thoại..."></div><select class="form-select" style="width: auto;"><option>Tất cả hạng thẻ</option><option>Thường</option><option>Bạc</option><option>Vàng</option></select><button type="button" class="btn-ck btn-ck-primary ms-auto" data-bs-toggle="modal" data-bs-target="#modalKhachHang"><i class="fa-solid fa-plus"></i> Thêm khách hàng</button></div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Mã thẻ</th><th>Họ tên</th><th>Điện thoại</th><th>Hạng thẻ</th><th class="text-end">Điểm tích lũy</th><th>Ngày đăng ký</th><th></th></tr></thead>
            <tbody>
                <tr><td><span class="order-code">KH0001</span></td><td><span class="fw-semibold">Phạm Minh E</span></td><td>0901 234 567</td><td><span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Vàng</span></td><td class="text-end"><span class="num-font fw-bold ">1,250</span></td><td>12/03/2026</td><td><div class="d-flex justify-content-end gap-2"><a href="#" class="action-icon-btn sm" title="Sửa"><i class="fa-solid fa-pen"></i></a><a href="#" class="action-icon-btn sm" title="Xóa"><i class="fa-solid fa-trash"></i></a></div></td></tr>
                <tr><td><span class="order-code">KH0002</span></td><td><span class="fw-semibold">Lê Văn D</span></td><td>0912 345 678</td><td><span class="st-badge st-wait"><i class="fa-solid fa-clock"></i> Bạc</span></td><td class="text-end"><span class="num-font fw-bold ">480</span></td><td>25/06/2026</td><td><div class="d-flex justify-content-end gap-2"><a href="#" class="action-icon-btn sm" title="Sửa"><i class="fa-solid fa-pen"></i></a><a href="#" class="action-icon-btn sm" title="Xóa"><i class="fa-solid fa-trash"></i></a></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalKhachHang" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Thêm / sửa khách hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="khMa">Mã thẻ</label><input type="text" class="form-control" id="khMa" placeholder="KH0003"></div>
                    <div class="col-md-6"><label class="form-label" for="khTen">Họ và tên</label><input type="text" class="form-control" id="khTen" placeholder=""></div>
                    <div class="col-md-6"><label class="form-label" for="khSdt">Số điện thoại</label><input type="text" class="form-control" id="khSdt" placeholder=""></div>
                    <div class="col-md-6"><label class="form-label" for="khHang">Hạng thẻ</label><select class="form-select" id="khHang"><option>Thường</option><option>Bạc</option><option>Vàng</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="khNs">Ngày sinh</label><input type="date" class="form-control" id="khNs" placeholder=""></div>
                    <div class="col-md-6"><label class="form-label" for="khMail">Email</label><input type="email" class="form-control" id="khMail" placeholder=""></div>
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
