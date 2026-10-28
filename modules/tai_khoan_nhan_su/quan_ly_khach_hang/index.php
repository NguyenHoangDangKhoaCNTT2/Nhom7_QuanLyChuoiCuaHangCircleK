<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

// kết nối CSDL giống các module khác (circlek)
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

// xử lý thêm / sửa / xóa khách hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id             = (int)($_POST['id'] ?? 0);
            $ho_ten         = trim($_POST['ho_ten'] ?? '');
            $so_dien_thoai  = trim($_POST['so_dien_thoai'] ?? '');
            $thetichdiem_id = !empty($_POST['thetichdiem_id']) ? (int)$_POST['thetichdiem_id'] : null;
            $cuahang_id     = (int)($_POST['cuahang_id'] ?? 0);

            if ($ho_ten === '' || $so_dien_thoai === '' || $cuahang_id <= 0) {
                throw new Exception('Vui lòng nhập đầy đủ họ tên, số điện thoại và chọn cửa hàng!');
            }

            // check trùng số điện thoại (cột UNIQUE trong CSDL)
            $check = $pdo->prepare("SELECT id FROM khachhang WHERE so_dien_thoai = ? AND id != ?");
            $check->execute([$so_dien_thoai, $id]);
            if ($check->fetch()) {
                throw new Exception("Số điện thoại $so_dien_thoai đã được khách hàng khác đăng ký!");
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE khachhang SET ho_ten=?, so_dien_thoai=?, thetichdiem_id=?, cuahang_id=? WHERE id=?");
                $stmt->execute([$ho_ten, $so_dien_thoai, $thetichdiem_id, $cuahang_id, $id]);
                $alertMsg = "Đã cập nhật khách hàng <b>" . htmlspecialchars($ho_ten) . "</b>!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO khachhang (ho_ten, so_dien_thoai, thetichdiem_id, cuahang_id) VALUES (?,?,?,?)");
                $stmt->execute([$ho_ten, $so_dien_thoai, $thetichdiem_id, $cuahang_id]);
                $alertMsg = "Đã thêm khách hàng <b>" . htmlspecialchars($ho_ten) . "</b>!";
            }
        }

        if ($action === 'delete') {
            $delId = (int)($_POST['delete_id'] ?? 0);
            $pdo->prepare("DELETE FROM khachhang WHERE id = ?")->execute([$delId]);
            $alertMsg = 'Đã xóa khách hàng khỏi hệ thống!';
        }
    } catch (Exception $ex) {
        $alertMsg  = $ex->getMessage();
        $alertType = 'danger';
    }
}

// tìm kiếm + lọc theo TÊN hạng thẻ (không lọc theo id vì CSDL có thể có 2 dòng trùng tên khác id)
$search     = trim($_GET['q'] ?? '');
$filterHang = trim($_GET['hang'] ?? '');

$where  = "WHERE 1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (kh.ho_ten LIKE ? OR kh.so_dien_thoai LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterHang !== '') {
    $where .= " AND ttd.hang_the = ?";
    $params[] = $filterHang;
}

// phân trang - mỗi trang 10 khách hàng
$moiTrang = 10;
$trang    = max(1, (int)($_GET['trang'] ?? 1));

$dem = $pdo->prepare("SELECT COUNT(*) FROM khachhang kh LEFT JOIN thetichdiem ttd ON kh.thetichdiem_id = ttd.id $where");
$dem->execute($params);
$tongKhach = (int)$dem->fetchColumn();
$tongTrang = max(1, (int)ceil($tongKhach / $moiTrang));
$trang     = min($trang, $tongTrang); // tránh trang=999 vượt quá dữ liệu
$offset    = ($trang - 1) * $moiTrang;

// sắp xếp id tăng dần -> hiện đúng thứ tự KH0001, KH0002, KH0003...
$sql = "SELECT kh.*, ttd.hang_the
        FROM khachhang kh
        LEFT JOIN thetichdiem ttd ON kh.thetichdiem_id = ttd.id
        $where
        ORDER BY kh.id ASC
        LIMIT $moiTrang OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dsKhachHang = $stmt->fetchAll();

