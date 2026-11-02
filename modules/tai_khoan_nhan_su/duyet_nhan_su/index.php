<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$vaiTro = $_SESSION['vai_tro'] ?? null;
if (!is_array($vaiTro) || !in_array('duyet_nhansu', $vaiTro, true)) {
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

$nguoiDuyetId = (int)($_SESSION['user_id'] ?? 0);
$alertMsg = ''; $alertType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    try {
        $stmtYc = $pdo->prepare("SELECT * FROM yeucau_nhansu WHERE id = ? AND trang_thai = 'cho_duyet'");
        $stmtYc->execute([$id]);
        $yc = $stmtYc->fetch();
        if (!$yc) throw new Exception('Yêu cầu không tồn tại hoặc đã được xử lý!');

        if ($action === 'duyet') {
            $pdo->beginTransaction();

            if ($yc['loai'] === 'them') {
                $pq = $pdo->prepare("SELECT id FROM phanquyen WHERE ten_quyen = ?");
                $pq->execute([$yc['chuc_vu']]);
                $phanquyen_id = $pq->fetchColumn() ?: 4;

                $username = 'nv' . preg_replace('/\D/', '', $yc['so_dien_thoai'] ?: uniqid());
                $passHash = '123456'; 

                $uStmt = $pdo->prepare("INSERT INTO nguoidung (username, password, phanquyen_id) VALUES (?,?,?)");
                $uStmt->execute([$username, $passHash, $phanquyen_id]);
                $nguoidung_id = $pdo->lastInsertId();

                $nvStmt = $pdo->prepare("INSERT INTO nhanvien (nguoidung_id, cuahang_id, ho_ten, so_dien_thoai, chuc_vu, luong) VALUES (?,?,?,?,?,?)");
                $nvStmt->execute([$nguoidung_id, $yc['cuahang_id'], $yc['ho_ten'], $yc['so_dien_thoai'], $yc['chuc_vu'], $yc['luong']]);

                $alertMsg = "Đã duyệt thêm nhân viên <b>" . htmlspecialchars($yc['ho_ten']) . "</b>! Tài khoản: <b>$username</b> / mật khẩu mặc định <b>123456</b>";
            } else { // xoa
                $pdo->prepare("DELETE FROM nhanvien WHERE id = ?")->execute([$yc['nhanvien_id']]);
                $alertMsg = "Đã duyệt cho nghỉ việc nhân viên <b>" . htmlspecialchars($yc['ho_ten']) . "</b>!";
            }

            $pdo->prepare("UPDATE yeucau_nhansu SET trang_thai = 'da_duyet', nguoi_duyet_id = ?, ngay_duyet = NOW() WHERE id = ?")->execute([$nguoiDuyetId, $id]);
            $pdo->commit();
        }

        if ($action === 'tu_choi') {
            $pdo->prepare("UPDATE yeucau_nhansu SET trang_thai = 'tu_choi', nguoi_duyet_id = ?, ngay_duyet = NOW() WHERE id = ?")->execute([$nguoiDuyetId, $id]);
            $alertMsg = 'Đã từ chối yêu cầu!';
        }
    } catch (PDOException $pe) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $alertMsg  = 'Không thể duyệt: nhân viên này đã có hóa đơn hoặc phiếu liên quan, không xóa được!';
        $alertType = 'danger';
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $alertMsg  = $ex->getMessage();
        $alertType = 'danger';
    }
}

