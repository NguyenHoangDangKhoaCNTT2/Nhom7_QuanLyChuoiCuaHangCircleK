<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

$pageTitle  = 'Quản lý Khuyến mãi';
$activeMenu = 'khuyenmai';

// 1. KẾT NỐI CƠ SỞ DỮ LIỆU BẰNG PDO
$host = '127.0.0.1';
$db   = 'circlek';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}

// 2. XỬ LÝ NGHIỆP VỤ THÊM / SỬA / XÓA (CRUD BACKEND)
$alertMsg  = '';
$alertType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- THÊM HOẶC CẬP NHẬT KHUYẾN MÃI ---
    if ($action === 'add' || $action === 'edit') {
        $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $ten_km      = trim($_POST['ten_km'] ?? '');
        $ma_voucher  = trim($_POST['ma_voucher'] ?? '') !== '' ? strtoupper(trim($_POST['ma_voucher'])) : null;
        $loai_km     = trim($_POST['loai_km'] ?? 'Phần trăm');
        $gia_tri     = (float)($_POST['gia_tri'] ?? 0);
        $so_luong    = ($_POST['so_luong'] ?? '') !== '' ? (int)$_POST['so_luong'] : null;
        $cuahang_id  = ($_POST['cuahang_id'] ?? '') !== '' ? (int)$_POST['cuahang_id'] : null;
        $tu_ngay     = $_POST['thoi_gian_bat_dau'] ?? '';
        $den_ngay    = $_POST['thoi_gian_ket_thuc'] ?? '';

        // Kiểm tra dữ liệu hợp lệ
        if ($ten_km === '' || $gia_tri <= 0 || $tu_ngay === '' || $den_ngay === '') {
            $alertMsg  = 'Vui lòng nhập đầy đủ các thông tin bắt buộc và giá trị phải lớn hơn 0!';
            $alertType = 'danger';
        } elseif ($loai_km === 'Phần trăm' && $gia_tri > 100) {
            $alertMsg  = 'Khuyến mãi theo Phần trăm không được vượt quá 100%!';
            $alertType = 'warning';
        } elseif (strtotime($den_ngay) <= strtotime($tu_ngay)) {
            $alertMsg  = 'Thời gian kết thúc phải lớn hơn thời gian bắt đầu!';
            $alertType = 'warning';
        } else {
            try {
                if ($action === 'add') {
                    $sql = "INSERT INTO khuyenmai (cuahang_id, ten_km, ma_voucher, loai_km, gia_tri, so_luong, thoi_gian_bat_dau, thoi_gian_ket_thuc)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$cuahang_id, $ten_km, $ma_voucher, $loai_km, $gia_tri, $so_luong, $tu_ngay, $den_ngay]);
                    $alertMsg  = 'Đã thêm chương trình khuyến mãi "' . htmlspecialchars($ten_km) . '" thành công!';
                    $alertType = 'success';
                } else {
                    $sql = "UPDATE khuyenmai 
                            SET cuahang_id = ?, ten_km = ?, ma_voucher = ?, loai_km = ?, gia_tri = ?, so_luong = ?, thoi_gian_bat_dau = ?, thoi_gian_ket_thuc = ?
                            WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$cuahang_id, $ten_km, $ma_voucher, $loai_km, $gia_tri, $so_luong, $tu_ngay, $den_ngay, $id]);
                    $alertMsg  = 'Đã cập nhật chương trình khuyến mãi #' . $id . ' thành công!';
                    $alertType = 'success';
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $alertMsg  = 'Mã Voucher "' . htmlspecialchars($ma_voucher) . '" đã tồn tại trong hệ thống. Vui lòng chọn mã khác!';
                    $alertType = 'warning';
                } else {
                    $alertMsg  = 'Lỗi CSDL: ' . $e->getMessage();
                    $alertType = 'danger';
                }
            }
        }
    }

    // --- XÓA KHUYẾN MÃI ---
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Kiểm tra xem mã KM đã dùng trong hóa đơn chưa
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM hoadon WHERE khuyenmai_id = ?");
                $checkStmt->execute([$id]);
                $usedCount = (int)$checkStmt->fetchColumn();

                if ($usedCount > 0) {
                    $alertMsg  = "Không thể xóa! Chương trình này đã được áp dụng cho $usedCount hóa đơn trong hệ thống.";
                    $alertType = 'warning';
                } else {
                    $delStmt = $pdo->prepare("DELETE FROM khuyenmai WHERE id = ?");
                    $delStmt->execute([$id]);
                    $alertMsg  = 'Đã xóa chương trình khuyến mãi thành công!';
                    $alertType = 'success';
                }
            } catch (PDOException $e) {
                $alertMsg  = 'Không thể xóa khuyến mãi này do ràng buộc dữ liệu: ' . $e->getMessage();
                $alertType = 'danger';
            }
        }
    }
}

