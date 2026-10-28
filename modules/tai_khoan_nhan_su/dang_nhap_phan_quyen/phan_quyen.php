<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$host = '127.0.0.1'; $db = 'circlek'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('<div style="padding:20px;color:#e11d48;font-family:sans-serif;">Không thể kết nối CSDL.</div>');
}

$coTheSua = isset($_SESSION['vai_tro']) && count($_SESSION['vai_tro']) >= 4;
$thongBao = ''; $loaiTB = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $coTheSua) {
    $targetId = (int)($_POST['nguoidung_id'] ?? 0);
    $action   = $_POST['action'] ?? '';

    try {
        if ($action === 'luu_quyen') {
            $nhomChon = array_unique($_POST['nhom'] ?? []); 
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM phanquyen_tuychinh WHERE nguoidung_id = ?")->execute([$targetId]);
            $insert = $pdo->prepare("INSERT INTO phanquyen_tuychinh (nguoidung_id, nhom) VALUES (?, ?)");
            foreach ($nhomChon as $nhom) {
                $insert->execute([$targetId, $nhom]);
            }
            $pdo->commit();
            $thongBao = 'Đã cập nhật quyền truy cập!';
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $thongBao = 'Lỗi khi lưu: ' . $e->getMessage();
        $loaiTB   = 'danger';
    }
}

$dsTaiKhoan = $pdo->query("SELECT nd.id, nd.username, nd.phanquyen_id, pq.ten_quyen, nv.ho_ten, nv.cuahang_id, ch.ten_cua_hang
                            FROM nguoidung nd
                            JOIN phanquyen pq ON pq.id = nd.phanquyen_id
                            LEFT JOIN nhanvien nv ON nv.nguoidung_id = nd.id
                            LEFT JOIN cuahang ch ON ch.id = nv.cuahang_id
                            ORDER BY nd.id ASC")->fetchAll();

$dangChon     = (int)($_GET['id'] ?? ($dsTaiKhoan[0]['id'] ?? 0));
$taiKhoanChon = null;
foreach ($dsTaiKhoan as $tk) {
    if ((int)$tk['id'] === $dangChon) { $taiKhoanChon = $tk; break; }
}

$nhomDuocCapChon = $taiKhoanChon ? layVaiTro($pdo, (int)$taiKhoanChon['id'], (int)$taiKhoanChon['phanquyen_id'], $taiKhoanChon['username']) : [];
$chucNang        = require ROOT_PATH . '/includes/chucnang.php';
$pageTitle  = 'Đăng nhập & Phân quyền';
$activeMenu = 'phanquyen';

require ROOT_PATH . '/includes/header.php';
?>

<?php if ($thongBao !== ''): ?>
<div class="mb-3 py-2 px-3 rounded-3" style="background: <?= $loaiTB === 'success' ? '#ecfdf5' : '#fff1f2' ?>; border:1px solid <?= $loaiTB === 'success' ? '#a7f3d0' : '#fecdd3' ?>; color: <?= $loaiTB === 'success' ? '#059669' : 'var(--ck-red)' ?>; font-size:0.86rem;">
    <i class="fa-solid <?= $loaiTB === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> me-2"></i><?= e($thongBao) ?>
</div>
<?php endif; ?>

<?php if (!$coTheSua): ?>
<div class="mb-3 py-2 px-3 rounded-3" style="background:#fffbeb;border:1px solid #fde68a;color:#d97706;font-size:0.86rem;">
    <i class="fa-solid fa-lock me-2"></i>Chỉ Giám Đốc mới chỉnh được phân quyền và chi nhánh. Bạn đang ở chế độ xem.
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="bento-panel fade-up">
            <div class="panel-header">
                <div>
                    <h5 class="panel-title">
                        <span class="title-icon" style="background: linear-gradient(135deg, var(--emerald), #059669)"><i class="fa-solid fa-user-shield"></i></span>
                        Tài khoản
                    </h5>
                    <div class="panel-subtitle ms-5">Chọn tài khoản để xem/sửa</div>
                </div>
            </div>
            <div style="max-height: 480px; overflow-y: auto;">
                <?php foreach ($dsTaiKhoan as $tk):
                    $ten = $tk['ho_ten'] ?: $tk['username'];
                ?>
                <a href="?id=<?= (int)$tk['id'] ?>" class="store-item text-decoration-none" style="color: inherit; <?= (int)$tk['id'] === $dangChon ? 'background:#f8fafc;' : '' ?>">
                    <span class="store-tag" style="background: var(--ck-red)"><?= e(initial($ten)) ?></span>
                    <div class="flex-grow-1">
                        <div class="fw-bold" style="font-size: 0.9rem;"><?= e($ten) ?></div>
                        <small style="color: var(--ink-500); font-size: 0.78rem;"><?= e($tk['ten_quyen']) ?><?= $tk['ten_cua_hang'] ? ' · ' . e($tk['ten_cua_hang']) : '' ?></small>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="bento-panel fade-up d-1">
            <div class="panel-header">
                <div>
                    <h5 class="panel-title">
                        <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-table-cells"></i></span>
                        Phân quyền
                    </h5>
                    <div class="panel-subtitle ms-5"><?= $taiKhoanChon ? e($taiKhoanChon['ten_quyen']) . ': ' . e($taiKhoanChon['ho_ten'] ?: $taiKhoanChon['username']) : '—' ?></div>
                </div>
            </div>
            <form method="POST" action="?id=<?= $dangChon ?>">
                <input type="hidden" name="action" value="luu_quyen">
                <input type="hidden" name="nguoidung_id" value="<?= $dangChon ?>">
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead><tr><th>Chức năng</th><th class="text-center">Xem</th><th class="text-center">Thêm</th><th class="text-center">Sửa</th><th class="text-center">Xóa</th></tr></thead>
                        <tbody>
                            <?php foreach ($chucNang as $nhom): foreach ($nhom['items'] as $cn):
                                $duocCap = in_array($nhom['nhom'], $nhomDuocCapChon, true);
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= e($cn['label']) ?></td>
                                <?php for ($c = 0; $c < 4; $c++): ?>
                                <td class="text-center"><input class="form-check-input nhom-checkbox" type="checkbox" name="nhom[]" value="<?= e($nhom['nhom']) ?>" data-nhom="<?= e($nhom['nhom']) ?>" <?= $duocCap ? 'checked' : '' ?> <?= $coTheSua ? '' : 'disabled' ?>></td>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($coTheSua): ?>
                <button type="submit" class="btn-ck btn-ck-primary mt-3"><i class="fa-solid fa-floppy-disk"></i> Lưu phân quyền</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>