$dsChoDuyet = $pdo->query("SELECT yc.*, ch.ten_cua_hang, nv.ho_ten AS ten_quanlytruong
                            FROM yeucau_nhansu yc
                            LEFT JOIN cuahang ch ON ch.id = yc.cuahang_id
                            LEFT JOIN nhanvien nv ON nv.nguoidung_id = yc.quanlytruong_id
                            WHERE yc.trang_thai = 'cho_duyet'
                            ORDER BY yc.ngay_yeu_cau ASC")->fetchAll();

$dsDaXuLy = $pdo->query("SELECT yc.*, ch.ten_cua_hang, nv.ho_ten AS ten_quanlytruong
                          FROM yeucau_nhansu yc
                          LEFT JOIN cuahang ch ON ch.id = yc.cuahang_id
                          LEFT JOIN nhanvien nv ON nv.nguoidung_id = yc.quanlytruong_id
                          WHERE yc.trang_thai <> 'cho_duyet'
                          ORDER BY yc.ngay_duyet DESC LIMIT 20")->fetchAll();

$pageTitle  = 'Phê duyệt nhân sự';
$activeMenu = 'duyet_nhansu';

require ROOT_PATH . '/includes/header.php';
?>

<?php if ($alertMsg !== ''): ?>
<div class="alert alert-<?= htmlspecialchars($alertType) ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-3" role="alert">
    <i class="fa-solid <?= $alertType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> me-2"></i>
    <?= $alertMsg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
</div>
<?php endif; ?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), var(--ck-orange))"><i class="fa-solid fa-clipboard-check"></i></span>
                Yêu cầu chờ duyệt
            </h5>
            <div class="panel-subtitle ms-5">Từ các Quản Lý Trưởng <span class="ms-2 badge rounded-pill bg-light text-dark border" style="font-size:0.85rem;">Tổng: <?= count($dsChoDuyet) ?> yêu cầu</span></div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Loại</th><th>Họ tên</th><th>Chức vụ</th><th>Cửa hàng</th><th>Người gửi</th><th>Ngày gửi</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($dsChoDuyet)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-inbox fs-3 d-block mb-2"></i>Không có yêu cầu nào đang chờ.</td></tr>
                <?php else: foreach ($dsChoDuyet as $yc): ?>
                <tr>
                    <td>
                        <?php if ($yc['loai'] === 'them'): ?>
                            <span class="badge" style="background:#ecfdf5;color:#059669;">+ Thêm mới</span>
                        <?php else: ?>
                            <span class="badge" style="background:#fff1f2;color:var(--ck-red);">− Cho nghỉ</span>
                        <?php endif; ?>
                    </td>
                    <td class="fw-semibold"><?= htmlspecialchars($yc['ho_ten']) ?></td>
                    <td><?= htmlspecialchars($yc['chuc_vu'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($yc['ten_cua_hang'] ?? '') ?></td>
                    <td><?= htmlspecialchars($yc['ten_quanlytruong'] ?? '') ?></td>
                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($yc['ngay_yeu_cau'])) ?></td>
                    <td>
                        <div class="d-flex gap-2 justify-content-end">
                            <form method="POST" action="" onsubmit="return confirm('Duyệt yêu cầu này?');">
                                <input type="hidden" name="action" value="duyet">
                                <input type="hidden" name="id" value="<?= (int)$yc['id'] ?>">
                                <button type="submit" class="btn-ck btn-ck-primary" style="padding:6px 12px;font-size:0.82rem;"><i class="fa-solid fa-check"></i> Duyệt</button>
                            </form>
                            <form method="POST" action="" onsubmit="return confirm('Từ chối yêu cầu này?');">
                                <input type="hidden" name="action" value="tu_choi">
                                <input type="hidden" name="id" value="<?= (int)$yc['id'] ?>">
                                <button type="submit" class="btn-ck btn-ck-soft text-danger" style="padding:6px 12px;font-size:0.82rem;"><i class="fa-solid fa-xmark"></i> Từ chối</button>
                            </form>
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
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-clock-rotate-left"></i></span>
                Lịch sử đã xử lý
            </h5>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Loại</th><th>Họ tên</th><th>Cửa hàng</th><th>Người gửi</th><th>Kết quả</th><th>Ngày xử lý</th></tr></thead>
            <tbody>
                <?php if (empty($dsDaXuLy)): ?>
                <tr><td colspan="6" class="text-center py-3 text-muted">Chưa xử lý yêu cầu nào.</td></tr>
                <?php else: foreach ($dsDaXuLy as $yc):
                    $ketQua = $yc['trang_thai'] === 'da_duyet' ? ['Đã duyệt', '#059669'] : ['Từ chối', 'var(--ck-red)'];
                ?>
                <tr>
                    <td><?= $yc['loai'] === 'them' ? 'Thêm mới' : 'Cho nghỉ' ?></td>
                    <td><?= htmlspecialchars($yc['ho_ten']) ?></td>
                    <td><?= htmlspecialchars($yc['ten_cua_hang'] ?? '') ?></td>
                    <td><?= htmlspecialchars($yc['ten_quanlytruong'] ?? '') ?></td>
                    <td><span class="fw-bold" style="color:<?= $ketQua[1] ?>;"><?= $ketQua[0] ?></span></td>
                    <td class="small text-muted"><?= $yc['ngay_duyet'] ? date('d/m/Y H:i', strtotime($yc['ngay_duyet'])) : '' ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>