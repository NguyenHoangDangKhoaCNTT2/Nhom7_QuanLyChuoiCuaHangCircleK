<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Nhập hàng vào kho';
$activeMenu = 'nhapkho';

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

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_nv_kho') {
    $ch_id = (int)$_GET['cuahang_id'];
    $stmt = $pdo->prepare("
        SELECT id, ho_ten, chuc_vu 
        FROM nhanvien 
        WHERE cuahang_id = ? AND LOWER(chuc_vu) LIKE '%kho%'
        ORDER BY ho_ten ASC
    ");
    $stmt->execute([$ch_id]);
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_ctpn') {
    $pn_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("
        SELECT sp.ma_san_pham, sp.ten_san_pham, sp.hinh_anh, ct.so_luong, ct.gia_nhap
        FROM chitietphieunhap ct
        JOIN sanpham sp ON ct.sanpham_id = sp.id
        WHERE ct.phieunhap_id = ?
    ");
    $stmt->execute([$pn_id]);
    $details = $stmt->fetchAll();
    
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $projectBaseUrl = preg_replace('#/modules/.*$#', '', $scriptName);
    $imageDirPhysical = ROOT_PATH . '/assets/images/';
    
    foreach ($details as &$d) {
        $imgUrl = '';
        if (!empty($d['hinh_anh'])) {
            $cleanPath = ltrim($d['hinh_anh'], '/');
            $imgUrl = $projectBaseUrl . '/assets/images/' . basename($cleanPath);
        } else {
            $exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            foreach ($exts as $ext) {
                $tryUpper = strtoupper($d['ma_san_pham']) . '.' . $ext;
                $tryLower = strtolower($d['ma_san_pham']) . '.' . $ext;
                if (file_exists($imageDirPhysical . $tryUpper)) { $imgUrl = $projectBaseUrl . '/assets/images/' . $tryUpper; break; }
                if (file_exists($imageDirPhysical . $tryLower)) { $imgUrl = $projectBaseUrl . '/assets/images/' . $tryLower; break; }
            }
        }
        $d['img_url'] = $imgUrl;
    }
    
    header('Content-Type: application/json');
    echo json_encode($details);
    exit;
}

$alertMsg  = '';
$alertType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_pn') {
    try {
        $cuahang_id    = (int)($_POST['cuahang_id'] ?? 0);
        $nhacungcap_id = (int)($_POST['nhacungcap_id'] ?? 0);
        $nguoi_nhap    = (int)($_POST['nguoi_nhap'] ?? 0);
        $ngay_nhap     = trim($_POST['ngay_nhap'] ?? date('Y-m-d H:i:s'));
        $tong_tien     = (float)($_POST['tong_tien_hidden'] ?? 0);
        
        $sp_ids  = $_POST['sp_id'] ?? [];
        $sls     = $_POST['sl'] ?? [];
        $gias    = $_POST['gia'] ?? [];

        if ($cuahang_id <= 0 || $nhacungcap_id <= 0 || $nguoi_nhap <= 0 || empty($sp_ids)) {
            throw new Exception('Vui lòng chọn đầy đủ thông tin Cửa hàng, Nhà cung cấp, Người nhập và ít nhất 1 sản phẩm!');
        }

        $pdo->beginTransaction();

        $stmtPn = $pdo->prepare("INSERT INTO phieunhap (cuahang_id, nhacungcap_id, ngay_nhap, tong_tien, nguoi_nhap) VALUES (?, ?, ?, ?, ?)");
        $stmtPn->execute([$cuahang_id, $nhacungcap_id, $ngay_nhap, $tong_tien, $nguoi_nhap]);
        $pnId = $pdo->lastInsertId();

        $stmtCt = $pdo->prepare("INSERT INTO chitietphieunhap (phieunhap_id, sanpham_id, so_luong, gia_nhap) VALUES (?, ?, ?, ?)");
        $stmtTk = $pdo->prepare("UPDATE tonkho SET so_luong = so_luong + ? WHERE sanpham_id = ? AND cuahang_id = ?");
        
        for ($i = 0; $i < count($sp_ids); $i++) {
            $sp_id = (int)$sp_ids[$i];
            $sl    = (int)$sls[$i];
            $gia   = (float)$gias[$i];
            
            if ($sp_id > 0 && $sl > 0) {
                $stmtCt->execute([$pnId, $sp_id, $sl, $gia]);
                
                $stmtTk->execute([$sl, $sp_id, $cuahang_id]);
                
                if ($stmtTk->rowCount() === 0) {
                    $stmtInsTk = $pdo->prepare("INSERT INTO tonkho (sanpham_id, cuahang_id, so_luong) VALUES (?, ?, ?)");
                    $stmtInsTk->execute([$sp_id, $cuahang_id, $sl]);
                }
            }
        }

        $pdo->commit();
        $alertMsg = "Tạo phiếu nhập thành công và đã tự động cộng số lượng vào kho!";
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $alertMsg  = $ex->getMessage();
        $alertType = 'danger';
    }
}

