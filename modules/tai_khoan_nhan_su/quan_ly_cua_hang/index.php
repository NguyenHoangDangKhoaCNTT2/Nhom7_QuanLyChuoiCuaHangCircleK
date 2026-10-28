<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

// kết nối CSDL giống các module khác
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
            $ma_cua_hang   = trim($_POST['ma_cua_hang'] ?? '');
            $ten_cua_hang  = trim($_POST['ten_cua_hang'] ?? '');
            $dia_chi       = trim($_POST['dia_chi'] ?? '');
            $so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
            $trang_thai    = (int)($_POST['trang_thai'] ?? 1);

            if ($ma_cua_hang === '' || $ten_cua_hang === '' || $dia_chi === '') {
                throw new Exception('Vui lòng nhập đầy đủ mã, tên và địa chỉ cửa hàng!');
            }

            // check trùng mã cửa hàng (cột UNIQUE)
            $check = $pdo->prepare("SELECT id FROM cuahang WHERE ma_cua_hang = ? AND id != ?");
            $check->execute([$ma_cua_hang, $id]);
            if ($check->fetch()) {
                throw new Exception("Mã cửa hàng $ma_cua_hang đã tồn tại!");
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE cuahang SET ma_cua_hang=?, ten_cua_hang=?, dia_chi=?, so_dien_thoai=?, trang_thai=? WHERE id=?");
                $stmt->execute([$ma_cua_hang, $ten_cua_hang, $dia_chi, $so_dien_thoai, $trang_thai, $id]);
                $alertMsg = "Đã cập nhật cửa hàng <b>" . htmlspecialchars($ten_cua_hang) . "</b>!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO cuahang (ma_cua_hang, ten_cua_hang, dia_chi, so_dien_thoai, trang_thai) VALUES (?,?,?,?,?)");
                $stmt->execute([$ma_cua_hang, $ten_cua_hang, $dia_chi, $so_dien_thoai, $trang_thai]);
                $alertMsg = "Đã thêm cửa hàng <b>" . htmlspecialchars($ten_cua_hang) . "</b>!";
            }
        }

        if ($action === 'delete') {
            $delId = (int)($_POST['delete_id'] ?? 0);
            $pdo->prepare("DELETE FROM cuahang WHERE id = ?")->execute([$delId]);
            $alertMsg = 'Đã xóa cửa hàng khỏi hệ thống!';
        }
    } catch (PDOException $pe) {
        // cửa hàng đã có nhân viên/sản phẩm/hóa đơn liên quan -> không xóa được do ràng buộc khóa ngoại
        $alertMsg  = 'Không thể xóa: cửa hàng này còn nhân viên, sản phẩm hoặc hóa đơn liên quan! Hãy chuyển trạng thái "Tạm đóng" thay vì xóa.';
        $alertType = 'danger';
    } catch (Exception $ex) {
        $alertMsg  = $ex->getMessage();
        $alertType = 'danger';
    }
}

// tìm kiếm + lọc
$search     = trim($_GET['q'] ?? '');
$filterTt   = $_GET['tt'] ?? '';

$where  = "WHERE 1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (ch.ma_cua_hang LIKE ? OR ch.ten_cua_hang LIKE ? OR ch.dia_chi LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filterTt !== '') {
    $where .= " AND ch.trang_thai = ?";
    $params[] = (int)$filterTt;
}

// phân trang 10 cửa hàng/trang
$moiTrang = 10;
$trang    = max(1, (int)($_GET['trang'] ?? 1));

$dem = $pdo->prepare("SELECT COUNT(*) FROM cuahang ch $where");
$dem->execute($params);
$tongCH    = (int)$dem->fetchColumn();
$tongTrang = max(1, (int)ceil($tongCH / $moiTrang));
$trang     = min($trang, $tongTrang);
$offset    = ($trang - 1) * $moiTrang;

// "Quản lý" không có cột riêng trong cuahang -> suy ra từ nhân viên chức vụ "Quản Lý CH" của cửa hàng đó
$sql = "SELECT ch.*,
        (SELECT nv.ho_ten FROM nhanvien nv WHERE nv.cuahang_id = ch.id AND nv.chuc_vu = 'Quản Lý CH' LIMIT 1) AS ten_quan_ly
        FROM cuahang ch
        $where
        ORDER BY ch.id ASC
        LIMIT $moiTrang OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dsCuaHang = $stmt->fetchAll();

