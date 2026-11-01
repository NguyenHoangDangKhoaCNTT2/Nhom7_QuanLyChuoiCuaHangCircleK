<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

$host = '127.0.0.1';
$db   = 'circlek';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) {
    die(json_encode(['success' => false, 'message' => $e->getMessage()]));
}

if (empty($_SESSION['user_id'])) {
    header('Location: ' . url('modules/tai_khoan_nhan_su/dang_nhap_phan_quyen/dang_nhap.php'));
    exit;
}
$stmtSignedIn = $pdo->prepare("SELECT nv.id, nv.ho_ten, nv.chuc_vu, nv.cuahang_id, nd.phanquyen_id FROM nhanvien nv JOIN nguoidung nd ON nd.id = nv.nguoidung_id WHERE nd.id = ? AND nd.trang_thai = 1 LIMIT 1");
$stmtSignedIn->execute([(int)$_SESSION['user_id']]);
$posSignedInEmployee = $stmtSignedIn->fetch();
if (!$posSignedInEmployee) {
    http_response_code(403);
    exit('Tài khoản đăng nhập không có hồ sơ nhân viên hợp lệ.');
}
$posStaffId = (int)$posSignedInEmployee['id'];
$posAssignedStoreId = (int)$posSignedInEmployee['cuahang_id'];
$posUserRole = (int)$posSignedInEmployee['phanquyen_id'];
$posCanSwitchStore = in_array($posUserRole, [1, 2], true);
$posStaffLabel = $posSignedInEmployee['ho_ten'];
if (trim((string)$posSignedInEmployee['chuc_vu']) !== '') {
    $posStaffLabel .= ' [' . $posSignedInEmployee['chuc_vu'] . ']';
}

