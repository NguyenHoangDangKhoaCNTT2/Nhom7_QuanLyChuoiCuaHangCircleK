<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Quản lý sản phẩm';
$activeMenu = 'sanpham';

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
    die('<div style="padding:20px;color:#e11d48;font-family:sans-serif;">
            <h3>Không thể kết nối Database!</h3>
            <small>Chi tiết lỗi: ' . htmlspecialchars($e->getMessage()) . '</small>
         </div>');
}

$imageDirPhysical = ROOT_PATH . '/assets/images/';
if (!is_dir($imageDirPhysical)) {
    @mkdir($imageDirPhysical, 0777, true);
}

$scriptName = $_SERVER['SCRIPT_NAME'];
$projectBaseUrl = preg_replace('#/modules/.*$#', '', $scriptName);

function getProductImageUrl($hinh_anh, $ma_sp, $imageDirPhysical, $projectBaseUrl) {
    if (!empty($hinh_anh)) {
        if (preg_match('#^https?://#i', $hinh_anh)) {
            return $hinh_anh;
        }
        $cleanPath = ltrim($hinh_anh, '/');
        if (strpos($cleanPath, 'assets/images/') === 0) {
            return $projectBaseUrl . '/' . $cleanPath;
        }
        return $projectBaseUrl . '/assets/images/' . basename($cleanPath);
    }
    $exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    foreach ($exts as $ext) {
        $tryUpper = strtoupper($ma_sp) . '.' . $ext;
        $tryLower = strtolower($ma_sp) . '.' . $ext;
        if (file_exists($imageDirPhysical . $tryUpper)) {
            return $projectBaseUrl . '/assets/images/' . $tryUpper;
        }
        if (file_exists($imageDirPhysical . $tryLower)) {
            return $projectBaseUrl . '/assets/images/' . $tryLower;
        }
    }
    return '';
}

$alertMsg  = '';
$alertType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $id           = (int)($_POST['id'] ?? 0);
            $ma_san_pham  = strtoupper(trim($_POST['ma_san_pham'] ?? ''));
            $ten_san_pham = trim($_POST['ten_san_pham'] ?? '');
            $danhmuc_id   = (int)($_POST['danhmuc_id'] ?? 0);
            $gia_nhap     = (float)($_POST['gia_nhap'] ?? 0);
            $gia_ban      = (float)($_POST['gia_ban'] ?? 0);
            $trang_thai   = (int)($_POST['trang_thai'] ?? 1);
            $old_image    = trim($_POST['old_image'] ?? '');

            if ($ma_san_pham === '' || $ten_san_pham === '' || $danhmuc_id <= 0) {
                throw new Exception('Vui lòng nhập đầy đủ Mã SKU, Tên sản phẩm và Danh mục!');
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM sanpham WHERE ma_san_pham = ? AND id != ?");
            $stmtCheck->execute([$ma_san_pham, $id]);
            if ($stmtCheck->fetch()) {
                throw new Exception("Mã sản phẩm '$ma_san_pham' đã tồn tại trên hệ thống!");
            }

            $hinh_anh = $old_image !== '' ? $old_image : null;
            if (isset($_FILES['hinh_anh_file']) && $_FILES['hinh_anh_file']['error'] === UPLOAD_ERR_OK) {
                $tmpName  = $_FILES['hinh_anh_file']['tmp_name'];
                $origName = $_FILES['hinh_anh_file']['name'];
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $allowed  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                if (!in_array($ext, $allowed)) {
                    throw new Exception('Chỉ hỗ trợ file ảnh định dạng JPG, PNG, WEBP, GIF!');
                }

                $newFileName = strtolower($ma_san_pham) . '_' . time() . '.' . $ext;
                if (move_uploaded_file($tmpName, $imageDirPhysical . $newFileName)) {
                    $hinh_anh = $newFileName;
                }
            }

            if ($id > 0) {
                $stmtUpdate = $pdo->prepare("
                    UPDATE sanpham 
                    SET danhmuc_id = ?, ma_san_pham = ?, ten_san_pham = ?, gia_nhap = ?, gia_ban = ?, hinh_anh = ?, trang_thai = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$danhmuc_id, $ma_san_pham, $ten_san_pham, $gia_nhap, $gia_ban, $hinh_anh, $trang_thai, $id]);
                $alertMsg = "Đã cập nhật sản phẩm <b>$ma_san_pham - $ten_san_pham</b> thành công!";
            } else {
                $pdo->beginTransaction();
                $stmtInsert = $pdo->prepare("
                    INSERT INTO sanpham (danhmuc_id, ma_san_pham, ten_san_pham, gia_nhap, gia_ban, hinh_anh, trang_thai)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtInsert->execute([$danhmuc_id, $ma_san_pham, $ten_san_pham, $gia_nhap, $gia_ban, $hinh_anh, $trang_thai]);
                $newSpId = (int)$pdo->lastInsertId();

                $stores = $pdo->query("SELECT id FROM cuahang")->fetchAll();
                $stmtTk = $pdo->prepare("INSERT IGNORE INTO tonkho (sanpham_id, cuahang_id, so_luong) VALUES (?, ?, 0)");
                foreach ($stores as $st) {
                    $stmtTk->execute([$newSpId, $st['id']]);
                }
                $pdo->commit();
                $alertMsg = "Đã thêm mới sản phẩm <b>$ma_san_pham - $ten_san_pham</b> thành công!";
            }
        }

        if ($action === 'delete') {
            $delId = (int)($_POST['delete_id'] ?? 0);
            if ($delId > 0) {
                $checkUsed = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM chitiethoadon WHERE sanpham_id = ?) +
                        (SELECT COUNT(*) FROM chitietphieunhap WHERE sanpham_id = ?) +
                        (SELECT COUNT(*) FROM chitietdieuchuyen WHERE sanpham_id = ?) AS total_used
                ");
                $checkUsed->execute([$delId, $delId, $delId]);
                $usedCount = (int)$checkUsed->fetchColumn();

                if ($usedCount > 0) {
                    $pdo->prepare("UPDATE sanpham SET trang_thai = 0 WHERE id = ?")->execute([$delId]);
                    $alertMsg  = "Sản phẩm này đã có lịch sử giao dịch nên được chuyển sang trạng thái <b>Ngừng bán</b> để bảo toàn dữ liệu!";
                    $alertType = 'warning';
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare("DELETE FROM tonkho WHERE sanpham_id = ?")->execute([$delId]);
                    $pdo->prepare("DELETE FROM sanpham WHERE id = ?")->execute([$delId]);
                    $pdo->commit();
                    $alertMsg = "Đã xóa sản phẩm khỏi hệ thống thành công!";
                }
            }
        }
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $alertMsg  = $ex->getMessage();
        $alertType = 'danger';
    }
}

