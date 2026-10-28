<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$host = '127.0.0.1'; $db = 'circlek'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('<div style="padding:20px;color:#e11d48;font-family:sans-serif;"><h3>Không thể kết nối Database!</h3><p>Kiểm tra MySQL trong XAMPP đã Start chưa và đã import file SQL database <b>circlek</b> chưa.</p><small>' . htmlspecialchars($e->getMessage()) . '</small></div>');
}

$alertMsg = ''; $alertType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id            = (int)($_POST['id'] ?? 0);
            $ho_ten        = trim($_POST['ho_ten'] ?? '');
            $so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
            $chuc_vu       = trim($_POST['chuc_vu'] ?? '');
            $cuahang_id    = (int)($_POST['cuahang_id'] ?? 0);
            $luong         = (float)($_POST['luong'] ?? 0);
            $ngay_vao_lam  = trim($_POST['ngay_vao_lam'] ?? '') ?: null;

            if ($ho_ten === '' || $cuahang_id <= 0 || $chuc_vu === '') {
                throw new Exception('Vui lòng nhập đầy đủ họ tên, chức vụ và chọn cửa hàng!');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE nhanvien SET ho_ten=?, so_dien_thoai=?, chuc_vu=?, cuahang_id=?, luong=?, ngay_vao_lam=? WHERE id=?");
                $stmt->execute([$ho_ten, $so_dien_thoai, $chuc_vu, $cuahang_id, $luong, $ngay_vao_lam, $id]);
                $alertMsg = "Đã cập nhật nhân viên <b>" . htmlspecialchars($ho_ten) . "</b>!";
            } else {
                $phanquyen = $pdo->prepare("SELECT id FROM phanquyen WHERE ten_quyen = ?");
                $phanquyen->execute([$chuc_vu]);
                $phanquyen_id = $phanquyen->fetchColumn();
                if (!$phanquyen_id) $phanquyen_id = 4;

                $username = 'nv' . preg_replace('/\D/', '', $so_dien_thoai ?: uniqid());
                $passHash = password_hash('123456', PASSWORD_DEFAULT); 

                $pdo->beginTransaction();
                $uStmt = $pdo->prepare("INSERT INTO nguoidung (username, password, phanquyen_id) VALUES (?,?,?)");
                $uStmt->execute([$username, $passHash, $phanquyen_id]);
                $nguoidung_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO nhanvien (nguoidung_id, cuahang_id, ho_ten, so_dien_thoai, chuc_vu, luong, ngay_vao_lam) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$nguoidung_id, $cuahang_id, $ho_ten, $so_dien_thoai, $chuc_vu, $luong, $ngay_vao_lam]);
                $pdo->commit();

                $alertMsg = "Đã thêm nhân viên <b>" . htmlspecialchars($ho_ten) . "</b>! Tài khoản đăng nhập: <b>$username</b> / mật khẩu mặc định <b>123456</b>";
            }
        }

        if ($action === 'delete') {
            $delId = (int)($_POST['delete_id'] ?? 0);
            $pdo->prepare("DELETE FROM nhanvien WHERE id = ?")->execute([$delId]);
            $alertMsg = 'Đã xóa nhân viên khỏi hệ thống!';
        }
    } catch (PDOException $pe) {
        $alertMsg  = 'Không thể xóa: nhân viên này đã có hóa đơn hoặc phiếu liên quan trong hệ thống!';
        $alertType = 'danger';
        if ($pdo->inTransaction()) $pdo->rollBack();
    } catch (Exception $ex) {
        $alertMsg  = $ex->getMessage();
        $alertType = 'danger';
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}

// tìm kiếm + lọc
$search      = trim($_GET['q'] ?? '');
$filterCh    = (int)($_GET['cuahang'] ?? 0);
$filterCv    = trim($_GET['chucvu'] ?? '');

$where  = "WHERE 1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (nv.ho_ten LIKE ? OR nv.so_dien_thoai LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterCh > 0) {
    $where .= " AND nv.cuahang_id = ?";
    $params[] = $filterCh;
}
if ($filterCv !== '') {
    $where .= " AND nv.chuc_vu = ?";
    $params[] = $filterCv;
}

$moiTrang = 10;
$trang    = max(1, (int)($_GET['trang'] ?? 1));