$posAllStores = $pdo->query("SELECT id, ma_cua_hang, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();
$posStoreCodeIds = array_column($posAllStores, 'id', 'ma_cua_hang');
$posStoreNames = array_column($posAllStores, 'ten_cua_hang', 'id');
$posRegionalLeaders = $pdo->query("SELECT nv.id, nv.ho_ten FROM nhanvien nv JOIN nguoidung nd ON nd.id = nv.nguoidung_id WHERE nd.phanquyen_id = 2 AND nd.trang_thai = 1 ORDER BY nv.id ASC")->fetchAll();
$posBranchManagers = $pdo->query("SELECT nv.cuahang_id, nv.ho_ten FROM nhanvien nv JOIN nguoidung nd ON nd.id = nv.nguoidung_id WHERE nd.phanquyen_id = 3 AND nd.trang_thai = 1 ORDER BY nv.id ASC")->fetchAll();
$posBranchManagerNames = [];
foreach ($posBranchManagers as $manager) {
    $storeId = (int)$manager['cuahang_id'];
    if (!isset($posBranchManagerNames[$storeId])) {
        $posBranchManagerNames[$storeId] = (string)$manager['ho_ten'];
    }
}

$posRegionalAssignments = [];
$khuVucRows = $pdo->query("SELECT kv.cuahang_id, nv.id AS nv_id, nv.ho_ten
                            FROM khuvuc_phancong kv
                            JOIN nhanvien nv ON nv.nguoidung_id = kv.quanlytruong_id")->fetchAll();
foreach ($khuVucRows as $row) {
    $storeId = (int)$row['cuahang_id'];
    $posRegionalAssignments[$storeId] = [
        'regional_id' => (int)$row['nv_id'],
        'regional' => (string)$row['ho_ten'],
        'branch' => $posBranchManagerNames[$storeId] ?? 'Chưa phân công'
    ];
}
$posAllowedStoreIds = [];
foreach ($posAllStores as $store) {
    $storeId = (int)$store['id'];
    if ($posUserRole === 1 ||
        ($posUserRole === 2 && (int)($posRegionalAssignments[$storeId]['regional_id'] ?? 0) === $posStaffId) ||
        (!in_array($posUserRole, [1, 2], true) && $storeId === $posAssignedStoreId)) {
        $posAllowedStoreIds[] = $storeId;
    }
}
if (!$posAllowedStoreIds) {
    http_response_code(403);
    exit('Tài khoản này chưa được phân công cửa hàng đang hoạt động.');
}
$posAllowedStoreLookup = array_fill_keys($posAllowedStoreIds, true);
$posDefaultStoreId = in_array($posAssignedStoreId, $posAllowedStoreIds, true)
    ? $posAssignedStoreId : $posAllowedStoreIds[0];
$cuahang_list = array_values(array_filter($posAllStores, static function ($store) use ($posAllowedStoreLookup) {
    return isset($posAllowedStoreLookup[(int)$store['id']]);
}));
$posRegionalAssignments = array_intersect_key($posRegionalAssignments, $posAllowedStoreLookup);
if (empty($_SESSION['ck_pos_member_csrf'])) {
    $_SESSION['ck_pos_member_csrf'] = bin2hex(random_bytes(32));
}
$posMemberCsrf = $_SESSION['ck_pos_member_csrf'];

try {
    $tenKhachColumn = $pdo->query("SHOW COLUMNS FROM hoadon LIKE 'ten_khach_nhap'")->fetch();
    if (!$tenKhachColumn) {
        $pdo->exec("ALTER TABLE hoadon ADD COLUMN ten_khach_nhap VARCHAR(100) NULL");
    }
} catch (PDOException $e) {
    die('Không thể cập nhật cột tên khách hàng: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pos_action'] ?? '') === 'register_member') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        $csrf = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals($posMemberCsrf, $csrf)) {
            http_response_code(403);
            throw new RuntimeException('Phiên làm việc không hợp lệ. Vui lòng tải lại trang.');
        }
        $fullName = trim(preg_replace('/\s+/u', ' ', (string)($_POST['ho_ten'] ?? '')));
        $phone = preg_replace('/[\s.\-]/', '', trim((string)($_POST['so_dien_thoai'] ?? '')));
        $storeId = (int)($_POST['cuahang_id'] ?? 0);
        if (!isset($posAllowedStoreLookup[$storeId])) {
            http_response_code(403);
            throw new RuntimeException('Bạn không được đăng ký thành viên tại chi nhánh này.');
        }
        if (mb_strlen($fullName, 'UTF-8') < 2 || mb_strlen($fullName, 'UTF-8') > 100) {
            throw new RuntimeException('Họ tên phải có từ 2 đến 100 ký tự.');
        }
        if (!preg_match('/^0[0-9]{9,10}$/', $phone)) {
            throw new RuntimeException('Số điện thoại phải có 10 hoặc 11 chữ số và bắt đầu bằng 0.');
        }
        $memberSelect = $pdo->prepare("SELECT kh.id, kh.ho_ten, kh.so_dien_thoai, kh.diem_tich_luy, COALESCE(tt.hang_the, 'Bạc') AS hang_the, COALESCE(tt.ti_le_giam_gia, 0) AS ti_le_giam_gia FROM khachhang kh LEFT JOIN thetichdiem tt ON tt.id = kh.thetichdiem_id WHERE kh.so_dien_thoai = ? LIMIT 1");
        $memberSelect->execute([$phone]);
        $member = $memberSelect->fetch();
        $alreadyExists = (bool)$member;
        if (!$member) {
            $firstTier = $pdo->query("SELECT id FROM thetichdiem ORDER BY diem_toi_thieu ASC, id ASC LIMIT 1")->fetchColumn();
            if (!$firstTier) throw new RuntimeException('Chưa cấu hình hạng thành viên trong hệ thống.');
            try {
                $insert = $pdo->prepare("INSERT INTO khachhang (ho_ten, so_dien_thoai, thetichdiem_id, diem_tich_luy, cuahang_id, ngay_dang_ky) VALUES (?, ?, ?, 0, ?, NOW())");
                $insert->execute([$fullName, $phone, (int)$firstTier, $storeId]);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') throw $e;
                $alreadyExists = true;
            }
            $memberSelect->execute([$phone]);
            $member = $memberSelect->fetch();
        }
        if (!$member) throw new RuntimeException('Không thể lấy thông tin thành viên vừa đăng ký.');
        echo json_encode([
            'success' => true,
            'existing' => $alreadyExists,
            'member' => [
                'id' => (int)$member['id'],
                'name' => $member['ho_ten'],
                'phone' => $member['so_dien_thoai'],
                'tier' => $member['hang_the'],
                'points' => (int)$member['diem_tich_luy'],
                'discount' => (float)$member['ti_le_giam_gia']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if (http_response_code() === 200) http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (($_GET['pos_api'] ?? '') === 'stock') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    try {
        $storeId = (int)($_GET['cuahang_id'] ?? $posDefaultStoreId);
        if (!isset($posAllowedStoreLookup[$storeId])) {
            http_response_code(403);
            throw new RuntimeException('Bạn không có quyền truy cập tồn kho chi nhánh này.');
        }
        $stmtStock = $pdo->prepare("SELECT tk.sanpham_id, GREATEST(tk.so_luong, 0) AS so_luong FROM tonkho tk JOIN sanpham sp ON sp.id = tk.sanpham_id AND sp.trang_thai = 1 WHERE tk.cuahang_id = ?");
        $stmtStock->execute([$storeId]);
        $stock = [];
        foreach ($stmtStock->fetchAll() as $row) {
            $stock[(int)$row['sanpham_id']] = (int)$row['so_luong'];
        }
        $stmtReserved = $pdo->prepare("SELECT ct.sanpham_id, SUM(ct.so_luong) AS reserved_qty FROM hoadon hd JOIN chitiethoadon ct ON ct.hoadon_id = hd.id WHERE hd.id = ? AND hd.cuahang_id = ? AND hd.nhanvien_id = ? AND hd.trang_thai = 'Chờ thanh toán' GROUP BY ct.sanpham_id");
        $stmtReserved->execute([(int)($_SESSION['ck_pos_active_order_id'] ?? 0), $storeId, $posStaffId]);
        foreach ($stmtReserved->fetchAll() as $row) {
            $id = (int)$row['sanpham_id'];
            $stock[$id] = ($stock[$id] ?? 0) + (int)$row['reserved_qty'];
        }
        echo json_encode(['success' => true, 'cuahang_id' => $storeId, 'stock' => $stock], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if (http_response_code() === 200) http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (($_GET['pos_api'] ?? '') === 'promotions') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    try {
        $stmt = $pdo->prepare("SELECT km.id, km.cuahang_id, km.ten_km, km.ma_voucher, km.loai_km, km.gia_tri, ch.ten_cua_hang FROM khuyenmai km LEFT JOIN cuahang ch ON km.cuahang_id = ch.id WHERE km.thoi_gian_bat_dau <= ? AND km.thoi_gian_ket_thuc >= ? AND (km.so_luong IS NULL OR km.so_luong > 0) ORDER BY km.id DESC");
        $promoNow = date('Y-m-d H:i:s');
        $stmt->execute([$promoNow, $promoNow]);
        $allPromos = [];
        foreach ($stmt->fetchAll() as $km) {
            $allPromos[] = [
                'id' => (int)$km['id'],
                'code' => (string)($km['ma_voucher'] ?? ''),
                'name' => (string)$km['ten_km'],
                'type' => (string)$km['loai_km'],
                'value' => (float)$km['gia_tri'],
                'store_id' => $km['cuahang_id'] === null ? 0 : (int)$km['cuahang_id'],
                'store_name' => $km['cuahang_id'] === null ? 'Toàn hệ thống' : (string)($km['ten_cua_hang'] ?? 'Cửa hàng'),
                'status' => 'active'
            ];
        }
        echo json_encode(['success' => true, 'promotions' => $allPromos], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function posStockLimitMessage(PDO $pdo, int $productId, int $storeId, int $requested, int $ownReserved = 0): string {
    $stockStmt = $pdo->prepare("SELECT GREATEST(so_luong, 0) FROM tonkho WHERE sanpham_id = ? AND cuahang_id = ? LIMIT 1");
    $stockStmt->execute([$productId, $storeId]);
    $available = max(0, (int)$stockStmt->fetchColumn() + $ownReserved);
    $nameStmt = $pdo->prepare("SELECT ten_san_pham FROM sanpham WHERE id = ? LIMIT 1");
    $nameStmt->execute([$productId]);
    $name = (string)($nameStmt->fetchColumn() ?: 'Sản phẩm');
    if ($available === 0) {
        return $name . ' đã hết hàng tại chi nhánh này. Bạn đang yêu cầu ' . number_format($requested, 0, ',', '.') . ' sản phẩm, hiện có thể mua 0 sản phẩm.';
    }
    return $name . ' hiện chỉ còn ' . number_format($available, 0, ',', '.') . ' sản phẩm tại chi nhánh này. Bạn đang yêu cầu ' . number_format($requested, 0, ',', '.') . ' sản phẩm. Tối đa có thể mua ' . number_format($available, 0, ',', '.') . ' sản phẩm.';
}

function cancelActivePosOrder(PDO $pdo, int $orderId): void {
    if ($orderId <= 0) return;
    $stmtOrder = $pdo->prepare("SELECT id, cuahang_id, trang_thai FROM hoadon WHERE id = ? FOR UPDATE");
    $stmtOrder->execute([$orderId]);
    $activeOrder = $stmtOrder->fetch();
    if (!$activeOrder || $activeOrder['trang_thai'] !== 'Chờ thanh toán') return;
    $stmtItems = $pdo->prepare("SELECT sanpham_id, so_luong FROM chitiethoadon WHERE hoadon_id = ?");
    $stmtItems->execute([$orderId]);
    $restoreStock = $pdo->prepare("UPDATE tonkho SET so_luong = so_luong + ? WHERE sanpham_id = ? AND cuahang_id = ?");
    foreach ($stmtItems->fetchAll() as $item) {
        $restoreStock->execute([(int)$item['so_luong'], (int)$item['sanpham_id'], (int)$activeOrder['cuahang_id']]);
    }
    $stmtCancel = $pdo->prepare("UPDATE hoadon SET trang_thai = 'Đã hủy' WHERE id = ? AND trang_thai = 'Chờ thanh toán'");
    $stmtCancel->execute([$orderId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pos_action'] ?? '') === 'cancel_cart_pending') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pdo->beginTransaction();
        cancelActivePosOrder($pdo, (int)($_SESSION['ck_pos_active_order_id'] ?? 0));
        $pdo->commit();
        unset($_SESSION['ck_pos_active_order_id']);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pos_action'] ?? '') === 'sync_cart_pending') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    try {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $sessionOrderId = (int)($_SESSION['ck_pos_active_order_id'] ?? 0);
        if ($orderId <= 0 || $orderId !== $sessionOrderId) {
            echo json_encode(['success' => true, 'active' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $newCart = json_decode($_POST['cart'] ?? '[]', true);
        if (!is_array($newCart)) throw new RuntimeException('Dữ liệu giỏ hàng không hợp lệ.');
        $newItems = [];
        foreach ($newCart as $item) {
            if (!is_array($item)) throw new RuntimeException('Sản phẩm không hợp lệ.');
            $id = (int)($item['id'] ?? 0);
            $qty = (int)($item['qty'] ?? 0);
            $price = (float)($item['price'] ?? 0);
            if ($id <= 0 || $qty <= 0 || !is_finite($price) || $price < 0) {
                throw new RuntimeException('Số lượng hoặc đơn giá sản phẩm không hợp lệ.');
            }
            if (!isset($newItems[$id])) {
                $newItems[$id] = ['qty' => 0, 'price' => $price];
            }
            $newItems[$id]['qty'] += $qty;
            $newItems[$id]['price'] = $price;
        }
        ksort($newItems, SORT_NUMERIC);
        $pdo->beginTransaction();
        $stmtOrder = $pdo->prepare("SELECT id, cuahang_id, nhanvien_id, trang_thai FROM hoadon WHERE id = ? FOR UPDATE");
        $stmtOrder->execute([$orderId]);
        $order = $stmtOrder->fetch();
        if (!$order || $order['trang_thai'] !== 'Chờ thanh toán' || (int)$order['nhanvien_id'] !== $posStaffId) {
            $pdo->commit();
            unset($_SESSION['ck_pos_active_order_id']);
            echo json_encode(['success' => true, 'active' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $storeId = $posCanSwitchStore ? (int)($_POST['cuahang_id'] ?? $posDefaultStoreId) : $posAssignedStoreId;
        if (!isset($posAllowedStoreLookup[$storeId])) {
            throw new RuntimeException('Bạn không được phép bán hàng tại chi nhánh này.');
        }
        if (!$newItems || $storeId !== (int)$order['cuahang_id']) {
            cancelActivePosOrder($pdo, $orderId);
            $pdo->commit();
            unset($_SESSION['ck_pos_active_order_id']);
            echo json_encode(['success' => true, 'active' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $oldItemsStmt = $pdo->prepare("SELECT sanpham_id, so_luong FROM chitiethoadon WHERE hoadon_id = ?");
        $oldItemsStmt->execute([$orderId]);
        $oldItems = [];
        foreach ($oldItemsStmt->fetchAll() as $old) {
            $oldId = (int)$old['sanpham_id'];
            $oldItems[$oldId] = ($oldItems[$oldId] ?? 0) + (int)$old['so_luong'];
        }
        $allIds = array_unique(array_merge(array_keys($oldItems), array_keys($newItems)));
        sort($allIds, SORT_NUMERIC);
        $takeStock = $pdo->prepare("UPDATE tonkho SET so_luong = so_luong - ? WHERE sanpham_id = ? AND cuahang_id = ? AND so_luong >= ?");
        $restoreStock = $pdo->prepare("UPDATE tonkho SET so_luong = so_luong + ? WHERE sanpham_id = ? AND cuahang_id = ?");
        $verifyProduct = $pdo->prepare("SELECT id FROM sanpham WHERE id = ? AND trang_thai = 1 LIMIT 1");
        foreach ($allIds as $productId) {
            $newQty = (int)($newItems[$productId]['qty'] ?? 0);
            $oldQty = (int)($oldItems[$productId] ?? 0);
            if ($newQty > 0) {
                $verifyProduct->execute([$productId]);
                if (!$verifyProduct->fetchColumn()) throw new RuntimeException('Sản phẩm đã ngừng bán.');
            }
            $difference = $newQty - $oldQty;
            if ($difference > 0) {
                $takeStock->execute([$difference, $productId, $storeId, $difference]);
                if ($takeStock->rowCount() !== 1) {
                    throw new RuntimeException(posStockLimitMessage($pdo, (int)$productId, $storeId, $newQty, $oldQty));
                }
            } elseif ($difference < 0) {
                $restoreStock->execute([-$difference, $productId, $storeId]);
            }
        }
        $promoId = !empty($_POST['khuyenmai_id']) ? (int)$_POST['khuyenmai_id'] : null;
        if ($promoId !== null) {
            $promoStmt = $pdo->prepare("SELECT id FROM khuyenmai WHERE id = ? AND (cuahang_id IS NULL OR cuahang_id = ?) AND thoi_gian_bat_dau <= NOW() AND thoi_gian_ket_thuc >= NOW() AND (so_luong IS NULL OR so_luong > 0)");
            $promoStmt->execute([$promoId, $storeId]);
            if (!$promoStmt->fetchColumn()) throw new RuntimeException('Mã khuyến mãi không còn hợp lệ.');
        }
        $customerId = !empty($_POST['khachhang_id']) ? (int)$_POST['khachhang_id'] : null;
        $manualName = $customerId === null ? mb_substr(trim((string)($_POST['ten_khach_nhap'] ?? '')), 0, 100, 'UTF-8') : '';
        $subtotal = 0;
        foreach ($newItems as $line) $subtotal += $line['qty'] * $line['price'];
        $discount = max(0, min($subtotal, (float)($_POST['tien_giam_gia'] ?? 0)));
        $total = $subtotal - $discount + round(($subtotal - $discount) * 0.08);
        $pdo->prepare("DELETE FROM chitiethoadon WHERE hoadon_id = ?")->execute([$orderId]);
        $addItem = $pdo->prepare("INSERT INTO chitiethoadon (hoadon_id, sanpham_id, so_luong, don_gia, thanh_tien) VALUES (?, ?, ?, ?, ?)");
        foreach ($newItems as $productId => $line) {
            $addItem->execute([$orderId, $productId, $line['qty'], $line['price'], $line['qty'] * $line['price']]);
        }
        $updateOrder = $pdo->prepare("UPDATE hoadon SET khachhang_id = ?, ten_khach_nhap = ?, khuyenmai_id = ?, tong_tien = ?, tien_giam_gia = ?, khach_phai_tra = ? WHERE id = ? AND trang_thai = 'Chờ thanh toán'");
        $updateOrder->execute([$customerId, $manualName !== '' ? $manualName : null, $promoId, $subtotal, $discount, $total, $orderId]);
        $pdo->commit();
        echo json_encode(['success' => true, 'active' => true, 'hoadon_id' => $orderId], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pos_action']) && $_POST['pos_action'] === 'create_order_pending') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $cart           = json_decode($_POST['cart'] ?? '[]', true);
        $cuahang_id     = $posCanSwitchStore ? (int)($_POST['cuahang_id'] ?? $posDefaultStoreId) : $posAssignedStoreId;
        if (!isset($posAllowedStoreLookup[$cuahang_id])) {
            echo json_encode(['success' => false, 'message' => 'Bạn không được phép bán hàng tại chi nhánh này.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $nhanvien_id    = $posStaffId;
        $verifyStore = $pdo->prepare("SELECT id FROM cuahang WHERE id = ? AND trang_thai = 1 LIMIT 1");
        $verifyStore->execute([$cuahang_id]);
        if (!$verifyStore->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Chi nhánh bán hàng không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $khachhang_id   = !empty($_POST['khachhang_id']) ? (int)$_POST['khachhang_id'] : null;
        $ten_khach_nhap = mb_substr(trim((string)($_POST['ten_khach_nhap'] ?? '')), 0, 100, 'UTF-8');
        if ($khachhang_id !== null) $ten_khach_nhap = '';
        $khuyenmai_id   = !empty($_POST['khuyenmai_id']) ? (int)$_POST['khuyenmai_id'] : null;
        $tong_tien      = (float)($_POST['tong_tien'] ?? 0);
        $tien_giam_gia  = (float)($_POST['tien_giam_gia'] ?? 0);
        $khach_phai_tra = (float)($_POST['khach_phai_tra'] ?? 0);

        if (!is_array($cart) || empty($cart)) {
            echo json_encode(['success' => false, 'message' => 'Giỏ hàng trống hoặc không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($khuyenmai_id !== null) {
            $stmtPromo = $pdo->prepare("SELECT id, cuahang_id, ma_voucher FROM khuyenmai WHERE id = ? AND thoi_gian_bat_dau <= ? AND thoi_gian_ket_thuc >= ? AND (so_luong IS NULL OR so_luong > 0) LIMIT 1");
            $promoNow = date('Y-m-d H:i:s');
            $stmtPromo->execute([$khuyenmai_id, $promoNow, $promoNow]);
            $promo = $stmtPromo->fetch();
            $promoStoreId = $promo ? ($promo['cuahang_id'] === null ? 0 : (int)$promo['cuahang_id']) : -1;
            if (!$promo || ($promoStoreId !== 0 && $promoStoreId !== $cuahang_id)) {
                echo json_encode(['success' => false, 'message' => 'Mã khuyến mãi không áp dụng cho cửa hàng này hoặc đã hết hiệu lực.']);
                exit;
            }
        }

        $pdo->beginTransaction();
        cancelActivePosOrder($pdo, (int)($_SESSION['ck_pos_active_order_id'] ?? 0));
        
        $stmtHd = $pdo->prepare("INSERT INTO hoadon (cuahang_id, nhanvien_id, khachhang_id, ten_khach_nhap, khuyenmai_id, ngay_tao, tong_tien, tien_giam_gia, khach_phai_tra, trang_thai) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, 'Chờ thanh toán')");
        $stmtHd->execute([$cuahang_id, $nhanvien_id, $khachhang_id, $ten_khach_nhap !== '' ? $ten_khach_nhap : null, $khuyenmai_id, $tong_tien, $tien_giam_gia, $khach_phai_tra]);
        $hoadon_id = (int)$pdo->lastInsertId();

        $stmtCt = $pdo->prepare("INSERT INTO chitiethoadon (hoadon_id, sanpham_id, so_luong, don_gia, thanh_tien) VALUES (?, ?, ?, ?, ?)");
        $stmtTk = $pdo->prepare("UPDATE tonkho SET so_luong = so_luong - ? WHERE sanpham_id = ? AND cuahang_id = ? AND so_luong >= ?");
        $stmtProduct = $pdo->prepare("SELECT ten_san_pham FROM sanpham WHERE id = ? AND trang_thai = 1 LIMIT 1");
        foreach ($cart as $item) {
            if (!is_array($item)) throw new RuntimeException('Dữ liệu sản phẩm không hợp lệ.');
            $productId = (int)($item['id'] ?? 0);
            $quantity = (int)($item['qty'] ?? 0);
            if ($productId <= 0 || $quantity < 1 || !isset($item['qty']) || !is_numeric($item['qty'])) {
                throw new RuntimeException('Số lượng sản phẩm không hợp lệ.');
            }
            $stmtProduct->execute([$productId]);
            $product = $stmtProduct->fetch();
            if (!$product) throw new RuntimeException('Sản phẩm không còn được phép bán.');
            $stmtTk->execute([$quantity, $productId, $cuahang_id, $quantity]);
            if ($stmtTk->rowCount() !== 1) {
                throw new RuntimeException(posStockLimitMessage($pdo, $productId, $cuahang_id, $quantity));
            }
            $unitPrice = (float)$item['price'];
            if (!is_finite($unitPrice) || $unitPrice < 0) throw new RuntimeException('Đơn giá sản phẩm không hợp lệ.');
            $stmtCt->execute([$hoadon_id, $productId, $quantity, $unitPrice, $quantity * $unitPrice]);
        }
        
        $pdo->commit();
        $_SESSION['ck_pos_active_order_id'] = $hoadon_id;
        echo json_encode(['success' => true, 'hoadon_id' => $hoadon_id]); 
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]); 
        exit;
    }
}

$sanpham_list = $pdo->query("SELECT sp.*, dm.ten_danh_muc FROM sanpham sp LEFT JOIN danhmuc dm ON sp.danhmuc_id = dm.id WHERE sp.trang_thai = 1 ORDER BY sp.id ASC")->fetchAll();
$posStockByStore = [];
foreach ($posAllowedStoreIds as $allowedStoreId) {
    $posStockByStore[(int)$allowedStoreId] = [];
}
$stockPlaceholders = implode(',', array_fill(0, count($posAllowedStoreIds), '?'));
$stmtInitialStock = $pdo->prepare("SELECT tk.cuahang_id, tk.sanpham_id, GREATEST(tk.so_luong, 0) AS so_luong FROM tonkho tk JOIN sanpham sp ON sp.id = tk.sanpham_id AND sp.trang_thai = 1 WHERE tk.cuahang_id IN ($stockPlaceholders)");
$stmtInitialStock->execute($posAllowedStoreIds);
foreach ($stmtInitialStock->fetchAll() as $row) {
    $posStockByStore[(int)$row['cuahang_id']][(int)$row['sanpham_id']] = (int)$row['so_luong'];
}
$stmtInitialReservation = $pdo->prepare("SELECT hd.cuahang_id, ct.sanpham_id, SUM(ct.so_luong) AS reserved_qty FROM hoadon hd JOIN chitiethoadon ct ON ct.hoadon_id = hd.id WHERE hd.id = ? AND hd.nhanvien_id = ? AND hd.trang_thai = 'Chờ thanh toán' GROUP BY hd.cuahang_id, ct.sanpham_id");
$stmtInitialReservation->execute([(int)($_SESSION['ck_pos_active_order_id'] ?? 0), $posStaffId]);
foreach ($stmtInitialReservation->fetchAll() as $row) {
    $storeId = (int)$row['cuahang_id'];
    $productId = (int)$row['sanpham_id'];
    if (isset($posAllowedStoreLookup[$storeId])) {
        $posStockByStore[$storeId][$productId] = ($posStockByStore[$storeId][$productId] ?? 0) + (int)$row['reserved_qty'];
    }
}


$khachhang_list = $pdo->query("SELECT kh.*, ttd.hang_the, ttd.ti_le_giam_gia FROM khachhang kh LEFT JOIN thetichdiem ttd ON kh.thetichdiem_id = ttd.id ORDER BY kh.id ASC")->fetchAll();
$stmtAvailablePromos = $pdo->prepare("SELECT id, cuahang_id, ten_km, ma_voucher, loai_km, gia_tri FROM khuyenmai WHERE thoi_gian_bat_dau <= ? AND thoi_gian_ket_thuc >= ? AND (so_luong IS NULL OR so_luong > 0) ORDER BY id ASC");
$nowPos = date('Y-m-d H:i:s');
$stmtAvailablePromos->execute([$nowPos, $nowPos]);
$khuyenmai_list = $stmtAvailablePromos->fetchAll();
foreach ($khuyenmai_list as &$km) {
    $km['pos_store_id'] = $km['cuahang_id'] === null ? 0 : (int)$km['cuahang_id'];
}
unset($km);

$nextId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM hoadon")->fetchColumn();
$nextHdCode = '#HD' . (1024 + $nextId);

function getVisual($n, $c) {
    $str = mb_strtolower($n.' '.$c);
    if(strpos($str,'cà phê')!==false || strpos($str,'trà')!==false || strpos($str,'uống')!==false) return ['ic'=>'fa-mug-hot','cl'=>'#f97316'];
    if(strpos($str,'mì')!==false || strpos($str,'ăn')!==false || strpos($str,'gà')!==false || strpos($str,'cơm')!==false) return ['ic'=>'fa-bowl-food','cl'=>'#e11d48'];
    if(strpos($str,'bánh')!==false || strpos($str,'kẹo')!==false || strpos($str,'snack')!==false) return ['ic'=>'fa-cookie-bite','cl'=>'#10b981'];
    return ['ic'=>'fa-box','cl'=>'#6366f1'];
}

$pageTitle = 'Màn hình bán hàng (POS)'; 
$activeMenu = 'pos';
require ROOT_PATH . '/includes/header.php';
?>

<style>
    body:not(.layout-fixed) .main-sidebar { height: inherit; min-height: 100vh; position: absolute; top: 0; }
    .main-sidebar, .main-sidebar::before { transition: margin-left .3s ease-in-out,width .3s ease-in-out; width: 250px; }
    .layout-fixed .main-sidebar { bottom: 0; float: none; left: 0; position: fixed; top: 0; }
    aside.main-sidebar { display: flex !important; flex-direction: column !important; height: 100vh !important; overflow: hidden !important; z-index: 1038 !important; }
    .brand-link { flex-shrink: 0 !important; position: relative !important; z-index: 10 !important; border-bottom: 1px solid rgba(255,255,255,0.05) !important; background: inherit !important; display: block; padding: .8125rem .5rem; white-space: nowrap; }
    .sidebar { flex-grow: 1 !important; overflow-y: auto !important; overflow-x: hidden !important; height: 100% !important; display: block !important; padding-bottom: 50px !important; }
    .sidebar::-webkit-scrollbar { width: 5px; } .sidebar::-webkit-scrollbar-track { background: transparent; } .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }

    .pos-main-row { min-height: calc(100vh - 120px); }
    .pos-panel-stretch { height: calc(100vh - 115px); max-height: calc(100vh - 115px); display: flex; flex-direction: column; overflow: hidden; position: relative; }
    
    .prod-scroll-wrap { flex: 1 1 auto; overflow-y: auto; overflow-x: hidden; padding-right: 6px; padding-bottom: 8px; } 
    .prod-scroll-wrap::-webkit-scrollbar, #cartBox::-webkit-scrollbar { width: 6px; }
    .prod-scroll-wrap::-webkit-scrollbar-track, #cartBox::-webkit-scrollbar-track { background: transparent; }
    .prod-scroll-wrap::-webkit-scrollbar-thumb, #cartBox::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .prod-scroll-wrap::-webkit-scrollbar-thumb:hover, #cartBox::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    .prod-item-col { height: auto !important; }
    .prod-row { position: relative; transition: all 0.2s ease; cursor: pointer; user-select: none; border: 1.5px solid var(--border-light); border-radius: 14px; display: flex; flex-direction: column; padding: 10px; background: #fff; height: 226px !important; max-height: 226px !important; overflow: hidden; }
    .prod-row:hover { transform: translateY(-3px); border-color: var(--ck-red) !important; box-shadow: 0 8px 20px rgba(238,49,36,0.12); }
    .prod-row.is-out-stock { opacity: .62; filter: grayscale(.6); cursor: not-allowed; }
    .prod-row.is-out-stock:hover { transform: none; border-color: var(--border-light) !important; box-shadow: none; }
    .stock-out-tag { display: none; position: absolute; top: 8px; left: 8px; z-index: 3; background: #e11d48; color: #fff; border-radius: 7px; padding: 4px 8px; font-size: .76rem; font-weight: 700; }
    .prod-row.is-out-stock .stock-out-tag { display: block; }

    .prod-row:active { transform: scale(0.97); }
    .prod-row.in-cart { border-color: var(--ck-red) !important; background: linear-gradient(180deg, rgba(238,49,36,0.04), #fff); }
    
    .prod-img-box { width: 100%; height: 115px; min-height: 115px; max-height: 115px; background: #ffffff; border: 1px solid #f1f5f9; border-radius: 10px; overflow: hidden; display: flex; align-items: center; justify-content: center; padding: 6px; margin-bottom: 8px; flex-shrink: 0; }
    .prod-img-box img { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; transition: transform 0.25s ease; }
    .prod-row:hover .prod-img-box img { transform: scale(1.08); }
    .prod-icon-fallback { width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 2.2rem; border-radius: 8px; }

    .prod-title-text { font-size: 0.88rem; font-weight: 700; color: #1e293b; line-height: 1.3; height: 36px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; margin-bottom: 4px; }

    .prod-qty-badge { position: absolute; top: 6px; right: 6px; background: var(--ck-red); color: #fff; font-size: 0.75rem; font-weight: 700; width: 24px; height: 24px; border-radius: 50%; display: none; align-items: center; justify-content: center; box-shadow: 0 3px 8px rgba(238,49,36,0.3); z-index: 2; animation: popIn 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .prod-row.in-cart .prod-qty-badge { display: flex; }
    @keyframes popIn { 0% { transform: scale(0.5); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

    .sku-tag { font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; background: #f1f5f9; color: #64748b; font-family: monospace; font-weight: 600; }
    .qty-stepper { display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 2px 4px; }
    .qty-btn { width: 26px; height: 26px; border: none; background: #fff; border-radius: 6px; font-weight: 700; color: #334155; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.08); transition: 0.15s; display: flex; align-items: center; justify-content: center; }
    .qty-btn:hover { background: var(--ck-red); color: #fff; }
    .qty-input { width: 36px; height: 26px; text-align: center; font-size: .85rem; font-weight: 700; color: #1e293b; border: 1px solid transparent; background: transparent; border-radius: 6px; padding: 0 2px; outline: none; -moz-appearance: textfield; appearance: textfield; }
    .qty-input:focus { border-color: #f43f5e; background: #fff; box-shadow: 0 0 0 2px rgba(244,63,94,.1); }
    .qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    
    .tier-badge { font-weight: 700; border-radius: 6px; padding: 3px 8px; font-size: 0.75rem; text-transform: uppercase; }
    .tier-bac { background: #94a3b8; color: #fff; }
    .tier-vang { background: #f59e0b; color: #fff; }
    .tier-kimcuong { background: #06b6d4; color: #fff; }
    .tier-none { background: #e2e8f0; color: #475569; }
    .pos-customer-search { position: relative; z-index: 15; }
    .pos-customer-suggestions { position: absolute; z-index: 100; top: calc(100% + 5px); left: 0; right: 0; max-height: 245px; overflow-y: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 14px 35px rgba(15,23,42,.16); padding: 5px; }
    .pos-customer-suggestions[hidden] { display: none !important; }
    .pos-customer-suggestion { width: 100%; background: #fff; border: 0; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 10px 12px; border-radius: 8px; text-align: left; cursor: pointer; color: #1e293b; }
    .pos-customer-suggestion:hover, .pos-customer-suggestion.is-active { background: #eff6ff; }
    .pos-customer-suggestion-name { font-weight: 700; font-size: .9rem; }
    .pos-customer-suggestion-detail { font-size: .8rem; color: #64748b; }
    .pos-customer-no-result { padding: 12px; font-size: .85rem; color: #64748b; }
    .pos-member-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 9px; }
    .pos-member-points { color: #64748b; font-size: .75rem; font-weight: 600; line-height: 1.35; }
    .pos-member-add { display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap; border: 1px solid #fecdd3; background: #fff1f2; color: #e11d48; border-radius: 9px; padding: 7px 10px; font-weight: 800; font-size: .79rem; cursor: pointer; }
    .pos-member-add:hover { background: #ffe4e6; }
    .pos-member-dialog { width: min(475px, calc(100vw - 28px)); max-width: 100%; padding: 0; border: 0; border-radius: 22px; background: #fff; color: #1e293b; box-shadow: 0 25px 80px rgba(15,23,42,.23); }
    .pos-member-dialog::backdrop { background: rgba(15,23,42,.47); backdrop-filter: blur(3px); }
    .pos-member-dialog[open] { animation: pos-dialog-in .2s ease-out; }
    .pos-member-content { padding: 28px; position: relative; }
    .pos-member-heading { text-align: center; margin-bottom: 20px; }
    .pos-member-heading-icon { width: 56px; height: 56px; border-radius: 17px; margin: 0 auto 12px; display: grid; place-items: center; font-size: 23px; background: #fff1f2; color: #e11d48; }
    .pos-member-heading h4 { font-size: 1.22rem; font-weight: 800; margin: 0 0 5px; }
    .pos-member-heading p { color: #64748b; font-size: .88rem; margin: 0; }
    .pos-member-form-label { display: block; font-size: .83rem; font-weight: 750; margin-bottom: 5px; }
    .pos-member-input { display: block; width: 100%; border: 1px solid #dce3ed; padding: 10px 12px; border-radius: 10px; outline: none; background: #fff; color: #1e293b; }
    .pos-member-input:focus { border-color: #fb7185; box-shadow: 0 0 0 3px #ffe4e6; }
    .pos-member-meta { margin-top: 14px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 13px; font-size: .84rem; color: #475569; }
    .pos-member-meta div + div { margin-top: 6px; }
    .pos-member-message { font-size: .84rem; color: #b91c1c; min-height: 18px; margin-top: 10px; }
    .pos-member-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 16px; }
    .pos-member-actions button { border-radius: 11px; padding: 10px; border: 0; font-weight: 800; }
    .pos-member-actions .is-cancel { background: #f1f5f9; color: #334155; }
    .pos-member-actions .is-submit { background: linear-gradient(110deg,#e11d48,#fb573d); color: #fff; }
    .pos-member-actions .is-submit:disabled { opacity: .65; cursor: wait; }
    @media(max-width:480px) { .pos-member-content { padding: 24px 18px; } }
    
    .cart-item-anim { animation: slideInLeft 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
    @keyframes slideInLeft { 0% { opacity: 0; transform: translateX(-15px); } 100% { opacity: 1; transform: translateX(0); } }

    .pagination-wrapper { flex-shrink: 0; margin-top: auto; padding-top: 12px; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; gap: 10px; background: #fff; }
    #pageNumbers { display: flex; align-items: center; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 10px; padding: 3px 6px; gap: 4px; }
    .page-btn { width: 34px; height: 34px; border: none; background: transparent; color: #1e3a8a; font-weight: 600; font-size: 0.9rem; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
    .page-btn:hover { background: #e2e8f0; }
    .page-btn.active { color: #0f172a; font-weight: 800; border-bottom: 2.5px solid #0f172a; border-radius: 4px 4px 0 0; background: #fff; }
    .page-nav-btn { background: transparent; border: none; color: #1e3a8a; font-weight: 600; font-size: 0.9rem; padding: 6px 12px; display: flex; align-items: center; gap: 6px; border-radius: 8px; transition: all 0.2s; }
    .page-nav-btn:hover:not(:disabled) { background: #f1f5f9; }
    .page-nav-btn:disabled { color: #94a3b8; cursor: not-allowed; opacity: 0.6; }

    .pos-feedback-dialog { width: min(440px, calc(100vw - 32px)); max-width: 100%; padding: 0; border: 0; border-radius: 22px; color: #1e293b; background: #ffffff; box-shadow: 0 24px 75px rgba(15, 23, 42, 0.23); overflow: visible; }
    .pos-feedback-dialog[open] { animation: pos-dialog-in .2s ease-out; }
    .pos-feedback-dialog::backdrop { background: rgba(15, 23, 42, .46); backdrop-filter: blur(3px); }
    @keyframes pos-dialog-in { from { opacity: 0; transform: translateY(12px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
    .pos-dialog-inner { position: relative; padding: 30px 30px 26px; text-align: center; }
    .pos-dialog-close { position: absolute; top: 13px; right: 14px; display: grid; place-items: center; width: 32px; height: 32px; border: 0; background: #f1f5f9; color: #64748b; border-radius: 10px; cursor: pointer; }
    .pos-dialog-close:hover { background: #e2e8f0; color: #334155; }
    .pos-dialog-icon { display: grid; place-items: center; width: 60px; height: 60px; margin: 0 auto 18px; border-radius: 18px; background: #fff1f2; color: #e11d48; font-size: 24px; }
    .pos-dialog-icon.is-info { background: #eff6ff; color: #2563eb; }
    .pos-dialog-title { margin: 0 0 9px; font-size: 1.24rem; font-weight: 800; color: #172033; }
    .pos-dialog-message { margin: 0; color: #64748b; font-size: .93rem; line-height: 1.65; overflow-wrap: anywhere; }
    .pos-dialog-actions { display: flex; justify-content: center; gap: 10px; margin-top: 26px; }
    .pos-dialog-actions button { flex: 1; min-height: 43px; border-radius: 11px; padding: 9px 14px; font-size: .9rem; font-weight: 750; cursor: pointer; transition: transform .15s, background .15s; }
    .pos-dialog-actions button:hover { transform: translateY(-1px); }
    .pos-dialog-secondary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
    .pos-dialog-danger { background: linear-gradient(105deg, #e11d48, #fb573d); border: 1px solid transparent; color: white; }
    .pos-dialog-info { background: #e11d48; border: 1px solid transparent; color: white; }
    @media (max-width: 480px) { .pos-dialog-inner { padding: 28px 22px 22px; } }
</style>

<script>
    document.querySelector('body').classList.add('layout-fixed');
</script>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="bento-panel fade-up pos-panel-stretch">
            <div class="panel-header flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="panel-title"><span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-cash-register"></i></span> Màn hình bán hàng</h5>
                    <div class="panel-subtitle ms-5">Bấm vào thẻ sản phẩm để thêm vào đơn hàng</div>
                </div>
                <div class="segmented-control" id="categoryTabs">
                    <button class="seg-btn active" data-cat="Tất cả">Tất cả</button>
                    <button class="seg-btn" data-cat="Thức uống">Thức uống</button>
                    <button class="seg-btn" data-cat="Thức ăn nhanh">Thức ăn nhanh</button>
                    <button class="seg-btn" data-cat="Bánh kẹo">Bánh kẹo</button>
                    <button class="seg-btn" data-cat="Đồ dùng cá nhân">Đồ dùng cá nhân</button>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3 flex-shrink-0">
                <div class="search-bar flex-grow-1"><i class="fa-solid fa-barcode s-ico"></i><input type="text" id="posSearchInput" placeholder="Tìm tên món (Enter xem tất cả danh mục) hoặc quét mã F2..." autocomplete="off"></div>
                
                <select class="form-select flex-shrink-0" id="posStoreSelect" onchange="updateStaffByBranch()" style="width: auto; min-width: 220px; height: 44px; border-radius: 10px; font-weight: 500;">
                    <?php foreach($cuahang_list as $ch): ?>
                        <option value="<?=$ch['id']?>">🏪 <?=htmlspecialchars($ch['ten_cua_hang'])?></option>
                    <?php endforeach; ?>
                </select>

                <select class="form-select flex-shrink-0" id="posStaffSelect" style="width: auto; min-width: 220px; height: 44px; border-radius: 10px; font-weight: 500;" disabled>
                    <option value="<?=$posStaffId?>">👤 <?=htmlspecialchars($posStaffLabel, ENT_QUOTES, 'UTF-8')?></option>
                </select>

                <div class="w-100 d-flex flex-wrap align-items-center gap-3" style="font-size: .8rem; color: #64748b; margin-top: -5px;">
                    <span><i class="fa-solid fa-user-tie me-1"></i>Quản lý trưởng phụ trách: <strong id="posRegionalLeaderName" style="color: #334155;"></strong></span>
                    <span><i class="fa-solid fa-store me-1"></i>Quản lý cửa hàng: <strong id="posBranchManagerName" style="color: #334155;"></strong></span>
                </div>
            </div>

            <div class="prod-scroll-wrap" id="productScrollWrap">
                <div class="row g-3" id="productGrid">
                    <?php foreach($sanpham_list as $sp): 
                        $catName = $sp['ten_danh_muc'] ?? 'Khác';
                        $v = getVisual($sp['ten_san_pham'], $catName);
                        $imgSrc = "../../../assets/images/" . $sp['ma_san_pham'] . ".jpg";
                        $initialStock = (int)($posStockByStore[$posDefaultStoreId][(int)$sp['id']] ?? 0);
                    ?>
                    <div class="col-6 col-md-4 col-lg-3 prod-item-col" data-cat="<?=$catName?>" data-search="<?=mb_strtolower($sp['ma_san_pham'].' '.$sp['ten_san_pham'])?>">
                        <div id="prod-<?=$sp['id']?>" onclick="addToCart(<?=$sp['id']?>,'<?=addslashes($sp['ten_san_pham'])?>',<?=$sp['gia_ban']?>,'<?=$sp['ma_san_pham']?>')" class="prod-row<?=$initialStock <= 0 ? ' is-out-stock' : ''?>" aria-disabled="<?=$initialStock <= 0 ? 'true' : 'false'?>">
                            <span class="stock-out-tag">Hết hàng</span>
                            <span class="prod-qty-badge" id="b-<?=$sp['id']?>">0</span>
                            
                            <div class="prod-img-box">
                                <img src="<?=$imgSrc?>" alt="<?=htmlspecialchars($sp['ten_san_pham'])?>" 
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="prod-icon-fallback" style="display:none; color:<?=$v['cl']?>; background: color-mix(in srgb, <?=$v['cl']?> 14%, transparent);">
                                    <i class="fa-solid <?=$v['ic']?>"></i>
                                </div>
                            </div>

                            <div class="prod-title-text" title="<?=htmlspecialchars($sp['ten_san_pham'])?>">
                                <?=htmlspecialchars($sp['ten_san_pham'])?>
                            </div>

                            <div class="d-flex align-items-center justify-content-between mt-auto pt-2 border-top border-light">
                                <span class="num-font fw-bold" style="color: var(--ck-red); font-size: 1.02rem;"><?=number_format($sp['gia_ban'])?> ₫</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pagination-wrapper" id="paginationBar">
                <button type="button" class="page-nav-btn" id="btnPrevPage" onclick="changePage(-1)">
                    <i class="fa-solid fa-chevron-left" style="font-size: 0.75rem;"></i> Trước
                </button>
                <div id="pageNumbers"></div>
                <button type="button" class="page-nav-btn" id="btnNextPage" onclick="changePage(1)">
                    Sau <i class="fa-solid fa-chevron-right" style="font-size: 0.75rem;"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4 d-flex flex-column">
        <div class="bento-panel fade-up d-1 pos-panel-stretch">
            <div class="panel-header mb-2">
                <div>
                    <h5 class="panel-title"><span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), var(--ck-orange))"><i class="fa-solid fa-cart-shopping"></i></span> Giỏ hàng</h5>
                </div>
                <span class="order-code fs-6" id="currentOrderCode"><?=$nextHdCode?></span>
            </div>

            <div class="p-3 bg-light border rounded-3 mb-3 flex-shrink-0">
                <div class="input-group input-group-sm mb-2 shadow-sm pos-customer-search">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-user-pen"></i></span>
                    <input type="text" class="form-control border-start-0 fw-medium" id="posCustomerInput" placeholder="Nhập tên khách hàng..." autocomplete="off" aria-autocomplete="list" aria-controls="posCustomerSuggestions" aria-expanded="false" oninput="handleCustomerInput()">
                    <div id="posCustomerSuggestions" class="pos-customer-suggestions" role="listbox" aria-label="Gợi ý tên khách hàng" hidden></div>
                </div>

                <div class="d-flex align-items-center gap-2 mb-2">
                    <select class="form-select form-select-sm border-0 fw-medium shadow-sm flex-grow-1" id="posCustomerSelect" onchange="handleCustomerSelect()" style="height: 36px;">
                        <option value="" data-name="Khách lẻ (Vãng lai)" data-disc="0" data-tier="none">Khách lẻ (Vãng lai)</option>
                        <?php foreach($khachhang_list as $kh): 
                            $tier = $kh['hang_the'] ?? 'Bạc';
                            $disc = (float)($kh['ti_le_giam_gia'] ?? 0);
                            $badgeLabel = '[' . $tier . ']';
                        ?>
                            <option value="<?=$kh['id']?>" 
                                    data-name="<?=htmlspecialchars($kh['ho_ten'], ENT_QUOTES, 'UTF-8')?>" 
                                    data-phone="<?=htmlspecialchars($kh['so_dien_thoai'] ?? '', ENT_QUOTES, 'UTF-8')?>"
                                    data-disc="<?=$disc?>" 
                                    data-points="<?=(int)$kh['diem_tich_luy']?>"
                                    data-tier="<?=htmlspecialchars($tier)?>">
                                <?= $badgeLabel ?> <?= htmlspecialchars($kh['ho_ten']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span id="tierBadgeEl" class="tier-badge tier-none">Khách lẻ</span>
                </div>

                <div class="pos-member-toolbar">
                    <span class="pos-member-points" id="posMemberPointsPreview">Tích 1 điểm mỗi 1.000đ thanh toán</span>
                    <button type="button" class="pos-member-add" onclick="openPosMemberDialog()"><i class="fa-solid fa-user-plus"></i> Tạo thành viên</button>
                </div>
                <select class="form-select form-select-sm border-0 text-danger fw-bold shadow-sm" id="posPromoSelect" onchange="onPromoSelectChange()" style="height: 36px;">
                    <option value="" data-val="0" data-type="">-- Áp dụng Voucher / Khuyến mãi --</option>
                    <?php foreach ($khuyenmai_list as $km):
                    ?>
                    <option value="<?=(int)$km['id']?>" data-code="<?=htmlspecialchars($km['ma_voucher'] ?? '', ENT_QUOTES, 'UTF-8')?>" data-store="<?=(int)$km['pos_store_id']?>" data-store-name="<?=htmlspecialchars($posStoreNames[$km['pos_store_id']] ?? 'Toàn hệ thống', ENT_QUOTES, 'UTF-8')?>" data-status="active" data-val="<?=(float)$km['gia_tri']?>" data-type="<?=htmlspecialchars($km['loai_km'], ENT_QUOTES, 'UTF-8')?>">[<?=htmlspecialchars($km['ma_voucher'] ?? '', ENT_QUOTES, 'UTF-8')?>] <?=htmlspecialchars($km['ten_km'], ENT_QUOTES, 'UTF-8')?></option>
                    <?php endforeach; ?>
                </select>
                <div class="input-group input-group-sm mt-2 shadow-sm" style="height: 36px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-ticket"></i></span>
                    <input type="text" id="posPromoCodeInput" class="form-control border-start-0" placeholder="Nhập mã voucher để tìm nhanh..." autocomplete="off" onkeydown="if(event.key === 'Enter'){ event.preventDefault(); applyPromoCode(); }" oninput="clearPromoFeedback()">
                    <button class="btn btn-danger fw-bold px-3" type="button" onclick="applyPromoCode()">Áp dụng</button>
                </div>
                <div id="posPromoCodeFeedback" class="small mt-1" role="status" aria-live="polite" style="display:none;"></div>
            </div>

            <div id="cartBox" class="flex-grow-1" style="overflow-y: auto; padding-right: 5px;"></div>

            <div class="mt-auto pt-3 border-top flex-shrink-0">
                <div class="d-flex justify-content-between mb-1 small text-muted"><span class="fw-medium">Tạm tính</span><span class="num-font fw-bold text-dark" id="lSub">0 ₫</span></div>
                <div class="d-flex justify-content-between mb-1 small text-success"><span class="fw-medium">Khuyến mãi & Thành viên</span><span class="num-font fw-bold" id="lDisc">- 0 ₫</span></div>
                <div class="d-flex justify-content-between mb-2 small text-muted"><span class="fw-medium">VAT (8%)</span><span class="num-font fw-bold text-dark" id="lVat">0 ₫</span></div>
                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top"><span class="fw-bold fs-6">TỔNG CỘNG</span><span class="num-font fw-bold" style="font-size: 1.7rem; color: var(--ck-red);" id="lTotal">0 ₫</span></div>
                
                <button class="btn-ck btn-ck-primary w-100 mt-3 py-2 fs-6" id="btnGoCheckout" onclick="goToCheckout()">
                    <i class="fa-solid fa-credit-card me-2"></i>Thanh toán đơn hàng
                </button>
                <button class="btn-ck btn-ck-soft w-100 mt-2" onclick="clearCartConfirm()">Hủy & Xóa giỏ hàng</button>
            </div>
        </div>
    </div>
</div>

<dialog class="pos-feedback-dialog" id="posFeedbackDialog" aria-labelledby="posDialogTitle" aria-describedby="posDialogMessage">
    <div class="pos-dialog-inner">
        <button type="button" class="pos-dialog-close" id="posDialogClose" aria-label="Đóng"><i class="fa-solid fa-xmark"></i></button>
        <div class="pos-dialog-icon" id="posDialogIcon"><i class="fa-solid fa-trash-can"></i></div>
        <h4 class="pos-dialog-title" id="posDialogTitle"></h4>
        <p class="pos-dialog-message" id="posDialogMessage"></p>
        <div class="pos-dialog-actions">
            <button type="button" class="pos-dialog-secondary" id="posDialogCancel">Giữ giỏ hàng</button>
            <button type="button" class="pos-dialog-danger" id="posDialogConfirm">Xóa giỏ hàng</button>
        </div>
    </div>
</dialog>

<dialog class="pos-member-dialog" id="posMemberDialog" aria-labelledby="posMemberTitle">
    <div class="pos-member-content">
        <button type="button" class="pos-dialog-close" onclick="closePosMemberDialog()" aria-label="Đóng"><i class="fa-solid fa-xmark"></i></button>
        <div class="pos-member-heading">
            <div class="pos-member-heading-icon"><i class="fa-solid fa-user-plus"></i></div>
            <h4 id="posMemberTitle">Đăng ký thành viên</h4>
            <p>Tạo tài khoản tích điểm Circle K cho khách hàng</p>
        </div>
        <form id="posMemberForm" onsubmit="registerPosMember(event)">
            <div class="mb-3">
                <label class="pos-member-form-label" for="posMemberName">Họ và tên khách hàng <span class="text-danger">*</span></label>
                <input class="pos-member-input" type="text" id="posMemberName" maxlength="100" minlength="2" required autocomplete="name" placeholder="Nhập họ và tên">
            </div>
            <div class="mb-3">
                <label class="pos-member-form-label" for="posMemberPhone">Số điện thoại <span class="text-danger">*</span></label>
                <input class="pos-member-input" type="tel" inputmode="tel" id="posMemberPhone" maxlength="15" required autocomplete="tel" placeholder="Ví dụ: 0912345678">
            </div>
            <div class="pos-member-meta">
                <div><i class="fa-solid fa-store me-1"></i> Chi nhánh: <strong id="posMemberStoreName"></strong></div>
                <div><i class="fa-solid fa-id-card me-1"></i> Hạng khi đăng ký: <strong>Bạc</strong> – 0 điểm</div>
                <div><i class="fa-solid fa-star me-1"></i> Cứ 1.000đ thanh toán thành công được 1 điểm</div>
                <div><i class="fa-solid fa-arrow-trend-up me-1"></i> Hạng thẻ được cập nhật tự động theo tổng điểm</div>
            </div>
            <div class="pos-member-message" id="posMemberMessage" role="status" aria-live="polite"></div>
            <div class="pos-member-actions">
                <button type="button" class="is-cancel" onclick="closePosMemberDialog()">Đóng</button>
                <button type="submit" class="is-submit" id="posMemberSubmit"><i class="fa-solid fa-check me-1"></i> Tạo thành viên</button>
            </div>
        </form>
    </div>
</dialog>

<script>
    const posMemberCsrf = <?= json_encode($posMemberCsrf) ?>;
    const posSignedInUserId = <?= (int)$_SESSION['user_id'] ?>;
    let pendingActiveOrderId = <?= (int)($_SESSION['ck_pos_active_order_id'] ?? 0) ?>;
    let pendingCartSync = Promise.resolve();
    let pendingCartSyncTimer = null;
    const posSignedInStaffId = <?= $posStaffId ?>;
    const posSignedInStoreId = <?= $posAssignedStoreId ?>;
    const posDefaultStoreId = <?= $posDefaultStoreId ?>;
    const posCanSwitchStore = <?= $posCanSwitchStore ? 'true' : 'false' ?>;
    const posRegionalAssignments = <?= json_encode($posRegionalAssignments, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const posStockByStore = <?= json_encode($posStockByStore, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function getPosStock(productId) {
        const storeId = document.getElementById('posStoreSelect').value;
        return Math.max(0, Number((posStockByStore[storeId] || {})[String(productId)] || 0));
    }

    function renderPosStock() {
        document.querySelectorAll('.prod-row[id^="prod-"]').forEach(element => {
            const productId = Number(element.id.substring(5));
            const outOfStock = getPosStock(productId) <= 0;
            element.classList.toggle('is-out-stock', outOfStock);
            element.setAttribute('aria-disabled', outOfStock ? 'true' : 'false');
        });
    }

    async function refreshPosStock() {
        const storeId = document.getElementById('posStoreSelect').value;
        const url = new URL(window.location.pathname, window.location.href);
        url.searchParams.set('pos_api', 'stock');
        url.searchParams.set('cuahang_id', storeId);
        const response = await fetch(url.toString(), {cache: 'no-store'});
        if (!response.ok) throw new Error('Không thể kiểm tra tồn kho tại cửa hàng.');
        const result = await response.json();
        if (!result.success || !result.stock || Number(result.cuahang_id) !== Number(storeId)) {
            throw new Error(result.message || 'Dữ liệu tồn kho không hợp lệ.');
        }
        posStockByStore[storeId] = result.stock;
        if (document.getElementById('posStoreSelect').value === storeId) renderPosStock();
        return result.stock;
    }

    function showStockWarning(name, remaining, requested = null) {
        const maximum = Math.max(0, Math.trunc(Number(remaining) || 0));
        const storeName = document.getElementById('posStoreSelect').selectedOptions[0]?.textContent.trim() || 'chi nhánh hiện tại';
        const requestedText = requested === null ? '' : String(requested).replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        const wanted = requested === null ? '' : `Bạn yêu cầu ${requestedText} sản phẩm. `;
        const available = maximum === 0
            ? `${name} đã hết hàng tại ${storeName}. Hiện không thể mua sản phẩm này.`
            : `${name} tại ${storeName} chỉ còn ${maximum.toLocaleString('vi-VN')} sản phẩm. Bạn có thể mua tối đa ${maximum.toLocaleString('vi-VN')} sản phẩm.`;
        openPosNotice({
            title: maximum === 0 ? 'Sản phẩm đã hết hàng' : 'Không đủ số lượng tồn kho',
            message: wanted + available,
            mode: 'info', confirmText: 'Đã hiểu'
        });
    }


    let allPromoOptions = Array.from(document.querySelectorAll('#posPromoSelect option')).slice(1);

    async function refreshPosPromotions() {
        try {
            const response = await fetch(window.location.pathname + '?pos_api=promotions', { cache: 'no-store' });
            const result = await response.json();
            if (!result.success || !Array.isArray(result.promotions)) return;
            allPromoOptions = result.promotions.map(p => {
                const option = document.createElement('option');
                option.value = String(p.id);
                option.dataset.code = p.code || '';
                option.dataset.store = String(p.store_id);
                option.dataset.storeName = p.store_name || 'Toàn hệ thống';
                option.dataset.status = p.status;
                option.dataset.val = String(p.value);
                option.dataset.type = p.type;
                option.disabled = false;
                option.textContent = '[' + (p.code || '') + '] ' + p.name;
                return option;
            });
            updatePromoByBranch();
        } catch (e) {
        }
    }


    function updatePromoByBranch() {
        const select = document.getElementById('posPromoSelect');
        const branchId = Number(document.getElementById('posStoreSelect').value);
        const previousPromo = select.value || localStorage.getItem('ck_pos_promo') || '';
        select.length = 1;
        allPromoOptions.forEach(option => {
            const storeId = Number(option.dataset.store || 0);
            if (option.dataset.status === 'active' && (storeId === 0 || storeId === branchId)) select.appendChild(option);
        });
        const validSelection = Array.from(select.options).find(option => option.value === previousPromo && !option.disabled);
        select.value = validSelection ? previousPromo : '';
        localStorage.setItem('ck_pos_promo', select.value);
        const codeInput = document.getElementById('posPromoCodeInput');
        codeInput.value = validSelection ? (validSelection.dataset.code || '') : '';
        clearPromoFeedback();
        calcTotal();
        markPendingCartChanged();
    }


    function openPosNotice({title, message, mode = 'info', confirmText = 'Đã hiểu', cancelText = '', onConfirm = null}) {
        const dialog = document.getElementById('posFeedbackDialog');
        const icon = document.getElementById('posDialogIcon');
        const confirmBtn = document.getElementById('posDialogConfirm');
        const cancelBtn = document.getElementById('posDialogCancel');
        document.getElementById('posDialogTitle').textContent = title;
        document.getElementById('posDialogMessage').textContent = message;
        icon.className = 'pos-dialog-icon' + (mode === 'info' ? ' is-info' : '');
        icon.innerHTML = mode === 'info' ? '<i class="fa-solid fa-store"></i>' : '<i class="fa-solid fa-trash-can"></i>';
        confirmBtn.className = mode === 'info' ? 'pos-dialog-info' : 'pos-dialog-danger';
        confirmBtn.textContent = confirmText;
        cancelBtn.hidden = !cancelText;
        cancelBtn.textContent = cancelText;
        document.getElementById('posDialogClose').onclick = () => dialog.close();
        cancelBtn.onclick = () => dialog.close();
        confirmBtn.onclick = () => {
            dialog.close();
            if (typeof onConfirm === 'function') onConfirm();
        };
        if (!dialog.open) dialog.showModal();
    }

    function showPromoFeedback(message, success) {
        const el = document.getElementById('posPromoCodeFeedback');
        el.textContent = message;
        el.className = 'small mt-1 ' + (success ? 'text-success' : 'text-danger');
        el.style.display = message ? 'block' : 'none';
    }

    function clearPromoFeedback() {
        const el = document.getElementById('posPromoCodeFeedback');
        el.textContent = '';
        el.style.display = 'none';
    }

    function onPromoSelectChange() {
        const select = document.getElementById('posPromoSelect');
        document.getElementById('posPromoCodeInput').value = select.selectedIndex > 0 ? (select.options[select.selectedIndex].dataset.code || '') : '';
        clearPromoFeedback();
        calcTotal();
    }

    function applyPromoCode() {
        const input = document.getElementById('posPromoCodeInput');
        const code = input.value.trim().toUpperCase();
        const select = document.getElementById('posPromoSelect');
        select.value = '';
        calcTotal();
        markPendingCartChanged();
        if (!code) {
            showPromoFeedback('Vui lòng nhập mã voucher.', false);
            input.focus();
            return;
        }
        const option = allPromoOptions.find(item => (item.dataset.code || '').toUpperCase() === code);
        if (!option) {
            showPromoFeedback('Mã ' + code + ' không tồn tại, chưa có hiệu lực hoặc đã hết lượt sử dụng.', false);
            return;
        }
        const branchId = Number(document.getElementById('posStoreSelect').value);
        const promoStore = Number(option.dataset.store || 0);
        if (promoStore !== 0 && promoStore !== branchId) {
            const allowedStore = option.dataset.storeName || 'chi nhánh khác';
            const currentStore = document.getElementById('posStoreSelect').selectedOptions[0]?.textContent.trim().replace(/^🏪\s*/, '') || 'cửa hàng hiện tại';
            showPromoFeedback('Mã ' + code + ' không áp dụng tại chi nhánh đang chọn.', false);
            openPosNotice({
                title: 'Mã không áp dụng tại đây',
                message: 'Mã ' + code + ' chỉ áp dụng tại ' + allowedStore + ', không áp dụng tại ' + currentStore + '.',
                mode: 'info'
            });
            return;
        }
        if (option.dataset.status === 'upcoming') {
            showPromoFeedback('Mã ' + code + ' chưa đến thời gian áp dụng.', false);
            return;
        }
        if (option.dataset.status === 'expired' || option.disabled) {
            showPromoFeedback('Mã ' + code + ' đã hết hạn hoặc hết lượt.', false);
            return;
        }
        select.value = option.value;
        input.value = code;
        calcTotal();
        markPendingCartChanged();
        showPromoFeedback('Đã áp dụng mã ' + code + ' thành công.', true);
    }

    function updateStaffByBranch() {
        const branchSelect = document.getElementById('posStoreSelect');
        if (!posCanSwitchStore) branchSelect.value = String(posDefaultStoreId);
        const assignedManagers = posRegionalAssignments[branchSelect.value] || {};
        document.getElementById('posRegionalLeaderName').textContent = assignedManagers.regional || 'Chưa phân công';
        document.getElementById('posBranchManagerName').textContent = assignedManagers.branch || 'Chưa phân công';
        localStorage.setItem('ck_pos_store', branchSelect.value);
        localStorage.setItem('ck_pos_staff', String(posSignedInStaffId));
        renderPosStock();
        refreshPosStock().catch(error => console.error(error));
        updatePromoByBranch();
        refreshPosPromotions();
    }

    const itemsPerPage = 16; 
    let currentPage = 1;
    let visibleItems = [];

    function renderPagination() {
        const totalPages = Math.ceil(visibleItems.length / itemsPerPage);
        const wrapper = document.getElementById('paginationBar');
        const pageNumbers = document.getElementById('pageNumbers');
        const btnPrev = document.getElementById('btnPrevPage');
        const btnNext = document.getElementById('btnNextPage');
        
        pageNumbers.innerHTML = '';
        
        if (totalPages <= 1) {
            wrapper.style.display = 'none';
            showPageItems();
            return;
        }
        
        wrapper.style.display = 'flex';
        btnPrev.disabled = (currentPage === 1);
        btnNext.disabled = (currentPage === totalPages);

        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        for (let i = startPage; i <= endPage; i++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `page-btn ${i === currentPage ? 'active' : ''}`;
            btn.textContent = i;
            btn.onclick = () => {
                currentPage = i;
                renderPagination();
            };
            pageNumbers.appendChild(btn);
        }
        
        showPageItems();
        const scrollWrap = document.getElementById('productScrollWrap');
        if (scrollWrap) scrollWrap.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function showPageItems() {
        visibleItems.forEach(el => el.style.display = 'none');
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        for (let i = startIndex; i < endIndex && i < visibleItems.length; i++) {
            visibleItems[i].style.display = '';
        }
    }

    function changePage(delta) {
        const totalPages = Math.ceil(visibleItems.length / itemsPerPage);
        currentPage += delta;
        if (currentPage < 1) currentPage = 1;
        if (currentPage > totalPages) currentPage = totalPages;
        renderPagination();
    }

    function triggerSearch(kw, forceAllCat = false) {
        if (forceAllCat) {
            document.querySelectorAll('.seg-btn').forEach(x => x.classList.remove('active'));
            const allBtn = document.querySelector('.seg-btn[data-cat="Tất cả"]');
            if (allBtn) allBtn.classList.add('active');
        }
        const activeCat = document.querySelector('.seg-btn.active').dataset.cat;
        const cleanKw = kw.toLowerCase().trim();

        visibleItems = [];
        document.querySelectorAll('.prod-item-col').forEach(c => {
            const matchCat = (activeCat === 'Tất cả' || c.dataset.cat === activeCat);
            const matchKw  = c.dataset.search.includes(cleanKw);
            
            c.style.display = 'none'; 

            if (matchCat && matchKw) {
                visibleItems.push(c);
            }
        });

        currentPage = 1; 
        renderPagination();
    }

    document.querySelectorAll('.seg-btn').forEach(b => b.onclick = function(){
        document.querySelectorAll('.seg-btn').forEach(x => x.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('posSearchInput').value = '';
        triggerSearch('', false);
    });

    const posInput = document.getElementById('posSearchInput');
    posInput.oninput = function() { triggerSearch(this.value, false); };
    posInput.onkeydown = function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            triggerSearch(this.value, true);
        }
    };

    let cart = JSON.parse(localStorage.getItem('ck_pos_cart')) || [];
    let calc = {s:0, d:0, v:0, t:0};
    const fVND = n => Math.round(n).toLocaleString() + ' ₫';
    const beep = () => { try { const x=new (window.AudioContext||window.webkitAudioContext)(), o=x.createOscillator(), g=x.createGain(); o.type='sine'; o.frequency.value=880; g.gain.value=0.05; o.connect(g); g.connect(x.destination); o.start(); o.stop(x.currentTime+0.05); } catch(e){} };

    function saveState() {
        localStorage.setItem('ck_pos_cart', JSON.stringify(cart));
        localStorage.setItem('ck_pos_cus', document.getElementById('posCustomerSelect').value);
        localStorage.setItem('ck_pos_customer_name', document.getElementById('posCustomerInput').value.trim());
        localStorage.setItem('ck_pos_promo', document.getElementById('posPromoSelect').value);
        localStorage.setItem('ck_pos_staff', document.getElementById('posStaffSelect').value);
    }

    function currentPendingOrderId() {
        return pendingActiveOrderId || Number(localStorage.getItem('ck_pos_pending_order') || 0);
    }

    function createPendingOrderFormData(action) {
        const fd = new FormData();
        fd.append('pos_action', action);
        fd.append('cart', JSON.stringify(cart));
        fd.append('cuahang_id', document.getElementById('posStoreSelect').value);
        const selectedCustomerId = document.getElementById('posCustomerSelect').value;
        fd.append('khachhang_id', /^\d+$/.test(selectedCustomerId) ? selectedCustomerId : '');
        fd.append('ten_khach_nhap', selectedCustomerId === '__manual__' ? document.getElementById('posCustomerInput').value.trim() : '');
        fd.append('khuyenmai_id', document.getElementById('posPromoSelect').value);
        fd.append('tong_tien', calc.s);
        fd.append('tien_giam_gia', calc.d);
        fd.append('khach_phai_tra', calc.t);
        return fd;
    }

    function queuePendingCartSync() {
        if (!currentPendingOrderId()) return pendingCartSync;
        pendingCartSync = pendingCartSync.catch(() => {}).then(async () => {
            const orderId = currentPendingOrderId();
            if (!orderId) return;
            const fd = createPendingOrderFormData('sync_cart_pending');
            fd.append('order_id', String(orderId));
            const response = await fetch(window.location.pathname, { method: 'POST', body: fd, keepalive: true, cache: 'no-store' });
            if (!response.ok) throw new Error('Không thể kết nối máy chủ để đồng bộ đơn hàng.');
            const result = await response.json();
            if (!result.success) throw new Error(result.message || 'Không thể cập nhật đơn chờ thanh toán.');
            if (!result.active) {
                pendingActiveOrderId = 0;
                localStorage.removeItem('ck_pos_pending_order');
            } else {
                pendingActiveOrderId = Number(result.hoadon_id);
                localStorage.setItem('ck_pos_pending_order', String(result.hoadon_id));
            }
            localStorage.setItem('ck_pos_order_updated', String(Date.now()));
        });
        return pendingCartSync;
    }

    function markPendingCartChanged() {
        if (!currentPendingOrderId()) return;
        if (pendingCartSyncTimer !== null) clearTimeout(pendingCartSyncTimer);
        pendingCartSyncTimer = setTimeout(() => {
            pendingCartSyncTimer = null;
            queuePendingCartSync().catch(error => {
                console.error(error);
            });
        }, 120);
    }

    async function flushPendingCartSync() {
        if (pendingCartSyncTimer !== null) {
            clearTimeout(pendingCartSyncTimer);
            pendingCartSyncTimer = null;
            queuePendingCartSync();
        }
        await pendingCartSync;
    }

    async function addToCart(id, name, price, sku) {
        const selectedStore = document.getElementById('posStoreSelect').value;
        try {
            await refreshPosStock();
        } catch (error) {
            openPosNotice({title: 'Không kiểm tra được tồn kho', message: error.message, mode: 'info'});
            return;
        }
        if (document.getElementById('posStoreSelect').value !== selectedStore) return;
        const existing = cart.find(item => Number(item.id) === Number(id));
        const remaining = getPosStock(id);
        if ((existing ? Number(existing.qty) : 0) >= remaining) {
            showStockWarning(name, remaining, (existing ? Number(existing.qty) : 0) + 1);
            return;
        }
        beep();
        const index = cart.findIndex(item => Number(item.id) === Number(id));
        if (index > -1) {
            const item = cart.splice(index, 1)[0];
            item.qty++;
            cart.unshift(item);
        } else {
            cart.unshift({id, name, price, sku, qty: 1});
        }
        drawCart();
        markPendingCartChanged();
        document.getElementById('cartBox').scrollTop = 0;
    }

    async function uQty(id, delta) {
        const index = cart.findIndex(item => Number(item.id) === Number(id));
        if (index < 0) return;
        if (delta > 0) {
            const selectedStore = document.getElementById('posStoreSelect').value;
            try {
                await refreshPosStock();
            } catch (error) {
                openPosNotice({title: 'Không kiểm tra được tồn kho', message: error.message, mode: 'info'});
                return;
            }
            if (document.getElementById('posStoreSelect').value !== selectedStore) return;
            const refreshedItem = cart.find(item => Number(item.id) === Number(id));
            if (!refreshedItem) return;
            if (refreshedItem.qty + delta > getPosStock(id)) {
                showStockWarning(refreshedItem.name, getPosStock(id), refreshedItem.qty + delta);
                return;
            }
        }
        const currentIndex = cart.findIndex(item => Number(item.id) === Number(id));
        if (currentIndex < 0) return;
        const item = cart.splice(currentIndex, 1)[0];
        item.qty += delta;
        if (item.qty > 0) cart.unshift(item);
        drawCart();
        markPendingCartChanged();
        document.getElementById('cartBox').scrollTop = 0;
    }

    const posQuantityCommits = new Set();

    function commitPosQuantityInput(input, id) {
        if (input.dataset.committing === '1') return Promise.resolve(true);
        input.dataset.committing = '1';
        const work = setQty(id, input.value);
        posQuantityCommits.add(work);
        work.finally(() => {
            posQuantityCommits.delete(work);
            input.dataset.committing = '0';
        });
        return work;
    }

    async function flushPosQuantityInputs() {
        const inputs = Array.from(document.querySelectorAll('.qty-input'));
        for (const input of inputs) {
            const id = Number(input.dataset.productId);
            const item = cart.find(product => Number(product.id) === id);
            if (item && input.dataset.committing !== '1' && input.value.trim() !== String(item.qty)) {
                commitPosQuantityInput(input, id);
            }
        }
        const results = await Promise.all(Array.from(posQuantityCommits));
        return results.every(Boolean);
    }

    async function setQty(id, value) {
        const initialItem = cart.find(item => Number(item.id) === Number(id));
        if (!initialItem) return false;
        const raw = String(value).trim();
        if (!/^\d+$/.test(raw)) {
            drawCart();
            openPosNotice({title: 'Số lượng không hợp lệ', message: 'Vui lòng nhập một số nguyên từ 0 trở lên. Nhập 0 nếu muốn xóa sản phẩm.', mode: 'info'});
            return false;
        }
        const requestedQuantity = BigInt(raw);
        const originalQuantity = Number(initialItem.qty);
        if (requestedQuantity === BigInt(originalQuantity)) return true;
        if (requestedQuantity > 0n) {
            const storeId = document.getElementById('posStoreSelect').value;
            try {
                await refreshPosStock();
            } catch (error) {
                drawCart();
                openPosNotice({title: 'Không kiểm tra được tồn kho', message: error.message, mode: 'info'});
                return false;
            }
            if (document.getElementById('posStoreSelect').value !== storeId) {
                drawCart();
                return false;
            }
            const remaining = getPosStock(id);
            if (requestedQuantity > BigInt(remaining)) {
                drawCart();
                showStockWarning(initialItem.name, remaining, raw);
                return false;
            }
        }
        const quantity = Number(requestedQuantity);
        const current = cart.find(item => Number(item.id) === Number(id));
        if (!current) return false;
        if (quantity === 0) cart = cart.filter(item => Number(item.id) !== Number(id));
        else current.qty = quantity;
        drawCart();
        markPendingCartChanged();
        return true;
    }

    function drawCart() {
        document.querySelectorAll('.prod-row').forEach(e => e.classList.remove('in-cart'));
        document.querySelectorAll('.prod-qty-badge').forEach(e => e.style.display = 'none');
        let h = '';
        if(cart.length === 0) h = '<div class="text-center text-muted py-5"><i class="fa-solid fa-cart-shopping fs-1 opacity-25 mb-3 d-block"></i>Giỏ hàng trống</div>';
        else {
            cart.forEach((i, index) => {
                let skuImg = (i.sku && i.sku !== 'undefined') ? i.sku : 'SP001';
                let animClass = index === 0 ? 'cart-item-anim' : '';
                h += `<div class="d-flex justify-content-between align-items-center py-2 border-bottom ${animClass}">
                    <div class="d-flex align-items-center gap-2 pe-2">
                        <img src="../../../assets/images/${skuImg}.jpg" style="width:44px;height:44px;min-width:44px;object-fit:contain;padding:2px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;" onerror="this.src='../../../assets/images/SP001.jpg'; this.onerror=null;">
                        <div>
                            <div class="fw-semibold small text-dark" style="line-height:1.2; margin-bottom:4px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">${i.name}</div>
                            <small class="text-muted">${i.qty} x ${fVND(i.price)}</small>
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1">
                        <span class="num-font fw-bold text-dark" style="min-width:75px; text-align:right;">${fVND(i.price*i.qty)}</span>
                        <div class="qty-stepper mt-1"><button class="qty-btn" onclick="uQty(${i.id},-1)"><i class="fa-solid fa-minus" style="font-size:10px"></i></button><input type="text" class="qty-input" data-product-id="${i.id}" value="${i.qty}" inputmode="numeric" pattern="[0-9]*" aria-label="Nhập số lượng" title="Nhập số lượng rồi nhấn Enter hoặc bấm ra ngoài" onchange="commitPosQuantityInput(this,${i.id})" onkeydown="if(event.key === 'Enter'){event.preventDefault();this.blur();}" onclick="event.stopPropagation()"><button class="qty-btn" onclick="uQty(${i.id},1)"><i class="fa-solid fa-plus" style="font-size:10px"></i></button></div>
                    </div>
                </div>`;
                const cEl = document.getElementById('prod-'+i.id), bEl = document.getElementById('b-'+i.id);
                if(cEl && bEl){ cEl.classList.add('in-cart'); bEl.textContent = i.qty; bEl.style.display = 'flex'; }
            });
        }
        document.getElementById('cartBox').innerHTML = h; 
        calcTotal();
        saveState();
    }

    function calcTotal() {
        let s = 0; cart.forEach(i => s += i.price * i.qty); let d = 0;
        const p = document.getElementById('posPromoSelect').options[document.getElementById('posPromoSelect').selectedIndex];
        if(p && p.value && s>0) {
            d += (p.dataset.type === 'Giảm %' || p.dataset.type === 'Phần trăm') 
                 ? s * (parseFloat(p.dataset.val)/100) 
                 : parseFloat(p.dataset.val);
        }
        
        const c = document.getElementById('posCustomerSelect').options[document.getElementById('posCustomerSelect').selectedIndex];
        if(c && c.value && s>0) {
            let discRate = Number(c.dataset.disc);
            if (!Number.isFinite(discRate)) discRate = 0;
            if (c.dataset.disc === undefined || c.dataset.disc === '') {
                const tName = (c.dataset.tier || '').toLowerCase();
                if (tName.includes('bạc')) discRate = 2;
                else if (tName.includes('vàng')) discRate = 5;
                else if (tName.includes('kim cương')) discRate = 10;
            }
            d += s * (discRate / 100);
        }
        
        if(d > s) d = s; 
        let v = Math.round((s - d) * 0.08); 
        let t = s - d + v;
        calc = {s, d, v, t};
        document.getElementById('lSub').textContent = fVND(s); 
        document.getElementById('lDisc').textContent = '- ' + fVND(d);
        document.getElementById('lVat').textContent = fVND(v); 
        document.getElementById('lTotal').textContent = fVND(t);
        saveState();
    }

    function updateTierBadge(tierName) {
        const badge = document.getElementById('tierBadgeEl');
        badge.className = 'tier-badge';
        const cleanTier = (tierName || '').toLowerCase().trim();
        if (cleanTier.includes('bạc')) {
            badge.classList.add('tier-bac');
            badge.textContent = '[Bạc]';
        } else if (cleanTier.includes('vàng')) {
            badge.classList.add('tier-vang');
            badge.textContent = '[Vàng]';
        } else if (cleanTier.includes('kim cương')) {
            badge.classList.add('tier-kimcuong');
            badge.textContent = '[Kim Cương]';
        } else {
            badge.classList.add('tier-none');
            badge.textContent = 'Khách lẻ';
        }
    }

    function removeManualCustomerOption() {
        const option = document.getElementById('posManualCustomerOption');
        if (option) option.remove();
    }

    let activeCustomerSuggestionIndex = -1;

    function normalizeCustomerText(value) {
        return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase().trim();
    }

    function closeCustomerSuggestions() {
        const dropdown = document.getElementById('posCustomerSuggestions');
        dropdown.hidden = true;
        dropdown.replaceChildren();
        document.getElementById('posCustomerInput').setAttribute('aria-expanded', 'false');
        activeCustomerSuggestionIndex = -1;
    }

    function chooseCustomerSuggestion(id) {
        const select = document.getElementById('posCustomerSelect');
        select.value = id;
        handleCustomerSelect();
        closeCustomerSuggestions();
        markPendingCartChanged();
    }

    function updateCustomerSuggestions() {
        const input = document.getElementById('posCustomerInput');
        const dropdown = document.getElementById('posCustomerSuggestions');
        const keyword = normalizeCustomerText(input.value);
        if (!keyword || document.activeElement !== input) {
            closeCustomerSuggestions();
            return;
        }
        const matches = Array.from(document.getElementById('posCustomerSelect').options).filter(option => {
            if (!/^\d+$/.test(option.value)) return false;
            const name = normalizeCustomerText(option.dataset.name);
            const phone = String(option.dataset.phone || '').trim();
            return name.includes(keyword) || phone.includes(input.value.trim());
        }).slice(0, 40);
        dropdown.replaceChildren();
        activeCustomerSuggestionIndex = -1;
        if (!matches.length) {
            const empty = document.createElement('div');
            empty.className = 'pos-customer-no-result';
            empty.textContent = 'Không tìm thấy khách hàng. Bạn có thể nhập tên mới.';
            dropdown.appendChild(empty);
        }
        for (const option of matches) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'pos-customer-suggestion';
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', 'false');
            const name = document.createElement('span');
            name.className = 'pos-customer-suggestion-name';
            name.textContent = option.dataset.name || '';
            const detail = document.createElement('span');
            detail.className = 'pos-customer-suggestion-detail';
            detail.textContent = '[' + (option.dataset.tier || 'Bạc') + ']' + (option.dataset.phone ? ' - SĐT: ' + option.dataset.phone : '');
            item.append(name, detail);
            item.addEventListener('click', () => chooseCustomerSuggestion(option.value));
            dropdown.appendChild(item);
        }
        dropdown.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function updatePosMemberPointsPreview() {
        const select = document.getElementById('posCustomerSelect');
        const option = select.selectedOptions[0];
        const preview = document.getElementById('posMemberPointsPreview');
        if (option && /^\d+$/.test(option.value)) {
            const points = Math.max(0, Number(option.dataset.points || 0));
            preview.textContent = 'Điểm tích lũy: ' + points.toLocaleString('vi-VN') + ' điểm';
        } else {
            preview.textContent = 'Tích 1 điểm mỗi 1.000đ thanh toán';
        }
    }

    function openPosMemberDialog() {
        const dialog = document.getElementById('posMemberDialog');
        const select = document.getElementById('posCustomerSelect');
        const name = select.value === '__manual__' ? document.getElementById('posCustomerInput').value.trim() : '';
        document.getElementById('posMemberForm').reset();
        document.getElementById('posMemberName').value = name;
        document.getElementById('posMemberStoreName').textContent = document.getElementById('posStoreSelect').selectedOptions[0]?.textContent.trim() || '';
        document.getElementById('posMemberMessage').textContent = '';
        if (!dialog.open) dialog.showModal();
        document.getElementById(name ? 'posMemberPhone' : 'posMemberName').focus();
    }

    function closePosMemberDialog() {
        const dialog = document.getElementById('posMemberDialog');
        if (dialog.open) dialog.close();
    }

    async function registerPosMember(event) {
        event.preventDefault();
        const form = document.getElementById('posMemberForm');
        if (!form.reportValidity()) return;
        const name = document.getElementById('posMemberName').value.trim();
        const phone = document.getElementById('posMemberPhone').value.trim().replace(/[\s.\-]/g, '');
        const message = document.getElementById('posMemberMessage');
        const button = document.getElementById('posMemberSubmit');
        if (!/^0\d{9,10}$/.test(phone)) {
            message.textContent = 'Số điện thoại phải có 10 hoặc 11 chữ số, bắt đầu bằng số 0.';
            return;
        }
        button.disabled = true;
        message.textContent = '';
        try {
            const data = new FormData();
            data.append('pos_action', 'register_member');
            data.append('csrf_token', posMemberCsrf);
            data.append('ho_ten', name);
            data.append('so_dien_thoai', phone);
            data.append('cuahang_id', document.getElementById('posStoreSelect').value);
            const response = await fetch(window.location.pathname, {method: 'POST', body: data, cache: 'no-store'});
            const result = await response.json();
            if (!result.success || !result.member) throw new Error(result.message || 'Không thể đăng ký thành viên.');
            const member = result.member;
            const select = document.getElementById('posCustomerSelect');
            removeManualCustomerOption();
            let option = Array.from(select.options).find(o => o.value === String(member.id));
            if (!option) {
                option = document.createElement('option');
                option.value = String(member.id);
                select.appendChild(option);
            }
            option.dataset.name = member.name;
            option.dataset.phone = member.phone;
            option.dataset.disc = String(member.discount);
            option.dataset.points = String(member.points);
            option.dataset.tier = member.tier || 'Bạc';
            option.textContent = '[' + (member.tier || 'Bạc') + '] ' + member.name;
            select.value = option.value;
            handleCustomerSelect();
            updatePosMemberPointsPreview();
            saveState();
            markPendingCartChanged();
            closePosMemberDialog();
            openPosNotice({
                title: result.existing ? 'Thành viên đã tồn tại' : 'Tạo thành viên thành công',
                message: result.existing
                    ? 'Số điện thoại này đã được đăng ký cho ' + member.name + '. Đã chọn thành viên có ' + member.points + ' điểm.'
                    : 'Đã đăng ký ' + member.name + ' với hạng ' + member.tier + ', 0 điểm. Khi thanh toán thành công, điểm sẽ được cộng tự động.',
                mode: 'info', confirmText: 'Đã hiểu'
            });
        } catch (error) {
            message.textContent = error.message || 'Không thể tạo thành viên. Vui lòng thử lại.';
        } finally {
            button.disabled = false;
        }
    }

    function handleCustomerSelect() {
        const sel = document.getElementById('posCustomerSelect');
        const opt = sel.options[sel.selectedIndex];
        if (!opt) return;
        if (opt.value !== '__manual__') removeManualCustomerOption();
        document.getElementById('posCustomerInput').value = opt.value ? (opt.dataset.name || '') : '';
        updateTierBadge(opt.dataset.tier || '');
        updatePosMemberPointsPreview();
        calcTotal();
        closeCustomerSuggestions();
    }

    function handleCustomerInput() {
        const rawName = document.getElementById('posCustomerInput').value.trim();
        const normalizedName = rawName.toLocaleLowerCase('vi');
        const sel = document.getElementById('posCustomerSelect');
        const registered = Array.from(sel.options).find(opt => opt.value !== '' && opt.value !== '__manual__' && (opt.dataset.name || '').trim().toLocaleLowerCase('vi') === normalizedName);
        if (rawName && registered) {
            removeManualCustomerOption();
            sel.value = registered.value;
            updateTierBadge(registered.dataset.tier || '');
        } else if (rawName) {
            let manual = document.getElementById('posManualCustomerOption');
            if (!manual) {
                manual = document.createElement('option');
                manual.id = 'posManualCustomerOption';
                manual.value = '__manual__';
                manual.dataset.disc = '0';
                manual.dataset.tier = 'none';
                sel.add(manual, 1);
            }
            manual.dataset.name = rawName;
            manual.textContent = 'Khách tự nhập: ' + rawName;
            sel.value = '__manual__';
            updateTierBadge('none');
        } else {
            removeManualCustomerOption();
            sel.value = '';
            updateTierBadge('none');
        }
        updatePosMemberPointsPreview();
        calcTotal();
        updateCustomerSuggestions();
    }

    const headerSearch = document.querySelector('header input[type="text"], input[placeholder*="Tìm"]');
    if (headerSearch) {
        headerSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                posInput.value = this.value;
                triggerSearch(this.value, true);
                posInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    document.addEventListener('keydown', e => { 
        if (e.key === 'F2') { 
            e.preventDefault(); 
            posInput.focus(); 
            posInput.select();
        } 
    });

    async function goToCheckout(targetHref = '../thanh_toan_don_hang/index.php') {
        if (cart.length === 0) {
            openPosNotice({
                title: 'Giỏ hàng trống',
                message: 'Vui lòng quét hoặc chọn ít nhất 1 sản phẩm để thanh toán!',
                mode: 'info',
                confirmText: 'Đã hiểu'
            });
            return;
        }
        const btn = document.getElementById('btnGoCheckout');
        if (btn.disabled) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Đang chốt đơn...';
        try {
            if (!await flushPosQuantityInputs()) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-credit-card me-2"></i>Thanh toán đơn hàng';
                return;
            }
            if (cart.length === 0) throw new Error('Giỏ hàng trống.');
            await refreshPosStock();
            const exceeding = cart.find(item => Number(item.qty) > getPosStock(item.id));
            if (exceeding) {
                showStockWarning(exceeding.name, getPosStock(exceeding.id), exceeding.qty);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-credit-card me-2"></i>Thanh toán đơn hàng';
                return;
            }
            await flushPendingCartSync();
            if (cart.length === 0) throw new Error('Giỏ hàng trống.');
            let orderId = currentPendingOrderId();
            if (!orderId) {
                const fd = createPendingOrderFormData('create_order_pending');
                const response = await fetch(window.location.pathname, { method: 'POST', body: fd, cache: 'no-store' });
                if (!response.ok) throw new Error('Không thể kết nối máy chủ.');
                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'Không thể tạo đơn hàng.');
                orderId = Number(result.hoadon_id);
                pendingActiveOrderId = orderId;
                localStorage.setItem('ck_pos_pending_order', String(orderId));
            }
            window.location.assign(targetHref.split('?')[0] + '?id=' + orderId);
        } catch (error) {
            openPosNotice({title: 'Không thể chuyển thanh toán', message: error.message, mode: 'info'});
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-credit-card me-2"></i>Thanh toán đơn hàng';
        }
    }

    async function clearPendingPosOrder() {
        if (pendingCartSyncTimer !== null) { clearTimeout(pendingCartSyncTimer); pendingCartSyncTimer = null; }
        await pendingCartSync.catch(() => {});
        const fd = new FormData();
        fd.append('pos_action', 'cancel_cart_pending');
        const response = await fetch(window.location.pathname, { method: 'POST', body: fd });
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Không thể hủy đơn hàng.');
        pendingActiveOrderId = 0;
        localStorage.removeItem('ck_pos_pending_order');
        localStorage.setItem('ck_pos_order_updated', String(Date.now()));
        localStorage.setItem('ck_pos_cart_cleared', String(Date.now()));
        cart = [];
        document.getElementById('posCustomerInput').value = '';
        removeManualCustomerOption();
        document.getElementById('posCustomerSelect').selectedIndex = 0;
        updateTierBadge('none');
        updatePosMemberPointsPreview();
        document.getElementById('posPromoSelect').selectedIndex = 0;
        document.getElementById('posPromoCodeInput').value = '';
        clearPromoFeedback();
        localStorage.removeItem('ck_pos_cart');
        localStorage.removeItem('ck_pos_cus');
        localStorage.removeItem('ck_pos_customer_name');
        localStorage.removeItem('ck_pos_promo');
        drawCart();
    }

    function clearCartConfirm() {
        openPosNotice({
            title: 'Xóa giỏ hàng hiện tại?',
            message: 'Toàn bộ sản phẩm và khuyến mãi đã chọn sẽ được xóa khỏi giỏ hàng.',
            mode: 'danger',
            confirmText: 'Xóa giỏ hàng',
            cancelText: 'Giữ giỏ hàng',
            onConfirm: async () => {
                try {
                    await clearPendingPosOrder();
                } catch (err) {
                    alert('Lỗi: ' + err.message);
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const previousUser = localStorage.getItem('ck_pos_last_user_id');
        if (previousUser !== String(posSignedInUserId)) {
            ['ck_pos_cart', 'ck_pos_cus', 'ck_pos_customer_name', 'ck_pos_promo', 'ck_pos_staff', 'ck_pos_pending_order', 'ck_pos_store'].forEach(key => localStorage.removeItem(key));
            localStorage.setItem('ck_pos_last_user_id', String(posSignedInUserId));
            cart = [];
        }
        let savedCustomer = localStorage.getItem('ck_pos_cus') || '';
        let savedCustomerName = localStorage.getItem('ck_pos_customer_name') || '';
        let savedPromo = localStorage.getItem('ck_pos_promo') || '';
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.has('clear') || urlParams.has('clear_cart')) {
            savedCustomer = '';
            savedCustomerName = '';
            savedPromo = '';
            cart = [];
            localStorage.removeItem('ck_pos_cart');
            localStorage.removeItem('ck_pos_cus');
            localStorage.removeItem('ck_pos_customer_name');
            localStorage.removeItem('ck_pos_promo');
            localStorage.removeItem('ck_pos_pending_order');
            localStorage.setItem('ck_pos_cart_cleared', String(Date.now()));
            window.history.replaceState({}, document.title, window.location.pathname);
        } else {
            cart = JSON.parse(localStorage.getItem('ck_pos_cart')) || [];
        }

        const savedStore = localStorage.getItem('ck_pos_store');
        const storeSelect = document.getElementById('posStoreSelect');
        if (posCanSwitchStore && savedStore && Array.from(storeSelect.options).some(option => option.value === savedStore)) {
            storeSelect.value = savedStore;
        } else {
            storeSelect.value = String(posDefaultStoreId);
        }
        updateStaffByBranch();

        if (savedCustomer === '__manual__' && savedCustomerName) {
            document.getElementById('posCustomerInput').value = savedCustomerName;
            handleCustomerInput();
        } else if (savedCustomer && Array.from(document.getElementById('posCustomerSelect').options).some(opt => opt.value === savedCustomer)) {
            document.getElementById('posCustomerSelect').value = savedCustomer;
            handleCustomerSelect();
        } else if (savedCustomerName) {
            document.getElementById('posCustomerInput').value = savedCustomerName;
            handleCustomerInput();
        }
        if(savedPromo) {
            const select = document.getElementById('posPromoSelect');
            const option = Array.from(select.options).find(opt => opt.value === savedPromo && !opt.disabled);
            select.value = option ? savedPromo : '';
            document.getElementById('posPromoCodeInput').value = option ? (option.dataset.code || '') : '';
        }
        drawCart();
        renderPosStock();
        triggerSearch('', false);
        window.addEventListener('focus', refreshPosPromotions);
        window.addEventListener('focus', () => refreshPosStock().catch(error => console.error(error)));
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshPosStock().catch(error => console.error(error));
        });
        ['posCustomerSelect', 'posPromoSelect', 'posStoreSelect'].forEach(id => {
            document.getElementById(id).addEventListener('change', () => {
                calcTotal();
                markPendingCartChanged();
            });
        });
        const customerInputEl = document.getElementById('posCustomerInput');
        const customerSuggestionsEl = document.getElementById('posCustomerSuggestions');
        customerInputEl.addEventListener('input', markPendingCartChanged);
        customerInputEl.addEventListener('focus', updateCustomerSuggestions);
        customerInputEl.addEventListener('keydown', event => {
            const buttons = Array.from(customerSuggestionsEl.querySelectorAll('.pos-customer-suggestion'));
            if (customerSuggestionsEl.hidden || !buttons.length) {
                if (event.key === 'Escape') closeCustomerSuggestions();
                return;
            }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                activeCustomerSuggestionIndex = event.key === 'ArrowDown'
                    ? (activeCustomerSuggestionIndex + 1) % buttons.length
                    : (activeCustomerSuggestionIndex + buttons.length - 1) % buttons.length;
                buttons.forEach((button, index) => {
                    button.classList.toggle('is-active', index === activeCustomerSuggestionIndex);
                    button.setAttribute('aria-selected', String(index === activeCustomerSuggestionIndex));
                });
                buttons[activeCustomerSuggestionIndex].scrollIntoView({block: 'nearest'});
            } else if (event.key === 'Enter' && activeCustomerSuggestionIndex >= 0) {
                event.preventDefault();
                buttons[activeCustomerSuggestionIndex].click();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closeCustomerSuggestions();
            }
        });
        document.addEventListener('pointerdown', event => {
            if (!event.target.closest('.pos-customer-search')) closeCustomerSuggestions();
        });
        markPendingCartChanged();


        function initSidebarScroll() {
            const viewport = document.querySelector('.os-viewport') || document.querySelector('.sidebar');
            if (viewport) {
                const savedScroll = localStorage.getItem('ck_sidebar_scroll_master');
                if (savedScroll) {
                    viewport.scrollTop = parseInt(savedScroll, 10);
                }
                viewport.addEventListener('scroll', () => {
                    localStorage.setItem('ck_sidebar_scroll_master', viewport.scrollTop);
                });
            } else {
                setTimeout(initSidebarScroll, 100);
            }
        }
        initSidebarScroll();

        window.addEventListener('beforeunload', () => {
            const viewport = document.querySelector('.os-viewport') || document.querySelector('.sidebar');
            if (viewport) {
                localStorage.setItem('ck_sidebar_scroll_master', viewport.scrollTop);
            }
        });

        const currentUrl = window.location.href.split('?')[0].replace('#', '');
        document.querySelectorAll('aside a, .sidebar a, .nav-sidebar a').forEach(link => {
            const linkUrl = link.href.split('?')[0].replace('#', '');
            if (linkUrl === currentUrl || currentUrl.includes(link.getAttribute('href'))) {
                link.classList.add('active');
                link.style.setProperty('background', 'linear-gradient(135deg, #ee3124 0%, #ff4b3a 100%)', 'important');
                link.style.setProperty('color', '#ffffff', 'important');
                link.style.setProperty('border-radius', '16px', 'important');
                link.style.setProperty('box-shadow', '0 8px 24px rgba(238, 49, 36, 0.38)', 'important');
                link.style.setProperty('border', 'none', 'important');

                link.querySelectorAll('*').forEach(el => {
                    el.style.setProperty('color', '#ffffff', 'important');
                });

                const iconBox = link.querySelector('div, span, .nav-icon, .icon-wrap, .sidebar-icon');
                if (iconBox) {
                    iconBox.style.setProperty('background', 'rgba(255, 255, 255, 0.25)', 'important');
                    iconBox.style.setProperty('border-radius', '12px', 'important');
                }
            } else {
                link.classList.remove('active');
                link.style.background = '';
                link.style.boxShadow = '';
            }
        });

        document.querySelectorAll('aside a, .sidebar a').forEach(link => {
            if (link.href && link.href.includes('thanh_toan_don_hang')) {
                link.addEventListener('click', function(e) {
                    if (cart.length > 0) {
                        e.preventDefault();
                        goToCheckout(link.href.split('?')[0]);
                    } else if (currentPendingOrderId() || pendingCartSyncTimer !== null) {
                        e.preventDefault();
                        flushPendingCartSync().then(() => {
                            window.location.assign(link.href.split('?')[0]);
                        }).catch(error => alert('Lỗi đồng bộ giỏ hàng: ' + error.message));
                    }
                });
            }
        });
        window.addEventListener('storage', event => {
            if (event.key === 'ck_pos_cart_cleared' && cart.length > 0) {
                cart = [];
                removeManualCustomerOption();
                document.getElementById('posCustomerSelect').selectedIndex = 0;
                document.getElementById('posPromoSelect').selectedIndex = 0;
                document.getElementById('posCustomerInput').value = '';
                localStorage.removeItem('ck_pos_customer_name');
                document.getElementById('posPromoCodeInput').value = '';
                updateTierBadge('none');
                updatePosMemberPointsPreview();
                drawCart();
            }
        });
    });
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>