$pageTitle  = 'Quản lý Cửa hàng';
$activeMenu = 'cuahang';

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
                <span class="title-icon" style="background: linear-gradient(135deg, var(--emerald), #059669)"><i class="fa-solid fa-store"></i></span>
                Danh sách cửa hàng
            </h5>
            <div class="panel-subtitle ms-5">Các cửa hàng trong chuỗi Circle K <span class="ms-2 badge rounded-pill bg-light text-dark border">Tổng: <?= $tongCH ?> cửa hàng</span></div>
        </div>
    </div>

    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass s-ico"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm theo mã, tên, địa chỉ...">
        </div>
        <select name="tt" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            <option value="1" <?= $filterTt === '1' ? 'selected' : '' ?>>Đang hoạt động</option>
            <option value="0" <?= $filterTt === '0' ? 'selected' : '' ?>>Tạm đóng</option>
        </select>
        <?php if ($search !== '' || $filterTt !== ''): ?>
            <a href="index.php" class="btn-ck btn-ck-soft text-danger"><i class="fa-solid fa-rotate-left"></i> Đặt lại</a>
        <?php endif; ?>
        <button type="button" class="btn-ck btn-ck-primary ms-auto" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Thêm cửa hàng</button>
    </form>

    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Mã CH</th><th>Tên cửa hàng</th><th>Địa chỉ</th><th>Quản lý</th><th>Điện thoại</th><th>Trạng thái</th><th class="text-end"></th></tr></thead>
            <tbody>
                <?php if (empty($dsCuaHang)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-store-slash fs-3 d-block mb-2"></i>Không tìm thấy cửa hàng nào phù hợp.</td></tr>
                <?php else: ?>
                <?php foreach ($dsCuaHang as $ch): ?>
                <tr>
                    <td><span class="order-code"><?= htmlspecialchars($ch['ma_cua_hang']) ?></span></td>
                    <td><span class="fw-semibold"><?= htmlspecialchars($ch['ten_cua_hang']) ?></span></td>
                    <td><?= htmlspecialchars($ch['dia_chi']) ?></td>
                    <td><?= $ch['ten_quan_ly'] ? htmlspecialchars($ch['ten_quan_ly']) : '<span class="text-muted">Chưa có</span>' ?></td>
                    <td><?= htmlspecialchars($ch['so_dien_thoai'] ?? '') ?></td>
                    <td>
                        <?php if ((int)$ch['trang_thai'] === 1): ?>
                            <span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Hoạt động</span>
                        <?php else: ?>
                            <span class="st-badge st-wait"><i class="fa-solid fa-clock"></i> Tạm đóng</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="action-icon-btn sm" title="Sửa" onclick='openEditModal(<?= json_encode($ch, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                            <button type="button" class="action-icon-btn sm" title="Xóa" onclick="confirmDelete(<?= (int)$ch['id'] ?>, '<?= htmlspecialchars(addslashes($ch['ten_cua_hang'])) ?>')"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- phân trang kiểu 1,2,3... -->
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

<div class="modal fade" id="modalCuaHang" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="" class="modal-content">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="chId" value="0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalCuaHangTitle">Thêm cửa hàng mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="chMa">Mã cửa hàng <span class="text-danger">*</span></label><input type="text" class="form-control" name="ma_cua_hang" id="chMa" placeholder="CK013" required></div>
                    <div class="col-md-6"><label class="form-label" for="chTen">Tên cửa hàng <span class="text-danger">*</span></label><input type="text" class="form-control" name="ten_cua_hang" id="chTen" placeholder="Circle K ..." required></div>
                    <div class="col-md-12"><label class="form-label" for="chDiaChi">Địa chỉ <span class="text-danger">*</span></label><input type="text" class="form-control" name="dia_chi" id="chDiaChi" required></div>
                    <div class="col-md-6"><label class="form-label" for="chSdt">Điện thoại</label><input type="text" class="form-control" name="so_dien_thoai" id="chSdt"></div>
                    <div class="col-md-6">
                        <label class="form-label" for="chTt">Trạng thái</label>
                        <select class="form-select" name="trang_thai" id="chTt">
                            <option value="1">Hoạt động</option>
                            <option value="0">Tạm đóng</option>
                        </select>
                    </div>
                </div>
                <div class="small text-muted mt-2"><i class="fa-solid fa-circle-info me-1"></i>Quản lý cửa hàng được gán qua trang Quản lý Nhân viên (chọn chức vụ "Quản Lý CH").</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn-ck btn-ck-primary">Lưu</button>
            </div>
        </form>
    </div>
</div>

<!-- form ẩn dùng để xóa -->
<form method="POST" action="" id="deleteChForm" class="d-none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="delete_id" id="deleteChId" value="0">
</form>

<script>
function openAddModal() {
    document.getElementById('modalCuaHangTitle').innerText = 'Thêm cửa hàng mới';
    document.getElementById('chId').value = 0;
    document.getElementById('chMa').value = '';
    document.getElementById('chTen').value = '';
    document.getElementById('chDiaChi').value = '';
    document.getElementById('chSdt').value = '';
    document.getElementById('chTt').value = '1';
    new bootstrap.Modal(document.getElementById('modalCuaHang')).show();
}

function openEditModal(ch) {
    document.getElementById('modalCuaHangTitle').innerText = 'Cập nhật: ' + ch.ten_cua_hang;
    document.getElementById('chId').value = ch.id;
    document.getElementById('chMa').value = ch.ma_cua_hang;
    document.getElementById('chTen').value = ch.ten_cua_hang;
    document.getElementById('chDiaChi').value = ch.dia_chi;
    document.getElementById('chSdt').value = ch.so_dien_thoai || '';
    document.getElementById('chTt').value = ch.trang_thai;
    new bootstrap.Modal(document.getElementById('modalCuaHang')).show();
}

function confirmDelete(id, ten) {
    if (confirm('Bạn có chắc chắn muốn xóa cửa hàng [' + ten + '] không?')) {
        document.getElementById('deleteChId').value = id;
        document.getElementById('deleteChForm').submit();
    }
}
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>