$dem = $pdo->prepare("SELECT COUNT(*) FROM nhanvien nv $where");
$dem->execute($params);
$tongNV    = (int)$dem->fetchColumn();
$tongTrang = max(1, (int)ceil($tongNV / $moiTrang));
$trang     = min($trang, $tongTrang);
$offset    = ($trang - 1) * $moiTrang;

$sql = "SELECT nv.*, ch.ten_cua_hang
        FROM nhanvien nv
        LEFT JOIN cuahang ch ON nv.cuahang_id = ch.id
        $where
        ORDER BY nv.id ASC
        LIMIT $moiTrang OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dsNhanVien = $stmt->fetchAll();

$maNvTiepTheo = (int)$pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nhanvien'")->fetchColumn();

$pageTitle  = 'Quản lý Nhân viên';
$activeMenu = 'nhanvien';

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
                <span class="title-icon" style="background: linear-gradient(135deg, var(--emerald), #059669)"><i class="fa-solid fa-user-clock"></i></span>
                Danh sách nhân viên
            </h5>
            <div class="panel-subtitle ms-5">Hồ sơ nhân viên toàn chuỗi <span class="ms-2 badge rounded-pill bg-light text-dark border">Tổng: <?= $tongNV ?> người</span></div>
        </div>
    </div>

    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass s-ico"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm theo tên, số điện thoại...">
        </div>
        <select name="cuahang" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="0">Tất cả cửa hàng</option>
            <?php foreach ($dsCuaHang as $ch): ?>
                <option value="<?= $ch['id'] ?>" <?= $filterCh === (int)$ch['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ch['ten_cua_hang']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="chucvu" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="">Tất cả chức vụ</option>
            <?php foreach ($dsChucVu as $cv): ?>
                <option value="<?= htmlspecialchars($cv['ten_quyen']) ?>" <?= $filterCv === $cv['ten_quyen'] ? 'selected' : '' ?>><?= htmlspecialchars($cv['ten_quyen']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($search !== '' || $filterCh > 0 || $filterCv !== ''): ?>
            <a href="index.php" class="btn-ck btn-ck-soft text-danger"><i class="fa-solid fa-rotate-left"></i> Đặt lại</a>
        <?php endif; ?>
        <button type="button" class="btn-ck btn-ck-primary ms-auto" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Thêm nhân viên</button>
    </form>

    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Mã NV</th><th>Họ tên</th><th>Điện thoại</th><th>Chức vụ</th><th>Cửa hàng</th><th class="text-end">Lương</th><th>Ngày vào làm</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($dsNhanVien)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted"><i class="fa-solid fa-user-slash fs-3 d-block mb-2"></i>Không tìm thấy nhân viên nào phù hợp.</td></tr>
                <?php else: ?>
                <?php foreach ($dsNhanVien as $nv): ?>
                <tr>
                    <td><span class="order-code">NV<?= str_pad($nv['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                    <td><span class="fw-semibold"><?= htmlspecialchars($nv['ho_ten']) ?></span></td>
                    <td><?= htmlspecialchars($nv['so_dien_thoai'] ?? '') ?></td>
                    <td><?= htmlspecialchars($nv['chuc_vu'] ?? '') ?></td>
                    <td><?= htmlspecialchars($nv['ten_cua_hang'] ?? '') ?></td>
                    <td class="text-end"><span class="num-font fw-bold"><?= number_format((float)$nv['luong']) ?></span></td>
                    <td><?= $nv['ngay_vao_lam'] ? date('d/m/Y', strtotime($nv['ngay_vao_lam'])) : '—' ?></td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="action-icon-btn sm" title="Sửa" onclick='openEditModal(<?= json_encode($nv, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                            <button type="button" class="action-icon-btn sm" title="Xóa" onclick="confirmDelete(<?= (int)$nv['id'] ?>, '<?= htmlspecialchars(addslashes($nv['ho_ten'])) ?>')"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($tongTrang > 1): ?>
    <div class="d-flex flex-wrap justify-content-center align-items-center gap-2 mt-3">
        <?php
        $qs = $_GET; unset($qs['trang']);
        $qsPrefix = ($s = http_build_query($qs)) !== '' ? $s . '&' : '';
        ?>
        <a href="?<?= $qsPrefix ?>trang=<?= max(1, $trang - 1) ?>" class="btn btn-sm btn-outline-secondary <?= $trang === 1 ? 'disabled' : '' ?>">Trước</a>
        <?php for ($p = 1; $p <= $tongTrang; $p++): ?>
            <a href="?<?= $qsPrefix ?>trang=<?= $p ?>" class="btn btn-sm <?= $p === $trang ? 'btn-danger' : 'btn-outline-secondary' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a href="?<?= $qsPrefix ?>trang=<?= min($tongTrang, $trang + 1) ?>" class="btn btn-sm btn-outline-secondary <?= $trang === $tongTrang ? 'disabled' : '' ?>">Sau</a>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalNhanVien" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="" class="modal-content">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="nvId" value="0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalNhanVienTitle">Thêm nhân viên mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="nvMa">Mã nhân viên</label><input type="text" class="form-control" id="nvMa" readonly></div>
                    <div class="col-md-6"><label class="form-label" for="nvTen">Họ và tên <span class="text-danger">*</span></label><input type="text" class="form-control" name="ho_ten" id="nvTen" required></div>
                    <div class="col-md-6">
                        <label class="form-label" for="nvCv">Chức vụ <span class="text-danger">*</span></label>
                        <select class="form-select" name="chuc_vu" id="nvCv" required>
                            <?php foreach ($dsChucVu as $cv): ?>
                                <option value="<?= htmlspecialchars($cv['ten_quyen']) ?>"><?= htmlspecialchars($cv['ten_quyen']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="nvCh">Cửa hàng <span class="text-danger">*</span></label>
                        <select class="form-select" name="cuahang_id" id="nvCh" required>
                            <?php foreach ($dsCuaHang as $ch): ?>
                                <option value="<?= $ch['id'] ?>"><?= htmlspecialchars($ch['ten_cua_hang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label" for="nvSdt">Số điện thoại</label><input type="text" class="form-control" name="so_dien_thoai" id="nvSdt"></div>
                    <div class="col-md-6"><label class="form-label" for="nvLuong">Lương (đ)</label><input type="number" class="form-control" name="luong" id="nvLuong" min="0" step="100000"></div>
                    <div class="col-md-6"><label class="form-label" for="nvNgay">Ngày vào làm</label><input type="date" class="form-control" name="ngay_vao_lam" id="nvNgay"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn-ck btn-ck-primary">Lưu</button>
            </div>
        </form>
    </div>
</div>

<!-- form ẩn dùng để xóa -->
<form method="POST" action="" id="deleteNvForm" class="d-none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="delete_id" id="deleteNvId" value="0">
</form>

<script>
function openAddModal() {
    document.getElementById('modalNhanVienTitle').innerText = 'Thêm nhân viên mới';
    document.getElementById('nvId').value = 0;
    document.getElementById('nvMa').value = 'NV<?= str_pad($maNvTiepTheo, 4, '0', STR_PAD_LEFT) ?>';
    document.getElementById('nvTen').value = '';
    document.getElementById('nvCv').selectedIndex = 0;
    document.getElementById('nvCh').selectedIndex = 0;
    document.getElementById('nvSdt').value = '';
    document.getElementById('nvLuong').value = '';
    document.getElementById('nvNgay').value = '';
    new bootstrap.Modal(document.getElementById('modalNhanVien')).show();
}

function openEditModal(nv) {
    document.getElementById('modalNhanVienTitle').innerText = 'Cập nhật nhân viên: ' + nv.ho_ten;
    document.getElementById('nvId').value = nv.id;
    document.getElementById('nvMa').value = 'NV' + String(nv.id).padStart(4, '0');
    document.getElementById('nvTen').value = nv.ho_ten;
    document.getElementById('nvCv').value = nv.chuc_vu || '';
    document.getElementById('nvCh').value = nv.cuahang_id;
    document.getElementById('nvSdt').value = nv.so_dien_thoai || '';
    document.getElementById('nvLuong').value = nv.luong || 0;
    document.getElementById('nvNgay').value = nv.ngay_vao_lam || '';
    new bootstrap.Modal(document.getElementById('modalNhanVien')).show();
}

function confirmDelete(id, ten) {
    if (confirm('Bạn có chắc chắn muốn xóa nhân viên [' + ten + '] không?')) {
        document.getElementById('deleteNvId').value = id;
        document.getElementById('deleteNvForm').submit();
    }
}
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>