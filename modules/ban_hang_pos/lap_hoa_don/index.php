<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

$host = '127.0.0.1';
$db   = 'circlek';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user,$pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_POST['action'] === 'get_detail') {
        $hd_id = (int)($_POST['hoadon_id'] ?? 0);
        $stmtHd =$pdo->prepare("SELECT hd.*, ch.ten_cua_hang, ch.dia_chi AS dia_chi_ch, ch.so_dien_thoai AS sdt_ch,
                                        nv.ho_ten AS ten_nv, kh.ho_ten AS ten_kh, kh.so_dien_thoai AS sdt_kh,
                                        km.ten_km, km.ma_voucher, tt.phuong_thuc, tt.so_tien_thanh_toan
                                 FROM hoadon hd
                                 LEFT JOIN cuahang ch ON hd.cuahang_id = ch.id
                                 LEFT JOIN nhanvien nv ON hd.nhanvien_id = nv.id
                                 LEFT JOIN khachhang kh ON hd.khachhang_id = kh.id
                                 LEFT JOIN khuyenmai km ON hd.khuyenmai_id = km.id
                                 LEFT JOIN thanhtoan tt ON hd.id = tt.hoadon_id
                                 WHERE hd.id = ?");
        $stmtHd->execute([$hd_id]);
        $order =$stmtHd->fetch();

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy hóa đơn']);
            exit;
        }

        $stmtItems =$pdo->prepare("SELECT ct.*, sp.ma_san_pham, sp.ten_san_pham
                                    FROM chitiethoadon ct
                                    JOIN sanpham sp ON ct.sanpham_id = sp.id
                                    WHERE ct.hoadon_id = ?");
        $stmtItems->execute([$hd_id]);
        $items =$stmtItems->fetchAll();

        echo json_encode(['success' => true, 'order' => $order, 'items' =>$items]);
        exit;
    }

    if ($_POST['action'] === 'cancel_order') {
        $hd_id = (int)($_POST['hoadon_id'] ?? 0);
        try {
            $pdo->beginTransaction();
            $stmtCancel =$pdo->prepare("UPDATE hoadon SET trang_thai = 'Đã hủy' WHERE id = ?");
            $stmtCancel->execute([$hd_id]);

            $stmtItems =$pdo->prepare("SELECT sanpham_id, so_luong FROM chitiethoadon WHERE hoadon_id = ?");
            $stmtItems->execute([$hd_id]);
            $items =$stmtItems->fetchAll();
            
            $stmtRestock =$pdo->prepare("UPDATE tonkho SET so_luong = so_luong + ? WHERE sanpham_id = ?");
            foreach ($items as $it) {$stmtRestock->execute([$it['so_luong'],$it['sanpham_id']]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction())$pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

$cuahang_list =$pdo->query("SELECT id, ma_cua_hang, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();

$sqlStats = "SELECT 
                COUNT(*) AS tong_don,
                SUM(CASE WHEN trang_thai = 'Hoàn thành' THEN khach_phai_tra ELSE 0 END) AS tong_doanh_thu,
                SUM(CASE WHEN trang_thai = 'Hoàn thành' THEN tien_giam_gia ELSE 0 END) AS tong_giam_gia,
                SUM(CASE WHEN trang_thai = 'Đã hủy' THEN 1 ELSE 0 END) AS tong_don_huy
             FROM hoadon";
$stats = $pdo->query($sqlStats)->fetch();

$sqlOrders = "SELECT hd.*, ch.ten_cua_hang, nv.ho_ten AS ten_nv, 
                     COALESCE(kh.ho_ten, 'Khách lẻ') AS ten_kh,
                     COALESCE(tt.phuong_thuc, 'Tiền mặt') AS phuong_thuc
              FROM hoadon hd
              LEFT JOIN cuahang ch ON hd.cuahang_id = ch.id
              LEFT JOIN nhanvien nv ON hd.nhanvien_id = nv.id
              LEFT JOIN khachhang kh ON hd.khachhang_id = kh.id
              LEFT JOIN thanhtoan tt ON hd.id = tt.hoadon_id
              ORDER BY hd.id DESC";
$orders = $pdo->query($sqlOrders)->fetchAll();

$pageTitle  = 'Lập & Quản lý Hóa đơn';
$activeMenu = 'lap_hoa_don';

require ROOT_PATH . '/includes/header.php';
?>

<style>
    .stat-card-pos { border-radius: 16px; padding: 18px 20px; transition: all 0.2s ease; border: 1px solid var(--border-light); background: #fff; }
    .stat-card-pos:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,0.05); }
    .stat-icon-wrap { width: 46px; height: 46px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; }
    .table-order-pos th { font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 700; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .table-order-pos td { font-size: 0.88rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .badge-status { padding: 4px 10px; border-radius: 99px; font-weight: 600; font-size: 0.76rem; display: inline-flex; align-items: center; gap: 4px; }
    .badge-status.success { background: #dcfce7; color: #15803d; }
    .badge-status.canceled { background: #fee2e2; color: #b91c1c; }
    .badge-pay { padding: 3px 8px; border-radius: 6px; font-size: 0.76rem; font-weight: 600; background: #f1f5f9; color: #475569; }
    .receipt-modal-paper { background: #fff; border: 1px dashed #cbd5e1; border-radius: 14px; padding: 24px; font-family: 'Courier New', Courier, monospace; color: #0f172a; max-width: 420px; margin: 0 auto; box-shadow: 0 4px 16px rgba(0,0,0,0.03); }
    @media print {
        body * { visibility: hidden; }
        #printableArea, #printableArea * { visibility: visible; }
        #printableArea { position: absolute; left: 0; top: 0; width: 100%; border: none; }
    }
</style>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card-pos d-flex align-items-center justify-content-between">
            <div>
                <small class="text-muted fw-semibold">DOANH THU HỆ THỐNG</small>
                <div class="num-font fw-bold fs-4 mt-1" style="color: var(--ck-red);"><?= number_format($stats['tong_doanh_thu'] ?? 0) ?> ₫</div>
            </div>
            <div class="stat-icon-wrap" style="background: rgba(238,49,36,0.1); color: var(--ck-red);"><i class="fa-solid fa-sack-dollar"></i></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card-pos d-flex align-items-center justify-content-between">
            <div>
                <small class="text-muted fw-semibold">TỔNG HÓA ĐƠN</small>
                <div class="num-font fw-bold fs-4 mt-1 text-dark"><?= number_format($stats['tong_don'] ?? 0) ?> đơn</div>
            </div>
            <div class="stat-icon-wrap" style="background: rgba(14,165,233,0.1); color: #0ea5e9;"><i class="fa-solid fa-receipt"></i></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card-pos d-flex align-items-center justify-content-between">
            <div>
                <small class="text-muted fw-semibold">TỔNG KHUYẾN MÃI</small>
                <div class="num-font fw-bold fs-4 mt-1 text-success">- <?= number_format($stats['tong_giam_gia'] ?? 0) ?> ₫</div>
            </div>
            <div class="stat-icon-wrap" style="background: rgba(34,197,94,0.1); color: #22c55e;"><i class="fa-solid fa-tags"></i></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card-pos d-flex align-items-center justify-content-between">
            <div>
                <small class="text-muted fw-semibold">ĐƠN HỦY / TRẢ</small>
                <div class="num-font fw-bold fs-4 mt-1 text-muted"><?= number_format($stats['tong_don_huy'] ?? 0) ?> đơn</div>
            </div>
            <div class="stat-icon-wrap" style="background: rgba(148,163,184,0.15); color: #64748b;"><i class="fa-solid fa-ban"></i></div>
        </div>
    </div>
</div>

<div class="bento-panel fade-up">
    <div class="panel-header flex-wrap gap-2">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                Danh sách Hóa đơn & Chứng từ
            </h5>
            <div class="panel-subtitle ms-5">Tra cứu, in lại phiếu thu và quản lý lịch sử thanh toán toàn chuỗi Nha Trang</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="../man_hinh_ban_hang/index.php" class="btn-ck btn-ck-primary"><i class="fa-solid fa-cash-register me-1"></i> Bán hàng mới (POS)</a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-12 col-md-4">
            <div class="search-bar w-100">
                <i class="fa-solid fa-magnifying-glass s-ico"></i>
                <input type="text" id="filterKeyword" placeholder="Tìm theo mã #HD, thu ngân, khách hàng..." onkeyup="filterInvoices()">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <select class="form-select" id="filterStore" onchange="filterInvoices()" style="height: 44px; border-radius: 10px;">
                <option value="">🏪 Tất cả chi nhánh Nha Trang</option>
                <?php foreach ($cuahang_list as$ch): ?>
                    <option value="<?= htmlspecialchars($ch['ten_cua_hang']) ?>"><?= htmlspecialchars($ch['ten_cua_hang']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select" id="filterMethod" onchange="filterInvoices()" style="height: 44px; border-radius: 10px;">
                <option value="">💳 Tất cả thanh toán</option>
                <option value="Tiền mặt">Tiền mặt</option>
                <option value="Chuyển khoản">Chuyển khoản</option>
                <option value="Ví điện tử">Ví điện tử</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select class="form-select" id="filterStatus" onchange="filterInvoices()" style="height: 44px; border-radius: 10px;">
                <option value="">Trạng thái: Tất cả</option>
                <option value="Hoàn thành">Hoàn thành</option>
                <option value="Đã hủy">Đã hủy</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-order-pos align-middle">
            <thead>
                <tr>
                    <th>Mã HĐ</th>
                    <th>Thời gian lập</th>
                    <th>Chi nhánh</th>
                    <th>Thu ngân</th>
                    <th>Khách hàng</th>
                    <th class="text-end">Khách trả</th>
                    <th class="text-center">Hình thức</th>
                    <th class="text-center">Trạng thái</th>
                    <th class="text-center">Thao tác</th>
                </tr>
            </thead>
            <tbody id="invoiceTableBody">
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-receipt fs-2 mb-2 d-block opacity-25"></i>
                            Chưa có hóa đơn nào được khởi tạo trong hệ thống.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as$od): 
                        $code = '#HD' . (1024 + $od['id']);
                        $isCompleted = ($od['trang_thai'] === 'Hoàn thành');
                    ?>
                    <tr class="invoice-item-row" 
                        data-search="<?= mb_strtolower($code . ' ' . $od['ten_nv'] . ' ' .$od['ten_kh']) ?>"
                        data-store="<?= htmlspecialchars($od['ten_cua_hang'] ?? '') ?>"
                        data-method="<?= htmlspecialchars($od['phuong_thuc'] ?? '') ?>"
                        data-status="<?= htmlspecialchars($od['trang_thai'] ?? '') ?>">
                        <td><span class="order-code fs-6"><?= $code ?></span></td>
                        <td>
                            <div class="fw-semibold text-dark"><?= date('H:i:s', strtotime($od['ngay_tao'])) ?></div>
                            <small class="text-muted"><?= date('d/m/Y', strtotime($od['ngay_tao'])) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border px-2 py-1">
                                <i class="fa-solid fa-store me-1 text-danger"></i><?= htmlspecialchars($od['ten_cua_hang'] ?? 'Circle K - Trần Phú') ?>
                            </span>
                        </td>
                        <td><i class="fa-solid fa-user-circle me-1 text-muted"></i><?= htmlspecialchars($od['ten_nv'] ?? 'Admin') ?></td>
                        <td><?= htmlspecialchars($od['ten_kh']) ?></td>
                        <td class="text-end">
                            <span class="num-font fw-bold <?= $isCompleted ? 'text-danger' : 'text-muted text-decoration-line-through' ?>">
                                <?= number_format($od['khach_phai_tra']) ?> ₫
                            </span>
                            <?php if ($od['tien_giam_gia'] > 0): ?>
                                <div class="small text-success">-<?= number_format($od['tien_giam_gia']) ?> ₫</div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><span class="badge-pay"><?= htmlspecialchars($od['phuong_thuc']) ?></span></td>
                        <td class="text-center">
                            <?php if ($isCompleted): ?>
                                <span class="badge-status success"><i class="fa-solid fa-circle-check"></i> Hoàn thành</span>
                            <?php else: ?>
                                <span class="badge-status canceled"><i class="fa-solid fa-circle-xmark"></i> Đã hủy</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-light border px-2 py-1 me-1 text-primary" title="Xem & In hóa đơn" onclick="viewInvoiceDetail(<?= $od['id'] ?>)">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <?php if ($isCompleted): ?>
                                <button type="button" class="btn btn-sm btn-light border px-2 py-1 text-danger" title="Hủy hóa đơn hoàn trả" onclick="cancelInvoice(<?= $od['id'] ?>, '<?=$code ?>')">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <div id="noMatchMsg" class="text-center py-4 text-muted" style="display: none;">
            <i class="fa-solid fa-search fs-3 mb-2 d-block opacity-25"></i> Không tìm thấy hóa đơn phù hợp với bộ lọc.
        </div>
    </div>
</div>

<div class="modal fade" id="modalViewDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 18px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-receipt text-danger me-2"></i>Chi tiết Hóa đơn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="receipt-modal-paper" id="printableArea">
                    <div class="text-center border-bottom pb-3 mb-3">
                        <div class="fw-bold fs-4">CIRCLE K VIETNAM</div>
                        <div class="fw-semibold mt-1" id="mStoreName">Circle K - Trần Phú</div>
                        <div class="small text-muted" id="mStoreAddr">76 Trần Phú, Lộc Thọ, Nha Trang</div>
                        <div class="small text-muted mt-1" id="mOrderTime">17/10/2026 19:30:00</div>
                        <div class="fw-bold mt-2 border border-dark rounded d-inline-block px-3 py-1" id="mOrderCode">#HD1025</div>
                    </div>
                    <div class="small mb-3 pb-2 border-bottom">
                        <div class="d-flex justify-content-between"><span>Thu ngân:</span><strong id="mCashier">Nguyễn Phương Nam</strong></div>
                        <div class="d-flex justify-content-between"><span>Khách hàng:</span><span id="mCustomer">Khách lẻ</span></div>
                    </div>
                    <div id="mItemsContainer" class="small border-bottom pb-3 mb-3"></div>
                    <div class="small fw-medium">
                        <div class="d-flex justify-content-between mb-1"><span>Tạm tính:</span><span id="mSubtotal">0 ₫</span></div>
                        <div class="d-flex justify-content-between mb-1 text-success"><span>Giảm giá:</span><span id="mDiscount">- 0 ₫</span></div>
                        <div class="d-flex justify-content-between mb-2"><span>VAT (8%):</span><span id="mVat">0 ₫</span></div>
                        <div class="d-flex justify-content-between fw-bold fs-6 pt-2 border-top mt-2">
                            <span>TỔNG TIỀN:</span><span class="text-danger" id="mGrandTotal">0 ₫</span>
                        </div>
                        <div class="d-flex justify-content-between mt-2 pt-2 border-top text-muted">
                            <span>Phương thức:</span><span id="mPayMethod">Tiền mặt</span>
                        </div>
                    </div>
                    <div class="text-center small text-muted mt-4">Cảm ơn quý khách & Hẹn gặp lại!</div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn-ck btn-ck-primary px-4" onclick="window.print()">
                    <i class="fa-solid fa-print me-2"></i>In phiếu thu
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const fVND = n => Math.round(n).toLocaleString() + ' ₫';

    function filterInvoices() {
        const kw = document.getElementById('filterKeyword').value.toLowerCase().trim();
        const store = document.getElementById('filterStore').value;
        const method = document.getElementById('filterMethod').value;
        const status = document.getElementById('filterStatus').value;

        const rows = document.querySelectorAll('.invoice-item-row');
        let visibleCount = 0;

        rows.forEach(r => {
            const mSearch = r.getAttribute('data-search').includes(kw);
            const mStore  = !store || r.getAttribute('data-store') === store;
            const mMethod = !method || r.getAttribute('data-method') === method;
            const mStatus = !status || r.getAttribute('data-status') === status;

            if (mSearch && mStore && mMethod && mStatus) {
                r.style.display = '';
                visibleCount++;
            } else {
                r.style.display = 'none';
            }
        });

        document.getElementById('noMatchMsg').style.display = (visibleCount === 0) ? '' : 'none';
    }

    function viewInvoiceDetail(id) {
        const fd = new FormData();
        fd.append('action', 'get_detail');
        fd.append('hoadon_id', id);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const od = data.order;
                    const items = data.items;
                    const code = '#HD' + (1024 + parseInt(od.id));

                    document.getElementById('mStoreName').textContent = od.ten_cua_hang || 'Circle K - Trần Phú';
                    document.getElementById('mStoreAddr').textContent = od.dia_chi_ch || '76 Trần Phú, Lộc Thọ, Nha Trang';
                    document.getElementById('mOrderTime').textContent = od.ngay_tao;
                    document.getElementById('mOrderCode').textContent = code;
                    document.getElementById('mCashier').textContent   = od.ten_nv || 'Thu ngân';
                    document.getElementById('mCustomer').textContent  = od.ten_kh ? (od.ten_kh + (od.sdt_kh ? ' (' + od.sdt_kh + ')' : '')) : 'Khách lẻ';

                    let itemsHtml = '';
                    items.forEach(it => {
                        itemsHtml += `
                            <div class="d-flex justify-content-between mb-1">
                                <span>${it.ten_san_pham} (x${it.so_luong})</span>
                                <span class="fw-semibold">${fVND(it.thanh_tien)}</span>
                            </div>`;
                    });
                    document.getElementById('mItemsContainer').innerHTML = itemsHtml;

                    const subtotal = parseFloat(od.tong_tien) || 0;
                    const discount = parseFloat(od.tien_giam_gia) || 0;
                    const total    = parseFloat(od.khach_phai_tra) || 0;
                    const vat      = Math.max(0, total - (subtotal - discount));

                    document.getElementById('mSubtotal').textContent   = fVND(subtotal);
                    document.getElementById('mDiscount').textContent   = '- ' + fVND(discount);
                    document.getElementById('mVat').textContent        = fVND(vat);
                    document.getElementById('mGrandTotal').textContent = fVND(total);
                    document.getElementById('mPayMethod').textContent  = od.phuong_thuc || 'Tiền mặt';

                    new bootstrap.Modal(document.getElementById('modalViewDetail')).show();
                } else {
                    alert(data.message);
                }
            })
            .catch(err => alert('Lỗi tải thông tin hóa đơn: ' + err));
    }

    function cancelInvoice(id, code) {
        if (!confirm(`Bạn có chắc chắn muốn hủy đơn hàng ${code}? Dữ liệu tồn kho sẽ được hoàn trả tự động.`)) return;

        const fd = new FormData();
        fd.append('action', 'cancel_order');
        fd.append('hoadon_id', id);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Lỗi hủy đơn: ' + data.message);
                }
            })
            .catch(err => alert('Lỗi kết nối máy chủ: ' + err));
    }
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>