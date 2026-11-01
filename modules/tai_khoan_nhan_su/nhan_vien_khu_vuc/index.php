<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$vaiTro = $_SESSION['vai_tro'] ?? null;
if (!is_array($vaiTro) || !in_array('nhanvien_khuvuc', $vaiTro, true)) {
    header('Location: ' . url('index.php'));
    exit;
}

$host = '127.0.0.1'; $db = 'circlek'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('<div style="padding:20px;color:#e11d48;font-family:sans-serif;">Không thể kết nối CSDL.</div>');
}

$quanlytruongId = (int)($_SESSION['user_id'] ?? 0);
$laGiamDoc      = (int)($_SESSION['phanquyen_id'] ?? 0) === 1;
if ($laGiamDoc) {
    $khuVuc = array_map('intval', $pdo->query("SELECT id FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN));
} else {
    $khuVuc = layKhuVuc($pdo, $quanlytruongId); 
}
if (empty($khuVuc)) $khuVuc = [0]; 

$alertMsg = ''; $alertType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'sua') {
            $id            = (int)($_POST['id'] ?? 0);
            $ho_ten        = trim($_POST['ho_ten'] ?? '');
            $so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
            $chuc_vu       = trim($_POST['chuc_vu'] ?? '');
            $cuahang_id    = (int)($_POST['cuahang_id'] ?? 0);
            $luong         = (float)($_POST['luong'] ?? 0);

            if ($ho_ten === '' || $chuc_vu === '' || !in_array($cuahang_id, $khuVuc, true)) {
                throw new Exception('Vui lòng nhập đủ thông tin và chọn đúng cửa hàng trong khu vực bạn phụ trách!');
            }
            $chk = $pdo->prepare("SELECT cuahang_id FROM nhanvien WHERE id = ?");
            $chk->execute([$id]);
            if (!in_array((int)$chk->fetchColumn(), $khuVuc, true)) {
                throw new Exception('Nhân viên này không thuộc khu vực bạn phụ trách!');
            }

            $stmt = $pdo->prepare("UPDATE nhanvien SET ho_ten=?, so_dien_thoai=?, chuc_vu=?, cuahang_id=?, luong=? WHERE id=?");
            $stmt->execute([$ho_ten, $so_dien_thoai, $chuc_vu, $cuahang_id, $luong, $id]);

            $pq = $pdo->prepare("SELECT id FROM phanquyen WHERE ten_quyen = ?");
            $pq->execute([$chuc_vu]);
            $phanquyen_id = $pq->fetchColumn();
            if ($phanquyen_id) {
                $pdo->prepare("UPDATE nguoidung SET phanquyen_id = ? WHERE id = (SELECT nguoidung_id FROM nhanvien WHERE id = ?)")->execute([$phanquyen_id, $id]);
            }
            $alertMsg = "Đã cập nhật nhân viên <b>" . htmlspecialchars($ho_ten) . "</b>!";
        }

        // thêm mới 
        if ($action === 'yc_them') {
            $cuahang_id = (int)($_POST['cuahang_id'] ?? 0);
            $ho_ten     = trim($_POST['ho_ten'] ?? '');
            $sdt        = trim($_POST['so_dien_thoai'] ?? '');
            $chuc_vu    = trim($_POST['chuc_vu'] ?? '');
            $luong      = (float)($_POST['luong'] ?? 0);

            if ($ho_ten === '' || $chuc_vu === '' || !in_array($cuahang_id, $khuVuc, true)) {
                throw new Exception('Vui lòng nhập đủ thông tin và chọn đúng cửa hàng trong khu vực bạn phụ trách!');
            }
            $stmt = $pdo->prepare("INSERT INTO yeucau_nhansu (loai, quanlytruong_id, cuahang_id, ho_ten, so_dien_thoai, chuc_vu, luong) VALUES ('them', ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$quanlytruongId, $cuahang_id, $ho_ten, $sdt, $chuc_vu, $luong]);
            $alertMsg = "Đã gửi yêu cầu thêm nhân viên <b>" . htmlspecialchars($ho_ten) . "</b>, chờ Giám Đốc duyệt!";
        }

        // xóa 
        if ($action === 'yc_xoa') {
            $nhanvien_id = (int)($_POST['nhanvien_id'] ?? 0);
            $chk = $pdo->prepare("SELECT cuahang_id, ho_ten FROM nhanvien WHERE id = ?");
            $chk->execute([$nhanvien_id]);
            $tt = $chk->fetch();
            if (!$tt || !in_array((int)$tt['cuahang_id'], $khuVuc, true)) {
                throw new Exception('Nhân viên này không thuộc khu vực bạn phụ trách!');
            }
            $daCo = $pdo->prepare("SELECT COUNT(*) FROM yeucau_nhansu WHERE loai='xoa' AND nhanvien_id = ? AND trang_thai = 'cho_duyet'");
            $daCo->execute([$nhanvien_id]);
            if ((int)$daCo->fetchColumn() > 0) {
                throw new Exception('Nhân viên này đã có 1 yêu cầu nghỉ việc đang chờ duyệt rồi!');
            }
            $stmt = $pdo->prepare("INSERT INTO yeucau_nhansu (loai, quanlytruong_id, cuahang_id, nhanvien_id, ho_ten) VALUES ('xoa', ?, ?, ?, ?)");
            $stmt->execute([$quanlytruongId, (int)$tt['cuahang_id'], $nhanvien_id, $tt['ho_ten']]);
            $alertMsg = "Đã gửi yêu cầu cho nghỉ việc <b>" . htmlspecialchars($tt['ho_ten']) . "</b>, chờ Giám Đốc duyệt!";
        }
    } catch (Exception $ex) {
        $alertMsg  = $ex->getMessage();
        $alertType = 'danger';
    }
}