$scriptName = $_SERVER['SCRIPT_NAME'];
$projectBaseUrl = preg_replace('#/modules/.*$#', '', $scriptName);
$imageDirPhysical = ROOT_PATH . '/assets/images/';

$dsCuaHang   = $pdo->query("SELECT id, ma_cua_hang, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();
$dsNcc       = $pdo->query("SELECT id, ten_ncc FROM nhacungcap ORDER BY ten_ncc ASC")->fetchAll();
$dsSanPham   = $pdo->query("SELECT id, ma_san_pham, ten_san_pham, gia_nhap, hinh_anh FROM sanpham WHERE trang_thai = 1 ORDER BY ten_san_pham ASC")->fetchAll();

foreach ($dsSanPham as &$sp) {
    $imgUrl = '';
    if (!empty($sp['hinh_anh'])) {
        $cleanPath = ltrim($sp['hinh_anh'], '/');
        $imgUrl = $projectBaseUrl . '/assets/images/' . basename($cleanPath);
    } else {
        $exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        foreach ($exts as $ext) {
            $tryUpper = strtoupper($sp['ma_san_pham']) . '.' . $ext;
            $tryLower = strtolower($sp['ma_san_pham']) . '.' . $ext;
            if (file_exists($imageDirPhysical . $tryUpper)) { $imgUrl = $projectBaseUrl . '/assets/images/' . $tryUpper; break; }
            if (file_exists($imageDirPhysical . $tryLower)) { $imgUrl = $projectBaseUrl . '/assets/images/' . $tryLower; break; }
        }
    }
    $sp['img_url'] = $imgUrl;
}

$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

$totalRows = $pdo->query("SELECT COUNT(*) FROM phieunhap")->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$sqlHistory = "
    SELECT pn.id, pn.ngay_nhap, pn.tong_tien, ch.ten_cua_hang, ncc.ten_ncc, nv.ho_ten AS nguoi_nhap
    FROM phieunhap pn
    JOIN cuahang ch ON pn.cuahang_id = ch.id
    JOIN nhacungcap ncc ON pn.nhacungcap_id = ncc.id
    LEFT JOIN nhanvien nv ON pn.nguoi_nhap = nv.id
    ORDER BY pn.id DESC
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
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-amber), #f97316)"><i class="fa-solid fa-dolly"></i></span>
                Phiếu nhập kho mới
            </h5>
            <div class="panel-subtitle ms-5">Tạo lệnh nhập hàng hóa từ nhà cung cấp vào chi nhánh</div>
        </div>
    </div>

    <form method="POST" action="" id="formNhapKho">
        <input type="hidden" name="action" value="save_pn">
        <input type="hidden" name="tong_tien_hidden" id="tong_tien_hidden" value="0">
        
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Kho nhận </label>
                <select class="form-select" name="cuahang_id" id="cuahangSelect" required onchange="loadNhanVienKho(this.value)">
                    <option value="">-- Chọn cửa hàng --</option>
                    <?php foreach ($dsCuaHang as $ch): ?>
                        <option value="<?= $ch['id'] ?>"><?= htmlspecialchars($ch['ma_cua_hang'] . ' - ' . $ch['ten_cua_hang']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Nhà cung cấp </label>
                <select class="form-select" name="nhacungcap_id" required>
                    <option value="">-- Chọn nhà cung cấp --</option>
                    <?php foreach ($dsNcc as $ncc): ?>
                        <option value="<?= $ncc['id'] ?>"><?= htmlspecialchars($ncc['ten_ncc']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Nhân Viên nhập</label>
                <select class="form-select" name="nguoi_nhap" id="nguoiNhapSelect" required>
                    <option value="">-- Chọn cửa hàng trước --</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Thời gian nhập</label>
                <input type="datetime-local" class="form-control" name="ngay_nhap" value="<?= date('Y-m-d\TH:i') ?>" required>
            </div>
        </div>

        <div class="table-responsive" style="overflow: visible;">
            <table class="table table-modern mb-0" id="tbSanPham">
                <thead>
                    <tr>
                        <th style="width: 45%;">Sản phẩm</th>
                        <th class="text-end" style="width: 15%;">Số lượng</th>
                        <th class="text-end" style="width: 20%;">Đơn giá nhập (₫)</th>
                        <th class="text-end" style="width: 15%;">Thành tiền</th>
                        <th class="text-center" style="width: 5%;"></th>
                    </tr>
                </thead>
                <tbody id="dsSanPhamInputs">
                    <tr>
                        <td>
                            <select name="sp_id[]" class="form-select sp-select" required onchange="updateProductData(this)">
                                <option value="" data-gia="0" data-img="">-- Chọn sản phẩm --</option>
                                <?php foreach ($dsSanPham as $sp): ?>
                                    <option value="<?= $sp['id'] ?>" data-gia="<?= $sp['gia_nhap'] ?>" data-img="<?= htmlspecialchars($sp['img_url']) ?>">
                                        <?= htmlspecialchars($sp['ma_san_pham'] . ' - ' . $sp['ten_san_pham']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="number" name="sl[]" class="form-control text-end sp-qty" min="1" value="1" required oninput="calcRow(this)">
                        </td>
                        <td>
                            <input type="number" name="gia[]" class="form-control text-end sp-price" min="0" required oninput="calcRow(this)">
                        </td>
                        <td class="text-end align-middle">
                            <span class="num-font fw-bold text-dark sp-total-text">0 ₫</span>
                        </td>
                        <td class="text-center align-middle">
                            <button type="button" class="action-icon-btn sm text-danger mx-auto" onclick="removeRow(this)" title="Xóa dòng">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <button type="button" class="btn-ck btn-ck-soft mt-3" onclick="addRow()">
            <i class="fa-solid fa-plus"></i> Thêm dòng sản phẩm
        </button>

        <div class="mt-4 pt-3 d-flex justify-content-between align-items-center" style="border-top: 1px dashed var(--border-light);">
            <span class="fw-bold fs-5 text-dark">Tổng tiền thanh toán:</span>
            <span class="num-font fw-bold" id="grandTotalText" style="font-size: 1.8rem; color: var(--ck-red);">0 ₫</span>
        </div>

        <div class="d-flex justify-content-end gap-3 mt-4">
            <a href="index.php" class="btn-ck btn-ck-soft text-decoration-none">Nhập lại</a>
            <button type="submit" class="btn-ck btn-ck-primary"><i class="fa-solid fa-check"></i> Xác nhận nhập kho</button>
        </div>
    </form>
</div>

<div class="bento-panel fade-up d-1 mt-4">
    <div class="panel-header mb-3">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-clock-rotate-left"></i></span>
                Lịch sử nhập kho
            </h5>
            <div class="panel-subtitle ms-5">Danh sách các phiếu nhập gần đây trên toàn hệ thống</div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead>
                <tr>
                    <th class="text-nowrap">Mã phiếu</th>
                    <th class="text-nowrap">Ngày nhập</th>
                    <th class="text-nowrap">Kho nhận</th>
                    <th class="text-nowrap">Nhà cung cấp</th>
                    <th class="text-nowrap">NHÂN VIÊN NHẬP</th>
                    <th class="text-end text-nowrap">Tổng tiền</th>
                    <th class="text-center text-nowrap">Trạng thái</th>
                    <th class="text-end text-nowrap">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dsLichSu)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">Chưa có lịch sử nhập kho nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($dsLichSu as $ls): 
                        $pnCode = 'PN' . str_pad($ls['id'], 4, '0', STR_PAD_LEFT);
                        $pnDate = date('d/m/Y H:i', strtotime($ls['ngay_nhap']));
                    ?>
                        <tr>
                            <td class="text-nowrap"><span class="order-code"><?= $pnCode ?></span></td>
                            <td class="text-nowrap"><?= $pnDate ?></td>
                            <td><?= htmlspecialchars($ls['ten_cua_hang']) ?></td>
                            <td><?= htmlspecialchars($ls['ten_ncc']) ?></td>
                            <td><span class="fw-semibold text-dark"><?= htmlspecialchars($ls['nguoi_nhap'] ?? 'System') ?></span></td>
                            <td class="text-end text-nowrap"><span class="num-font fw-bold"><?= number_format($ls['tong_tien'], 0, ',', '.') ?> ₫</span></td>
                            <td class="text-center text-nowrap"><span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Đã nhập kho</span></td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="action-icon-btn sm text-info ms-auto" title="Xem chi tiết"
                                    onclick='viewPnDetail(
                                        <?= $ls['id'] ?>, 
                                        "<?= $pnCode ?>", 
                                        "<?= $pnDate ?>", 
                                        <?= json_encode($ls['ten_cua_hang'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, 
                                        <?= json_encode($ls['ten_ncc'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, 
                                        <?= (float)$ls['tong_tien'] ?>
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

<div class="modal fade" id="modalPnDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Chi tiết phiếu nhập <span id="dtPnCode" class="text-danger"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom">
                    <div class="row text-dark">
                        <div class="col-sm-6 mb-2 mb-sm-0">
                            <div class="small text-muted mb-1">Thời gian nhập:</div>
                            <div class="fw-semibold" id="dtPnDate"></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted mb-1">Nhà cung cấp:</div>
                            <div class="fw-semibold" id="dtPnSupplier"></div>
                        </div>
                        <div class="col-12 mt-3">
                            <div class="small text-muted mb-1">Kho nhận:</div>
                            <div class="fw-semibold" id="dtPnStore"></div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-modern mb-0">
                        <thead style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th class="text-nowrap">Mã sản phẩm</th>
                                <th class="text-nowrap">Tên sản phẩm</th>
                                <th class="text-end text-nowrap">Số lượng</th>
                                <th class="text-end text-nowrap">Đơn giá</th>
                                <th class="text-end text-nowrap">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody id="dtPnTbody">
                        </tbody>
                    </table>
                </div>
                <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark fs-6">Tổng giá trị:</span>
                    <span class="num-font fw-bold fs-5 text-danger" id="dtPnTotal">0 ₫</span>
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
function formatMoney(amount) {
    return new Intl.NumberFormat('vi-VN').format(amount) + ' ₫';
}

function formatProductOption (opt) {
    if (!opt.id || opt.id === "") { return opt.text; }
    var imgUrl = $(opt.element).attr('data-img');
    if (!imgUrl || imgUrl === '') {
        return $('<span><div style="display:inline-block;width:48px;height:48px;background:#f8fafc;border-radius:8px;margin-right:12px;vertical-align:middle;border:1px dashed var(--border-subtle); text-align:center; line-height:48px;"><i class="fa-solid fa-image text-muted"></i></div><span class="fw-semibold text-dark">' + opt.text + '</span></span>');
    }
    var $opt = $(
        '<span><img src="' + imgUrl + '" style="width:48px;height:48px;object-fit:cover;border-radius:8px;margin-right:12px;border:1px solid var(--border-light);vertical-align:middle;box-shadow: 0 2px 4px rgba(0,0,0,0.05);" /> <span class="fw-semibold text-dark">' + opt.text + '</span></span>'
    );
    return $opt;
}

function formatProductSelection (opt) {
    if (!opt.id || opt.id === "") { return opt.text; }
    var imgUrl = $(opt.element).attr('data-img');
    var title = $(opt.element).text().trim();
    if (!imgUrl || imgUrl === '') {
        return $('<span><div style="display:inline-block;width:48px;height:48px;background:#f8fafc;border-radius:8px;margin-right:12px;vertical-align:middle;border:1px dashed var(--border-subtle); text-align:center; line-height:48px;"><i class="fa-solid fa-image text-muted"></i></div><span class="fw-semibold text-dark">' + opt.text + '</span></span>');
    }
    
    var $img = $('<img src="' + imgUrl + '" style="width:48px;height:48px;object-fit:cover;border-radius:8px;margin-right:12px;border:1px solid var(--border-light);vertical-align:middle;box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: zoom-in; transition: transform 0.2s ease;" />');
    
    $img.on('mouseenter', function() { $(this).css('transform', 'scale(1.15)'); });
    $img.on('mouseleave', function() { $(this).css('transform', 'scale(1)'); });
    $img.on('mousedown click', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Ngăn không cho Select2 mở danh sách khi bấm vào ảnh
        viewLargeImage(imgUrl, title);
    });
    
    var $span = $('<span></span>').append($img).append('<span class="fw-semibold text-dark">' + opt.text + '</span>');
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

$(document).ready(function() {
    initSelect2();
});

function updateProductData(selectElement) {
    const row = selectElement.closest('tr');
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    
    const price = selectedOption.getAttribute('data-gia') || 0;
    const priceInput = row.querySelector('.sp-price');
    priceInput.value = price;
    
    calcRow(priceInput);
}

function calcRow(element) {
    const row = element.closest('tr');
    const qty = parseFloat(row.querySelector('.sp-qty').value) || 0;
    const price = parseFloat(row.querySelector('.sp-price').value) || 0;
    const total = qty * price;
    
    row.querySelector('.sp-total-text').innerText = formatMoney(total);
    row.setAttribute('data-total', total);
    
    calcGrandTotal();
}

function calcGrandTotal() {
    let grandTotal = 0;
    const rows = document.querySelectorAll('#dsSanPhamInputs tr');
    rows.forEach(row => {
        grandTotal += parseFloat(row.getAttribute('data-total') || 0);
    });
    
    document.getElementById('grandTotalText').innerText = formatMoney(grandTotal);
    document.getElementById('tong_tien_hidden').value = grandTotal;
}

function addRow() {
    $('.sp-select').select2('destroy');
    
    const tbody = document.getElementById('dsSanPhamInputs');
    const firstRow = tbody.querySelector('tr');
    if (!firstRow) return;
    
    const newRow = firstRow.cloneNode(true);
    newRow.querySelector('.sp-select').value = '';
    newRow.querySelector('.sp-qty').value = 1;
    newRow.querySelector('.sp-price').value = '';
    newRow.querySelector('.sp-total-text').innerText = '0 ₫';
    newRow.setAttribute('data-total', 0);
    
    tbody.appendChild(newRow);
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

function viewPnDetail(id, code, date, store, supplier, total) {
    document.getElementById('dtPnCode').innerText = code;
    document.getElementById('dtPnDate').innerText = date;
    document.getElementById('dtPnStore').innerText = store;
    document.getElementById('dtPnSupplier').innerText = supplier;
    document.getElementById('dtPnTotal').innerText = formatMoney(total);
    document.getElementById('dtPnTbody').innerHTML = '<tr><td colspan="5" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin text-muted me-2"></i> Đang tải dữ liệu...</td></tr>';
    
    const modal = new bootstrap.Modal(document.getElementById('modalPnDetail'));
    modal.show();

    fetch('index.php?ajax=get_ctpn&id=' + id)
        .then(response => response.json())
        .then(data => {
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="5" class="text-center text-muted py-4">Phiếu nhập này không có sản phẩm nào.</td></tr>';
            } else {
                data.forEach(item => {
                    let subTotal = parseFloat(item.so_luong) * parseFloat(item.gia_nhap);
                    let imgHtml = item.img_url ? `<img src="${item.img_url}" style="width:48px;height:48px;object-fit:cover;border-radius:10px;border:1px solid var(--border-light);margin-right:12px;flex-shrink:0;box-shadow:0 2px 4px rgba(0,0,0,0.05);transition:transform 0.2s ease;cursor:zoom-in;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'" onclick="viewLargeImage('${item.img_url}', '${item.ten_san_pham.replace(/'/g, "\\'")}')">` : `<div class="d-flex align-items-center justify-content-center" style="width:48px;height:48px;border-radius:10px;background:#f8fafc;margin-right:12px;flex-shrink:0;border:1px dashed var(--border-subtle);"><i class="fa-solid fa-image text-muted"></i></div>`;
                    
                    html += `<tr>
                                <td class="align-middle"><span class="order-code">${item.ma_san_pham}</span></td>
                                <td class="align-middle">
                                    <div class="d-flex align-items-center" style="min-width: 200px;">
                                        ${imgHtml}
                                        <span class="fw-semibold text-dark">${item.ten_san_pham}</span>
                                    </div>
                                </td>
                                <td class="text-end fw-bold align-middle">${item.so_luong}</td>
                                <td class="text-end num-font text-muted align-middle">${formatMoney(item.gia_nhap)}</td>
                                <td class="text-end num-font fw-bold align-middle">${formatMoney(subTotal)}</td>
                             </tr>`;
                });
            }
            document.getElementById('dtPnTbody').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('dtPnTbody').innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Lỗi tải dữ liệu. Vui lòng thử lại.</td></tr>';
            console.error('Error fetching detail:', error);
        });
}

function loadNhanVienKho(cuahangId) {
    const nvSelect = document.getElementById('nguoiNhapSelect');
    if (!cuahangId) {
        nvSelect.innerHTML = '<option value="">-- Chọn cửa hàng trước --</option>';
        return;
    }
    
    nvSelect.innerHTML = '<option value="">-- Đang tải... --</option>';
    
    fetch('index.php?ajax=get_nv_kho&cuahang_id=' + cuahangId)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                nvSelect.innerHTML = '<option value="">-- Cửa hàng không có nhân viên kho --</option>';
            } else {
                let html = '<option value="">-- Chọn nhân viên kho --</option>';
                data.forEach(nv => {
                    html += `<option value="${nv.id}">${nv.ho_ten} (${nv.chuc_vu})</option>`;
                });
                nvSelect.innerHTML = html;
            }
        })
        .catch(error => {
            nvSelect.innerHTML = '<option value="">-- Lỗi dữ liệu --</option>';
            console.error(error);
        });
}


function viewLargeImage(url, title) {
    document.getElementById('largeImagePreview').src = url;
    document.getElementById('largeImageTitle').innerText = title;
    
    const modal = new bootstrap.Modal(document.getElementById('modalViewImage'));
    modal.show();
}

if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>