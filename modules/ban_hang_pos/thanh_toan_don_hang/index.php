<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

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
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}

try {
    $tenKhachColumn = $pdo->query("SHOW COLUMNS FROM hoadon LIKE 'ten_khach_nhap'")->fetch();
    if (!$tenKhachColumn) {
        $pdo->exec("ALTER TABLE hoadon ADD COLUMN ten_khach_nhap VARCHAR(100) NULL");
    }
} catch (PDOException $e) {
    die('Không thể cập nhật cột tên khách hàng: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}


try { $pdo->exec("ALTER TABLE thanhtoan ADD COLUMN tien_khach_dua DECIMAL(12,2) NULL"); } catch (Exception $e) { }
try { $pdo->exec("ALTER TABLE thanhtoan ADD COLUMN tien_thoi DECIMAL(12,2) NULL"); } catch (Exception $e) { }
try { $pdo->exec("ALTER TABLE khuyenmai ADD COLUMN so_luong_da_dung INT DEFAULT 0"); } catch (Exception $e) { }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_payment_order') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $hoadon_id = (int)($_POST['hoadon_id'] ?? 0);
        $sessionOrderId = (int)($_SESSION['ck_pos_active_order_id'] ?? 0);
        if ($hoadon_id <= 0 || $hoadon_id !== $sessionOrderId) {
            throw new RuntimeException('Đơn hàng không còn chờ thanh toán.');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id, cuahang_id, trang_thai FROM hoadon WHERE id = ? FOR UPDATE");
        $stmt->execute([$hoadon_id]);
        $activeOrder = $stmt->fetch();
        if (!$activeOrder || $activeOrder['trang_thai'] !== 'Chờ thanh toán') {
            throw new RuntimeException('Đơn hàng đã được thanh toán hoặc đã hủy.');
        }

        $stmtItems = $pdo->prepare("SELECT sanpham_id, so_luong FROM chitiethoadon WHERE hoadon_id = ?");
        $stmtItems->execute([$hoadon_id]);
        $restoreStock = $pdo->prepare("UPDATE tonkho SET so_luong = so_luong + ? WHERE sanpham_id = ? AND cuahang_id = ?");
        foreach ($stmtItems->fetchAll() as $item) {
            $restoreStock->execute([(int)$item['so_luong'], (int)$item['sanpham_id'], (int)$activeOrder['cuahang_id']]);
        }

        $stmtCancel = $pdo->prepare("UPDATE hoadon SET trang_thai = 'Đã hủy' WHERE id = ? AND trang_thai = 'Chờ thanh toán'");
        $stmtCancel->execute([$hoadon_id]);
        if ($stmtCancel->rowCount() !== 1) {
            throw new RuntimeException('Không thể hủy đơn hàng. Vui lòng thử lại.');
        }

        $pdo->commit();
        unset($_SESSION['ck_pos_active_order_id']);
        echo json_encode(['success' => true, 'message' => 'Đã hủy đơn hàng thành công.'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_payment') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $hoadon_id      = (int)($_POST['hoadon_id'] ?? 0);
        $phuong_thuc    = trim($_POST['phuong_thuc'] ?? 'Tiền mặt');
        $so_tien        = (float)($_POST['so_tien'] ?? 0);
        $tien_khach_dua = isset($_POST['tien_khach_dua']) ? (float)$_POST['tien_khach_dua'] : $so_tien;
        $tien_thoi      = isset($_POST['tien_thoi']) ? (float)$_POST['tien_thoi'] : 0;

        if ($hoadon_id <= 0 || $hoadon_id !== (int)($_SESSION['ck_pos_active_order_id'] ?? 0)) {
            echo json_encode(['success' => false, 'message' => 'Đơn hàng không còn chờ thanh toán.']);
            exit;
        }

        $pdo->beginTransaction();
        $stmtActive = $pdo->prepare("SELECT trang_thai, khach_phai_tra FROM hoadon WHERE id = ? FOR UPDATE");
        $stmtActive->execute([$hoadon_id]);
        $activeOrder = $stmtActive->fetch();
        if (!$activeOrder || $activeOrder['trang_thai'] !== 'Chờ thanh toán') {
            throw new RuntimeException('Đơn hàng đã được thanh toán hoặc đã hủy.');
        }
        $so_tien = (float)$activeOrder['khach_phai_tra'];
        if ($phuong_thuc === 'Tiền mặt' && $tien_khach_dua < $so_tien) {
            throw new RuntimeException('Số tiền khách đưa không đủ để thanh toán.');
        }
        $tien_thoi = $phuong_thuc === 'Tiền mặt' ? max(0, $tien_khach_dua - $so_tien) : 0;

        $stmtHd = $pdo->prepare("UPDATE hoadon SET trang_thai = 'Hoàn thành' WHERE id = ? AND trang_thai = 'Chờ thanh toán'");
        $stmtHd->execute([$hoadon_id]);

        $stmtCheck = $pdo->prepare("SELECT id FROM thanhtoan WHERE hoadon_id = ?");
        $stmtCheck->execute([$hoadon_id]);
        if ($stmtCheck->fetch()) {
            $stmtTt = $pdo->prepare("UPDATE thanhtoan SET phuong_thuc = ?, so_tien_thanh_toan = ?, tien_khach_dua = ?, tien_thoi = ?, thoi_gian_thanh_toan = NOW() WHERE hoadon_id = ?");
            $stmtTt->execute([$phuong_thuc, $so_tien, $tien_khach_dua, $tien_thoi, $hoadon_id]);
        } else {
            $stmtTt = $pdo->prepare("INSERT INTO thanhtoan (hoadon_id, phuong_thuc, so_tien_thanh_toan, thoi_gian_thanh_toan, tien_khach_dua, tien_thoi) VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmtTt->execute([$hoadon_id, $phuong_thuc, $so_tien, $tien_khach_dua, $tien_thoi]);
        }

        $stmtInfo = $pdo->prepare("SELECT khachhang_id, khuyenmai_id FROM hoadon WHERE id = ?");
        $stmtInfo->execute([$hoadon_id]);
        $hdInfo = $stmtInfo->fetch();

        if ($hdInfo && !empty($hdInfo['khachhang_id'])) {
            $pdo->prepare("UPDATE khachhang SET diem_tich_luy = diem_tich_luy + ? WHERE id = ?")
                ->execute([(int)floor($so_tien / 1000), $hdInfo['khachhang_id']]);
        }
        if ($hdInfo && !empty($hdInfo['khuyenmai_id'])) {
            try {
                $pdo->prepare("UPDATE khuyenmai SET so_luong = so_luong - 1, so_luong_da_dung = so_luong_da_dung + 1 WHERE id = ? AND so_luong IS NOT NULL AND so_luong > 0")
                    ->execute([$hdInfo['khuyenmai_id']]);
            } catch (Exception $ex) {
                $pdo->prepare("UPDATE khuyenmai SET so_luong = so_luong - 1 WHERE id = ? AND so_luong IS NOT NULL AND so_luong > 0")
                    ->execute([$hdInfo['khuyenmai_id']]);
            }
        }

        $pdo->commit();
        unset($_SESSION['ck_pos_active_order_id']);
        echo json_encode(['success' => true, 'message' => 'Thanh toán thành công!']);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$sessionOrderId = (int)($_SESSION['ck_pos_active_order_id'] ?? 0);
$requestedOrderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$hoadon_id = $sessionOrderId > 0 && ($requestedOrderId === 0 || $requestedOrderId === $sessionOrderId) ? $sessionOrderId : 0;
$order = false;
$items = [];

if ($hoadon_id > 0) {
    $stmt = $pdo->prepare("SELECT hd.*, ch.ten_cua_hang, ch.dia_chi AS dia_chi_ch, ch.so_dien_thoai AS sdt_ch, nv.ho_ten AS ten_nv, COALESCE(NULLIF(TRIM(hd.ten_khach_nhap), ''), kh.ho_ten) AS ten_kh, kh.so_dien_thoai AS sdt_kh, ttd.hang_the
                           FROM hoadon hd
                           LEFT JOIN cuahang ch ON hd.cuahang_id = ch.id
                           LEFT JOIN nhanvien nv ON hd.nhanvien_id = nv.id
                           LEFT JOIN khachhang kh ON hd.khachhang_id = kh.id
                           LEFT JOIN thetichdiem ttd ON kh.thetichdiem_id = ttd.id
                           WHERE hd.id = ? AND hd.trang_thai = 'Chờ thanh toán'");
    $stmt->execute([$hoadon_id]);
    $order = $stmt->fetch();
    if ($order) {
        $stmtItems = $pdo->prepare("SELECT ct.*, sp.ten_san_pham, sp.ma_san_pham, dm.ten_danh_muc
                                    FROM chitiethoadon ct
                                    JOIN sanpham sp ON ct.sanpham_id = sp.id
                                    LEFT JOIN danhmuc dm ON sp.danhmuc_id = dm.id
                                    WHERE ct.hoadon_id = ?");
        $stmtItems->execute([$hoadon_id]);
        $items = $stmtItems->fetchAll();
        if (!$items) $order = false;
    }
    if (!$order) unset($_SESSION['ck_pos_active_order_id']);
}

$orderCode  = $order ? '#HD' . (1024 + (int)$order['id']) : '';
$subtotal   = (float)($order['tong_tien'] ?? 0);
$discount   = (float)($order['tien_giam_gia'] ?? 0);
$grandTotal = (float)($order['khach_phai_tra'] ?? 0);
$vat        = max(0, $grandTotal - ($subtotal - $discount));

$pageTitle  = 'Thanh toán đơn hàng';
$activeMenu = 'thanhtoan';

require ROOT_PATH . '/includes/header.php';
?>

<style>
    .pay-opt-card { display: flex; align-items: center; gap: 16px; padding: 16px 20px; border: 1.5px solid var(--border-light); border-radius: 16px; margin-bottom: 12px; cursor: pointer; transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1); background: #fff; position: relative; }
    .pay-opt-card:hover { border-color: #cbd5e1; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.04); }
    .pay-opt-card.active { border-color: var(--ck-red); background: linear-gradient(180deg, rgba(238, 49, 36, 0.04), #fff); box-shadow: 0 8px 24px rgba(238, 49, 36, 0.08); }
    .pay-opt-card input[type="radio"] { accent-color: var(--ck-red); width: 20px; height: 20px; cursor: pointer; }
    .pay-icon-box { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .quick-tag { font-size: 0.7rem; font-weight: 700; padding: 3px 8px; border-radius: 99px; letter-spacing: 0.3px; }
    .quick-cash-chip { border: 1px solid var(--border-light); background: #fff; border-radius: 10px; padding: 8px 16px; font-size: 0.88rem; font-weight: 700; color: #334155; cursor: pointer; transition: all 0.15s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .quick-cash-chip:hover { background: #f1f5f9; border-color: #cbd5e1; transform: translateY(-1px); }
    .cash-mode-switch { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
    .cash-mode-switch .form-check-input { width: 2.35em; height: 1.2em; margin: 0; cursor: pointer; }
    .cash-mode-switch .form-check-input:checked { background-color: #ee3124; border-color: #ee3124; }
    .cash-mode-switch .form-check-label { cursor: pointer; font-weight: 700; font-size: .88rem; color: #334155; }
    .cash-real-amount { color: #047857; font-size: .82rem; font-weight: 700; margin-top: 8px; }

    
    .vietqr-card { background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 20px; padding: 24px; max-width: 380px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.06); }
    .qr-frame { position: relative; width: 220px; height: 220px; margin: 0 auto 16px; padding: 10px; background: #fff; border-radius: 16px; border: 2px solid #e2e8f0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .qr-copy-row { background: #f8fafc; border-radius: 10px; padding: 8px 12px; font-size: 0.82rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; border: 1px solid #f1f5f9; }
    .momo-card { background: linear-gradient(135deg, #a50064, #d82d8b); border-radius: 20px; padding: 24px; max-width: 380px; margin: 0 auto; color: #fff; box-shadow: 0 12px 32px rgba(165, 0, 100, 0.25); }
    .momo-qr-box { background: #fff; padding: 12px; border-radius: 16px; width: 210px; height: 210px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; }
    .card-emv-mockup { background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 20px; padding: 24px; max-width: 380px; margin: 0 auto; color: #fff; box-shadow: 0 14px 36px rgba(15, 23, 42, 0.3); position: relative; overflow: hidden; }
    .emv-chip { width: 44px; height: 32px; background: linear-gradient(135deg, #fbbf24, #d97706); border-radius: 6px; display: inline-block; }

    .receipt-print-box { background: #fff; border: 1px solid #cbd5e1; border-radius: 0px; padding: 20px; font-family: 'Courier New', Courier, monospace; color: #000; max-width: 360px; margin: 0 auto; box-shadow: 0 10px 25px rgba(0,0,0,0.1); font-size: 0.85rem; line-height: 1.4; }
    .receipt-print-box .dashed-line { border-bottom: 1px dashed #000; margin: 12px 0; }
    .receipt-print-box .brand-logo { font-family: 'Arial Black', Arial, sans-serif; font-size: 1.5rem; font-weight: 900; letter-spacing: -1px; margin-bottom: 5px; text-transform: uppercase; }
    

    .pay-cancel-dialog { width: min(440px, calc(100vw - 32px)); max-width: 100%; padding: 0; border: 0; border-radius: 22px; color: #1e293b; background: #ffffff; box-shadow: 0 24px 75px rgba(15, 23, 42, 0.23); overflow: visible; }
    .pay-cancel-dialog[open] { animation: pay-cancel-dialog-in .2s ease-out; }
    .pay-cancel-dialog::backdrop { background: rgba(15, 23, 42, .46); backdrop-filter: blur(3px); }
    @keyframes pay-cancel-dialog-in { from { opacity: 0; transform: translateY(12px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
    .pay-cancel-inner { position: relative; padding: 30px 30px 26px; text-align: center; }
    .pay-cancel-close { position: absolute; top: 13px; right: 14px; display: grid; place-items: center; width: 32px; height: 32px; border: 0; background: #f1f5f9; color: #64748b; border-radius: 10px; cursor: pointer; }
    .pay-cancel-close:hover { background: #e2e8f0; color: #334155; }
    .pay-cancel-icon { display: grid; place-items: center; width: 60px; height: 60px; margin: 0 auto 18px; border-radius: 18px; background: #fff1f2; color: #e11d48; font-size: 24px; }
    .pay-cancel-title { margin: 0 0 9px; font-size: 1.24rem; font-weight: 800; color: #172033; }
    .pay-cancel-message { margin: 0; color: #64748b; font-size: .93rem; line-height: 1.65; overflow-wrap: anywhere; }
    .pay-cancel-actions { display: flex; justify-content: center; gap: 10px; margin-top: 26px; }
    .pay-cancel-actions button { flex: 1; min-height: 43px; border-radius: 11px; padding: 9px 14px; font-size: .9rem; font-weight: 750; cursor: pointer; transition: transform .15s, background .15s; }
    .pay-cancel-actions button:hover { transform: translateY(-1px); }
    .pay-cancel-secondary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
    .pay-cancel-danger { background: linear-gradient(105deg, #e11d48, #fb573d); border: 1px solid transparent; color: white; }
    @media (max-width: 480px) { .pay-cancel-inner { padding: 28px 22px 22px; } }

    @media print {
        @page { margin: 0; size: 80mm auto; }
        body * { visibility: hidden; }
        .modal { position: absolute; left: 0; top: 0; margin: 0; padding: 0; min-height: 550px; visibility: visible; }
        .modal-dialog { margin: 0; padding: 0; max-width: 100%; width: 100%; }
        .modal-content { border: none; box-shadow: none; background: transparent; }
        .modal-backdrop, .modal-body > h4, .modal-body > .text-muted, .modal-body > .text-success, .modal-body > .d-flex.gap-2 { display: none !important; }
        #printableBill, #printableBill * { visibility: visible; color: #000 !important; }
        #printableBill { position: absolute; left: 0; top: 0; width: 78mm !important; max-width: 78mm !important; padding: 2mm !important; margin: 0 !important; border: none !important; box-shadow: none !important; font-size: 11px !important; }
        #printableBill .dashed-line { margin: 8px 0 !important; }
    }
</style>

<?php if (!$order): ?>
<div class="row g-4">
    <div class="col-12">
        <div class="bento-panel text-center py-5">
            <i class="fa-solid fa-cart-shopping text-muted opacity-25 mb-3" style="font-size: 4rem;"></i>
            <h4 class="fw-bold text-dark mb-2">Chưa có đơn hàng chờ thanh toán</h4>
            <p class="text-muted mb-4">Giỏ hàng hiện đang trống. Vui lòng thêm sản phẩm tại màn hình bán hàng và chọn thanh toán.</p>
            <a href="../man_hinh_ban_hang/index.php" class="btn-ck btn-ck-primary d-inline-flex align-items-center justify-content-center text-decoration-none px-4">
                <i class="fa-solid fa-cart-plus me-2"></i>Đến màn hình bán hàng
            </a>
        </div>
    </div>
</div>
<?php else: ?>
<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="bento-panel fade-up">
            <div class="panel-header mb-4">
                <div>
                    <h5 class="panel-title">
                        <span class="title-icon" style="background: linear-gradient(135deg, #0ea5e9, #6366f1)">
                            <i class="fa-solid fa-credit-card"></i>
                        </span>
                        Phương thức thanh toán
                    </h5>
                    <div class="panel-subtitle ms-5">Chọn cách khách trả tiền & nhận diện tự động</div>
                </div>
            </div>

            <div class="pay-method-list mb-4">
                <label class="pay-opt-card active" onclick="selectMethod('Tiền mặt', this)">
                    <input type="radio" name="pay_method" value="Tiền mặt" checked>
                    <div class="pay-icon-box" style="background: linear-gradient(135deg, #10b981, #059669); color: #fff;">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark">Tiền mặt</span>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-muted opacity-50"></i>
                </label>

                <label class="pay-opt-card" onclick="selectMethod('Thẻ ATM / Visa', this)">
                    <input type="radio" name="pay_method" value="Thẻ ATM / Visa">
                    <div class="pay-icon-box" style="background: linear-gradient(135deg, #0284c7, #2563eb); color: #fff;">
                        <i class="fa-solid fa-credit-card"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark">Thẻ ATM / Visa</span>
                        </div>
                        <div class="small text-muted">Quẹt thẻ hoặc chạm không dây qua máy POS ngân hàng</div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-muted opacity-50"></i>
                </label>

                <label class="pay-opt-card" onclick="selectMethod('VNPay QR', this)">
                    <input type="radio" name="pay_method" value="VNPay QR">
                    <div class="pay-icon-box" style="background: linear-gradient(135deg, #2563eb, #6366f1); color: #fff;">
                        <i class="fa-solid fa-qrcode"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark">VNPay QR / VietQR</span>
                        </div>
                        <div class="small text-muted">Khách quét mã QR để thanh toán qua Mobile Banking</div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-muted opacity-50"></i>
                </label>

                <label class="pay-opt-card" onclick="selectMethod('Ví MoMo', this)">
                    <input type="radio" name="pay_method" value="Ví MoMo">
                    <div class="pay-icon-box" style="background: linear-gradient(135deg, #a50064, #d82d8b); color: #fff;">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark">Ví MoMo</span>
                        </div>
                        <div class="small text-muted">Thanh toán tự động bằng ví điện tử MoMo</div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-muted opacity-50"></i>
                </label>
            </div>

            <div id="cashInputBlock" class="p-3 bg-light rounded-4 border">
                <div class="cash-mode-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="cashThousandMode" onchange="switchCashUnit()">
                    <label class="form-check-label" for="cashThousandMode">Nhập theo nghìn đồng (× 1.000)</label>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold text-muted" id="lblCustomerCash">TIỀN KHÁCH ĐƯA (VNĐ)</label>
                        <input type="number" min="0" step="1" inputmode="decimal" class="form-control form-control-lg num-font fw-bold" id="customerCash" placeholder="0" oninput="calculateChange()">
                        <div class="cash-real-amount" aria-live="polite">Số tiền thực: <span id="cashActualAmount">0 ₫</span></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold text-muted" id="lblChangeTitle">TIỀN THỐI LẠI (VNĐ)</label>
                        <input type="text" class="form-control form-control-lg num-font fw-bold text-primary bg-white" id="customerChange" value="0 ₫" readonly>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="quick-cash-chip" onclick="setCash(<?= $grandTotal ?>)">Đủ tiền (<?= number_format($grandTotal) ?> ₫)</button>
                    <button type="button" class="quick-cash-chip" onclick="setCash(50000)">50,000</button>
                    <button type="button" class="quick-cash-chip" onclick="setCash(100000)">100,000</button>
                    <button type="button" class="quick-cash-chip" onclick="setCash(200000)">200,000</button>
                    <button type="button" class="quick-cash-chip" onclick="setCash(500000)">500,000</button>
                </div>
            </div>

            <div id="vietqrBlock" class="text-center py-2" style="display: none;">
                <div class="vietqr-card">
                    <div class="vietqr-header">
                        <div class="text-start">
                            <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                <span class="badge bg-primary" style="font-size:0.75rem;">VietQR</span>
                                <span class="badge bg-danger" style="font-size:0.75rem;">Napas247</span>
                            </div>
                            <small class="text-muted" style="font-size:0.72rem;">Ngân hàng TMCP Quân Đội (MB Bank)</small>
                        </div>
                        <div class="d-flex align-items-center gap-1 text-success small fw-semibold">
                            <span class="spinner-grow spinner-grow-sm text-success"></span> Live
                        </div>
                    </div>

                    <div class="qr-frame">
                        <img src="https://img.vietqr.io/image/MB-02583520111-compact2.png?amount=<?= (int)$grandTotal ?>&addInfo=<?= urlencode($orderCode) ?>&accountName=CIRCLE%20K%20VIETNAM" 
                             alt="VietQR Code" style="width:100%; height:100%; object-fit:contain;"
                             onerror="this.onerror=null; this.src='https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=CIRCLEK_VNPAY_<?= (int)$grandTotal ?>';">
                    </div>
                    <div class="qr-copy-row bg-white border-0 pt-2">
                        <span class="fw-semibold text-muted">Số tiền thanh toán:</span>
                        <strong class="num-font text-danger fs-5"><?= number_format($grandTotal) ?> ₫</strong>
                    </div>
                </div>
            </div>

            <div id="momoBlock" class="text-center py-2" style="display: none;">
                <div class="momo-card">
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom border-white border-opacity-25">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-wallet fs-4"></i>
                            <div class="text-start">
                                <div class="fw-bold fs-6">MoMo Pay</div>
                                <div style="font-size:0.72rem; opacity:0.85;">Ví điện tử số 1 Việt Nam</div>
                            </div>
                        </div>
                    </div>
                    <div class="momo-qr-box">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=2|99|0905123456|CircleK|NhaTrang|0|0|<?= (int)$grandTotal ?>|<?= urlencode($orderCode) ?>|transfer_p2p" 
                             alt="MoMo QR Code" style="width:100%; height:100%; object-fit:contain;">
                    </div>
                    <div class="fw-bold fs-4 num-font mb-1"><?= number_format($grandTotal) ?> ₫</div>
                </div>
            </div>

            <div id="cardSwipeBlock" class="text-center py-2" style="display: none;">
                <div class="card-emv-mockup text-start mb-3">
                    <div class="emv-chip mb-3"></div>
                    <div class="font-monospace fs-4 tracking-widest mb-3">•••• •••• •••• 8868</div>
                    <div class="d-flex justify-content-between align-items-end">
                        <div>
                            <small class="text-white-50 text-uppercase d-block" style="font-size:0.65rem;">Card Holder</small>
                            <span class="fw-bold font-monospace">CIRCLE K POS TERMINAL</span>
                        </div>
                    </div>
                </div>
                <div class="p-3 bg-light rounded-4 border text-center">
                    <div class="fw-bold text-dark fs-6">Vui lòng cắm hoặc chạm thẻ vào máy POS</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="bento-panel fade-up d-1">
            <div class="panel-header mb-3">
                <div>
                    <h5 class="panel-title">
                        <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), var(--ck-orange))">
                            <i class="fa-solid fa-file-invoice"></i>
                        </span>
                        Tóm tắt đơn hàng
                    </h5>
                    <div class="panel-subtitle ms-5">Kiểm tra lại trước khi hoàn tất</div>
                </div>
                <span class="order-code fs-6"><?= htmlspecialchars($orderCode) ?></span>
            </div>

            <div class="p-2 bg-light rounded-3 border mb-3 small d-flex justify-content-between align-items-center">
                <div>
                    <i class="fa-solid fa-store text-danger me-1"></i>
                    <strong><?= htmlspecialchars($order['ten_cua_hang'] ?? 'Circle K - Trần Phú') ?></strong>
                </div>
                <div class="text-muted">
                    <i class="fa-solid fa-user-circle me-1"></i><?= htmlspecialchars($order['ten_nv'] ?? 'Thu ngân') ?>
                </div>
            </div>

            <div class="small text-muted mb-2"><i class="fa-solid fa-user me-1"></i>Khách hàng: <strong class="text-dark"><?= htmlspecialchars($order['ten_kh'] ?: 'Khách lẻ (Vãng lai)', ENT_QUOTES, 'UTF-8') ?></strong></div>

            <div class="order-items-list mb-3" style="max-height: 290px; overflow-y: auto; padding-right: 4px;">
                <?php foreach ($items as $it): 
                    $skuImg = !empty($it['ma_san_pham']) ? $it['ma_san_pham'] : 'SP001';
                ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div class="d-flex align-items-center gap-3">
                        <img src="../../../assets/images/<?= $skuImg ?>.jpg" 
                             style="width: 44px; height: 44px; min-width: 44px; object-fit: cover; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc;" 
                             onerror="this.src='../../../assets/images/SP001.jpg'; this.onerror=null;">
                        <div>
                            <div class="fw-semibold text-dark" style="font-size: 0.9rem; line-height: 1.2; margin-bottom: 2px;"><?= htmlspecialchars($it['ten_san_pham']) ?></div>
                            <small class="text-muted"><?= $it['so_luong'] ?> × <?= number_format($it['don_gia']) ?> ₫</small>
                        </div>
                    </div>
                    <span class="num-font fw-bold text-dark"><?= number_format($it['thanh_tien']) ?> ₫</span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="pt-2 border-top">
                <div class="d-flex justify-content-between mb-2 small text-muted">
                    <span>Tạm tính</span>
                    <span class="num-font fw-bold text-dark"><?= number_format($subtotal) ?> ₫</span>
                </div>
                <div class="d-flex justify-content-between mb-2 small text-success">
                    <span>Khuyến mãi & Chiết khấu</span>
                    <span class="num-font fw-bold">- <?= number_format($discount) ?> ₫</span>
                </div>
                <div class="d-flex justify-content-between mb-2 small text-muted">
                    <span>VAT (8%)</span>
                    <span class="num-font fw-bold text-dark"><?= number_format($vat) ?> ₫</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                    <span class="fw-bold fs-6">Cần thanh toán</span>
                    <span class="num-font fw-bold" style="font-size: 1.85rem; color: var(--ck-red);" id="txtRightGrandTotal">
                        <?= number_format($grandTotal) ?> ₫
                    </span>
                </div>
            </div>

            <button type="button" class="btn-ck btn-ck-primary w-100 mt-4 py-2 fs-6" id="btnConfirmPay" onclick="submitPayment()">
                <i class="fa-solid fa-circle-check me-2"></i>Xác nhận thanh toán
            </button>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <a href="../man_hinh_ban_hang/index.php" class="btn-ck btn-ck-soft text-center text-decoration-none flex-grow-1">
                    <i class="fa-solid fa-arrow-left me-1"></i>Quay lại giỏ hàng
                </a>
                <button type="button" class="btn-ck btn-ck-soft flex-grow-1" id="btnCancelOrder" onclick="showCancelOrderDialog()" style="color: #dc2626; border: 1px solid #fecaca; background: #fff7f7;">
                    <i class="fa-solid fa-trash-can me-1"></i>Hủy đơn hàng
                </button>
            </div>
        </div>
    </div>
</div>

<dialog class="pay-cancel-dialog" id="payCancelDialog" aria-labelledby="payCancelTitle" aria-describedby="payCancelMessage">
    <div class="pay-cancel-inner">
        <button type="button" class="pay-cancel-close" onclick="closeCancelOrderDialog()" aria-label="Đóng"><i class="fa-solid fa-xmark"></i></button>
        <div class="pay-cancel-icon"><i class="fa-solid fa-trash-can"></i></div>
        <h4 class="pay-cancel-title" id="payCancelTitle">Hủy đơn hàng <?= htmlspecialchars($orderCode) ?>?</h4>
        <p class="pay-cancel-message" id="payCancelMessage">Đơn hàng sẽ bị hủy, sản phẩm được hoàn lại kho và giỏ hàng sẽ được xóa.</p>
        <div class="pay-cancel-actions">
            <button type="button" class="pay-cancel-secondary" onclick="closeCancelOrderDialog()">Giữ đơn hàng</button>
            <button type="button" class="pay-cancel-danger" onclick="confirmCancelOrderDialog()">Hủy đơn hàng</button>
        </div>
    </div>
</dialog>

<div class="modal fade" id="modalPaymentSuccess" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.15);">
            <div class="modal-body p-4 text-center">
                <div class="mb-3 text-success">
                    <i class="fa-solid fa-circle-check" style="font-size: 4.5rem;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1">Giao dịch thành công!</h4>
                <div class="text-muted small mb-4">Đơn hàng <strong class="text-danger"><?= htmlspecialchars($orderCode) ?></strong> đã được hoàn tất.</div>

                <div class="receipt-print-box text-start mb-4" id="printableBill">
                    <div class="text-center">
                        <div class="brand-logo">CIRCLE K</div>
                        <div class="fw-bold">Tiện lợi 24/7</div>
                        <div class="dashed-line"></div>
                        <div class="fw-bold"><?= htmlspecialchars($order['ten_cua_hang'] ?? 'Circle K Vietnam') ?></div>
                        <div><?= htmlspecialchars($order['dia_chi_ch'] ?? 'Nha Trang') ?></div>
                        <div>Điện thoại: <?= htmlspecialchars($order['sdt_ch'] ?? '1900 3110') ?></div>
                    </div>
                    
                    <div class="dashed-line"></div>

                    <div class="d-flex justify-content-between mb-1">
                        <span>Ngày: <?= date('d/m/Y H:i', strtotime($order['ngay_tao'])) ?></span>
                        <span>Số: <b><?= htmlspecialchars($orderCode) ?></b></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-1">
                        <span>Thu ngân:</span>
                        <span><?= htmlspecialchars($order['ten_nv'] ?? '') ?></span>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span>Khách hàng:</span>
                        <span>
                            <?php if (!empty($order['hang_the'])): ?>[<?= htmlspecialchars($order['hang_the']) ?>] <?php endif; ?>
                            <?= htmlspecialchars($order['ten_kh'] ?? 'Khách vãng lai') ?>
                        </span>
                    </div>

                    <div class="dashed-line"></div>

                    <div class="fw-bold d-flex justify-content-between mb-1">
                        <span style="width: 50%">Tên Món</span>
                        <span style="width: 15%" class="text-center">SL</span>
                        <span style="width: 35%" class="text-end">T.Tiền</span>
                    </div>
                    
                    <?php foreach ($items as $it): ?>
                    <div class="mb-2">
                        <div><?= htmlspecialchars($it['ten_san_pham']) ?></div>
                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.8rem;">
                            <span style="width: 50%"><?= number_format($it['don_gia']) ?></span>
                            <span style="width: 15%" class="text-center">x<?= $it['so_luong'] ?></span>
                            <span style="width: 35%" class="text-end text-dark fw-bold"><?= number_format($it['thanh_tien']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="dashed-line"></div>

                    <div class="d-flex justify-content-between mb-1">
                        <span>Cộng tiền hàng:</span>
                        <span><?= number_format($subtotal) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Khuyến mãi / Chiết khấu:</span>
                        <span>- <?= number_format($discount) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Tiền thuế GTGT (8%):</span>
                        <span><?= number_format($vat) ?></span>
                    </div>
                    
                    <div class="dashed-line"></div>
                    
                    <div class="d-flex justify-content-between fw-bold fs-6 mb-2">
                        <span>TỔNG THANH TOÁN:</span>
                        <span class="fs-5"><?= number_format($grandTotal) ?> ₫</span>
                    </div>

                    <div class="dashed-line"></div>

                    <div class="d-flex justify-content-between mb-1">
                        <span>Hình thức:</span>
                        <span class="fw-bold text-uppercase" id="txtConfirmedMethod">Tiền mặt</span>
                    </div>
                    
                    <div id="rcpCashDetails" style="display:none;">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Khách đưa:</span>
                            <span class="fw-bold" id="rcpGiven">0 ₫</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Tiền thối lại:</span>
                            <span class="fw-bold" id="rcpChange">0 ₫</span>
                        </div>
                    </div>

                    <div class="dashed-line"></div>

                    <div class="text-center mt-3">
                        <div class="mb-2">
                            <img src="https://barcode.tec-it.com/barcode.ashx?data=<?= urlencode(str_replace('#', '', $orderCode)) ?>&code=Code128&dpi=96" alt="Barcode" style="max-width: 90%; height: 45px;">
                        </div>
                        <div class="fw-bold mt-2">CẢM ƠN QUÝ KHÁCH!</div>
                        <div>Hẹn gặp lại</div>
                        <div class="mt-1" style="font-size: 0.75rem; font-style: italic;">(Mật khẩu Wifi: circlek123)</div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn-ck btn-ck-soft flex-grow-1" onclick="window.print()">
                        <i class="fa-solid fa-print me-1"></i> In hóa đơn
                    </button>
                    <a href="../man_hinh_ban_hang/index.php?clear=1" class="btn-ck btn-ck-primary flex-grow-1 text-decoration-none">
                        <i class="fa-solid fa-plus me-1"></i> Bán đơn mới
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const grandTotal = <?= (float)$grandTotal ?>;
    let selectedMethod = 'Tiền mặt';
    const fVND = n => Math.round(n).toLocaleString() + ' ₫';

    function selectMethod(method, cardEl) {
        selectedMethod = method;
        document.querySelectorAll('.pay-opt-card').forEach(el => el.classList.remove('active'));
        cardEl.classList.add('active');
        cardEl.querySelector('input[type="radio"]').checked = true;

        const cashBlock = document.getElementById('cashInputBlock');
        const vnpayBlock = document.getElementById('vietqrBlock');
        const momoBlock = document.getElementById('momoBlock');
        const cardBlock = document.getElementById('cardSwipeBlock');

        cashBlock.style.display  = 'none';
        vnpayBlock.style.display = 'none';
        momoBlock.style.display  = 'none';
        cardBlock.style.display  = 'none';

        const btn = document.getElementById('btnConfirmPay');

        if (method === 'Tiền mặt') {
            cashBlock.style.display = '';
            calculateChange();
        } else if (method === 'VNPay QR') {
            vnpayBlock.style.display = '';
            btn.disabled = false;
        } else if (method === 'Ví MoMo') {
            momoBlock.style.display = '';
            btn.disabled = false;
        } else if (method === 'Thẻ ATM / Visa') {
            cardBlock.style.display = '';
            btn.disabled = false;
        }
    }

    function readCashVND(thousandMode = document.getElementById('cashThousandMode').checked) {
        const value = document.getElementById('customerCash').value;
        if (value === '') return 0;
        const amount = Number(value);
        if (!Number.isFinite(amount) || amount < 0) return 0;
        return Math.round(amount * (thousandMode ? 1000 : 1));
    }

    function switchCashUnit() {
        const thousandMode = document.getElementById('cashThousandMode').checked;
        const amountInVND = readCashVND(!thousandMode);
        const input = document.getElementById('customerCash');
        input.value = thousandMode ? String(Number((amountInVND / 1000).toFixed(3))) : String(amountInVND);
        input.step = thousandMode ? '0.001' : '1';
        input.placeholder = thousandMode ? 'VD: 15 = 15.000 ₫' : '0';
        document.getElementById('lblCustomerCash').textContent = thousandMode ? 'TIỀN KHÁCH ĐƯA (NGHÌN ĐỒNG)' : 'TIỀN KHÁCH ĐƯA (VNĐ)';
        calculateChange();
    }

    function calculateChange() {
        if (selectedMethod !== 'Tiền mặt') return;
        const cashVal = readCashVND();
        document.getElementById('cashActualAmount').textContent = fVND(cashVal);
        const diff = cashVal - grandTotal;
        const lblTitle = document.getElementById('lblChangeTitle');
        const inpChange = document.getElementById('customerChange');
        const btn = document.getElementById('btnConfirmPay');

        if (diff < 0) {
            lblTitle.textContent = 'CÒN THIẾU (VNĐ)';
            lblTitle.className = 'form-label small fw-bold text-danger';
            inpChange.value = fVND(Math.abs(diff));
            inpChange.className = 'form-control form-control-lg num-font fw-bold text-danger bg-white';
            btn.disabled = true;
        } else {
            lblTitle.textContent = 'TIỀN THỐI LẠI (VNĐ)';
            lblTitle.className = 'form-label small fw-bold text-muted';
            inpChange.value = fVND(diff);
            inpChange.className = 'form-control form-control-lg num-font fw-bold text-primary bg-white';
            btn.disabled = false;
        }
    }

    function setCash(val) {
        document.getElementById('customerCash').value = document.getElementById('cashThousandMode').checked ? String(val / 1000) : String(val);
        calculateChange();
    }

    function showCancelOrderDialog() {
        const dialog = document.getElementById('payCancelDialog');
        if (dialog && !dialog.open) dialog.showModal();
    }

    function closeCancelOrderDialog() {
        const dialog = document.getElementById('payCancelDialog');
        if (dialog && dialog.open) dialog.close();
    }

    function confirmCancelOrderDialog() {
        closeCancelOrderDialog();
        cancelPendingOrder();
    }

    async function cancelPendingOrder() {

        const cancelBtn = document.getElementById('btnCancelOrder');
        const payBtn = document.getElementById('btnConfirmPay');
        const payWasDisabled = payBtn.disabled;
        cancelBtn.disabled = true;
        payBtn.disabled = true;
        cancelBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Đang hủy...';

        try {
            const fd = new FormData();
            fd.append('action', 'cancel_payment_order');
            fd.append('hoadon_id', <?= (int)$order['id'] ?>);
            const response = await fetch(window.location.pathname, {method: 'POST', body: fd});
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Không thể hủy đơn hàng.');

            localStorage.removeItem('ck_pos_cart');
            localStorage.removeItem('ck_pos_cus');
            localStorage.removeItem('ck_pos_customer_name');
            localStorage.removeItem('ck_pos_promo');
            localStorage.removeItem('ck_pos_pending_order');
            localStorage.setItem('ck_pos_cart_cleared', String(Date.now()));
            window.location.replace(window.location.pathname);
        } catch (err) {
            alert('Lỗi: ' + err.message);
            cancelBtn.disabled = false;
            payBtn.disabled = payWasDisabled;
            cancelBtn.innerHTML = '<i class="fa-solid fa-trash-can me-1"></i>Hủy đơn hàng';
        }
    }

    function submitPayment() {
        const givenCash = (selectedMethod === 'Tiền mặt') ? readCashVND() : grandTotal;
        if (selectedMethod === 'Tiền mặt' && givenCash < grandTotal) {
            calculateChange();
            document.getElementById('customerCash').focus();
            return;
        }
        const changeCash = (selectedMethod === 'Tiền mặt') ? Math.max(0, givenCash - grandTotal) : 0;
        const btn = document.getElementById('btnConfirmPay');
        const cancelBtn = document.getElementById('btnCancelOrder');
        btn.disabled = true;
        cancelBtn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Đang ghi nhận...';

        const fd = new FormData();
        fd.append('action', 'confirm_payment');
        fd.append('hoadon_id', <?= (int)$order['id'] ?>);
        fd.append('phuong_thuc', selectedMethod);
        fd.append('so_tien', grandTotal);
        fd.append('tien_khach_dua', givenCash);
        fd.append('tien_thoi', changeCash);

        fetch(window.location.href, {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            cancelBtn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-circle-check me-2"></i>Xác nhận thanh toán';
            if (data.success) {
                localStorage.removeItem('ck_pos_cart');
                localStorage.removeItem('ck_pos_cus');
            localStorage.removeItem('ck_pos_customer_name');
                localStorage.removeItem('ck_pos_promo');
                localStorage.removeItem('ck_pos_pending_order');
                localStorage.setItem('ck_pos_cart_cleared', String(Date.now()));
                document.getElementById('txtConfirmedMethod').textContent = selectedMethod;
                const rcpCashBox = document.getElementById('rcpCashDetails');
                if (selectedMethod === 'Tiền mặt') {
                    rcpCashBox.style.display = 'block';
                    document.getElementById('rcpGiven').textContent = fVND(givenCash);
                    document.getElementById('rcpChange').textContent = fVND(changeCash);
                } else {
                    rcpCashBox.style.display = 'none';
                }
                new bootstrap.Modal(document.getElementById('modalPaymentSuccess')).show();
            } else {
                alert('Lỗi: ' + data.message);
            }
        })
        .catch(err => {
            btn.disabled = false;
            cancelBtn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-circle-check me-2"></i>Xác nhận thanh toán';
            alert('Lỗi kết nối máy chủ: ' + err);
        });
    }

    window.addEventListener('storage', event => {
        if (event.key === 'ck_pos_cart_cleared' || event.key === 'ck_pos_order_updated') window.location.reload();
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) window.location.reload();
    });

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('customerCash').value = grandTotal;
        calculateChange();

        document.querySelectorAll('aside a, .sidebar a, nav a, .nav-link').forEach(link => {
            const href = link.getAttribute('href') || '';
            if (href.includes('thanh_toan_don_hang') || href.includes('thanh_toan')) {
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
            }
        });
    });
</script>
<?php endif; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>