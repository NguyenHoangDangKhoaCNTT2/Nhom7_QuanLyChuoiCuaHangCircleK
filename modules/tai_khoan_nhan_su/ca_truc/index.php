<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$vaiTro = $_SESSION['vai_tro'] ?? null;
if (!is_array($vaiTro) || !in_array('ca_truc', $vaiTro, true)) {
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

$phanQuyenId = (int)($_SESSION['phanquyen_id'] ?? 0);
$userId      = (int)($_SESSION['user_id'] ?? 0);

$coDropdown    = false;
$dsCuaHangChon = [];
$chon          = (int)($_REQUEST['xem_cuahang'] ?? 0); 

if ($phanQuyenId === 3) {
    $cuaHangId = (int)($_SESSION['cuahang_id'] ?? 0);
} elseif ($phanQuyenId === 1) {
    $coDropdown    = true;
    $dsCuaHangChon = $pdo->query("SELECT id, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();
    $maHopLe       = array_column($dsCuaHangChon, 'id');
    $cuaHangId     = in_array($chon, $maHopLe, true) ? $chon : (int)($dsCuaHangChon[0]['id'] ?? 0);
} elseif ($phanQuyenId === 2) {
    $khuVuc = layKhuVuc($pdo, $userId);
    $coDropdown = true;
    if (!empty($khuVuc)) {
        $inKv    = implode(',', array_fill(0, count($khuVuc), '?'));
        $stmtKv  = $pdo->prepare("SELECT id, ten_cua_hang FROM cuahang WHERE id IN ($inKv) ORDER BY id ASC");
        $stmtKv->execute($khuVuc);
        $dsCuaHangChon = $stmtKv->fetchAll();
    }
    $cuaHangId = in_array($chon, $khuVuc, true) ? $chon : (int)($dsCuaHangChon[0]['id'] ?? 0);
} else {
    $cuaHangId = 0;
}

if ($cuaHangId <= 0) {
    die('<div style="padding:20px;color:#e11d48;font-family:sans-serif;">Chưa có cửa hàng nào để quản lý ca trực.</div>');
}

$chucVuDuocGiao = $phanQuyenId === 3
    ? ['Nhân Viên Bán Hàng', 'Nhân Viên Kho']
    : ['Quản Lý CH', 'Nhân Viên Bán Hàng', 'Nhân Viên Kho'];

$alertMsg = ''; $alertType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id        = (int)($_POST['id'] ?? 0);
            $nvId      = (int)($_POST['nhanvien_id'] ?? 0);
            $tenCa     = trim($_POST['ten_ca'] ?? '');
            $batDau    = trim($_POST['thoi_gian_bat_dau'] ?? '');
            $ketThuc   = trim($_POST['thoi_gian_ket_thuc'] ?? '');

            if ($nvId <= 0 || $tenCa === '' || $batDau === '' || $ketThuc === '') {
                throw new Exception('Vui lòng nhập đầy đủ thông tin ca trực!');
            }
                        $inCv = implode(',', array_fill(0, count($chucVuDuocGiao), '?'));
            $chkNv = $pdo->prepare("SELECT COUNT(*) FROM nhanvien WHERE id = ? AND cuahang_id = ? AND chuc_vu IN ($inCv)");
            $chkNv->execute(array_merge([$nvId, $cuaHangId], $chucVuDuocGiao));
            if ((int)$chkNv->fetchColumn() === 0) throw new Exception('Bạn không được giao ca trực cho nhân viên này!');

            if ($id > 0) {
                $pdo->prepare("UPDATE calamviec SET nhanvien_id=?, ten_ca=?, thoi_gian_bat_dau=?, thoi_gian_ket_thuc=? WHERE id=? AND cuahang_id=?")
                    ->execute([$nvId, $tenCa, $batDau, $ketThuc, $id, $cuaHangId]);
                $alertMsg = 'Đã cập nhật ca trực!';
            } else {
                $pdo->prepare("INSERT INTO calamviec (cuahang_id, nhanvien_id, ten_ca, thoi_gian_bat_dau, thoi_gian_ket_thuc) VALUES (?,?,?,?,?)")
                    ->execute([$cuaHangId, $nvId, $tenCa, $batDau, $ketThuc]);
                $alertMsg = 'Đã thêm ca trực mới!';
            }
        }

        if ($action === 'checkin' || $action === 'checkout') {
            $id = (int)($_POST['id'] ?? 0);
            $cot = $action === 'checkin' ? 'check_in' : 'check_out';
            $pdo->prepare("UPDATE calamviec SET $cot = NOW() WHERE id = ? AND cuahang_id = ?")->execute([$id, $cuaHangId]);
            $alertMsg = $action === 'checkin' ? 'Đã ghi nhận giờ vào ca!' : 'Đã ghi nhận giờ kết thúc ca!';
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM calamviec WHERE id = ? AND cuahang_id = ?")->execute([$id, $cuaHangId]);
            $alertMsg = 'Đã xóa ca trực!';
        }
    } catch (Exception $ex) {
        $alertMsg  = $ex->getMessage();
        $alertType = 'danger';
    }
}

