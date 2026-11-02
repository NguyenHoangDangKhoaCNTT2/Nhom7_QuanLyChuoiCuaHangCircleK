<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$vaiTro = $_SESSION['vai_tro'] ?? null;
if (!is_array($vaiTro) || !in_array('xem_hoadon', $vaiTro, true)) {
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

try {
    $tenKhachColumn = $pdo->query("SHOW COLUMNS FROM hoadon LIKE 'ten_khach_nhap'")->fetch();
    if (!$tenKhachColumn) {
        $pdo->exec("ALTER TABLE hoadon ADD COLUMN ten_khach_nhap VARCHAR(100) NULL");
    }
} catch (PDOException $e) {
    die('Không thể cập nhật cột tên khách hàng: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$cuaHangId = (int)($_SESSION['cuahang_id'] ?? 0);
if ($cuaHangId <= 0) {
    die('<div style="padding:20px;color:#e11d48;font-family:sans-serif;">Tài khoản chưa được gán cửa hàng.</div>');
}

if (($_GET['ajax'] ?? '') === 'chi_tiet') {
    header('Content-Type: application/json; charset=utf-8');
    $hoadonId = (int)($_GET['id'] ?? 0);
    $stmtHd = $pdo->prepare("SELECT hd.*, ch.ten_cua_hang, nv.ho_ten AS ten_nv,
                                     COALESCE(NULLIF(TRIM(hd.ten_khach_nhap), ''), kh.ho_ten) AS ten_kh,
                                     kh.so_dien_thoai AS sdt_kh, tt.phuong_thuc
                              FROM hoadon hd
                              LEFT JOIN cuahang ch ON ch.id = hd.cuahang_id
                              LEFT JOIN nhanvien nv ON nv.id = hd.nhanvien_id
                              LEFT JOIN khachhang kh ON kh.id = hd.khachhang_id
                              LEFT JOIN thanhtoan tt ON tt.hoadon_id = hd.id
                              WHERE hd.id = ? AND hd.cuahang_id = ?");
    $stmtHd->execute([$hoadonId, $cuaHangId]);
    $hd = $stmtHd->fetch();
    if (!$hd) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy hóa đơn tại cửa hàng của bạn.']);
        exit;
    }
    $stmtCt = $pdo->prepare("SELECT ct.so_luong, ct.don_gia, ct.thanh_tien, sp.ten_san_pham, sp.ma_san_pham
                              FROM chitiethoadon ct JOIN sanpham sp ON sp.id = ct.sanpham_id
                              WHERE ct.hoadon_id = ?");
    $stmtCt->execute([$hoadonId]);
    $hd['chi_tiet'] = $stmtCt->fetchAll();
    echo json_encode(['success' => true, 'hoadon' => $hd], JSON_UNESCAPED_UNICODE);
    exit;
}

$ngayXem   = trim($_GET['ngay'] ?? '');
$timKhach  = trim($_GET['khach'] ?? '');

$where  = "WHERE hd.cuahang_id = ?";
$params = [$cuaHangId];
if ($ngayXem !== '') {
    $where .= " AND DATE(hd.ngay_tao) = ?";
    $params[] = $ngayXem;
}
if ($timKhach !== '') {
    $where .= " AND (kh.ho_ten LIKE ? OR hd.ten_khach_nhap LIKE ? OR kh.so_dien_thoai LIKE ?)";
    $params[] = "%$timKhach%"; $params[] = "%$timKhach%"; $params[] = "%$timKhach%";
}

$stmt = $pdo->prepare("SELECT hd.id, hd.ngay_tao, hd.tong_tien, hd.tien_giam_gia, hd.khach_phai_tra, hd.trang_thai,
                               COALESCE(NULLIF(TRIM(hd.ten_khach_nhap), ''), kh.ho_ten, 'Khách lẻ') AS ten_kh,
                               nv.ho_ten AS ten_nv
                        FROM hoadon hd
                        LEFT JOIN khachhang kh ON kh.id = hd.khachhang_id
                        LEFT JOIN nhanvien nv ON nv.id = hd.nhanvien_id
                        $where
                        ORDER BY hd.ngay_tao DESC, hd.id DESC
                        LIMIT 200");
$stmt->execute($params);
$dsHoaDon = $stmt->fetchAll();

$tenCH = $pdo->prepare("SELECT ten_cua_hang FROM cuahang WHERE id = ?");
$tenCH->execute([$cuaHangId]);
$tenCuaHang = $tenCH->fetchColumn();

$pageTitle  = 'Xem hóa đơn';
$activeMenu = 'xem_hoadon';

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-receipt"></i></span>
                Hóa đơn - <?= htmlspecialchars($tenCuaHang) ?>
            </h5>
            <div class="panel-subtitle ms-5">Chỉ xem, không chỉnh sửa được - <span class="ms-2 badge rounded-pill bg-light text-dark border" style="font-size:0.85rem;">Tổng: <?= count($dsHoaDon) ?> hóa đơn</span></div>
        </div>
    </div>

    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass s-ico"></i>
            <input type="text" name="khach" value="<?= htmlspecialchars($timKhach) ?>" placeholder="Tìm theo tên khách hàng, SĐT...">
        </div>
        <input type="date" name="ngay" class="form-control" style="width: auto;" value="<?= htmlspecialchars($ngayXem) ?>">
        <button type="submit" class="btn-ck btn-ck-soft"><i class="fa-solid fa-filter"></i> Lọc</button>
        <?php if ($ngayXem !== '' || $timKhach !== ''): ?>
            <a href="index.php" class="btn-ck btn-ck-soft text-danger"><i class="fa-solid fa-rotate-left"></i> Đặt lại</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Mã HĐ</th><th>Ngày giờ</th><th>Khách hàng</th><th>Nhân viên bán</th><th class="text-end">Khách phải trả</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($dsHoaDon)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-receipt fs-3 d-block mb-2"></i>Không có hóa đơn nào khớp bộ lọc.</td></tr>
                <?php else: foreach ($dsHoaDon as $hd): ?>
                <tr>
                    <td><span class="order-code">#HD-<?= (int)$hd['id'] ?></span></td>
                    <td class="small"><?= date('d/m/Y H:i', strtotime($hd['ngay_tao'])) ?></td>
                    <td><?= htmlspecialchars($hd['ten_kh']) ?></td>
                    <td><?= htmlspecialchars($hd['ten_nv'] ?? '') ?></td>
                    <td class="text-end"><span class="num-font fw-bold text-danger"><?= number_format((float)$hd['khach_phai_tra']) ?> ₫</span></td>
                    <td><?= htmlspecialchars($hd['trang_thai']) ?></td>
                    <td class="text-end"><button type="button" class="action-icon-btn sm" title="Xem chi tiết" onclick="xemChiTiet(<?= (int)$hd['id'] ?>)"><i class="fa-solid fa-eye"></i></button></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalChiTiet" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="ctTitle">Chi tiết hóa đơn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body" id="ctBody">
                <div class="text-center py-4 text-muted">Đang tải...</div>
            </div>
        </div>
    </div>
</div>

<script>
function xemChiTiet(id) {
    const modal = new bootstrap.Modal(document.getElementById('modalChiTiet'));
    document.getElementById('ctTitle').innerText = 'Chi tiết hóa đơn #HD-' + id;
    document.getElementById('ctBody').innerHTML = '<div class="text-center py-4 text-muted">Đang tải...</div>';
    modal.show();
    fetch('index.php?ajax=chi_tiet&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('ctBody').innerHTML = '<div class="text-center py-4 text-danger">' + data.message + '</div>';
                return;
            }
            const hd = data.hoadon;
            let rows = hd.chi_tiet.map(ct => `
                <tr>
                    <td>${ct.ten_san_pham}</td>
                    <td class="text-center">${ct.so_luong}</td>
                    <td class="text-end">${Number(ct.don_gia).toLocaleString('vi-VN')} ₫</td>
                    <td class="text-end">${Number(ct.thanh_tien).toLocaleString('vi-VN')} ₫</td>
                </tr>`).join('');
            document.getElementById('ctBody').innerHTML = `
                <div class="row g-2 mb-3 small">
                    <div class="col-6"><strong>Khách hàng:</strong> ${hd.ten_kh || 'Khách lẻ'}</div>
                    <div class="col-6"><strong>SĐT:</strong> ${hd.sdt_kh || '-'}</div>
                    <div class="col-6"><strong>Nhân viên bán:</strong> ${hd.ten_nv || '-'}</div>
                    <div class="col-6"><strong>Phương thức:</strong> ${hd.phuong_thuc || 'Tiền mặt'}</div>
                    <div class="col-6"><strong>Thời gian:</strong> ${hd.ngay_tao}</div>
                    <div class="col-6"><strong>Trạng thái:</strong> ${hd.trang_thai}</div>
                </div>
                <table class="table table-modern mb-2">
                    <thead><tr><th>Sản phẩm</th><th class="text-center">SL</th><th class="text-end">Đơn giá</th><th class="text-end">Thành tiền</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
                <div class="text-end small">
                    <div>Tạm tính: <strong>${Number(hd.tong_tien).toLocaleString('vi-VN')} ₫</strong></div>
                    <div>Giảm giá: <strong>${Number(hd.tien_giam_gia).toLocaleString('vi-VN')} ₫</strong></div>
                    <div class="fs-6 mt-1">Khách phải trả: <strong class="text-danger">${Number(hd.khach_phai_tra).toLocaleString('vi-VN')} ₫</strong></div>
                </div>`;
        })
        .catch(() => {
            document.getElementById('ctBody').innerHTML = '<div class="text-center py-4 text-danger">Lỗi kết nối, vui lòng thử lại.</div>';
        });
}
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>