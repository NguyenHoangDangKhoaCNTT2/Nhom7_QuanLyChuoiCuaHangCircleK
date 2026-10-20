<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Quản lý Sản phẩm';
$activeMenu = 'sanpham';

// ==========================================================
// 1. KẾT NỐI CƠ SỞ DỮ LIỆU (MySQL / phpMyAdmin - DB: circlek)
// ==========================================================
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
            <p>Vui lòng kiểm tra MySQL trong XAMPP đã Start chưa và đã Import file SQL tạo database <b>circlek</b> chưa.</p>
            <small>Chi tiết lỗi: ' . htmlspecialchars($e->getMessage()) . '</small>
         </div>');
}

// Đường dẫn thư mục ảnh vật lý và URL hiển thị ảnh
$imageDirPhysical = ROOT_PATH . '/assets/images/';
if (!is_dir($imageDirPhysical)) {
    @mkdir($imageDirPhysical, 0777, true);
}

// Tính toán đường dẫn tương đối từ gốc web tới thư mục dự án
$scriptName = $_SERVER['SCRIPT_NAME']; // VD: /Nhom7_QuanLyChuoiCuaHangCircleK/modules/hang_hoa_kho/quan_ly_san_pham/index.php
$projectBaseUrl = preg_replace('#/modules/.*$#', '', $scriptName);

// Hàm lấy URL ảnh sản phẩm chuẩn
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
    // Tự động dò ảnh theo mã SP trong assets/images nếu cột hinh_anh đang NULL
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