$inKhuVuc = implode(',', array_fill(0, count($khuVuc), '?'));

$search   = trim($_GET['q'] ?? '');
$filterCh = (int)($_GET['cuahang'] ?? 0);
$where    = "WHERE nv.cuahang_id IN ($inKhuVuc) AND nv.chuc_vu NOT IN ('Giám Đốc', 'Quản Lý Trưởng')";
$params   = $khuVuc;
if ($search !== '') {
    $where .= " AND (nv.ho_ten LIKE ? OR nv.so_dien_thoai LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filterCh > 0 && in_array($filterCh, $khuVuc, true)) {
    $where .= " AND nv.cuahang_id = ?";
    $params[] = $filterCh;
}

$stmt = $pdo->prepare("SELECT nv.*, ch.ten_cua_hang FROM nhanvien nv LEFT JOIN cuahang ch ON ch.id = nv.cuahang_id $where ORDER BY nv.cuahang_id ASC, nv.id ASC");
$stmt->execute($params);
$dsNhanVien = $stmt->fetchAll();

$stmtCh = $pdo->prepare("SELECT id, ten_cua_hang FROM cuahang WHERE id IN ($inKhuVuc) ORDER BY id ASC");
$stmtCh->execute($khuVuc);
$dsCuaHangKhuVuc = $stmtCh->fetchAll();

$dsChucVuChon = $pdo->query("SELECT id, ten_quyen FROM phanquyen WHERE id IN (3,4,5) ORDER BY id ASC")->fetchAll();

$stmtYc = $pdo->prepare("SELECT yc.*, ch.ten_cua_hang FROM yeucau_nhansu yc LEFT JOIN cuahang ch ON ch.id = yc.cuahang_id WHERE yc.quanlytruong_id = ? ORDER BY yc.ngay_yeu_cau DESC LIMIT 15");
$stmtYc->execute([$quanlytruongId]);
$dsYeuCau = $stmtYc->fetchAll();

$pageTitle  = 'Nhân viên khu vực';
$activeMenu = 'nhanvien_khuvuc';

require ROOT_PATH . '/includes/header.php';
?>

<?php if ($alertMsg !== ''): ?>
<div class="alert alert-<?= htmlspecialchars($alertType) ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-3" role="alert">
    <i class="fa-solid <?= $alertType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> me-2"></i>
    <?= $alertMsg ?>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