$inCvDs = implode(',', array_fill(0, count($chucVuDuocGiao), '?'));
$dsNhanVienCH = $pdo->prepare("SELECT id, ho_ten, chuc_vu FROM nhanvien WHERE cuahang_id = ? AND chuc_vu IN ($inCvDs) ORDER BY ho_ten ASC");
$dsNhanVienCH->execute(array_merge([$cuaHangId], $chucVuDuocGiao));
$dsNhanVienCH = $dsNhanVienCH->fetchAll();

$dsCa = $pdo->prepare("SELECT cl.*, nv.ho_ten FROM calamviec cl JOIN nhanvien nv ON nv.id = cl.nhanvien_id WHERE cl.cuahang_id = ? ORDER BY cl.thoi_gian_bat_dau DESC");
$dsCa->execute([$cuaHangId]);
$dsCa = $dsCa->fetchAll();

$tenCH = $pdo->prepare("SELECT ten_cua_hang FROM cuahang WHERE id = ?");
$tenCH->execute([$cuaHangId]);
$tenCuaHang = $tenCH->fetchColumn();

$pageTitle  = 'Ca trực';
$activeMenu = 'ca_truc';

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
    <div class="panel-header flex-wrap gap-2">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--emerald), #059669)"><i class="fa-solid fa-business-time"></i></span>
                Ca trực - <?= htmlspecialchars($tenCuaHang) ?>
            </h5>
            <div class="panel-subtitle ms-5">Lịch trực nhân viên tại cửa hàng <?= $coDropdown ? 'đang chọn' : 'của bạn' ?></div>
        </div>
        <?php if ($coDropdown): ?>
        <form method="get" class="d-inline-block">
            <select name="xem_cuahang" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <?php foreach ($dsCuaHangChon as $ch): ?>
                <option value="<?= (int)$ch['id'] ?>" <?= $cuaHangId === (int)$ch['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ch['ten_cua_hang']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
        <button type="button" class="btn-ck btn-ck-primary ms-auto" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Thêm ca trực</button>
    </div>

    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Nhân viên</th><th>Tên ca</th><th>Bắt đầu</th><th>Kết thúc</th><th>Check-in</th><th>Check-out</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($dsCa)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-calendar-xmark fs-3 d-block mb-2"></i>Chưa có ca trực nào.</td></tr>
                <?php else: foreach ($dsCa as $ca): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($ca['ho_ten']) ?></td>
                    <td><?= htmlspecialchars($ca['ten_ca']) ?></td>
                    <td class="small"><?= date('d/m/Y H:i', strtotime($ca['thoi_gian_bat_dau'])) ?></td>
                    <td class="small"><?= date('d/m/Y H:i', strtotime($ca['thoi_gian_ket_thuc'])) ?></td>
                    <td class="small">
                        <?php if ($ca['check_in']): ?>
                            <span class="text-success"><?= date('H:i d/m', strtotime($ca['check_in'])) ?></span>
                        <?php else: ?>
                            <form method="POST" action="" class="d-inline"><input type="hidden" name="action" value="checkin"><input type="hidden" name="id" value="<?= (int)$ca['id'] ?>"><input type="hidden" name="xem_cuahang" value="<?= $cuaHangId ?>"><button type="submit" class="btn btn-sm btn-outline-success py-0 px-2">Check-in</button></form>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?php if ($ca['check_out']): ?>
                            <span class="text-danger"><?= date('H:i d/m', strtotime($ca['check_out'])) ?></span>
                        <?php else: ?>
                            <form method="POST" action="" class="d-inline"><input type="hidden" name="action" value="checkout"><input type="hidden" name="id" value="<?= (int)$ca['id'] ?>"><input type="hidden" name="xem_cuahang" value="<?= $cuaHangId ?>"><button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">Check-out</button></form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="action-icon-btn sm" title="Sửa" onclick='openEditModal(<?= json_encode($ca, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                            <button type="button" class="action-icon-btn sm" title="Xóa" onclick="confirmDelete(<?= (int)$ca['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalCa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="caId" value="0">
            <input type="hidden" name="xem_cuahang" value="<?= $cuaHangId ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="caTitle">Thêm ca trực</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Nhân viên <span class="text-danger">*</span></label>
                        <select class="form-select" name="nhanvien_id" id="caNv" required>
                            <?php foreach ($dsNhanVienCH as $nv): ?><option value="<?= $nv['id'] ?>"><?= htmlspecialchars($nv['ho_ten']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Tên ca <span class="text-danger">*</span></label><input type="text" class="form-control" name="ten_ca" id="caTen" placeholder="VD: Ca sáng, Ca tối..." required></div>
                    <div class="col-md-6"><label class="form-label">Bắt đầu <span class="text-danger">*</span></label><input type="datetime-local" class="form-control" name="thoi_gian_bat_dau" id="caBd" required></div>
                    <div class="col-md-6"><label class="form-label">Kết thúc <span class="text-danger">*</span></label><input type="datetime-local" class="form-control" name="thoi_gian_ket_thuc" id="caKt" required></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn-ck btn-ck-primary">Lưu</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" action="" id="formXoaCa" class="d-none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="xoaCaId">
    <input type="hidden" name="xem_cuahang" value="<?= $cuaHangId ?>">
</form>

<script>
function openAddModal() {
    document.getElementById('caTitle').innerText = 'Thêm ca trực';
    document.getElementById('caId').value = 0;
    document.getElementById('caNv').selectedIndex = 0;
    document.getElementById('caTen').value = '';
    document.getElementById('caBd').value = '';
    document.getElementById('caKt').value = '';
    new bootstrap.Modal(document.getElementById('modalCa')).show();
}
function toDatetimeLocal(v) { return v ? v.replace(' ', 'T').substring(0, 16) : ''; }
function openEditModal(ca) {
    document.getElementById('caTitle').innerText = 'Cập nhật ca trực';
    document.getElementById('caId').value = ca.id;
    document.getElementById('caNv').value = ca.nhanvien_id;
    document.getElementById('caTen').value = ca.ten_ca;
    document.getElementById('caBd').value = toDatetimeLocal(ca.thoi_gian_bat_dau);
    document.getElementById('caKt').value = toDatetimeLocal(ca.thoi_gian_ket_thuc);
    new bootstrap.Modal(document.getElementById('modalCa')).show();
}
function confirmDelete(id) {
    if (confirm('Xóa ca trực này?')) {
        document.getElementById('xoaCaId').value = id;
        document.getElementById('formXoaCa').submit();
    }
}
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>