// ==========================================================
// 2. XỬ LÝ THÊM / SỬA / XÓA SẢN PHẨM (POST)
// ==========================================================
$alertMsg  = '';
$alertType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // --- XỬ LÝ THÊM HOẶC SỬA ---
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

            // Kiểm tra trùng mã sản phẩm
            $stmtCheck = $pdo->prepare("SELECT id FROM sanpham WHERE ma_san_pham = ? AND id != ?");
            $stmtCheck->execute([$ma_san_pham, $id]);
            if ($stmtCheck->fetch()) {
                throw new Exception("Mã sản phẩm '$ma_san_pham' đã tồn tại trên hệ thống!");
            }

            // Xử lý upload file ảnh vào thư mục assets/images/
            $hinh_anh = $old_image !== '' ? $old_image : null;
            if (isset($_FILES['hinh_anh_file']) && $_FILES['hinh_anh_file']['error'] === UPLOAD_ERR_OK) {
                $tmpName  = $_FILES['hinh_anh_file']['tmp_name'];
                $origName = $_FILES['hinh_anh_file']['name'];
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $allowed  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                if (!in_array($ext, $allowed)) {
                    throw new Exception('Chỉ hỗ trợ file ảnh định dạng JPG, PNG, WEBP, GIF!');
                }

                // Đặt tên file chuẩn theo mã sản phẩm (VD: SP089_1710000.jpg)
                $newFileName = strtolower($ma_san_pham) . '_' . time() . '.' . $ext;
                if (move_uploaded_file($tmpName, $imageDirPhysical . $newFileName)) {
                    $hinh_anh = $newFileName;
                }
            }

            if ($id > 0) {
                // CẬP NHẬT SẢN PHẨM
                $stmtUpdate = $pdo->prepare("
                    UPDATE sanpham 
                    SET danhmuc_id = ?, ma_san_pham = ?, ten_san_pham = ?, gia_nhap = ?, gia_ban = ?, hinh_anh = ?, trang_thai = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$danhmuc_id, $ma_san_pham, $ten_san_pham, $gia_nhap, $gia_ban, $hinh_anh, $trang_thai, $id]);
                $alertMsg = "Đã cập nhật sản phẩm <b>$ma_san_pham - $ten_san_pham</b> thành công!";
            } else {
                // THÊM MỚI SẢN PHẨM
                $pdo->beginTransaction();
                $stmtInsert = $pdo->prepare("
                    INSERT INTO sanpham (danhmuc_id, ma_san_pham, ten_san_pham, gia_nhap, gia_ban, hinh_anh, trang_thai)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtInsert->execute([$danhmuc_id, $ma_san_pham, $ten_san_pham, $gia_nhap, $gia_ban, $hinh_anh, $trang_thai]);
                $newSpId = (int)$pdo->lastInsertId();

                // Tự động khởi tạo tồn kho = 0 cho tất cả các cửa hàng trong chuỗi
                $stores = $pdo->query("SELECT id FROM cuahang")->fetchAll();
                $stmtTk = $pdo->prepare("INSERT IGNORE INTO tonkho (sanpham_id, cuahang_id, so_luong) VALUES (?, ?, 0)");
                foreach ($stores as $st) {
                    $stmtTk->execute([$newSpId, $st['id']]);
                }
                $pdo->commit();
                $alertMsg = "Đã thêm mới sản phẩm <b>$ma_san_pham - $ten_san_pham</b> thành công!";
            }
        }

        // --- XỬ LÝ XÓA SẢN PHẨM ---
        if ($action === 'delete') {
            $delId = (int)($_POST['delete_id'] ?? 0);
            if ($delId > 0) {
                // Kiểm tra xem sản phẩm đã phát sinh hóa đơn, phiếu nhập hay điều chuyển chưa
                $checkUsed = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM chitiethoadon WHERE sanpham_id = ?) +
                        (SELECT COUNT(*) FROM chitietphieunhap WHERE sanpham_id = ?) +
                        (SELECT COUNT(*) FROM chitietdieuchuyen WHERE sanpham_id = ?) AS total_used
                ");
                $checkUsed->execute([$delId, $delId, $delId]);
                $usedCount = (int)$checkUsed->fetchColumn();

                if ($usedCount > 0) {
                    // Đã có lịch sử giao dịch -> Chuyển sang Ngừng bán để bảo toàn dữ liệu thống kê
                    $pdo->prepare("UPDATE sanpham SET trang_thai = 0 WHERE id = ?")->execute([$delId]);
                    $alertMsg  = "Sản phẩm này đã có lịch sử hóa đơn/nhập kho nên được chuyển sang trạng thái <b>Ngừng bán</b> để bảo toàn dữ liệu!";
                    $alertType = 'warning';
                } else {
                    // Chưa có giao dịch -> Xóa tồn kho và xóa hẳn sản phẩm
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

// ==========================================================
// 3. TRUY VẤN DỮ LIỆU HIỂN THỊ & BỘ LỌC (GET)
// ==========================================================
$search     = trim($_GET['q'] ?? '');
$filterDm   = (int)($_GET['danhmuc_id'] ?? 0);
$filterCh   = (int)($_GET['cuahang_id'] ?? 0);
$filterSt   = $_GET['status'] ?? '';

// Lấy danh sách Danh mục & Cửa hàng cho combobox lọc
$dsDanhMuc = $pdo->query("SELECT id, ten_danh_muc FROM danhmuc ORDER BY id ASC")->fetchAll();
$dsCuaHang = $pdo->query("SELECT id, ma_cua_hang, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();

// Gợi ý mã SP tiếp theo cho form Thêm mới (VD: SP089)
$lastCodeRow = $pdo->query("SELECT ma_san_pham FROM sanpham WHERE ma_san_pham LIKE 'SP%' ORDER BY id DESC LIMIT 1")->fetch();
$nextSku = 'SP001';
if ($lastCodeRow && preg_match('/SP(\d+)/i', $lastCodeRow['ma_san_pham'], $m)) {
    $nextSku = 'SP' . str_pad(((int)$m[1]) + 1, 3, '0', STR_PAD_LEFT);
}

// Câu truy vấn lấy danh sách sản phẩm kèm tổng tồn kho (toàn chuỗi hoặc theo từng cửa hàng)
$sql = "
    SELECT 
        sp.id,
        sp.danhmuc_id,
        sp.ma_san_pham,
        sp.ten_san_pham,
        sp.gia_nhap,
        sp.gia_ban,
        sp.hinh_anh,
        sp.trang_thai,
        dm.ten_danh_muc,
        COALESCE(SUM(tk.so_luong), 0) AS tong_ton_kho
    FROM sanpham sp
    LEFT JOIN danhmuc dm ON sp.danhmuc_id = dm.id
    LEFT JOIN tonkho tk ON sp.id = tk.sanpham_id " . ($filterCh > 0 ? "AND tk.cuahang_id = :cuahang_id" : "") . "
    WHERE 1=1
";

$params = [];
if ($filterCh > 0) {
    $params[':cuahang_id'] = $filterCh;
}
if ($search !== '') {
    $sql .= " AND (sp.ma_san_pham LIKE :search OR sp.ten_san_pham LIKE :search)";
    $params[':search'] = "%$search%";
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

$sql .= " ORDER BY sp.id DESC";

$stmtList = $pdo->prepare($sql);
$stmtList->execute($params);
$dsSanPham = $stmtList->fetchAll();

// Thống kê nhanh trên đầu trang
$tongSp      = count($dsSanPham);
$tongDangBan = 0;
$tongSapHet  = 0;
foreach ($dsSanPham as $item) {
    if ((int)$item['trang_thai'] === 1) {
        $tongDangBan++;
        if ((int)$item['tong_ton_kho'] <= 20) {
            $tongSapHet++;
        }
    }
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
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-amber), #f97316)"><i class="fa-solid fa-boxes-stacked"></i></span>
                Danh sách sản phẩm
            </h5>
            <div class="panel-subtitle ms-5">
                Danh mục hàng hóa, giá nhập/bán và tồn kho 
                <span class="ms-2 badge rounded-pill bg-light text-dark border">Tổng: <?= $tongSp ?> SP</span>
                <?php if ($tongSapHet > 0): ?>
                    <span class="ms-1 badge rounded-pill" style="background:#fff1f2;color:#e11d48;border:1px solid #fecdd3;">Sắp hết/Hết hàng: <?= $tongSapHet ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Thanh công cụ Tìm kiếm & Lọc -->
    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass s-ico"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm theo mã SKU, tên...">
        </div>

        <select name="danhmuc_id" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="0">Tất cả danh mục</option>
            <?php foreach ($dsDanhMuc as $dm): ?>
                <option value="<?= $dm['id'] ?>" <?= $filterDm === (int)$dm['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dm['ten_danh_muc']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="cuahang_id" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="0">Tồn kho: Toàn chuỗi (12 CH)</option>
            <?php foreach ($dsCuaHang as $ch): ?>
                <option value="<?= $ch['id'] ?>" <?= $filterCh === (int)$ch['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ch['ma_cua_hang'] . ' - ' . $ch['ten_cua_hang']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            <option value="dang_ban" <?= $filterSt === 'dang_ban' ? 'selected' : '' ?>>Đang bán</option>
            <option value="sap_het" <?= $filterSt === 'sap_het' ? 'selected' : '' ?>>Sắp hết (≤ 20)</option>
            <option value="ngung_ban" <?= $filterSt === 'ngung_ban' ? 'selected' : '' ?>>Ngừng bán</option>
        </select>

        <button type="submit" class="btn-ck btn-ck-soft"><i class="fa-solid fa-filter"></i> Lọc</button>
        <?php if ($search !== '' || $filterDm > 0 || $filterCh > 0 || $filterSt !== ''): ?>
            <a href="index.php" class="btn-ck btn-ck-soft text-danger"><i class="fa-solid fa-rotate-left"></i> Đặt lại</a>
        <?php endif; ?>

        <button type="button" class="btn-ck btn-ck-primary ms-auto" onclick="openAddModal()">
            <i class="fa-solid fa-plus"></i> Thêm sản phẩm
        </button>
    </form>

    <!-- Bảng danh sách sản phẩm -->
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead>
                <tr>
                    <th>Mã SKU</th>
                    <th>Tên sản phẩm</th>
                    <th>Danh mục</th>
                    <th class="text-end">Giá nhập</th>
                    <th class="text-end">Giá bán</th>
                    <th class="text-end">Tồn kho <?= $filterCh > 0 ? '(CH)' : '(Chuỗi)' ?></th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dsSanPham)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-box-open fs-3 d-block mb-2"></i>
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
                        <td><span class="order-code"><?= htmlspecialchars($sp['ma_san_pham']) ?></span></td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($imgUrl !== ''): ?>
                                    <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($sp['ten_san_pham']) ?>" 
                                         style="width:42px;height:42px;object-fit:cover;border-radius:10px;border:1px solid var(--border-light);">
                                <?php else: ?>
                                    <div class="prod-rank-icon" style="background:#f1f5f9;color:var(--ink-400);width:42px;height:42px;border-radius:10px;">
                                        <i class="fa-solid fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span class="fw-semibold d-block"><?= htmlspecialchars($sp['ten_san_pham']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($sp['ten_danh_muc'] ?? 'Chưa phân loại') ?></td>
                        <td class="text-end"><span class="num-font text-muted"><?= number_format($sp['gia_nhap'], 0, ',', '.') ?> ₫</span></td>
                        <td class="text-end"><span class="num-font fw-bold"><?= number_format($sp['gia_ban'], 0, ',', '.') ?> ₫</span></td>
                        <td class="text-end"><span class="num-font fw-bold"><?= number_format($tonKho, 0, ',', '.') ?></span></td>
                        <td>
                            <?php if ($trangThai === 0): ?>
                                <span class="st-badge st-cancel"><i class="fa-solid fa-ban"></i> Ngừng bán</span>
                            <?php elseif ($tonKho <= 20): ?>
                                <span class="st-badge st-cancel"><i class="fa-solid fa-circle-xmark"></i> Sắp hết</span>
                            <?php else: ?>
                                <span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Đang bán</span>
                            <?php endif; ?>
                        </td>
                        <td>
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
</div>

<!-- Modal Thêm / Sửa sản phẩm -->
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
                        <label class="form-label fw-semibold" for="spMa">Mã SKU <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ma_san_pham" id="spMa" value="<?= htmlspecialchars($nextSku) ?>" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold" for="spTen">Tên sản phẩm <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ten_san_pham" id="spTen" placeholder="Nhập tên sản phẩm..." required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="spDm">Danh mục <span class="text-danger">*</span></label>
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
                        <label class="form-label fw-semibold" for="spGiaNhap">Giá nhập (₫) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="gia_nhap" id="spGiaNhap" min="0" step="100" placeholder="VD: 9600" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="spGia">Giá bán (₫) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="gia_ban" id="spGia" min="0" step="100" placeholder="VD: 12000" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold" for="spFile">Hình ảnh sản phẩm (Lưu vào <code>assets/images/</code>)</label>
                        <input type="file" class="form-control" name="hinh_anh_file" id="spFile" accept="image/*" onchange="previewSelectedImage(this)">
                        <div class="mt-2 d-none align-items-center gap-3" id="spImgPreviewBox">
                            <img src="" id="spImgPreview" alt="Preview" style="width:64px;height:64px;object-fit:cover;border-radius:12px;border:1px solid var(--border-light);">
                            <span class="text-muted small">Ảnh hiển thị của sản phẩm</span>
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

<!-- Form ẩn dùng để Xóa sản phẩm -->
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
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>