</div>
<?php endif; ?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--emerald), #059669)"><i class="fa-solid fa-people-roof"></i></span>
                Nhân viên khu vực
            </h5>
            <div class="panel-subtitle ms-5">Các cửa hàng bạn phụ trách <span class="ms-2 badge rounded-pill bg-light text-dark border" style="font-size:0.85rem;">Tổng: <?= count($dsNhanVien) ?> người</span></div>
        </div>
    </div>

    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass s-ico"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm theo tên, số điện thoại...">
        </div>
            <select name="cuahang" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="0">Tất cả cửa hàng khu vực</option>
            <?php foreach ($dsCuaHangKhuVuc as $ch): ?>
                <option value="<?= $ch['id'] ?>" <?= $filterCh === (int)$ch['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ch['ten_cua_hang']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-ck btn-ck-soft"><i class="fa-solid fa-filter"></i> Lọc</button>
        <button type="button" class="btn-ck btn-ck-primary ms-auto" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Yêu cầu thêm nhân viên</button>
    </form>

    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Mã NV</th><th>Họ tên</th><th>Điện thoại</th><th>Chức vụ</th><th>Cửa hàng</th><th class="text-end">Lương</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($dsNhanVien)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-user-slash fs-3 d-block mb-2"></i>Chưa có nhân viên nào.</td></tr>
                <?php else: foreach ($dsNhanVien as $nv): ?>
                <tr>
                    <td><span class="order-code">NV<?= str_pad($nv['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                    <td><span class="fw-semibold"><?= htmlspecialchars($nv['ho_ten']) ?></span></td>
                    <td><?= htmlspecialchars($nv['so_dien_thoai'] ?? '') ?></td>
                    <td><?= htmlspecialchars($nv['chuc_vu'] ?? '') ?></td>
                    <td><?= htmlspecialchars($nv['ten_cua_hang'] ?? '') ?></td>
                    <td class="text-end"><span class="num-font fw-bold"><?= number_format((float)$nv['luong']) ?></span></td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="action-icon-btn sm" title="Sửa" onclick='openEditModal(<?= json_encode($nv, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                            <button type="button" class="action-icon-btn sm" title="Đề xuất cho nghỉ" onclick="confirmXoa(<?= (int)$nv['id'] ?>, '<?= htmlspecialchars(addslashes($nv['ho_ten'])) ?>')"><i class="fa-solid fa-user-xmark"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="bento-panel fade-up d-1 mt-4">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-amber), #f97316)"><i class="fa-solid fa-clock-rotate-left"></i></span>
                Yêu cầu đã gửi
            </h5>
            <div class="panel-subtitle ms-5">Theo dõi trạng thái Giám Đốc duyệt</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Loại</th><th>Họ tên</th><th>Cửa hàng</th><th>Ngày gửi</th><th>Trạng thái</th></tr></thead>
            <tbody>
                <?php if (empty($dsYeuCau)): ?>
                <tr><td colspan="5" class="text-center py-3 text-muted">Chưa gửi yêu cầu nào.</td></tr>
                <?php else: foreach ($dsYeuCau as $yc):
                    $mauTT = ['cho_duyet' => '#d97706', 'da_duyet' => '#059669', 'tu_choi' => 'var(--ck-red)'][$yc['trang_thai']];
                    $tenTT = ['cho_duyet' => 'Chờ duyệt', 'da_duyet' => 'Đã duyệt', 'tu_choi' => 'Từ chối'][$yc['trang_thai']];
                ?>
                <tr>
                    <td><?= $yc['loai'] === 'them' ? 'Thêm nhân viên' : 'Cho nghỉ việc' ?></td>
                    <td><?= htmlspecialchars($yc['ho_ten']) ?></td>
                    <td><?= htmlspecialchars($yc['ten_cua_hang'] ?? '') ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($yc['ngay_yeu_cau'])) ?></td>
                    <td><span class="fw-bold" style="color: <?= $mauTT ?>;"><?= $tenTT ?></span></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalThemNV" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="" class="modal-content">
            <input type="hidden" name="action" value="yc_them">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Yêu cầu thêm nhân viên mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2" style="font-size:0.85rem;">Yêu cầu này sẽ gửi cho Giám Đốc duyệt, nhân viên chỉ chính thức được thêm sau khi duyệt.</div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Họ và tên <span class="text-danger">*</span></label><input type="text" class="form-control" name="ho_ten" required></div>
                    <div class="col-md-6">
                        <label class="form-label">Chức vụ <span class="text-danger">*</span></label>
                        <select class="form-select" name="chuc_vu" required>
                            <?php foreach ($dsChucVuChon as $cv): ?><option value="<?= htmlspecialchars($cv['ten_quyen']) ?>"><?= htmlspecialchars($cv['ten_quyen']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cửa hàng <span class="text-danger">*</span></label>
                        <select class="form-select" name="cuahang_id" required>
                            <?php foreach ($dsCuaHangKhuVuc as $ch): ?><option value="<?= $ch['id'] ?>"><?= htmlspecialchars($ch['ten_cua_hang']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Số điện thoại</label><input type="text" class="form-control" name="so_dien_thoai"></div>
                    <div class="col-md-6"><label class="form-label">Lương đề xuất (đ)</label><input type="number" class="form-control" name="luong" min="0" step="100000"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn-ck btn-ck-primary">Gửi yêu cầu</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalSuaNV" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="" class="modal-content">
            <input type="hidden" name="action" value="sua">
            <input type="hidden" name="id" id="svId">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="svTitle">Cập nhật nhân viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Họ và tên <span class="text-danger">*</span></label><input type="text" class="form-control" name="ho_ten" id="svTen" required></div>
                    <div class="col-md-6">
                        <label class="form-label">Chức vụ <span class="text-danger">*</span></label>
                        <select class="form-select" name="chuc_vu" id="svCv" required>
                            <?php foreach ($dsChucVuChon as $cv): ?><option value="<?= htmlspecialchars($cv['ten_quyen']) ?>"><?= htmlspecialchars($cv['ten_quyen']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cửa hàng <span class="text-danger">*</span></label>
                        <select class="form-select" name="cuahang_id" id="svCh" required>
                            <?php foreach ($dsCuaHangKhuVuc as $ch): ?><option value="<?= $ch['id'] ?>"><?= htmlspecialchars($ch['ten_cua_hang']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Số điện thoại</label><input type="text" class="form-control" name="so_dien_thoai" id="svSdt"></div>
                    <div class="col-md-6"><label class="form-label">Lương (đ)</label><input type="number" class="form-control" name="luong" id="svLuong" min="0" step="100000"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn-ck btn-ck-primary">Lưu</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" action="" id="formXoaNV" class="d-none">
    <input type="hidden" name="action" value="yc_xoa">
    <input type="hidden" name="nhanvien_id" id="xoaNvId">
</form>

<script>
function openAddModal() { new bootstrap.Modal(document.getElementById('modalThemNV')).show(); }
function openEditModal(nv) {
    document.getElementById('svTitle').innerText = 'Cập nhật: ' + nv.ho_ten;
    document.getElementById('svId').value = nv.id;
    document.getElementById('svTen').value = nv.ho_ten;
    document.getElementById('svCv').value = nv.chuc_vu || '';
    document.getElementById('svCh').value = nv.cuahang_id;
    document.getElementById('svSdt').value = nv.so_dien_thoai || '';
    document.getElementById('svLuong').value = nv.luong || 0;
    new bootstrap.Modal(document.getElementById('modalSuaNV')).show();
}
function confirmXoa(id, ten) {
    if (confirm('Gửi yêu cầu cho nghỉ việc [' + ten + ']? Yêu cầu này cần Giám Đốc duyệt mới có hiệu lực.')) {
        document.getElementById('xoaNvId').value = id;
        document.getElementById('formXoaNV').submit();
    }
}
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>