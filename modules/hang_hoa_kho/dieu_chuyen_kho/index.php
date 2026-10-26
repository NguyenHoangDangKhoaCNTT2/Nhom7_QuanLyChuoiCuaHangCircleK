<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Điều chuyển kho';
$activeMenu = 'dieuchuyen';

$db_host = 'localhost';
$db_name = 'circlek';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (PDOException $e) {
    die('<div style="padding:20px;color:#e11d48;font-family:sans-serif;"><h3>Không thể kết nối Database!</h3></div>');
}

$scriptName = $_SERVER['SCRIPT_NAME'];
$projectBaseUrl = preg_replace('#/modules/.*$#', '', $scriptName);
$imageDirPhysical = ROOT_PATH . '/assets/images/';

function getProductImageUrlLocal($hinh_anh, $ma_sp, $imageDirPhysical, $projectBaseUrl) {
    if (!empty($hinh_anh)) {
        $cleanPath = ltrim($hinh_anh, '/');
        return $projectBaseUrl . '/assets/images/' . basename($cleanPath);
    }
    $exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    foreach ($exts as $ext) {
        $tryUpper = strtoupper($ma_sp) . '.' . $ext;
        $tryLower = strtolower($ma_sp) . '.' . $ext;
        if (file_exists($imageDirPhysical . $tryUpper)) return $projectBaseUrl . '/assets/images/' . $tryUpper;
        if (file_exists($imageDirPhysical . $tryLower)) return $projectBaseUrl . '/assets/images/' . $tryLower;
    }
    return '';
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_data_xuat') {
    $ch_id = (int)$_GET['cuahang_id'];
    
    $stmtNv = $pdo->prepare("SELECT id, ho_ten, chuc_vu FROM nhanvien WHERE cuahang_id = ? AND LOWER(chuc_vu) LIKE '%kho%' ORDER BY ho_ten ASC");
    $stmtNv->execute([$ch_id]);
    $nhanvien = $stmtNv->fetchAll();
    
    $stmtSp = $pdo->prepare("
        SELECT sp.id, sp.ma_san_pham, sp.ten_san_pham, sp.hinh_anh, tk.so_luong 
        FROM tonkho tk 
        JOIN sanpham sp ON tk.sanpham_id = sp.id 
        WHERE tk.cuahang_id = ? AND tk.so_luong > 0 AND sp.trang_thai = 1 
        ORDER BY sp.ten_san_pham ASC
    ");
    $stmtSp->execute([$ch_id]);
    $sanpham = $stmtSp->fetchAll();
    
    foreach ($sanpham as &$sp) {
        $sp['img_url'] = getProductImageUrlLocal($sp['hinh_anh'], $sp['ma_san_pham'], $imageDirPhysical, $projectBaseUrl);
    }
    
    header('Content-Type: application/json');
    echo json_encode(['nhanvien' => $nhanvien, 'sanpham' => $sanpham]);
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_ctdc') {
    $dc_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("
        SELECT sp.ma_san_pham, sp.ten_san_pham, sp.hinh_anh, ct.so_luong
        FROM chitietdieuchuyen ct
        JOIN sanpham sp ON ct.sanpham_id = sp.id
        WHERE ct.phieudieuchuyen_id = ?
    ");
    $stmt->execute([$dc_id]);
    $details = $stmt->fetchAll();
    
    foreach ($details as &$d) {
        $d['img_url'] = getProductImageUrlLocal($d['hinh_anh'], $d['ma_san_pham'], $imageDirPhysical, $projectBaseUrl);
    }
    
    header('Content-Type: application/json');
    echo json_encode($details);
    exit;
}

$alertMsg  = '';
$alertType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_dc') {
    try {
        $tu_cuahang_id  = (int)($_POST['tu_cuahang_id'] ?? 0);
        $den_cuahang_id = (int)($_POST['den_cuahang_id'] ?? 0);
        $nguoi_chuyen   = (int)($_POST['nguoi_chuyen'] ?? 0);
        $ngay_chuyen    = trim($_POST['ngay_chuyen'] ?? date('Y-m-d H:i:s'));
        $ghi_chu        = trim($_POST['ghi_chu'] ?? '');
        
        $sp_ids = $_POST['sp_id'] ?? [];
        $sls    = $_POST['sl'] ?? [];

        if ($tu_cuahang_id <= 0 || $den_cuahang_id <= 0 || $nguoi_chuyen <= 0 || empty($sp_ids)) {
            throw new Exception('Vui lòng điền đầy đủ thông Kho xuất, Kho nhận, Người xuất và Sản phẩm!');
        }
        
        if ($tu_cuahang_id === $den_cuahang_id) {
            throw new Exception('Kho nhận phải khác Kho xuất!');
        }

        $pdo->beginTransaction();

        $stmtDc = $pdo->prepare("INSERT INTO phieudieuchuyen (tu_cuahang_id, den_cuahang_id, nguoi_chuyen, ngay_chuyen, trang_thai) VALUES (?, ?, ?, ?, 'Hoàn thành')");
        $stmtDc->execute([$tu_cuahang_id, $den_cuahang_id, $nguoi_chuyen, $ngay_chuyen]);
        $dcId = $pdo->lastInsertId();

        $stmtCt = $pdo->prepare("INSERT INTO chitietdieuchuyen (phieudieuchuyen_id, sanpham_id, so_luong) VALUES (?, ?, ?)");
        
        $stmtCheckTon = $pdo->prepare("SELECT so_luong FROM tonkho WHERE sanpham_id = ? AND cuahang_id = ? FOR UPDATE");
        $stmtTruTon   = $pdo->prepare("UPDATE tonkho SET so_luong = so_luong - ? WHERE sanpham_id = ? AND cuahang_id = ?");
        $stmtCongTon  = $pdo->prepare("UPDATE tonkho SET so_luong = so_luong + ? WHERE sanpham_id = ? AND cuahang_id = ?");
        $stmtThemTon  = $pdo->prepare("INSERT INTO tonkho (sanpham_id, cuahang_id, so_luong) VALUES (?, ?, ?)");
        
        for ($i = 0; $i < count($sp_ids); $i++) {
            $sp_id = (int)$sp_ids[$i];
            $sl    = (int)$sls[$i];
            
            if ($sp_id > 0 && $sl > 0) {
                $stmtCheckTon->execute([$sp_id, $tu_cuahang_id]);
                $tonXuat = $stmtCheckTon->fetchColumn();
                
                if ($tonXuat === false || $tonXuat < $sl) {
                    throw new Exception("Sản phẩm ID {$sp_id} không đủ tồn kho để chuyển (Tồn hiện tại: " . (int)$tonXuat . ")!");
                }
                
                $stmtCt->execute([$dcId, $sp_id, $sl]);
                $stmtTruTon->execute([$sl, $sp_id, $tu_cuahang_id]);
                
                $stmtCheckTon->execute([$sp_id, $den_cuahang_id]);
                if ($stmtCheckTon->fetch() !== false) {
                    $stmtCongTon->execute([$sl, $sp_id, $den_cuahang_id]);
                } else {
                    $stmtThemTon->execute([$sp_id, $den_cuahang_id, $sl]);
                }
            }
        }

        $pdo->commit();
        $alertMsg = "Đã điều chuyển hàng hóa thành công!";
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $alertMsg  = $ex->getMessage();
        $alertType = 'danger';
    }
}

$dsCuaHang = $pdo->query("SELECT id, ma_cua_hang, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();

$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

$totalRows = $pdo->query("SELECT COUNT(*) FROM phieudieuchuyen")->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$sqlHistory = "
    SELECT 
        dc.id, dc.ngay_chuyen, dc.trang_thai,
        cx.ten_cua_hang AS kho_xuat,
        cn.ten_cua_hang AS kho_nhan,
        nv.ho_ten AS nguoi_chuyen,
        COALESCE((SELECT SUM(so_luong) FROM chitietdieuchuyen WHERE phieudieuchuyen_id = dc.id), 0) AS tong_so_luong
    FROM phieudieuchuyen dc
    JOIN cuahang cx ON dc.tu_cuahang_id = cx.id
    JOIN cuahang cn ON dc.den_cuahang_id = cn.id
    LEFT JOIN nhanvien nv ON dc.nguoi_chuyen = nv.id
    ORDER BY dc.id DESC
    LIMIT $limit OFFSET $offset
";
$dsLichSu = $pdo->query($sqlHistory)->fetchAll();

function buildPageUrl($pageNum) {
    $gets = $_GET;
    $gets['page'] = $pageNum;
    return '?' . http_build_query($gets);
}

require ROOT_PATH . '/includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single {
        height: 60px !important;
        border: 1px solid var(--border-light) !important;
        border-radius: 12px !important;
        display: flex;
        align-items: center;
        background-color: var(--surface);
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 58px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: normal !important;
        padding-left: 12px;
    }
    .select2-dropdown {
        border: 1px solid var(--border-light) !important;
        border-radius: 12px !important;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    .select2-results__option {
        padding: 8px 12px;
        display: flex;
        align-items: center;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #f1f5f9;
        color: var(--ink-900);
    }
</style>

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
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-amber), #f97316)"><i class="fa-solid fa-right-left"></i></span>
                Phiếu điều chuyển mới
            </h5>
            <div class="panel-subtitle ms-5">Chuyển hàng từ kho này sang kho khác</div>
        </div>
    </div>

    <form method="POST" action="" id="formDieuChuyen">
        <input type="hidden" name="action" value="save_dc">
        
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Kho xuất </label>
                <select class="form-select" name="tu_cuahang_id" id="khoXuatSelect" required onchange="loadKhoXuatData(this.value)">
                    <option value="">-- Chọn kho xuất --</option>
                    <?php foreach ($dsCuaHang as $ch): ?>
                        <option value="<?= $ch['id'] ?>"><?= htmlspecialchars($ch['ma_cua_hang'] . ' - ' . $ch['ten_cua_hang']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Kho nhận </label>
                <select class="form-select" name="den_cuahang_id" id="khoNhanSelect" required>
                    <option value="">-- Chọn kho nhận --</option>
                    <?php foreach ($dsCuaHang as $ch): ?>
                        <option value="<?= $ch['id'] ?>"><?= htmlspecialchars($ch['ma_cua_hang'] . ' - ' . $ch['ten_cua_hang']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Người thực hiện</label>
                <select class="form-select" name="nguoi_chuyen" id="nguoiChuyenSelect" required>
                    <option value="">-- Chọn kho xuất trước --</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Thời gian chuyển</label>
                <input type="datetime-local" class="form-control" name="ngay_chuyen" value="<?= date('Y-m-d\TH:i') ?>" required>
            </div>
            <div class="col-md-12">
                <label class="form-label fw-semibold">Ghi chú điều chuyển</label>
                <textarea class="form-control" name="ghi_chu" rows="2" placeholder="Lý do điều chuyển..."></textarea>
            </div>
        </div>

        <div class="table-responsive" style="overflow: visible;">
            <table class="table table-modern mb-0" id="tbSanPham">
                <thead>
                    <tr>
                        <th style="width: 50%;">Sản phẩm</th>
                        <th class="text-end" style="width: 20%;">Tồn kho xuất</th>
                        <th class="text-end" style="width: 20%;">Số lượng chuyển</th>
                        <th class="text-center" style="width: 10%;"></th>
                    </tr>
                </thead>
                <tbody id="dsSanPhamInputs">
                </tbody>
            </table>
        </div>

        <button type="button" class="btn-ck btn-ck-soft mt-3" onclick="addRow()">
            <i class="fa-solid fa-plus"></i> Thêm dòng sản phẩm
        </button>

        <div class="mt-4 pt-3 d-flex justify-content-between align-items-center" style="border-top: 1px dashed var(--border-light);">
            <span class="fw-bold fs-5 text-dark">Tổng số lượng chuyển:</span>
            <span class="num-font fw-bold" id="grandTotalQty" style="font-size: 1.8rem; color: var(--ck-red);">0</span>
        </div>

        <div class="d-flex justify-content-end gap-3 mt-4">
            <a href="index.php" class="btn-ck btn-ck-soft text-decoration-none">Nhập lại</a>
            <button type="submit" class="btn-ck btn-ck-primary"><i class="fa-solid fa-truck-fast"></i> Tạo phiếu điều chuyển</button>
        </div>
    </form>
</div>

<div class="bento-panel fade-up d-1 mt-4">
    <div class="panel-header mb-3">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-list-check"></i></span>
                Danh sách phiếu điều chuyển
            </h5>
            <div class="panel-subtitle ms-5">Theo dõi lịch sử và trạng thái chuyển hàng</div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead>
                <tr>
                    <th class="text-nowrap">Mã phiếu</th>
                    <th class="text-nowrap">Ngày chuyển</th>
                    <th class="text-nowrap">Kho xuất</th>
                    <th class="text-nowrap">Kho nhận</th>
                    <th class="text-nowrap">Người chuyển</th>
                    <th class="text-end text-nowrap">Tổng số lượng</th>
                    <th class="text-center text-nowrap">Trạng thái</th>
                    <th class="text-end text-nowrap">Xem chi tiết</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dsLichSu)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">Chưa có phiếu điều chuyển nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($dsLichSu as $ls): 
                        $dcCode = 'DC' . str_pad($ls['id'], 4, '0', STR_PAD_LEFT);
                        $dcDate = date('d/m/Y H:i', strtotime($ls['ngay_chuyen']));
                        $stt = $ls['trang_thai'];
                    ?>
                        <tr>
                            <td class="text-nowrap"><span class="order-code"><?= $dcCode ?></span></td>
                            <td class="text-nowrap"><?= $dcDate ?></td>
                            <td><?= htmlspecialchars($ls['kho_xuat']) ?></td>
                            <td><?= htmlspecialchars($ls['kho_nhan']) ?></td>
                            <td><span class="fw-semibold text-dark"><?= htmlspecialchars($ls['nguoi_chuyen'] ?? 'System') ?></span></td>
                            <td class="text-end text-nowrap"><span class="num-font fw-bold text-dark"><?= number_format($ls['tong_so_luong'], 0, ',', '.') ?></span></td>
                            <td class="text-center text-nowrap">
                                <?php if ($stt === 'Hoàn thành'): ?>
                                    <span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($stt) ?></span>
                                <?php elseif ($stt === 'Đang chuyển'): ?>
                                    <span class="st-badge st-wait"><i class="fa-solid fa-clock"></i> <?= htmlspecialchars($stt) ?></span>
                                <?php else: ?>
                                    <span class="st-badge st-cancel"><i class="fa-solid fa-circle-xmark"></i> <?= htmlspecialchars($stt) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="action-icon-btn sm text-info ms-auto" title="Xem chi tiết"
                                    onclick='viewDcDetail(
                                        <?= $ls['id'] ?>, 
                                        "<?= $dcCode ?>", 
                                        "<?= $dcDate ?>", 
                                        <?= json_encode($ls['kho_xuat'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, 
                                        <?= json_encode($ls['kho_nhan'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, 
                                        <?= (int)$ls['tong_so_luong'] ?>
                                    )'>
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
        <span class="text-muted small fw-medium">Hiển thị trang <b class="text-dark"><?= $page ?></b> / <?= $totalPages ?></span>
        <nav>
            <ul class="ck-pagination">
                <li class="page-item nav-btn <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= buildPageUrl($page - 1) ?>"><i class="fa-solid fa-chevron-left me-1"></i> Trước</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= buildPageUrl($i) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item nav-btn <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= buildPageUrl($page + 1) ?>">Sau <i class="fa-solid fa-chevron-right ms-1"></i></a>
                </li>
            </ul>
        </nav>
    </div>
    
    <style>
        .ck-pagination { display: flex; gap: 6px; list-style: none; padding: 0; margin: 0; }
        .ck-pagination .page-item .page-link { border-radius: 8px !important; border: 1px solid var(--border-light); color: var(--ink-700); font-weight: 600; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; padding: 0; transition: all 0.2s ease; background: var(--surface); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .ck-pagination .page-item:not(.disabled) .page-link:hover { background: #fff1f2; color: var(--ck-red); border-color: #fecdd3; transform: translateY(-1px); }
        .ck-pagination .page-item.active .page-link { background: linear-gradient(135deg, var(--ck-red), var(--ck-orange)); color: #fff; border: none; box-shadow: 0 4px 10px rgba(225, 29, 72, 0.35); transform: translateY(-1px); }
        .ck-pagination .page-item.disabled .page-link { background: #f8fafc; color: #cbd5e1; border-color: var(--border-subtle); box-shadow: none; cursor: not-allowed; }
        .ck-pagination .page-item.nav-btn .page-link { width: auto; padding: 0 14px; font-size: 0.85rem; }
    </style>
    <?php endif; ?>
</div>

<!-- Modal xem ảnh lớn -->
<div class="modal fade" id="modalViewImage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
            <div class="modal-body text-center p-0 position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Đóng" style="z-index: 2; background-color: rgba(0,0,0,0.5); border-radius: 50%; padding: 10px;"></button>
                <img src="" id="largeImagePreview" class="img-fluid rounded-4 shadow-lg" style="max-height: 85vh; object-fit: contain;">
                <h5 id="largeImageTitle" class="text-white mt-3 fw-bold" style="text-shadow: 0 2px 4px rgba(0,0,0,0.8);"></h5>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDcDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Chi tiết điều chuyển <span id="dtDcCode" class="text-danger"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom">
                    <div class="row text-dark">
                        <div class="col-sm-6 mb-2 mb-sm-0">
                            <div class="small text-muted mb-1">Thời gian chuyển:</div>
                            <div class="fw-semibold" id="dtDcDate"></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted mb-1">Kho xuất:</div>
                            <div class="fw-semibold" id="dtDcXuat"></div>
                        </div>
                        <div class="col-12 mt-3">
                            <div class="small text-muted mb-1">Kho nhận:</div>
                            <div class="fw-semibold" id="dtDcNhan"></div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-modern mb-0">
                        <thead style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th class="text-nowrap">Mã sản phẩm</th>
                                <th class="text-nowrap">Tên sản phẩm</th>
                                <th class="text-end text-nowrap">Số lượng chuyển</th>
                            </tr>
                        </thead>
                        <tbody id="dtDcTbody">
                        </tbody>
                    </table>
                </div>
                <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark fs-6">Tổng số lượng:</span>
                    <span class="num-font fw-bold fs-5 text-danger" id="dtDcTotal">0</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
let availableProducts = [];

function formatProductOption(opt) {
    if (!opt.id || opt.id === "") { return opt.text; }
    var imgUrl = $(opt.element).attr('data-img');
    var maxTon = $(opt.element).attr('data-max');
    if (!imgUrl || imgUrl === '') {
        return $(`<span><div style="display:inline-block;width:28px;height:28px;background:#f1f5f9;border-radius:6px;margin-right:10px;vertical-align:middle;border:1px solid var(--border-light);"></div>${opt.text} <small class="text-muted ms-1">(Tồn: ${maxTon})</small></span>`);
    }
    return $(`<span><img src="${imgUrl}" style="width:28px;height:28px;object-fit:cover;border-radius:6px;margin-right:10px;border:1px solid var(--border-light);vertical-align:middle;" /> ${opt.text} <small class="text-muted ms-1">(Tồn: ${maxTon})</small></span>`);
}

function formatProductSelection (opt) {
    if (!opt.id || opt.id === "") { return opt.text; }
    var imgUrl = $(opt.element).attr('data-img');
    var maxTon = $(opt.element).attr('data-max');
    var title = $(opt.element).text().trim();
    if (!imgUrl || imgUrl === '') {
        return $('<span><div style="display:inline-block;width:48px;height:48px;background:#f8fafc;border-radius:8px;margin-right:12px;vertical-align:middle;border:1px dashed var(--border-subtle); text-align:center; line-height:48px;"><i class="fa-solid fa-image text-muted"></i></div><span class="fw-semibold text-dark">' + opt.text + '</span> <small class="text-muted ms-1">(Tồn: ' + maxTon + ')</small></span>');
    }
    
    var $img = $('<img src="' + imgUrl + '" style="width:48px;height:48px;object-fit:cover;border-radius:8px;margin-right:12px;border:1px solid var(--border-light);vertical-align:middle;box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: zoom-in; transition: transform 0.2s ease;" />');
    
    $img.on('mouseenter', function() { $(this).css('transform', 'scale(1.15)'); });
    $img.on('mouseleave', function() { $(this).css('transform', 'scale(1)'); });
    $img.on('mousedown click', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Ngăn không cho Select2 mở danh sách khi bấm vào ảnh
        viewLargeImage(imgUrl, title);
    });
    
    var $span = $('<span></span>').append($img).append('<span class="fw-semibold text-dark">' + opt.text + '</span> <small class="text-muted ms-1">(Tồn: ' + maxTon + ')</small>');
    return $span;
}

function initSelect2() {
    $('.sp-select').select2({
        templateResult: formatProductOption,
        templateSelection: formatProductSelection,
        width: '100%'
    }).on('change', function() {
        updateProductData(this);
    });
}

function loadKhoXuatData(cuahangId) {
    const nvSelect = document.getElementById('nguoiChuyenSelect');
    const tbody = document.getElementById('dsSanPhamInputs');
    const khoNhanSelect = document.getElementById('khoNhanSelect');
    
    tbody.innerHTML = '';
    availableProducts = [];
    calcGrandTotal();
    
    Array.from(khoNhanSelect.options).forEach(opt => {
        if(opt.value !== "") {
            opt.disabled = (opt.value === cuahangId);
        }
    });

    if (!cuahangId) {
        nvSelect.innerHTML = '<option value="">-- Chọn kho xuất trước --</option>';
        return;
    }
    
    nvSelect.innerHTML = '<option value="">-- Đang tải... --</option>';
    
    fetch('index.php?ajax=get_data_xuat&cuahang_id=' + cuahangId)
        .then(response => response.json())
        .then(data => {
            if (data.nhanvien.length === 0) {
                nvSelect.innerHTML = '<option value="">-- Kho không có nhân viên --</option>';
            } else {
                let html = '<option value="">-- Chọn nhân viên thực hiện --</option>';
                data.nhanvien.forEach(nv => {
                    html += `<option value="${nv.id}">${nv.ho_ten} (${nv.chuc_vu})</option>`;
                });
                nvSelect.innerHTML = html;
            }
            
            availableProducts = data.sanpham;
            if (availableProducts.length > 0) {
                addRow();
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Kho xuất này hiện không có sản phẩm nào tồn kho!</td></tr>';
            }
        })
        .catch(error => {
            nvSelect.innerHTML = '<option value="">-- Lỗi dữ liệu --</option>';
            console.error(error);
        });
}

function updateProductData(selectElement) {
    const row = selectElement.closest('tr');
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    
    const maxTon = selectedOption.getAttribute('data-max') || 0;
    row.querySelector('.sp-ton-text').innerText = new Intl.NumberFormat('vi-VN').format(maxTon);
    
    const qtyInput = row.querySelector('.sp-qty');
    qtyInput.max = maxTon;
    qtyInput.value = 1;
    
    calcGrandTotal();
}

function checkQty(element) {
    const max = parseInt(element.max) || 0;
    const val = parseInt(element.value) || 0;
    if (val > max) {
        element.value = max;
        alert('Số lượng chuyển không được vượt quá tồn kho hiện tại (' + max + ')!');
    }
    calcGrandTotal();
}

function calcGrandTotal() {
    let grandTotal = 0;
    const inputs = document.querySelectorAll('.sp-qty');
    inputs.forEach(input => {
        grandTotal += parseInt(input.value) || 0;
    });
    document.getElementById('grandTotalQty').innerText = new Intl.NumberFormat('vi-VN').format(grandTotal);
}

function addRow() {
    if (availableProducts.length === 0) {
        alert('Vui lòng chọn Kho xuất có hàng hóa trước!');
        return;
    }

    $('.sp-select').select2('destroy');
    
    const tbody = document.getElementById('dsSanPhamInputs');
    const tr = document.createElement('tr');
    
    let optionsHtml = '<option value="" data-max="0" data-img="">-- Chọn sản phẩm --</option>';
    availableProducts.forEach(sp => {
        optionsHtml += `<option value="${sp.id}" data-max="${sp.so_luong}" data-img="${sp.img_url}">${sp.ma_san_pham} - ${sp.ten_san_pham}</option>`;
    });

    tr.innerHTML = `
        <td>
            <select name="sp_id[]" class="form-select sp-select" required onchange="updateProductData(this)">
                ${optionsHtml}
            </select>
        </td>
        <td class="text-end align-middle">
            <span class="num-font fw-bold text-muted sp-ton-text">0</span>
        </td>
        <td>
            <input type="number" name="sl[]" class="form-control text-end sp-qty" min="1" max="0" value="1" required oninput="checkQty(this)">
        </td>
        <td class="text-center align-middle">
            <button type="button" class="action-icon-btn sm text-danger mx-auto" onclick="removeRow(this)" title="Xóa dòng">
                <i class="fa-solid fa-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(tr);
    initSelect2();
}

function removeRow(btn) {
    const tbody = document.getElementById('dsSanPhamInputs');
    if (tbody.children.length <= 1) {
        alert('Phải có ít nhất 1 dòng sản phẩm!');
        return;
    }
    $('.sp-select').select2('destroy');
    btn.closest('tr').remove();
    initSelect2();
    calcGrandTotal();
}


// Xử lý xem ảnh lớn
function viewLargeImage(url, title) {
    document.getElementById('largeImagePreview').src = url;
    document.getElementById('largeImageTitle').innerText = title;
    
    const modal = new bootstrap.Modal(document.getElementById('modalViewImage'));
    modal.show();
}

if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

function viewDcDetail(id, code, date, xuat, nhan, total) {
    document.getElementById('dtDcCode').innerText = code;
    document.getElementById('dtDcDate').innerText = date;
    document.getElementById('dtDcXuat').innerText = xuat;
    document.getElementById('dtDcNhan').innerText = nhan;
    document.getElementById('dtDcTotal').innerText = new Intl.NumberFormat('vi-VN').format(total);
    document.getElementById('dtDcTbody').innerHTML = '<tr><td colspan="3" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin text-muted me-2"></i> Đang tải dữ liệu...</td></tr>';
    
    const modal = new bootstrap.Modal(document.getElementById('modalDcDetail'));
    modal.show();

    fetch('index.php?ajax=get_ctdc&id=' + id)
        .then(response => response.json())
        .then(data => {
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="3" class="text-center text-muted py-4">Phiếu này không có sản phẩm nào.</td></tr>';
            } else {
                data.forEach(item => {
                    let imgHtml = item.img_url ? `<img src="${item.img_url}" style="width:48px;height:48px;object-fit:cover;border-radius:10px;border:1px solid var(--border-light);margin-right:12px;flex-shrink:0;box-shadow:0 2px 4px rgba(0,0,0,0.05);transition:transform 0.2s ease;cursor:zoom-in;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'" onclick="viewLargeImage('${item.img_url}', '${item.ten_san_pham.replace(/'/g, "\\'")}')">` : `<div class="d-flex align-items-center justify-content-center" style="width:48px;height:48px;border-radius:10px;background:#f8fafc;margin-right:12px;flex-shrink:0;border:1px dashed var(--border-subtle);"><i class="fa-solid fa-image text-muted"></i></div>`;
                    
                    html += `<tr>
                                <td class="align-middle"><span class="order-code">${item.ma_san_pham}</span></td>
                                <td class="align-middle">
                                    <div class="d-flex align-items-center" style="min-width: 200px;">
                                        ${imgHtml}
                                        <span class="fw-semibold text-dark">${item.ten_san_pham}</span>
                                    </div>
                                </td>
                                <td class="text-end num-font fw-bold align-middle">${item.so_luong}</td>
                             </tr>`;
                });
            }
            document.getElementById('dtDcTbody').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('dtDcTbody').innerHTML = '<tr><td colspan="3" class="text-center text-danger py-4">Lỗi tải dữ liệu. Vui lòng thử lại.</td></tr>';
        });
}

</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>