// gộp theo TÊN hạng thẻ để không hiện trùng (vd 2 dòng "Kim Cương" khác id trong CSDL vẫn chỉ hiện 1 lần)
$dsHangThe = $pdo->query("SELECT MIN(id) AS id, hang_the FROM thetichdiem GROUP BY hang_the ORDER BY MIN(id) ASC")->fetchAll();
$dsCuaHang = $pdo->query("SELECT id, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();

$pageTitle  = 'Quản lý Khách hàng';
$activeMenu = 'khachhang';

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
                <span class="title-icon" style="background: linear-gradient(135deg, var(--emerald), #059669)"><i class="fa-solid fa-id-card-clip"></i></span>
                Danh sách khách hàng
            </h5>
            <div class="panel-subtitle ms-5">Hội viên và điểm tích lũy <span class="ms-2 badge rounded-pill bg-light text-dark border">Tổng: <?= $tongKhach ?> khách</span></div>
        </div>
    </div>

    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass s-ico"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm theo tên, số điện thoại...">
        </div>
        <select name="hang" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="">Tất cả hạng thẻ</option>
            <?php foreach ($dsHangThe as $ht): ?>
                <option value="<?= htmlspecialchars($ht['hang_the']) ?>" <?= $filterHang === $ht['hang_the'] ? 'selected' : '' ?>><?= htmlspecialchars($ht['hang_the']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-ck btn-ck-soft"><i class="fa-solid fa-filter"></i> Lọc</button>
        <?php if ($search !== '' || $filterHang !== ''): ?>
            <a href="index.php" class="btn-ck btn-ck-soft text-danger"><i class="fa-solid fa-rotate-left"></i> Đặt lại</a>
        <?php endif; ?>
        <button type="button" class="btn-ck btn-ck-primary ms-auto" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Thêm khách hàng</button>
    </form>

    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Mã KH</th><th>Họ tên</th><th>Điện thoại</th><th>Hạng thẻ</th><th class="text-end">Điểm tích lũy</th><th>Ngày đăng ký</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($dsKhachHang)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-user-slash fs-3 d-block mb-2"></i>Không tìm thấy khách hàng nào phù hợp.</td></tr>
                <?php else: ?>
                <?php foreach ($dsKhachHang as $kh):
                    $hang = $kh['hang_the'] ?? '';
                    $badgeClass = 'st-badge ' . ($hang !== '' ? 'st-ok' : 'st-wait');
                ?>
                <tr>
                    <td><span class="order-code">KH<?= str_pad($kh['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                    <td><span class="fw-semibold"><?= htmlspecialchars($kh['ho_ten']) ?></span></td>
                    <td><?= htmlspecialchars($kh['so_dien_thoai']) ?></td>
                    <td><span class="<?= $badgeClass ?>"><i class="fa-solid fa-circle-check"></i> <?= $hang !== '' ? htmlspecialchars($hang) : 'Chưa xếp hạng' ?></span></td>
                    <td class="text-end"><span class="num-font fw-bold"><?= number_format((int)$kh['diem_tich_luy']) ?></span></td>
                    <td><?= date('d/m/Y', strtotime($kh['ngay_dang_ky'])) ?></td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="action-icon-btn sm" title="Sửa" onclick='openEditModal(<?= json_encode($kh, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                            <button type="button" class="action-icon-btn sm" title="Xóa" onclick="confirmDelete(<?= (int)$kh['id'] ?>, '<?= htmlspecialchars(addslashes($kh['ho_ten'])) ?>')"><i class="fa-solid fa-trash"></i></button>
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

<div class="modal fade" id="modalKhachHang" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="" class="modal-content">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="khId" value="0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalKhachHangTitle">Thêm khách hàng mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="khMa">Mã khách hàng</label><input type="text" class="form-control" id="khMa" readonly></div>
                    <div class="col-md-6"><label class="form-label" for="khTen">Họ và tên <span class="text-danger">*</span></label><input type="text" class="form-control" name="ho_ten" id="khTen" required></div>
                    <div class="col-md-6"><label class="form-label" for="khSdt">Số điện thoại <span class="text-danger">*</span></label><input type="text" class="form-control" name="so_dien_thoai" id="khSdt" required></div>
                    <div class="col-md-6">
                        <label class="form-label" for="khHang">Hạng thẻ</label>
                        <select class="form-select" name="thetichdiem_id" id="khHang">
                            <option value="">Chưa xếp hạng</option>
                            <?php foreach ($dsHangThe as $ht): ?>
                                <option value="<?= $ht['id'] ?>"><?= htmlspecialchars($ht['hang_the']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label" for="khCh">Cửa hàng đăng ký <span class="text-danger">*</span></label>
                        <select class="form-select" name="cuahang_id" id="khCh" required>
                            <?php foreach ($dsCuaHang as $ch): ?>
                                <option value="<?= $ch['id'] ?>"><?= htmlspecialchars($ch['ten_cua_hang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
<form method="POST" action="" id="deleteKhForm" class="d-none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="delete_id" id="deleteKhId" value="0">
</form>

<script>
function openAddModal() {
    document.getElementById('modalKhachHangTitle').innerText = 'Thêm khách hàng mới';
    document.getElementById('khId').value = 0;
    document.getElementById('khMa').value = 'Tự động sau khi lưu';
    document.getElementById('khTen').value = '';
    document.getElementById('khSdt').value = '';
    document.getElementById('khHang').value = '';
    document.getElementById('khCh').selectedIndex = 0;
    new bootstrap.Modal(document.getElementById('modalKhachHang')).show();
}

function openEditModal(kh) {
    document.getElementById('modalKhachHangTitle').innerText = 'Cập nhật khách hàng: ' + kh.ho_ten;
    document.getElementById('khId').value = kh.id;
    document.getElementById('khMa').value = 'KH' + String(kh.id).padStart(4, '0');
    document.getElementById('khTen').value = kh.ho_ten;
    document.getElementById('khSdt').value = kh.so_dien_thoai;
    document.getElementById('khHang').value = kh.thetichdiem_id || '';
    document.getElementById('khCh').value = kh.cuahang_id;
    new bootstrap.Modal(document.getElementById('modalKhachHang')).show();
}

function confirmDelete(id, ten) {
    if (confirm('Bạn có chắc chắn muốn xóa khách hàng [' + ten + '] không?')) {
        document.getElementById('deleteKhId').value = id;
        document.getElementById('deleteKhForm').submit();
    }
}
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>