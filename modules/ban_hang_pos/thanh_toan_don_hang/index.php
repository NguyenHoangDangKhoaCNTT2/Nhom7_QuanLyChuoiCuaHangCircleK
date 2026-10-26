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
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_payment') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $hoadon_id   = (int)($_POST['hoadon_id'] ?? 0);
        $phuong_thuc = trim($_POST['phuong_thuc'] ?? 'Tiền mặt');
        $so_tien     = (float)($_POST['so_tien'] ?? 0);

        if ($hoadon_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Mã hóa đơn không hợp lệ']);
            exit;
        }

        $pdo->beginTransaction();

        $stmtHd = $pdo->prepare("UPDATE hoadon SET trang_thai = 'Hoàn thành' WHERE id = ?");
        $stmtHd->execute([$hoadon_id]);

        $stmtCheck = $pdo->prepare("SELECT id FROM thanhtoan WHERE hoadon_id = ?");
        $stmtCheck->execute([$hoadon_id]);
        if ($stmtCheck->fetch()) {
            $stmtTt = $pdo->prepare("UPDATE thanhtoan SET phuong_thuc = ?, so_tien_thanh_toan = ?, thoi_gian_thanh_toan = NOW() WHERE hoadon_id = ?");
            $stmtTt->execute([$phuong_thuc, $so_tien, $hoadon_id]);
        } else {
            $stmtTt = $pdo->prepare("INSERT INTO thanhtoan (hoadon_id, phuong_thuc, so_tien_thanh_toan, thoi_gian_thanh_toan) VALUES (?, ?, ?, NOW())");
            $stmtTt->execute([$hoadon_id, $phuong_thuc, $so_tien]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Thanh toán thành công!']);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$hoadon_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($hoadon_id > 0) {
    $stmt = $pdo->prepare("SELECT hd.*, ch.ten_cua_hang, ch.dia_chi AS dia_chi_ch, nv.ho_ten AS ten_nv, kh.ho_ten AS ten_kh 
                           FROM hoadon hd 
                           LEFT JOIN cuahang ch ON hd.cuahang_id = ch.id 
                           LEFT JOIN nhanvien nv ON hd.nhanvien_id = nv.id 
                           LEFT JOIN khachhang kh ON hd.khachhang_id = kh.id 
                           WHERE hd.id = ?");
    $stmt->execute([$hoadon_id]);
    $order = $stmt->fetch();
} else {
    $order = $pdo->query("SELECT hd.*, ch.ten_cua_hang, ch.dia_chi AS dia_chi_ch, nv.ho_ten AS ten_nv, kh.ho_ten AS ten_kh 
                          FROM hoadon hd 
                          LEFT JOIN cuahang ch ON hd.cuahang_id = ch.id 
                          LEFT JOIN nhanvien nv ON hd.nhanvien_id = nv.id 
                          LEFT JOIN khachhang kh ON hd.khachhang_id = kh.id 
                          ORDER BY hd.id DESC LIMIT 1")->fetch();
}

$items = [];
if ($order) {
    $stmtItems = $pdo->prepare("SELECT ct.*, sp.ten_san_pham, sp.ma_san_pham 
                                FROM chitiethoadon ct 
                                JOIN sanpham sp ON ct.sanpham_id = sp.id 
                                WHERE ct.hoadon_id = ?");
    $stmtItems->execute([$order['id']]);
    $items = $stmtItems->fetchAll();
}

if (!$order) {
    $order = [
        'id' => 1,
        'tong_tien' => 72000,
        'tien_giam_gia' => 0,
        'khach_phai_tra' => 77760,
        'ten_cua_hang' => 'Circle K - Trần Phú',
        'dia_chi_ch' => '76 Trần Phú, Lộc Thọ, Nha Trang',
        'ten_nv' => 'Nguyễn Phương Nam',
        'ten_kh' => 'Khách lẻ',
        'ngay_tao' => date('Y-m-d H:i:s')
    ];
    $items = [
        ['ten_san_pham' => 'Cà phê sữa đá lon', 'so_luong' => 2, 'don_gia' => 30000, 'thanh_tien' => 60000],
        ['ten_san_pham' => 'Mì Hảo Hảo ly', 'so_luong' => 1, 'don_gia' => 12000, 'thanh_tien' => 12000],
    ];
}

$orderCode  = '#HD' . (1024 + (int)$order['id']);
$subtotal   = (float)($order['tong_tien'] ?? 0);
$discount   = (float)($order['tien_giam_gia'] ?? 0);
$grandTotal = (float)($order['khach_phai_tra'] ?? 0);
$vat        = max(0, $grandTotal - ($subtotal - $discount));

$pageTitle  = 'Thanh toán đơn hàng';
$activeMenu = 'thanhtoan';

require ROOT_PATH . '/includes/header.php';
?>

<style>
    .pay-opt-card {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px 20px;
        border: 1.5px solid var(--border-light);
        border-radius: 14px;
        margin-bottom: 12px;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        background: #fff;
    }
    .pay-opt-card:hover {
        border-color: #cbd5e1;
        transform: translateY(-2px);
    }
    .pay-opt-card.active {
        border-color: var(--ck-red);
        background: rgba(238, 49, 36, 0.03);
    }
    .pay-opt-card input[type="radio"] {
        accent-color: var(--ck-red);
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    .pay-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .quick-cash-chip {
        border: 1px solid var(--border-light);
        background: #f8fafc;
        border-radius: 8px;
        padding: 6px 14px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
    }
    .quick-cash-chip:hover {
        background: #e2e8f0;
    }
    .receipt-print-box {
        background: #fff;
        border: 1px dashed #cbd5e1;
        border-radius: 14px;
        padding: 24px;
        font-family: 'Courier New', Courier, monospace;
        color: #0f172a;
        max-width: 400px;
        margin: 0 auto;
    }
    @media print {
        body * { visibility: hidden; }
        #printableBill, #printableBill * { visibility: visible; }
        #printableBill { position: absolute; left: 0; top: 0; width: 100%; border: none; }
    }
</style>

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
                    <div class="panel-subtitle ms-5">Chọn cách khách trả tiền</div>
                </div>
            </div>

            <div class="pay-method-list mb-4">
                <label class="pay-opt-card active" onclick="selectMethod('Tiền mặt', this)">
                    <input type="radio" name="pay_method" value="Tiền mặt" checked>
                    <div class="pay-icon-box" style="background: #10b981; color: #fff;">
                        <i class="fa-solid fa-money-bill-1-wave"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-dark">Tiền mặt</div>
                        <div class="small text-muted">Nhập số tiền khách đưa, hệ thống tính tiền thối</div>
                    </div>
                </label>

                <label class="pay-opt-card" onclick="selectMethod('Thẻ ATM / Visa', this)">
                    <input type="radio" name="pay_method" value="Thẻ ATM / Visa">
                    <div class="pay-icon-box" style="background: #0ea5e9; color: #fff;">
                        <i class="fa-solid fa-credit-card"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-dark">Thẻ ATM / Visa</div>
                        <div class="small text-muted">Quẹt thẻ hoặc chạm không dây qua máy POS ngân hàng</div>
                    </div>
                </label>

                <label class="pay-opt-card" onclick="selectMethod('VNPay QR', this)">
                    <input type="radio" name="pay_method" value="VNPay QR">
                    <div class="pay-icon-box" style="background: #6366f1; color: #fff;">
                        <i class="fa-solid fa-qrcode"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-dark">VNPay QR</div>
                        <div class="small text-muted">Khách quét mã QR để thanh toán qua Mobile Banking</div>
                    </div>
                </label>

                <label class="pay-opt-card" onclick="selectMethod('Ví MoMo', this)">
                    <input type="radio" name="pay_method" value="Ví MoMo">
                    <div class="pay-icon-box" style="background: #d946ef; color: #fff;">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-dark">Ví MoMo</div>
                        <div class="small text-muted">Thanh toán tự động bằng ví điện tử MoMo</div>
                    </div>
                </label>
            </div>

            <div id="cashInputBlock" class="p-3 bg-light rounded-3 border">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold text-muted">Tiền khách đưa (đ)</label>
                        <input type="number" class="form-control form-control-lg num-font fw-bold" id="customerCash" placeholder="0" oninput="calculateChange()">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold text-muted">Tiền thối lại (đ)</label>
                        <input type="text" class="form-control form-control-lg num-font fw-bold text-primary bg-white" id="customerChange" value="0" readonly>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="quick-cash-chip" onclick="setCash(<?= $grandTotal ?>)">Đủ tiền</button>
                    <button type="button" class="quick-cash-chip" onclick="setCash(100000)">100,000</button>
                    <button type="button" class="quick-cash-chip" onclick="setCash(200000)">200,000</button>
                    <button type="button" class="quick-cash-chip" onclick="setCash(500000)">500,000</button>
                </div>
            </div>

            <div id="qrScanBlock" class="p-4 bg-light rounded-3 border text-center" style="display: none;">
                <div class="p-3 bg-white d-inline-block rounded-3 border shadow-sm mb-2">
                    <i class="fa-solid fa-qrcode" style="font-size: 6rem; color: #1e293b;"></i>
                </div>
                <div class="fw-bold text-dark mt-2" id="qrTitleTxt">Quét mã QR để thanh toán</div>
                <div class="small text-muted">Số tiền cần thanh toán: <strong class="text-danger num-font"><?= number_format($grandTotal) ?> ₫</strong></div>
            </div>

            <div id="cardSwipeBlock" class="p-4 bg-light rounded-3 border text-center" style="display: none;">
                <div class="p-3 bg-white d-inline-block rounded-3 border shadow-sm mb-2">
                    <i class="fa-solid fa-credit-card text-primary" style="font-size: 5rem;"></i>
                </div>
                <div class="fw-bold text-dark mt-2">Vui lòng cắm hoặc chạm thẻ vào thiết bị POS</div>
                <div class="small text-muted">Đang chờ tín hiệu giao dịch từ thiết bị quẹt thẻ...</div>
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
                    <div class="panel-subtitle ms-5">Kiểm tra lại trước khi thanh toán</div>
                </div>
                <span class="order-code fs-6"><?= htmlspecialchars($orderCode) ?></span>
            </div>

            <div class="order-items-list mb-3" style="max-height: 280px; overflow-y: auto;">
                <?php foreach ($items as $it): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <div class="fw-semibold text-dark" style="font-size: 0.9rem;"><?= htmlspecialchars($it['ten_san_pham']) ?></div>
                        <small class="text-muted"><?= $it['so_luong'] ?> × <?= number_format($it['don_gia']) ?> ₫</small>
                    </div>
                    <span class="num-font fw-bold text-dark"><?= number_format($it['thanh_tien']) ?> ₫</span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="pt-2">
                <div class="d-flex justify-content-between mb-2 small text-muted">
                    <span>Tạm tính</span>
                    <span class="num-font fw-bold text-dark"><?= number_format($subtotal) ?> ₫</span>
                </div>
                <div class="d-flex justify-content-between mb-2 small text-success">
                    <span>Khuyến mãi</span>
                    <span class="num-font fw-bold">- <?= number_format($discount) ?> ₫</span>
                </div>
                <div class="d-flex justify-content-between mb-2 small text-muted">
                    <span>VAT 8%</span>
                    <span class="num-font fw-bold text-dark"><?= number_format($vat) ?> ₫</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                    <span class="fw-bold fs-6">Cần thanh toán</span>
                    <span class="num-font fw-bold" style="font-size: 1.8rem; color: var(--ck-red);">
                        <?= number_format($grandTotal) ?> ₫
                    </span>
                </div>
            </div>

            <button type="button" class="btn-ck btn-ck-primary w-100 mt-4 py-2 fs-6" id="btnConfirmPay" onclick="submitPayment()">
                <i class="fa-solid fa-circle-check me-2"></i>Xác nhận thanh toán
            </button>
            <a href="../man_hinh_ban_hang/index.php" class="btn-ck btn-ck-soft w-100 mt-2 text-center text-decoration-none">
                Quay lại giỏ hàng
            </a>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPaymentSuccess" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 18px; border: none;">
            <div class="modal-body p-4 text-center" id="stepSuccessView">
                <div class="mb-3 text-success">
                    <i class="fa-solid fa-circle-check" style="font-size: 4rem;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1">Thanh toán hoàn tất!</h4>
                <div class="text-muted small mb-4">Đơn hàng <strong class="text-danger"><?= htmlspecialchars($orderCode) ?></strong> đã được cập nhật thành công vào hệ thống.</div>

                <div class="receipt-print-box text-start mb-4" id="printableBill">
                    <div class="text-center border-bottom pb-3 mb-3">
                        <div class="fw-bold fs-5">CIRCLE K VIETNAM</div>
                        <div class="small fw-semibold mt-1"><?= htmlspecialchars($order['ten_cua_hang'] ?? 'Circle K - Trần Phú') ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($order['dia_chi_ch'] ?? '76 Trần Phú, Nha Trang') ?></div>
                        <div class="small text-muted mt-1"><?= date('d/m/Y H:i:s') ?></div>
                        <div class="fw-bold mt-2 border border-dark rounded d-inline-block px-3 py-1"><?= htmlspecialchars($orderCode) ?></div>
                    </div>
                    <div class="small border-bottom pb-2 mb-2">
                        <?php foreach ($items as $it): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span><?= htmlspecialchars($it['ten_san_pham']) ?> (x<?= $it['so_luong'] ?>)</span>
                            <span class="num-font fw-bold"><?= number_format($it['thanh_tien']) ?> ₫</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="small">
                        <div class="d-flex justify-content-between"><span>Tạm tính:</span><span><?= number_format($subtotal) ?> ₫</span></div>
                        <div class="d-flex justify-content-between"><span>Giảm giá:</span><span>- <?= number_format($discount) ?> ₫</span></div>
                        <div class="d-flex justify-content-between"><span>VAT (8%):</span><span><?= number_format($vat) ?> ₫</span></div>
                        <div class="d-flex justify-content-between fw-bold fs-6 pt-2 border-top mt-2">
                            <span>TỔNG CỘNG:</span><span><?= number_format($grandTotal) ?> ₫</span>
                        </div>
                        <div class="d-flex justify-content-between mt-2 pt-2 border-top text-muted">
                            <span>Hình thức:</span><span id="txtConfirmedMethod">Tiền mặt</span>
                        </div>
                    </div>
                    <div class="text-center small text-muted mt-4">Cảm ơn quý khách & Hẹn gặp lại!</div>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn-ck btn-ck-soft flex-grow-1" onclick="window.print()">
                        <i class="fa-solid fa-print me-1"></i> In hóa đơn
                    </button>
                    <a href="../man_hinh_ban_hang/index.php" class="btn-ck btn-ck-primary flex-grow-1 text-decoration-none">
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

    function selectMethod(method, cardEl) {
        selectedMethod = method;
        document.querySelectorAll('.pay-opt-card').forEach(el => el.classList.remove('active'));
        cardEl.classList.add('active');
        cardEl.querySelector('input[type="radio"]').checked = true;

        const cashBlock = document.getElementById('cashInputBlock');
        const qrBlock   = document.getElementById('qrScanBlock');
        const cardBlock = document.getElementById('cardSwipeBlock');

        cashBlock.style.display = 'none';
        qrBlock.style.display   = 'none';
        cardBlock.style.display = 'none';

        if (method === 'Tiền mặt') {
            cashBlock.style.display = '';
        } else if (method === 'VNPay QR' || method === 'Ví MoMo') {
            qrBlock.style.display = '';
            document.getElementById('qrTitleTxt').textContent = 'Quét mã ' + method + ' để thanh toán';
        } else if (method === 'Thẻ ATM / Visa') {
            cardBlock.style.display = '';
        }
    }

    function calculateChange() {
        const cashVal = parseFloat(document.getElementById('customerCash').value) || 0;
        const change = Math.max(0, cashVal - grandTotal);
        document.getElementById('customerChange').value = change.toLocaleString('vi-VN');
    }

    function setCash(val) {
        document.getElementById('customerCash').value = val;
        calculateChange();
    }

    function submitPayment() {
        const btn = document.getElementById('btnConfirmPay');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Đang xử lý...';

        const fd = new FormData();
        fd.append('action', 'confirm_payment');
        fd.append('hoadon_id', <?= (int)$order['id'] ?>);
        fd.append('phuong_thuc', selectedMethod);
        fd.append('so_tien', grandTotal);

        fetch(window.location.href, {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-circle-check me-2"></i>Xác nhận thanh toán';
            if (data.success) {
                document.getElementById('txtConfirmedMethod').textContent = selectedMethod;
                new bootstrap.Modal(document.getElementById('modalPaymentSuccess')).show();
            } else {
                alert('Lỗi: ' + data.message);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-circle-check me-2"></i>Xác nhận thanh toán';
            alert('Lỗi kết nối máy chủ: ' + err);
        });
    }
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>