// 3. LẤY DANH SÁCH CỬA HÀNG & KHUYẾN MÃI
$cuahang_list = $pdo->query("SELECT id, ma_cua_hang, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();

$sql_km = "SELECT km.*, ch.ten_cua_hang, ch.ma_cua_hang,
           (SELECT COUNT(*) FROM hoadon hd WHERE hd.khuyenmai_id = km.id) AS da_su_dung
           FROM khuyenmai km
           LEFT JOIN cuahang ch ON km.cuahang_id = ch.id
           ORDER BY km.id DESC";
$khuyenmai_list = $pdo->query($sql_km)->fetchAll();

// 4. TÍNH TOÁN CHỈ SỐ THỐNG KÊ (KPI)
$now = date('Y-m-d H:i:s');
$total_km   = count($khuyenmai_list);
$running_km = 0;
$waiting_km = 0;
$ended_km   = 0;

foreach ($khuyenmai_list as &$item) {
    if ($now >= $item['thoi_gian_bat_dau'] && $now <= $item['thoi_gian_ket_thuc']) {
        $item['status_key'] = 'running';
        $running_km++;
    } elseif ($now < $item['thoi_gian_bat_dau']) {
        $item['status_key'] = 'waiting';
        $waiting_km++;
    } else {
        $item['status_key'] = 'ended';
        $ended_km++;
    }
}
unset($item);

require ROOT_PATH . '/includes/header.php';
?>

<style>
    .km-stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }
    .km-stat-card {
        background: #fff;
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .km-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    }
    .km-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    .order-code.copyable {
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .order-code.copyable:hover {
        background: #fee2e2;
        color: #dc2626;
        transform: scale(1.03);
    }
    .st-badge.st-ended {
        background: #f1f5f9;
        color: #64748b;
    }
    .type-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .type-percent { background: #eff6ff; color: #2563eb; }
    .type-cash    { background: #ecfdf5; color: #059669; }
    .type-voucher { background: #fef3c7; color: #d97706; }
    .type-gift    { background: #f3e8ff; color: #7c3aed; }
    .copy-toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: #1e293b;
        color: #fff;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 0.9rem;
        z-index: 9999;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        display: none;
        align-items: center;
        gap: 8px;
    }
</style>

<!-- THẺ THỐNG KÊ NHANH (KPI CARDS) -->
<div class="km-stat-grid fade-up">
    <div class="km-stat-card">
        <div>
            <div class="text-muted small fw-semibold mb-1">TỔNG CHƯƠNG TRÌNH</div>
            <div class="fs-3 fw-bold num-font text-dark"><?= $total_km ?></div>
        </div>
        <div class="km-stat-icon" style="background: rgba(238, 49, 36, 0.1); color: #ee3124;">
            <i class="fa-solid fa-tags"></i>
        </div>
    </div>
    <div class="km-stat-card">
        <div>
            <div class="text-muted small fw-semibold mb-1">ĐANG CHẠY</div>
            <div class="fs-3 fw-bold num-font text-success"><?= $running_km ?></div>
        </div>
        <div class="km-stat-icon" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
            <i class="fa-solid fa-bolt"></i>
        </div>
    </div>
    <div class="km-stat-card">
        <div>
            <div class="text-muted small fw-semibold mb-1">SẮP DIỄN RA</div>
            <div class="fs-3 fw-bold num-font text-warning"><?= $waiting_km ?></div>
        </div>
        <div class="km-stat-icon" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b;">
            <i class="fa-solid fa-clock"></i>
        </div>
    </div>
    <div class="km-stat-card">
        <div>
            <div class="text-muted small fw-semibold mb-1">ĐÃ KẾT THÚC</div>
            <div class="fs-3 fw-bold num-font text-secondary"><?= $ended_km ?></div>
        </div>
        <div class="km-stat-icon" style="background: rgba(100, 116, 139, 0.12); color: #64748b;">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
    </div>
</div>

<!-- BẢNG DANH SÁCH CHÍNH -->
<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), var(--ck-orange))">
                    <i class="fa-solid fa-ticket"></i>
                </span>
                Danh sách khuyến mãi
            </h5>
            <div class="panel-subtitle ms-5">Chương trình khuyến mãi và voucher toàn hệ thống Circle K</div>
        </div>
    </div>

    <?php if ($alertMsg !== ''): ?>
        <div class="alert alert-<?= $alertType ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-3" role="alert" style="border-radius: 12px;">
            <i class="fa-solid <?= $alertType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
            <div><?= $alertMsg ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endif; ?>

    <!-- Thanh công cụ tìm kiếm & lọc -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass s-ico"></i>
            <input type="text" id="searchInput" placeholder="Tìm theo mã, tên chương trình...">
        </div>

        <select class="form-select" id="filterStatus" style="width: auto;">
            <option value="all">Tất cả trạng thái</option>
            <option value="running">Đang chạy</option>
            <option value="waiting">Sắp diễn ra</option>
            <option value="ended">Đã kết thúc</option>
        </select>

        <select class="form-select" id="filterType" style="width: auto;">
            <option value="all">Tất cả loại KM</option>
            <option value="Phần trăm">Phần trăm (%)</option>
            <option value="Tiền mặt">Tiền mặt (VNĐ)</option>
            <option value="Voucher">Voucher</option>
            <option value="Tặng kèm">Tặng kèm</option>
        </select>

        <button type="button" class="btn-ck btn-ck-primary ms-auto" onclick="openAddModal()">
            <i class="fa-solid fa-plus"></i> Thêm khuyến mãi
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-modern mb-0" id="kmTable">
            <thead>
                <tr>
                    <th>Mã KM</th>
                    <th>Tên chương trình</th>
                    <th>Phạm vi</th>
                    <th>Loại</th>
                    <th class="text-end">Giá trị</th>
                    <th class="text-center">Số lượng</th>
                    <th>Thời gian áp dụng</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($khuyenmai_list as $km): 
                    $codeDisplay = $km['ma_voucher'] ? $km['ma_voucher'] : 'KM' . str_pad($km['id'], 3, '0', STR_PAD_LEFT);
                    
                    // Badge trạng thái
                    if ($km['status_key'] === 'running') {
                        $statusBadge = '<span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Đang chạy</span>';
                    } elseif ($km['status_key'] === 'waiting') {
                        $statusBadge = '<span class="st-badge st-wait"><i class="fa-solid fa-clock"></i> Sắp diễn ra</span>';
                    } else {
                        $statusBadge = '<span class="st-badge st-ended"><i class="fa-solid fa-circle-stop"></i> Đã kết thúc</span>';
                    }

                    // Pill loại KM
                    $typeClass = 'type-voucher';
                    $typeIcon  = 'fa-ticket';
                    if ($km['loai_km'] === 'Phần trăm') { $typeClass = 'type-percent'; $typeIcon = 'fa-percent'; }
                    elseif ($km['loai_km'] === 'Tiền mặt') { $typeClass = 'type-cash'; $typeIcon = 'fa-money-bill-wave'; }
                    elseif ($km['loai_km'] === 'Tặng kèm') { $typeClass = 'type-gift'; $typeIcon = 'fa-gift'; }

                    // Định dạng giá trị
                    if ($km['loai_km'] === 'Phần trăm') {
                        $valDisplay = rtrim(rtrim(number_format($km['gia_tri'], 2, '.', ''), '0'), '.') . '%';
                    } elseif ($km['loai_km'] === 'Tặng kèm') {
                        $valDisplay = number_format($km['gia_tri'], 0, ',', '.') . ' SP';
                    } else {
                        $valDisplay = number_format($km['gia_tri'], 0, ',', '.') . ' VNĐ';
                    }

                    // Dữ liệu JSON cho nút Sửa
                    $jsonData = htmlspecialchars(json_encode([
                        'id'                 => $km['id'],
                        'ten_km'             => $km['ten_km'],
                        'ma_voucher'         => $km['ma_voucher'] ?? '',
                        'loai_km'            => $km['loai_km'],
                        'gia_tri'            => (float)$km['gia_tri'],
                        'so_luong'           => $km['so_luong'] ?? '',
                        'cuahang_id'         => $km['cuahang_id'] ?? '',
                        'thoi_gian_bat_dau'  => date('Y-m-d\TH:i', strtotime($km['thoi_gian_bat_dau'])),
                        'thoi_gian_ket_thuc' => date('Y-m-d\TH:i', strtotime($km['thoi_gian_ket_thuc']))
                    ]), ENT_QUOTES, 'UTF-8');
                ?>
                <tr class="km-row" 
                    data-search="<?= htmlspecialchars(mb_strtolower($codeDisplay . ' ' . $km['ten_km'])) ?>"
                    data-status="<?= $km['status_key'] ?>"
                    data-type="<?= htmlspecialchars($km['loai_km']) ?>">
                    <td>
                        <span class="order-code copyable" onclick="copyVoucher('<?= htmlspecialchars($codeDisplay) ?>')" title="Bấm để sao chép mã">
                            <?= htmlspecialchars($codeDisplay) ?>
                            <i class="fa-regular fa-copy small opacity-75"></i>
                        </span>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark"><?= htmlspecialchars($km['ten_km']) ?></div>
                        <?php if ($km['da_su_dung'] > 0): ?>
                            <small class="text-muted"><i class="fa-solid fa-receipt me-1"></i>Đã áp dụng: <?= $km['da_su_dung'] ?> đơn</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (empty($km['cuahang_id'])): ?>
                            <span class="badge bg-light text-dark border"><i class="fa-solid fa-globe me-1 text-danger"></i>Toàn chuỗi</span>
                        <?php else: ?>
                            <span class="badge bg-light text-primary border"><i class="fa-solid fa-store me-1"></i><?= htmlspecialchars($km['ten_cua_hang']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="type-pill <?= $typeClass ?>">
                            <i class="fa-solid <?= $typeIcon ?>"></i> <?= htmlspecialchars($km['loai_km']) ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <span class="num-font fw-bold text-dark"><?= $valDisplay ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($km['so_luong'] === null): ?>
                            <span class="text-muted small"><i class="fa-solid fa-infinity"></i> Vô hạn</span>
                        <?php else: ?>
                            <span class="num-font fw-semibold"><?= number_format($km['so_luong']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="small fw-medium"><?= date('d/m/Y', strtotime($km['thoi_gian_bat_dau'])) ?> – <?= date('d/m/Y', strtotime($km['thoi_gian_ket_thuc'])) ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            <?= date('H:i', strtotime($km['thoi_gian_bat_dau'])) ?> đến <?= date('H:i', strtotime($km['thoi_gian_ket_thuc'])) ?>
                        </div>
                    </td>
                    <td><?= $statusBadge ?></td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="action-icon-btn sm border-0" title="Chỉnh sửa" onclick='openEditModal(<?= $jsonData ?>)'>
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button" class="action-icon-btn sm border-0 text-danger" title="Xóa" onclick="openDeleteModal(<?= $km['id'] ?>, '<?= htmlspecialchars(addslashes($km['ten_km'])) ?>')">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <tr id="emptyFilterRow" style="<?= count($khuyenmai_list) === 0 ? '' : 'display: none;' ?>">
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="fa-solid fa-ticket-simple fs-2 mb-2 d-block opacity-50"></i>
                        Không tìm thấy chương trình khuyến mãi nào phù hợp.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL THÊM / SỬA KHUYẾN MÃI -->
<div class="modal fade" id="modalKhuyenMai" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">
                    <i class="fa-solid fa-ticket text-danger me-2"></i>Thêm chương trình khuyến mãi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="kmId" value="">

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="kmMa">Mã khuyến mãi / Voucher</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-uppercase" id="kmMa" name="ma_voucher" placeholder="VD: CKVIP20 (Trống = tự động)">
                                <button class="btn btn-outline-secondary" type="button" onclick="generateRandomCode()" title="Tạo mã ngẫu nhiên">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> Random
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="kmTen">Tên chương trình <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="kmTen" name="ten_km" required placeholder="VD: Khuyến mãi giờ vàng Circle K">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="kmLoai">Loại khuyến mãi <span class="text-danger">*</span></label>
                            <select class="form-select" id="kmLoai" name="loai_km" onchange="updateValueUnit()" required>
                                <option value="Phần trăm">Phần trăm (%)</option>
                                <option value="Tiền mặt">Tiền mặt (VNĐ)</option>
                                <option value="Voucher">Voucher giảm giá</option>
                                <option value="Tặng kèm">Tặng kèm sản phẩm</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="kmGt">Giá trị giảm <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="1" class="form-control" id="kmGt" name="gia_tri" required placeholder="VD: 15 hoặc 20000">
                                <span class="input-group-text fw-bold" id="unitLabel">%</span>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="kmSoLuong">Số lượng phát hành</label>
                            <input type="number" min="1" class="form-control" id="kmSoLuong" name="so_luong" placeholder="Để trống = Vô hạn">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold" for="kmCuaHang">Phạm vi cửa hàng áp dụng</label>
                            <select class="form-select" id="kmCuaHang" name="cuahang_id">
                                <option value="">🌐 Áp dụng Toàn chuỗi Circle K</option>
                                <?php foreach ($cuahang_list as $ch): ?>
                                    <option value="<?= $ch['id'] ?>">
                                        🏪 <?= htmlspecialchars($ch['ma_cua_hang'] . ' - ' . $ch['ten_cua_hang']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="kmTu">Thời gian bắt đầu <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="kmTu" name="thoi_gian_bat_dau" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="kmDen">Thời gian kết thúc <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="kmDen" name="thoi_gian_ket_thuc" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn-ck btn-ck-primary" id="btnSubmitModal">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Lưu khuyến mãi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL XÁC NHẬN XÓA -->
<div class="modal fade" id="modalXoaKhuyenMai" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Xác nhận xóa khuyến mãi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteKmId" value="">
                <div class="modal-body py-3">
                    Bạn có chắc chắn muốn xóa chương trình <strong id="deleteKmName" class="text-dark"></strong> không?<br>
                    <small class="text-muted">Hành động này không thể hoàn tác sau khi thực hiện.</small>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger rounded-3 px-4 fw-semibold">
                        <i class="fa-solid fa-trash me-1"></i> Xóa ngay
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- THÔNG BÁO COPY MÃ VOUCHER -->
<div class="copy-toast" id="copyToast">
    <i class="fa-solid fa-circle-check text-success"></i>
    <span id="copyToastText">Đã sao chép mã!</span>
</div>

<script>
    // Chống gửi lại form khi nhấn F5
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    // Đổi nhãn đơn vị (% / VNĐ / SP)
    function updateValueUnit() {
        const loai = document.getElementById('kmLoai').value;
        const unitLabel = document.getElementById('unitLabel');
        const gtInput = document.getElementById('kmGt');
        if (loai === 'Phần trăm') {
            unitLabel.textContent = '%';
            gtInput.max = 100;
        } else if (loai === 'Tặng kèm') {
            unitLabel.textContent = 'SP';
            gtInput.removeAttribute('max');
        } else {
            unitLabel.textContent = 'VNĐ';
            gtInput.removeAttribute('max');
        }
    }

    // Tạo mã Voucher ngẫu nhiên chuyên nghiệp
    function generateRandomCode() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let code = 'CK';
        for (let i = 0; i < 5; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('kmMa').value = code;
    }

    // Mở Modal ở chế độ Thêm mới
    function openAddModal() {
        document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-ticket text-danger me-2"></i>Thêm chương trình khuyến mãi';
        document.getElementById('formAction').value = 'add';
        document.getElementById('kmId').value = '';
        document.getElementById('kmMa').value = '';
        document.getElementById('kmTen').value = '';
        document.getElementById('kmLoai').value = 'Phần trăm';
        document.getElementById('kmGt').value = '';
        document.getElementById('kmSoLuong').value = '';
        document.getElementById('kmCuaHang').value = '';

        // Mặc định từ hôm nay đến 30 ngày sau
        const now = new Date();
        const nextMonth = new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000);
        const toLocalISO = (d) => {
            const pad = (n) => n < 10 ? '0' + n : n;
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        };
        document.getElementById('kmTu').value = toLocalISO(now);
        document.getElementById('kmDen').value = toLocalISO(nextMonth);

        updateValueUnit();
        new bootstrap.Modal(document.getElementById('modalKhuyenMai')).show();
    }

    // Mở Modal ở chế độ Chỉnh sửa (đổ dữ liệu tự động)
    function openEditModal(data) {
        document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-primary me-2"></i>Cập nhật chương trình khuyến mãi';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('kmId').value = data.id;
        document.getElementById('kmMa').value = data.ma_voucher;
        document.getElementById('kmTen').value = data.ten_km;
        document.getElementById('kmLoai').value = data.loai_km;
        document.getElementById('kmGt').value = data.gia_tri;
        document.getElementById('kmSoLuong').value = data.so_luong;
        document.getElementById('kmCuaHang').value = data.cuahang_id;
        document.getElementById('kmTu').value = data.thoi_gian_bat_dau;
        document.getElementById('kmDen').value = data.thoi_gian_ket_thuc;

        updateValueUnit();
        new bootstrap.Modal(document.getElementById('modalKhuyenMai')).show();
    }

    // Mở Modal Xác nhận Xóa
    function openDeleteModal(id, name) {
        document.getElementById('deleteKmId').value = id;
        document.getElementById('deleteKmName').textContent = '"' + name + '"';
        new bootstrap.Modal(document.getElementById('modalXoaKhuyenMai')).show();
    }

    // Sao chép mã Voucher vào Clipboard
    function copyVoucher(code) {
        navigator.clipboard.writeText(code).then(() => {
            const toast = document.getElementById('copyToast');
            document.getElementById('copyToastText').textContent = 'Đã sao chép mã: ' + code;
            toast.style.display = 'flex';
            setTimeout(() => { toast.style.display = 'none'; }, 2200);
        });
    }

    // Bộ lọc Tìm kiếm + Trạng thái + Loại KM theo thời gian thực (Real-time)
    function filterTable() {
        const keyword = document.getElementById('searchInput').value.toLowerCase().trim();
        const status  = document.getElementById('filterStatus').value;
        const type    = document.getElementById('filterType').value;
        const rows    = document.querySelectorAll('.km-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const matchSearch = row.getAttribute('data-search').includes(keyword);
            const matchStatus = (status === 'all') || (row.getAttribute('data-status') === status);
            const matchType   = (type === 'all') || (row.getAttribute('data-type') === type);

            if (matchSearch && matchStatus && matchType) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        document.getElementById('emptyFilterRow').style.display = (visibleCount === 0) ? '' : 'none';
    }

    document.getElementById('searchInput').addEventListener('input', filterTable);
    document.getElementById('filterStatus').addEventListener('change', filterTable);
    document.getElementById('filterType').addEventListener('change', filterTable);
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>