$search     = trim($_GET['q'] ?? '');
$filterDm   = (int)($_GET['danhmuc_id'] ?? 0);
$filterCh   = (int)($_GET['cuahang_id'] ?? 0);
$filterSt   = $_GET['status'] ?? '';
$page       = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit      = 10;

$dsDanhMuc = $pdo->query("SELECT id, ten_danh_muc FROM danhmuc ORDER BY id ASC")->fetchAll();
$dsCuaHang = $pdo->query("SELECT id, ma_cua_hang, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();

$lastCodeRow = $pdo->query("SELECT ma_san_pham FROM sanpham WHERE ma_san_pham LIKE 'SP%' ORDER BY id DESC LIMIT 1")->fetch();
$nextSku = 'SP001';
if ($lastCodeRow && preg_match('/SP(\d+)/i', $lastCodeRow['ma_san_pham'], $m)) {
    $nextSku = 'SP' . str_pad(((int)$m[1]) + 1, 3, '0', STR_PAD_LEFT);
}

$sql = "
    SELECT 
        sp.id, sp.danhmuc_id, sp.ma_san_pham, sp.ten_san_pham, sp.gia_nhap, sp.gia_ban, sp.hinh_anh, sp.trang_thai, dm.ten_danh_muc,
        COALESCE(SUM(tk.so_luong), 0) AS tong_ton_kho
    FROM sanpham sp
    LEFT JOIN danhmuc dm ON sp.danhmuc_id = dm.id
    LEFT JOIN tonkho tk ON sp.id = tk.sanpham_id " . ($filterCh > 0 ? "AND tk.cuahang_id = :cuahang_id" : "") . "
    WHERE 1=1
";

$params = [];
if ($filterCh > 0) { $params[':cuahang_id'] = $filterCh; }
if ($search !== '') {
    $searchLower = mb_strtolower($search, 'UTF-8');
    $sql .= " AND (LOWER(sp.ma_san_pham) LIKE :search OR LOWER(sp.ten_san_pham) COLLATE utf8mb4_bin LIKE :search)";
    if ($searchLower === 'mì' || $searchLower === 'mi') {
        $sql .= " AND LOWER(sp.ten_san_pham) NOT LIKE '%bánh mì%'";
    }
    $params[':search'] = "%$searchLower%";
}
if ($filterDm > 0) {
    $sql .= " AND sp.danhmuc_id = :danhmuc_id";
    $params[':danhmuc_id'] = $filterDm;
}
if ($filterSt === 'ngung_ban') {
    $sql .= " AND sp.trang_thai = 0";
} elseif ($filterSt === 'dang_ban') {
    $sql .= " AND sp.trang_thai = 1";
}

$sql .= " GROUP BY sp.id, sp.danhmuc_id, sp.ma_san_pham, sp.ten_san_pham, sp.gia_nhap, sp.gia_ban, sp.hinh_anh, sp.trang_thai, dm.ten_danh_muc";

if ($filterSt === 'sap_het') {
    $sql .= " HAVING sp.trang_thai = 1 AND tong_ton_kho <= 20";
}
$sql .= " ORDER BY sp.id ASC";

$stmtList = $pdo->prepare($sql);
$stmtList->execute($params);
$allProducts = $stmtList->fetchAll();

$tongSp      = count($allProducts);
$tongDangBan = 0;
$tongSapHet  = 0;
foreach ($allProducts as $item) {
    if ((int)$item['trang_thai'] === 1) {
        $tongDangBan++;
        if ((int)$item['tong_ton_kho'] <= 20) {
            $tongSapHet++;
        }
    }
}

$totalPages = ceil($tongSp / $limit);
if ($page > $totalPages && $totalPages > 0) { $page = $totalPages; }
$offset = ($page - 1) * $limit;
$dsSanPham = array_slice($allProducts, $offset, $limit);

function buildPageUrl($pageNum) {
    $gets = $_GET;
    $gets['page'] = $pageNum;
    return '?' . http_build_query($gets);
}

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
    
    <div class="panel-header mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="title-icon d-flex align-items-center justify-content-center text-white rounded-4 shadow-sm" style="width: 56px; height: 56px; font-size: 1.75rem; background: linear-gradient(135deg, var(--ck-amber), #f97316);">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <div>
                <h4 class="panel-title mb-1 fw-bold fs-4" style="color: var(--ink-900);">Danh sách sản phẩm</h4>
                <div class="panel-subtitle text-muted fs-6 mb-0">Danh mục hàng hóa, giá nhập/bán và tồn kho</div>
            </div>
        </div>
    </div>

    <form method="GET" action="" class="row gx-2 gy-2 mb-4 align-items-center">
        <div class="col-auto">
            <div class="search-bar" style="width: 260px;">
                <i class="fa-solid fa-magnifying-glass s-ico"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm theo mã sản phẩm, tên...">
            </div>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn-ck btn-ck-soft"><i class="fa-solid fa-filter"></i> Lọc</button>
        </div>
        <div class="col-auto">
            <select name="danhmuc_id" class="form-select" onchange="this.form.submit()">
                <option value="0">Tất cả danh mục</option>
                <?php foreach ($dsDanhMuc as $dm): ?>
                    <option value="<?= $dm['id'] ?>" <?= $filterDm === (int)$dm['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dm['ten_danh_muc']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <select name="cuahang_id" class="form-select" onchange="this.form.submit()">
                <option value="0">Tồn kho: Toàn chuỗi (12 CH)</option>
                <?php foreach ($dsCuaHang as $ch): ?>
                    <option value="<?= $ch['id'] ?>" <?= $filterCh === (int)$ch['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ch['ma_cua_hang'] . ' - ' . $ch['ten_cua_hang']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">Tất cả trạng thái</option>
                <option value="dang_ban" <?= $filterSt === 'dang_ban' ? 'selected' : '' ?>>Đang bán</option>
                <option value="sap_het" <?= $filterSt === 'sap_het' ? 'selected' : '' ?>>Sắp hết (≤ 20)</option>
                <option value="ngung_ban" <?= $filterSt === 'ngung_ban' ? 'selected' : '' ?>>Ngừng bán</option>
            </select>
        </div>
        <?php if ($search !== '' || $filterDm > 0 || $filterCh > 0 || $filterSt !== ''): ?>
        <div class="col-auto">
            <a href="index.php" class="btn-ck btn-ck-soft text-danger"><i class="fa-solid fa-rotate-left"></i> Đặt lại</a>
        </div>
        <?php endif; ?>
        <div class="col-auto ms-auto">
            <button type="button" class="btn-ck btn-ck-primary" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Thêm sản phẩm
            </button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead>
                <tr>
                    <th class="text-nowrap">Mã SẢN PHẨM</th>
                    <th class="text-nowrap">Tên sản phẩm</th>
                    <th class="text-nowrap">Danh mục</th>
                    <th class="text-end text-nowrap">Giá nhập</th>
                    <th class="text-end text-nowrap">Giá bán</th>
                    <th class="text-end text-nowrap">Tồn kho <?= $filterCh > 0 ? '(CH)' : '(Chuỗi)' ?></th>
                    <th class="text-center text-nowrap">Trạng thái</th>
                    <th class="text-end text-nowrap">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dsSanPham)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-box-open fs-1 d-block mb-3 opacity-50"></i>
                            Không tìm thấy sản phẩm nào phù hợp với bộ lọc.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($dsSanPham as $sp): 
                        $tonKho    = (int)$sp['tong_ton_kho'];
                        $trangThai = (int)$sp['trang_thai'];
                        $imgUrl    = getProductImageUrl($sp['hinh_anh'], $sp['ma_san_pham'], $imageDirPhysical, $projectBaseUrl);
                    ?>
                    <tr>
                        <td class="text-nowrap align-middle"><span class="order-code"><?= htmlspecialchars($sp['ma_san_pham']) ?></span></td>
                        <td class="align-middle">
                            <div class="d-flex align-items-center gap-3" style="min-width: 280px;">
                                <?php if ($imgUrl !== ''): ?>
                                    <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($sp['ten_san_pham']) ?>" 
                                         style="width:72px;height:72px;object-fit:cover;border-radius:12px;border:1px solid var(--border-light);flex-shrink:0;box-shadow: 0 4px 8px rgba(0,0,0,0.06); transition: transform 0.2s ease-in-out; cursor: zoom-in;" 
                                         onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'"
                                         onclick="viewLargeImage('<?= htmlspecialchars($imgUrl) ?>', '<?= htmlspecialchars(addslashes($sp['ten_san_pham'])) ?>')">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center" style="background:#f8fafc;color:var(--ink-400);width:72px;height:72px;border-radius:12px;flex-shrink:0;border:1px dashed var(--border-subtle);font-size:1.8rem;">
                                        <i class="fa-solid fa-image text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span class="fw-bold text-dark fs-6 d-block mb-1"><?= htmlspecialchars($sp['ten_san_pham']) ?></span>
                                    <span class="small text-muted">SKU: <?= htmlspecialchars($sp['ma_san_pham']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="text-nowrap align-middle"><?= htmlspecialchars($sp['ten_danh_muc'] ?? 'Chưa phân loại') ?></td>
                        <td class="text-end text-nowrap align-middle"><span class="num-font text-muted"><?= number_format($sp['gia_nhap'], 0, ',', '.') ?> ₫</span></td>
                        <td class="text-end text-nowrap align-middle"><span class="num-font fw-bold text-dark fs-6"><?= number_format($sp['gia_ban'], 0, ',', '.') ?> ₫</span></td>
                        <td class="text-end text-nowrap align-middle"><span class="num-font fw-bold text-dark fs-6"><?= number_format($tonKho, 0, ',', '.') ?></span></td>
                        <td class="text-center text-nowrap align-middle">
                            <?php if ($trangThai === 0): ?>
                                <span class="st-badge st-cancel"><i class="fa-solid fa-ban"></i> Ngừng bán</span>
                            <?php elseif ($tonKho <= 20): ?>
                                <span class="st-badge st-wait"><i class="fa-solid fa-circle-exclamation"></i> Sắp hết</span>
                            <?php else: ?>
                                <span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Đang bán</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap align-middle">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="action-icon-btn sm" title="Sửa"
                                    onclick='openEditModal(<?= json_encode([
                                        "id"           => $sp["id"],
                                        "ma_san_pham"  => $sp["ma_san_pham"],
                                        "ten_san_pham" => $sp["ten_san_pham"],
                                        "danhmuc_id"   => $sp["danhmuc_id"],
                                        "gia_nhap"     => (float)$sp["gia_nhap"],
                                        "gia_ban"      => (float)$sp["gia_ban"],
                                        "trang_thai"   => (int)$sp["trang_thai"],
                                        "hinh_anh"     => $sp["hinh_anh"] ?? "",
                                        "img_url"      => $imgUrl
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" class="action-icon-btn sm text-danger" title="Xóa"
                                    onclick="confirmDelete(<?= (int)$sp['id'] ?>, '<?= htmlspecialchars(addslashes($sp['ma_san_pham'] . ' - ' . $sp['ten_san_pham'])) ?>')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
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

<div class="modal fade" id="modalSanPham" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="spId" value="0">
            <input type="hidden" name="old_image" id="spOldImage" value="">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalSanPhamTitle">Thêm sản phẩm mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="spMa">Mã sản phẩm </label>
                        <input type="text" class="form-control" name="ma_san_pham" id="spMa" value="<?= htmlspecialchars($nextSku) ?>" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold" for="spTen">Tên sản phẩm</label>
                        <input type="text" class="form-control" name="ten_san_pham" id="spTen" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="spDm">Danh mục </label>
                        <select class="form-select" name="danhmuc_id" id="spDm" required>
                            <?php foreach ($dsDanhMuc as $dm): ?>
                                <option value="<?= $dm['id'] ?>"><?= htmlspecialchars($dm['ten_danh_muc']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="spTt">Trạng thái</label>
                        <select class="form-select" name="trang_thai" id="spTt">
                            <option value="1">Đang bán</option>
                            <option value="0">Ngừng bán</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="spGiaNhap">Giá nhập (VND) </label>
                        <input type="number" class="form-control" name="gia_nhap" id="spGiaNhap" min="0" step="100" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="spGia">Giá bán (₫) </label>
                        <input type="number" class="form-control" name="gia_ban" id="spGia" min="0" step="100" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold" for="spFile">Hình ảnh sản phẩm</label>
                        <input type="file" class="form-control" name="hinh_anh_file" id="spFile" accept="image/*" onchange="previewSelectedImage(this)">
                        <div class="mt-2 d-none align-items-center gap-3" id="spImgPreviewBox">
                            <img src="" id="spImgPreview" alt="Preview" style="width:64px;height:64px;object-fit:cover;border-radius:12px;border:1px solid var(--border-light);">
                            <span class="text-muted small"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn-ck btn-ck-primary"><i class="fa-solid fa-floppy-disk"></i> Lưu sản phẩm</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" action="" id="deleteSpForm" class="d-none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="delete_id" id="deleteSpId" value="0">
</form>

<script>
const nextSuggestedSku = <?= json_encode($nextSku) ?>;

function openAddModal() {
    document.getElementById('modalSanPhamTitle').innerText = 'Thêm sản phẩm mới';
    document.getElementById('spId').value = 0;
    document.getElementById('spMa').value = nextSuggestedSku;
    document.getElementById('spTen').value = '';
    document.getElementById('spGiaNhap').value = '';
    document.getElementById('spGia').value = '';
    document.getElementById('spTt').value = '1';
    document.getElementById('spOldImage').value = '';
    document.getElementById('spFile').value = '';
    document.getElementById('spImgPreviewBox').classList.remove('d-flex');
    document.getElementById('spImgPreviewBox').classList.add('d-none');

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSanPham'));
    modal.show();
}

function openEditModal(sp) {
    document.getElementById('modalSanPhamTitle').innerText = 'Cập nhật sản phẩm: ' + sp.ma_san_pham;
    document.getElementById('spId').value = sp.id;
    document.getElementById('spMa').value = sp.ma_san_pham;
    document.getElementById('spTen').value = sp.ten_san_pham;
    document.getElementById('spDm').value = sp.danhmuc_id;
    document.getElementById('spGiaNhap').value = sp.gia_nhap;
    document.getElementById('spGia').value = sp.gia_ban;
    document.getElementById('spTt').value = sp.trang_thai;
    document.getElementById('spOldImage').value = sp.hinh_anh || '';
    document.getElementById('spFile').value = '';

    const previewBox = document.getElementById('spImgPreviewBox');
    const previewImg = document.getElementById('spImgPreview');
    if (sp.img_url && sp.img_url !== '') {
        previewImg.src = sp.img_url;
        previewBox.classList.remove('d-none');
        previewBox.classList.add('d-flex');
    } else {
        previewBox.classList.remove('d-flex');
        previewBox.classList.add('d-none');
    }

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSanPham'));
    modal.show();
}

function previewSelectedImage(input) {
    const previewBox = document.getElementById('spImgPreviewBox');
    const previewImg = document.getElementById('spImgPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewBox.classList.remove('d-none');
            previewBox.classList.add('d-flex');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function confirmDelete(id, name) {
    if (confirm('Bạn có chắc chắn muốn xóa sản phẩm [' + name + '] không?')) {
        document.getElementById('deleteSpId').value = id;
        document.getElementById('deleteSpForm').submit();
    }
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