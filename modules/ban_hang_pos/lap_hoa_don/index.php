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

try { $pdo->exec("ALTER TABLE thanhtoan ADD COLUMN tien_khach_dua DECIMAL(12,2) NULL"); } catch (Exception $e) { }
try { $pdo->exec("ALTER TABLE thanhtoan ADD COLUMN tien_thoi DECIMAL(12,2) NULL"); } catch (Exception $e) { }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_POST['action'] === 'get_detail') {
        $hd_id = (int)($_POST['hoadon_id'] ?? 0);
        $stmtHd = $pdo->prepare("SELECT hd.*, 
                                        DATE_FORMAT(hd.ngay_tao, '%d/%m/%Y %H:%i:%s') AS ngay_tao_fmt,
                                        ch.ten_cua_hang, ch.dia_chi AS dia_chi_ch, ch.so_dien_thoai AS sdt_ch,
                                        nv.ho_ten AS ten_nv, kh.ho_ten AS ten_kh, kh.so_dien_thoai AS sdt_kh,
                                        ttd.hang_the,
                                        km.ten_km, km.ma_voucher, tt.phuong_thuc, tt.so_tien_thanh_toan,
                                        tt.tien_khach_dua, tt.tien_thoi
                                 FROM hoadon hd
                                 LEFT JOIN cuahang ch ON hd.cuahang_id = ch.id
                                 LEFT JOIN nhanvien nv ON hd.nhanvien_id = nv.id
                                 LEFT JOIN khachhang kh ON hd.khachhang_id = kh.id
                                 LEFT JOIN thetichdiem ttd ON kh.thetichdiem_id = ttd.id
                                 LEFT JOIN khuyenmai km ON hd.khuyenmai_id = km.id
                                 LEFT JOIN thanhtoan tt ON hd.id = tt.hoadon_id
                                 WHERE hd.id = ?");
        $stmtHd->execute([$hd_id]);
        $order = $stmtHd->fetch();

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy hóa đơn']);
            exit;
        }

        $stmtItems = $pdo->prepare("SELECT ct.*, sp.ma_san_pham, sp.ten_san_pham
                                    FROM chitiethoadon ct
                                    JOIN sanpham sp ON ct.sanpham_id = sp.id
                                    WHERE ct.hoadon_id = ?");
        $stmtItems->execute([$hd_id]);
        $items = $stmtItems->fetchAll();

        echo json_encode(['success' => true, 'order' => $order, 'items' => $items]);
        exit;
    }

    if ($_POST['action'] === 'cancel_order') {
        $hd_id = (int)($_POST['hoadon_id'] ?? 0);
        try {
            $pdo->beginTransaction();
            $stmtCancel = $pdo->prepare("UPDATE hoadon SET trang_thai = 'Đã hủy' WHERE id = ?");
            $stmtCancel->execute([$hd_id]);

            $stmtItems = $pdo->prepare("SELECT sanpham_id, so_luong FROM chitiethoadon WHERE hoadon_id = ?");
            $stmtItems->execute([$hd_id]);
            $items = $stmtItems->fetchAll();
            
            $stmtRestock = $pdo->prepare("UPDATE tonkho SET so_luong = so_luong + ? WHERE sanpham_id = ?");
            foreach ($items as $it) {
                $stmtRestock->execute([$it['so_luong'], $it['sanpham_id']]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

$cuahang_list = $pdo->query("SELECT id, ma_cua_hang, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();

$sqlStats = "SELECT 
                SUM(CASE WHEN trang_thai IN ('Hoàn thành', 'Đã thanh toán') THEN 1 ELSE 0 END) AS tong_don_thanh_cong,
                SUM(CASE WHEN trang_thai IN ('Hoàn thành', 'Đã thanh toán') THEN khach_phai_tra ELSE 0 END) AS tong_doanh_thu,
                SUM(CASE WHEN trang_thai IN ('Hoàn thành', 'Đã thanh toán') THEN tien_giam_gia ELSE 0 END) AS tong_giam_gia,
                SUM(CASE WHEN trang_thai = 'Đã hủy' THEN 1 ELSE 0 END) AS tong_don_huy
             FROM hoadon";
$stats = $pdo->query($sqlStats)->fetch();

$tongDonThanhCong = (int)($stats['tong_don_thanh_cong'] ?? 0);
$tongDonHuy = (int)($stats['tong_don_huy'] ?? 0);
$tongDoanhThu = (float)($stats['tong_doanh_thu'] ?? 0);
$tongGiamGia = (float)($stats['tong_giam_gia'] ?? 0);
$aov = $tongDonThanhCong > 0 ? round($tongDoanhThu / $tongDonThanhCong) : 0;
$tiLeHuy = ($tongDonThanhCong + $tongDonHuy) > 0 ? round(($tongDonHuy / ($tongDonThanhCong + $tongDonHuy)) * 100, 1) : 0;

$payStats = $pdo->query("SELECT COALESCE(tt.phuong_thuc, 'Tiền mặt') AS pthuc, 
                                COUNT(hd.id) AS so_don, 
                                COALESCE(SUM(hd.khach_phai_tra), 0) AS tong_tien
                         FROM hoadon hd
                         LEFT JOIN thanhtoan tt ON hd.id = tt.hoadon_id
                         WHERE hd.trang_thai IN ('Hoàn thành', 'Đã thanh toán')
                         GROUP BY pthuc")->fetchAll();

$branchStats = $pdo->query("SELECT ch.ten_cua_hang, 
                                   COUNT(hd.id) AS so_don, 
                                   COALESCE(SUM(CASE WHEN hd.trang_thai IN ('Hoàn thành', 'Đã thanh toán') THEN hd.khach_phai_tra ELSE 0 END), 0) AS doanh_thu
                            FROM cuahang ch
                            LEFT JOIN hoadon hd ON ch.id = hd.cuahang_id
                            WHERE ch.trang_thai = 1
                            GROUP BY ch.id, ch.ten_cua_hang
                            ORDER BY doanh_thu DESC")->fetchAll();

$tierStats = $pdo->query("SELECT COALESCE(ttd.hang_the, 'Khách lẻ') AS hang, 
                                 COUNT(hd.id) AS so_don,
                                 COALESCE(SUM(hd.tien_giam_gia), 0) AS tong_giam
                          FROM hoadon hd
                          LEFT JOIN khachhang kh ON hd.khachhang_id = kh.id
                          LEFT JOIN thetichdiem ttd ON kh.thetichdiem_id = ttd.id
                          WHERE hd.trang_thai IN ('Hoàn thành', 'Đã thanh toán')
                          GROUP BY hang")->fetchAll();

$priceTiers = $pdo->query("SELECT 
        SUM(CASE WHEN khach_phai_tra < 50000 THEN 1 ELSE 0 END) AS tier_thap,
        SUM(CASE WHEN khach_phai_tra >= 50000 AND khach_phai_tra <= 100000 THEN 1 ELSE 0 END) AS tier_trung,
        SUM(CASE WHEN khach_phai_tra > 100000 THEN 1 ELSE 0 END) AS tier_cao
    FROM hoadon WHERE trang_thai IN ('Hoàn thành', 'Đã thanh toán')")->fetch();

$sqlOrders = "SELECT hd.*, ch.ten_cua_hang, nv.ho_ten AS ten_nv, 
                     kh.ho_ten AS ten_kh, kh.so_dien_thoai AS sdt_kh,
                     ttd.hang_the,
                     COALESCE(tt.phuong_thuc, 'Tiền mặt') AS phuong_thuc,
                     tt.tien_khach_dua, tt.tien_thoi
              FROM hoadon hd
              LEFT JOIN cuahang ch ON hd.cuahang_id = ch.id
              LEFT JOIN nhanvien nv ON hd.nhanvien_id = nv.id
              LEFT JOIN khachhang kh ON hd.khachhang_id = kh.id
              LEFT JOIN thetichdiem ttd ON kh.thetichdiem_id = ttd.id
              LEFT JOIN thanhtoan tt ON hd.id = tt.hoadon_id
              ORDER BY hd.id DESC";
$orders = $pdo->query($sqlOrders)->fetchAll();

$insightToday = date('Y-m-d');
$insightYesterday = date('Y-m-d', strtotime('-1 day'));
$insightTodayRevenue = 0;
$insightYesterdayRevenue = 0;
$insightTodayPaid = 0;
$insightTodayCanceled = 0;
$insightPending = 0;
$insightDiscountedOrders = 0;
foreach ($orders as $insightOrder) {
    $insightOrderDate = substr((string)($insightOrder['ngay_tao'] ?? ''), 0, 10);
    $insightOrderStatus = $insightOrder['trang_thai'] ?? '';
    if (in_array($insightOrderStatus, ['Hoàn thành', 'Đã thanh toán'], true)) {
        if ($insightOrderDate === $insightToday) {
            $insightTodayRevenue += (float)$insightOrder['khach_phai_tra'];
            $insightTodayPaid++;
        } elseif ($insightOrderDate === $insightYesterday) {
            $insightYesterdayRevenue += (float)$insightOrder['khach_phai_tra'];
        }
        if ((float)($insightOrder['tien_giam_gia'] ?? 0) > 0) $insightDiscountedOrders++;
    } elseif ($insightOrderStatus === 'Chờ thanh toán') {
        $insightPending++;
    } elseif ($insightOrderStatus === 'Đã hủy' && $insightOrderDate === $insightToday) {
        $insightTodayCanceled++;
    }
}
$insightAvgDiscount = $insightDiscountedOrders > 0 ? $tongGiamGia / $insightDiscountedOrders : 0;
$insightDiscountRate = ($tongDoanhThu + $tongGiamGia) > 0 ? round($tongGiamGia / ($tongDoanhThu + $tongGiamGia) * 100, 1) : 0;
$insightSuccessRate = ($tongDonThanhCong + $tongDonHuy) > 0 ? round($tongDonThanhCong / ($tongDonThanhCong + $tongDonHuy) * 100, 1) : 0;
$insightTopBranch = null;
foreach ($branchStats as $insightBranch) {
    if ((float)$insightBranch['doanh_thu'] > 0) {
        $insightTopBranch = $insightBranch;
        break;
    }
}
$insightTopTier = null;
foreach ($tierStats as $insightTier) {
    if ($insightTopTier === null || (float)$insightTier['tong_giam'] > (float)$insightTopTier['tong_giam']) {
        $insightTopTier = $insightTier;
    }
}

$pageTitle  = 'Lập & Quản lý Hóa đơn';
$activeMenu = 'lap_hoa_don';

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

    .stat-float-wrapper { background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 24px; padding: 24px; position: relative; cursor: pointer; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); overflow: hidden; height: 100%; box-shadow: 0 10px 25px rgba(0,0,0,0.02); }
    .stat-float-wrapper:hover { background: #ffffff; transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.06); }
    .sf-header-row { display: inline-flex; align-items: center; position: relative; margin-bottom: 24px; }
    .sf-pill { background: #ffffff; border-radius: 50px; padding: 8px 36px 8px 18px; font-size: 0.72rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); position: relative; z-index: 2; }
    .sf-icon-box { width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; position: absolute; right: -12px; z-index: 3; box-shadow: 0 6px 15px rgba(0,0,0,0.06); transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .stat-float-wrapper:hover .sf-icon-box { transform: scale(1.15) rotate(10deg); }
    .sf-body-row { display: flex; align-items: baseline; line-height: 1; }
    .sf-num { font-size: 2.1rem; font-weight: 900; font-family: 'Nunito', sans-serif; letter-spacing: -0.5px; }
    .sf-unit { font-size: 1.1rem; font-weight: 800; margin-left: 6px; }
    .sf-footer-row { margin-top: 14px; font-size: 0.82rem; font-weight: 600; color: #94a3b8; display: flex; align-items: center; gap: 6px; transition: color 0.3s; }
    .stat-float-wrapper:hover .sf-footer-row { color: #64748b; }

    .th-red .sf-icon-box { background: #ffe4e6; color: #e11d48; } .th-red .sf-num, .th-red .sf-unit { color: #e11d48; }
    .th-blue .sf-icon-box { background: #e0f2fe; color: #0ea5e9; } .th-blue .sf-num, .th-blue .sf-unit { color: #0ea5e9; }
    .th-green .sf-icon-box { background: #d1fae5; color: #10b981; } .th-green .sf-num, .th-green .sf-unit { color: #10b981; }
    .th-gray .sf-icon-box { background: #f1f5f9; color: #475569; } .th-gray .sf-num, .th-gray .sf-unit { color: #475569; }

    .stat-float-wrapper:focus-visible { outline: 3px solid #2563eb; outline-offset: 3px; }
    .invoice-insight-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; height: 100%; }
    .invoice-insight-label { color: #64748b; font-size: .78rem; font-weight: 700; }
    .invoice-insight-value { color: #0f172a; font-size: 1.4rem; font-weight: 800; line-height: 1.35; overflow-wrap: anywhere; }
    .invoice-insight-note { background: #f1f5f9; border-radius: 12px; padding: 14px; color: #475569; font-size: .87rem; }
    .invoice-insight-panel[hidden] { display: none !important; }
    .invoice-insight-bar { height: 7px; background: #e2e8f0; border-radius: 30px; overflow: hidden; }
    .invoice-insight-bar > div { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #ff3657, #fb7951); }

    .ripple { position: absolute; border-radius: 50%; transform: scale(0); animation: ripple 0.6s cubic-bezier(0.4, 0, 0.2, 1); background-color: rgba(0, 0, 0, 0.04); pointer-events: none; }
    @keyframes ripple { to { transform: scale(4); opacity: 0; } }

    .table-order-pos th { font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 700; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .table-order-pos td { font-size: 0.88rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; }
    .invoice-item-row { animation: fadeUpRow 0.4s ease backwards; }
    .invoice-item-row:hover td { background-color: #f8fafc; cursor: pointer; }
    @keyframes fadeUpRow { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    .badge-status { padding: 4px 10px; border-radius: 99px; font-weight: 600; font-size: 0.76rem; display: inline-flex; align-items: center; gap: 4px; }
    .badge-status.success { background: #dcfce7; color: #15803d; }
    .badge-status.canceled { background: #fee2e2; color: #b91c1c; }
    .badge-status.pending { background: #fef3c7; color: #d97706; }
    .badge-pay { padding: 3px 8px; border-radius: 6px; font-size: 0.76rem; font-weight: 600; background: #f1f5f9; color: #475569; }
    
    .tier-badge { font-weight: 800; border-radius: 6px; padding: 2px 7px; font-size: 0.72rem; text-transform: uppercase; display: inline-block; letter-spacing: 0.5px; }
    .tier-bac { background: linear-gradient(135deg, #94a3b8, #64748b); color: #fff; }
    .tier-vang { background: linear-gradient(135deg, #fcd34d, #f59e0b); color: #fff; }
    .tier-kimcuong { background: linear-gradient(135deg, #67e8f9, #06b6d4); color: #fff; }
    .tier-dong { background: linear-gradient(135deg, #d97706, #b45309); color: #fff; }
    .tier-none { background: #e2e8f0; color: #475569; }

    .receipt-print-box { background: #fff; border: 1px solid #cbd5e1; border-radius: 0px; padding: 20px; font-family: 'Courier New', Courier, monospace; color: #000; max-width: 360px; margin: 0 auto; box-shadow: 0 10px 25px rgba(0,0,0,0.1); font-size: 0.85rem; line-height: 1.4; }
    .receipt-print-box .dashed-line { border-bottom: 1px dashed #000; margin: 12px 0; }
    .receipt-print-box .brand-logo { font-family: 'Arial Black', Arial, sans-serif; font-size: 1.5rem; font-weight: 900; letter-spacing: -1px; margin-bottom: 5px; text-transform: uppercase; }

    .hub-nav-pill { border: none; background: #f1f5f9; color: #475569; font-weight: 700; font-size: 0.85rem; border-radius: 10px; padding: 10px 16px; transition: all 0.3s ease; }
    .hub-nav-pill.active { background: linear-gradient(135deg, var(--ck-red), #f97316); color: #fff; box-shadow: 0 6px 12px rgba(238,49,36,0.2); transform: translateY(-2px); }
    .hub-metric-tile { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s; }
    .hub-metric-tile:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.05); }
    .hub-progress-track { height: 8px; background: #e2e8f0; border-radius: 99px; overflow: hidden; margin-top: 6px; }
    .hub-progress-bar { height: 100%; border-radius: 99px; transition: width 1s ease; width: 0%; }

    @media print {
        @page { margin: 0; size: 80mm auto; }
        body * { visibility: hidden; }
        .modal { position: absolute; left: 0; top: 0; margin: 0; padding: 0; min-height: 550px; visibility: visible; }
        .modal-dialog { margin: 0; padding: 0; max-width: 100%; width: 100%; }
        .modal-content { border: none; box-shadow: none; background: transparent; }
        .modal-header, .modal-footer { display: none !important; }
        #printableArea, #printableArea * { visibility: visible; color: #000 !important; }
        #printableArea { position: absolute; left: 0; top: 0; width: 78mm !important; max-width: 78mm !important; padding: 2mm !important; margin: 0 !important; border: none !important; box-shadow: none !important; font-size: 11px !important; }
        #printableArea .dashed-line { margin: 8px 0 !important; }
    }
</style>

<script>document.querySelector('body').classList.add('layout-fixed');</script>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-float-wrapper th-red" role="button" tabindex="0" aria-label="Xem nhanh doanh thu" onclick="openInvoiceQuickInsight('revenue')" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openInvoiceQuickInsight('revenue'); }">
            <div class="sf-header-row">
                <div class="sf-pill">DOANH THU HỆ THỐNG</div>
                <div class="sf-icon-box"><i class="fa-solid fa-sack-dollar"></i></div>
            </div>
            <div class="sf-body-row">
                <span class="sf-num"><?= number_format($tongDoanhThu, 0, ',', '.') ?></span>
                <span class="sf-unit">₫</span>
            </div>
            <div class="sf-footer-row"><i class="fa-solid fa-chart-pie"></i> Nhấn để xem doanh thu hôm nay</div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-float-wrapper th-blue" role="button" tabindex="0" aria-label="Xem tình trạng hóa đơn" onclick="openInvoiceQuickInsight('orders')" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openInvoiceQuickInsight('orders'); }">
            <div class="sf-header-row">
                <div class="sf-pill">TỔNG HÓA ĐƠN</div>
                <div class="sf-icon-box"><i class="fa-solid fa-receipt"></i></div>
            </div>
            <div class="sf-body-row">
                <span class="sf-num"><?= number_format($tongDonThanhCong, 0, ',', '.') ?></span>
                <span class="sf-unit text-muted" style="font-size: 0.95rem;">ĐƠN</span>
            </div>
            <div class="sf-footer-row"><i class="fa-solid fa-arrow-trend-up"></i> Nhấn để xem đơn cần xử lý</div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-float-wrapper th-green" role="button" tabindex="0" aria-label="Xem hiệu quả ưu đãi" onclick="openInvoiceQuickInsight('discount')" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openInvoiceQuickInsight('discount'); }">
            <div class="sf-header-row">
                <div class="sf-pill">TỔNG KHUYẾN MÃI</div>
                <div class="sf-icon-box"><i class="fa-solid fa-tags"></i></div>
            </div>
            <div class="sf-body-row">
                <span style="font-size: 1.5rem; font-weight: 800; margin-right: 5px; color: #10b981;">-</span>
                <span class="sf-num"><?= number_format($tongGiamGia, 0, ',', '.') ?></span>
                <span class="sf-unit">₫</span>
            </div>
            <div class="sf-footer-row"><i class="fa-solid fa-id-card"></i> Nhấn để xem hiệu quả giảm giá</div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-float-wrapper th-gray" role="button" tabindex="0" aria-label="Xem nhanh đơn hủy" onclick="openInvoiceQuickInsight('cancel')" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openInvoiceQuickInsight('cancel'); }">
            <div class="sf-header-row">
                <div class="sf-pill">ĐƠN HỦY / TRẢ</div>
                <div class="sf-icon-box"><i class="fa-solid fa-ban"></i></div>
            </div>
            <div class="sf-body-row">
                <span class="sf-num"><?= number_format($tongDonHuy, 0, ',', '.') ?></span>
                <span class="sf-unit text-muted" style="font-size: 0.95rem;">ĐƠN</span>
            </div>
            <div class="sf-footer-row"><i class="fa-solid fa-shield-halved"></i> Nhấn để xem tỷ lệ hủy</div>
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
            <button type="button" class="btn-ck btn-ck-soft" onclick="openExecutiveHub('revenue')">
                <i class="fa-solid fa-chart-simple me-1"></i> Trung tâm Phân tích
            </button>
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
                <option value="">🏪 Tất cả chi nhánh</option>
                <?php foreach ($cuahang_list as $ch): ?>
                    <option value="<?= htmlspecialchars($ch['ten_cua_hang']) ?>"><?= htmlspecialchars($ch['ten_cua_hang']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select" id="filterMethod" onchange="filterInvoices()" style="height: 44px; border-radius: 10px;">
                <option value="">💳 Tất cả</option>
                <option value="Tiền mặt">Tiền mặt</option>
                <option value="Chuyển khoản">Chuyển khoản</option>
                <option value="Ví điện tử">Ví điện tử</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select class="form-select" id="filterStatus" onchange="filterInvoices()" style="height: 44px; border-radius: 10px;">
                <option value="">Trạng thái: Tất cả</option>
                <option value="Đã thanh toán">Đã thanh toán / Hoàn thành</option>
                <option value="Chờ thanh toán">Chờ thanh toán</option>
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
                    <?php 
                        $delay = 0;
                        foreach ($orders as $od): 
                        $code = '#HD' . (1024 + $od['id']);
                        $isCompleted = in_array($od['trang_thai'], ['Hoàn thành', 'Đã thanh toán']);
                        $isPending = ($od['trang_thai'] === 'Chờ thanh toán');
                        $tier = $od['hang_the'] ?? 'Đồng';
                        $tierClass = 'tier-none';
                        if (mb_stripos($tier, 'bạc') !== false) $tierClass = 'tier-bac';
                        elseif (mb_stripos($tier, 'vàng') !== false) $tierClass = 'tier-vang';
                        elseif (mb_stripos($tier, 'kim cương') !== false) $tierClass = 'tier-kimcuong';
                        elseif (mb_stripos($tier, 'đồng') !== false) $tierClass = 'tier-dong';
                        
                        $delay += 0.05;
                    ?>
                    <tr class="invoice-item-row" style="animation-delay: <?= min($delay, 1) ?>s;"
                        data-search="<?= mb_strtolower($code . ' ' . $od['ten_nv'] . ' ' . ($od['ten_kh'] ?? 'Khách lẻ')) ?>"
                        data-store="<?= htmlspecialchars($od['ten_cua_hang'] ?? '') ?>"
                        data-method="<?= htmlspecialchars($od['phuong_thuc'] ?? '') ?>"
                        data-status="<?= $isCompleted ? 'Đã thanh toán' : htmlspecialchars($od['trang_thai'] ?? '') ?>"
                        data-amount="<?= (float)$od['khach_phai_tra'] ?>">
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
                        <td>
                            <?php if (!empty($od['ten_kh'])): ?>
                                <span class="tier-badge <?= $tierClass ?> me-1">[<?= htmlspecialchars($tier) ?>]</span>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($od['ten_kh']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">Khách lẻ (Vãng lai)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <span class="num-font fw-bold <?= $isCompleted ? 'text-danger' : 'text-muted text-decoration-line-through' ?>">
                                <?= number_format($od['khach_phai_tra'], 0, ',', '.') ?> ₫
                            </span>
                            <?php if ($od['tien_giam_gia'] > 0): ?>
                                <div class="small text-success">-<?= number_format($od['tien_giam_gia'], 0, ',', '.') ?> ₫</div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><span class="badge-pay"><?= htmlspecialchars($od['phuong_thuc']) ?></span></td>
                        <td class="text-center">
                            <?php if ($isCompleted): ?>
                                <span class="badge-status success"><i class="fa-solid fa-circle-check"></i> Đã thanh toán</span>
                            <?php elseif ($isPending): ?>
                                <span class="badge-status pending"><i class="fa-solid fa-clock"></i> Chờ thanh toán</span>
                            <?php else: ?>
                                <span class="badge-status canceled"><i class="fa-solid fa-circle-xmark"></i> Đã hủy</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-light border px-2 py-1 me-1 text-primary" title="Xem & In hóa đơn" onclick="viewInvoiceDetail(<?= $od['id'] ?>)">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <?php if ($isCompleted || $isPending): ?>
                                <button type="button" class="btn btn-sm btn-light border px-2 py-1 text-danger" title="Hủy hóa đơn hoàn trả" onclick="cancelInvoice(<?= $od['id'] ?>, '<?= $code ?>')">
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
    <div id="invoicePagination" class="d-flex flex-wrap justify-content-center align-items-center gap-2 mt-3" aria-label="Phân trang hóa đơn"></div>
</div>

<div class="modal fade" id="modalInvoiceQuickInsight" tabindex="-1" aria-labelledby="invoiceQuickInsightTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 590px;">
        <div class="modal-content border-0" style="border-radius: 22px; box-shadow: 0 24px 64px rgba(15,23,42,.15);">
            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <div>
                    <div class="small text-muted fw-semibold mb-1">THỐNG KÊ NHANH · DỮ LIỆU HỆ THỐNG</div>
                    <h5 id="invoiceQuickInsightTitle" class="modal-title fw-bold mb-0">Tổng quan</h5>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body px-4 pb-3 pt-2">
                <section class="invoice-insight-panel" data-invoice-insight="revenue" hidden>
                    <div class="row g-3 mb-3">
                        <div class="col-6"><div class="invoice-insight-card"><div class="invoice-insight-label">Doanh thu hôm nay</div><div class="invoice-insight-value text-danger"><?= number_format($insightTodayRevenue, 0, ',', '.') ?> ₫</div></div></div>
                        <div class="col-6"><div class="invoice-insight-card"><div class="invoice-insight-label">Doanh thu hôm qua</div><div class="invoice-insight-value"><?= number_format($insightYesterdayRevenue, 0, ',', '.') ?> ₫</div></div></div>
                    </div>
                    <div class="invoice-insight-note mb-3">
                        <i class="fa-solid fa-chart-line me-1 text-danger"></i>
                        <?php if ($insightYesterdayRevenue > 0): ?>
                            Hôm nay doanh thu <?= $insightTodayRevenue >= $insightYesterdayRevenue ? 'tăng' : 'giảm' ?> <strong><?= number_format(abs($insightTodayRevenue - $insightYesterdayRevenue) / $insightYesterdayRevenue * 100, 1, ',', '.') ?>%</strong> so với hôm qua.
                        <?php else: ?>
                            Hôm qua chưa có doanh thu để đối chiếu. Hôm nay đã có <strong><?= $insightTodayPaid ?> hóa đơn thanh toán</strong>.
                        <?php endif; ?>
                    </div>
                    <?php if ($insightTopBranch !== null): ?>
                        <div class="small text-muted mb-1">Chi nhánh có doanh thu cao nhất (toàn bộ dữ liệu)</div>
                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap"><strong><?= htmlspecialchars($insightTopBranch['ten_cua_hang'], ENT_QUOTES, 'UTF-8') ?></strong><span class="fw-bold text-danger"><?= number_format($insightTopBranch['doanh_thu'], 0, ',', '.') ?> ₫</span></div>
                    <?php endif; ?>
                    <button class="btn btn-danger mt-4 w-100" type="button" onclick="filterInvoicesFromInsight('Đã thanh toán')"><i class="fa-solid fa-list me-2"></i>Xem hóa đơn đã thanh toán</button>
                </section>
                <section class="invoice-insight-panel" data-invoice-insight="orders" hidden>
                    <div class="row g-3 mb-3">
                        <div class="col-6"><div class="invoice-insight-card"><div class="invoice-insight-label">Giá trị trung bình / đơn</div><div class="invoice-insight-value text-primary"><?= number_format($aov, 0, ',', '.') ?> ₫</div></div></div>
                        <div class="col-6"><div class="invoice-insight-card"><div class="invoice-insight-label">Chờ thanh toán</div><div class="invoice-insight-value"><?= $insightPending ?> đơn</div></div></div>
                    </div>
                    <div class="invoice-insight-note"><i class="fa-solid fa-circle-info me-1 text-primary"></i> Hôm nay hoàn thành <strong><?= $insightTodayPaid ?> hóa đơn</strong>. <?= $insightPending > 0 ? 'Có đơn đang chờ, bạn có thể mở danh sách để kiểm tra.' : 'Hiện không có đơn nào đang chờ thanh toán.' ?></div>
                    <button class="btn btn-primary mt-4 w-100" type="button" onclick="filterInvoicesFromInsight('<?= $insightPending > 0 ? 'Chờ thanh toán' : '' ?>')"><i class="fa-solid fa-filter me-2"></i><?= $insightPending > 0 ? 'Xem các đơn chờ thanh toán' : 'Xem tất cả hóa đơn' ?></button>
                </section>
                <section class="invoice-insight-panel" data-invoice-insight="discount" hidden>
                    <div class="row g-3 mb-3">
                        <div class="col-6"><div class="invoice-insight-card"><div class="invoice-insight-label">Đơn có giảm giá</div><div class="invoice-insight-value text-success"><?= $insightDiscountedOrders ?> đơn</div></div></div>
                        <div class="col-6"><div class="invoice-insight-card"><div class="invoice-insight-label">Giảm trung bình / đơn có ưu đãi</div><div class="invoice-insight-value"><?= number_format($insightAvgDiscount, 0, ',', '.') ?> ₫</div></div></div>
                    </div>
                    <div class="d-flex justify-content-between small mb-2"><span class="text-muted fw-semibold">Tỷ trọng tiền giảm trong doanh số trước giảm</span><strong><?= number_format($insightDiscountRate, 1, ',', '.') ?>%</strong></div>
                    <div class="invoice-insight-bar mb-3"><div style="width: <?= max(0, min(100, $insightDiscountRate)) ?>%; background: linear-gradient(90deg,#10b981,#34d399);"></div></div>
                    <div class="invoice-insight-note"><i class="fa-solid fa-tags me-1 text-success"></i> Tổng giảm giá của các hóa đơn thành công: <strong><?= number_format($tongGiamGia, 0, ',', '.') ?> ₫</strong><?= $insightTopTier !== null && (float)$insightTopTier['tong_giam'] > 0 ? '. Nhóm khách nhận tổng mức giảm cao nhất: ' . htmlspecialchars($insightTopTier['hang'], ENT_QUOTES, 'UTF-8') . '.' : '.' ?></div>
                    <button class="btn btn-success mt-4 w-100" type="button" onclick="filterInvoicesFromInsight('Đã thanh toán')"><i class="fa-solid fa-receipt me-2"></i>Xem hóa đơn thành công</button>
                </section>
                <section class="invoice-insight-panel" data-invoice-insight="cancel" hidden>
                    <div class="row g-3 mb-3">
                        <div class="col-6"><div class="invoice-insight-card"><div class="invoice-insight-label">Đơn hủy hôm nay</div><div class="invoice-insight-value"><?= $insightTodayCanceled ?> đơn</div></div></div>
                        <div class="col-6"><div class="invoice-insight-card"><div class="invoice-insight-label">Tỷ lệ hủy toàn hệ thống</div><div class="invoice-insight-value"><?= number_format($tiLeHuy, 1, ',', '.') ?>%</div></div></div>
                    </div>
                    <div class="d-flex justify-content-between small mb-2"><span class="text-muted fw-semibold">Tỷ lệ hóa đơn hoàn thành</span><strong><?= number_format($insightSuccessRate, 1, ',', '.') ?>%</strong></div>
                    <div class="invoice-insight-bar mb-3"><div style="width: <?= max(0, min(100, $insightSuccessRate)) ?>%; background: linear-gradient(90deg,#64748b,#94a3b8);"></div></div>
                    <div class="invoice-insight-note"><i class="fa-solid fa-shield-halved me-1 text-secondary"></i> <?= $tongDonHuy > 0 ? 'Đã ghi nhận ' . $tongDonHuy . ' đơn hủy. Bạn có thể lọc để kiểm tra từng hóa đơn.' : 'Chưa ghi nhận hóa đơn hủy trong hệ thống.' ?></div>
                    <button class="btn btn-outline-secondary mt-4 w-100" type="button" onclick="filterInvoicesFromInsight('Đã hủy')"><i class="fa-solid fa-filter me-2"></i>Lọc danh sách đơn đã hủy</button>
                </section>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0"><button class="btn btn-light border w-100" type="button" data-bs-dismiss="modal">Đóng</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalExecutiveHub" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div>
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                        <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), #f97316); width:36px; height:36px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; color:#fff;">
                            <i class="fa-solid fa-chart-line"></i>
                        </span>
                        Trung tâm Phân tích Dữ liệu & Kiểm toán POS
                    </h5>
                    <div class="small text-muted ms-5">Phân tích chuỗi cửa hàng Circle K - Chi nhánh TP. Nha Trang</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <div class="d-flex flex-wrap gap-2 mb-4 p-1 bg-light rounded-3" id="hubTabsNav">
                    <button class="hub-nav-pill active flex-grow-1" onclick="switchHubTab('revenue', this)"><i class="fa-solid fa-sack-dollar me-1"></i> Cơ cấu Doanh thu</button>
                    <button class="hub-nav-pill flex-grow-1" onclick="switchHubTab('orders', this)"><i class="fa-solid fa-receipt me-1"></i> Phân khúc & AOV</button>
                    <button class="hub-nav-pill flex-grow-1" onclick="switchHubTab('discount', this)"><i class="fa-solid fa-tags me-1"></i> Hạng Khách & Voucher</button>
                    <button class="hub-nav-pill flex-grow-1" onclick="switchHubTab('cancel', this)"><i class="fa-solid fa-shield-halved me-1"></i> Kiểm toán Đơn hủy</button>
                </div>

                <div id="hubTabContent_revenue" class="hub-tab-pane">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <div class="hub-metric-tile text-center">
                                <small class="text-muted fw-bold">DOANH THU THỰC NHẬN</small>
                                <div class="num-font fw-bold text-danger fs-4 mt-1"><?= number_format($tongDoanhThu, 0, ',', '.') ?> ₫</div>
                                <span class="badge bg-success-subtle text-success mt-1">Đã trừ hủy & KM</span>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="hub-metric-tile text-center">
                                <small class="text-muted fw-bold">TỶ TRỌNG TIỀN MẶT</small>
                                <?php 
                                    $cashMoney = 0;
                                    foreach ($payStats as $ps) {
                                        if (mb_stripos($ps['pthuc'], 'tiền mặt') !== false) $cashMoney += (float)$ps['tong_tien'];
                                    }
                                    $cashPct = $tongDoanhThu > 0 ? round(($cashMoney / $tongDoanhThu) * 100, 1) : 0;
                                ?>
                                <div class="num-font fw-bold text-primary fs-4 mt-1"><?= $cashPct ?>%</div>
                                <small class="text-muted"><?= number_format($cashMoney, 0, ',', '.') ?> ₫</small>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="hub-metric-tile text-center">
                                <small class="text-muted fw-bold">THANH TOÁN SỐ (QR/VÍ)</small>
                                <div class="num-font fw-bold text-success fs-4 mt-1"><?= 100 - $cashPct ?>%</div>
                                <small class="text-muted"><?= number_format(max(0, $tongDoanhThu - $cashMoney), 0, ',', '.') ?> ₫</small>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="fw-bold text-dark mb-2 small"><i class="fa-solid fa-credit-card me-1 text-primary"></i> TỶ TRỌNG THEO HÌNH THỨC THANH TOÁN</div>
                        <?php foreach ($payStats as $ps): 
                            $pct = $tongDoanhThu > 0 ? round(((float)$ps['tong_tien'] / $tongDoanhThu) * 100, 1) : 0;
                            $barCl = 'linear-gradient(90deg, #10b981, #059669)';
                            if (mb_stripos($ps['pthuc'], 'chuyển') !== false || mb_stripos($ps['pthuc'], 'atm') !== false) $barCl = 'linear-gradient(90deg, #0ea5e9, #2563eb)';
                            if (mb_stripos($ps['pthuc'], 'ví') !== false) $barCl = 'linear-gradient(90deg, #d946ef, #a21caf)';
                        ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small fw-semibold">
                                <span><?= htmlspecialchars($ps['pthuc']) ?> (<?= $ps['so_don'] ?> đơn)</span>
                                <span class="num-font"><?= number_format($ps['tong_tien'], 0, ',', '.') ?> ₫ (<?= $pct ?>%)</span>
                            </div>
                            <div class="hub-progress-track">
                                <div class="hub-progress-bar bar-anim" data-width="<?= $pct ?>%" style="width: 0%; background: <?= $barCl ?>;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-bold text-dark small"><i class="fa-solid fa-store me-1 text-danger"></i> XẾP HẠNG DOANH SỐ TOÀN BỘ CHI NHÁNH</div>
                            <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.72rem;">Top Chi nhánh</span>
                        </div>
                        <div class="row g-2 branch-list-scroll" style="max-height: 270px; overflow-y: auto; padding-right: 4px;">
                            <?php foreach ($branchStats as $bs): 
                                $bPct = $tongDoanhThu > 0 ? round(((float)$bs['doanh_thu'] / $tongDoanhThu) * 100, 1) : 0;
                            ?>
                            <div class="col-12 col-md-6">
                                <div class="p-2 bg-white rounded border">
                                    <div class="d-flex justify-content-between small">
                                        <strong class="text-dark text-truncate" title="<?= htmlspecialchars($bs['ten_cua_hang']) ?>"><?= htmlspecialchars($bs['ten_cua_hang']) ?></strong>
                                        <span class="num-font fw-bold text-danger"><?= number_format($bs['doanh_thu'], 0, ',', '.') ?> ₫</span>
                                    </div>
                                    <div class="hub-progress-track mt-1" style="height:5px;">
                                        <div class="hub-progress-bar bar-anim" data-width="<?= min(100, $bPct * 2.5) ?>%" style="width: 0%; background: var(--ck-red);"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div id="hubTabContent_orders" class="hub-tab-pane" style="display: none;">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <div class="hub-metric-tile text-center">
                                <small class="text-muted fw-bold">GIÁ TRỊ ĐƠN TRUNG BÌNH (AOV)</small>
                                <div class="num-font fw-bold text-primary fs-3 mt-1"><?= number_format($aov, 0, ',', '.') ?> ₫</div>
                                <small class="text-muted">Trung bình mỗi khách mua</small>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="hub-metric-tile text-center">
                                <small class="text-muted fw-bold">TỶ LỆ GIAO DỊCH THÀNH CÔNG</small>
                                <div class="num-font fw-bold text-success fs-3 mt-1"><?= 100 - $tiLeHuy ?>%</div>
                                <small class="text-muted"><?= $tongDonThanhCong ?> / <?= $tongDonThanhCong + $tongDonHuy ?> đơn</small>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="hub-metric-tile text-center">
                                <small class="text-muted fw-bold">ĐƠN BỊ HỦY / HOÀN</small>
                                <div class="num-font fw-bold text-danger fs-3 mt-1"><?= $tongDonHuy ?> đơn</div>
                                <small class="text-muted">Tỷ lệ hủy: <?= $tiLeHuy ?>%</small>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border">
                        <div class="fw-bold text-dark mb-3 small"><i class="fa-solid fa-chart-column me-1 text-primary"></i> PHÂN BỔ GIÁ TRỊ GIỎ HÀNG</div>
                        <div class="row g-3 text-center">
                            <div class="col-4">
                                <div class="p-3 bg-white rounded border">
                                    <div class="small text-muted fw-bold">Dưới 50,000 ₫</div>
                                    <div class="num-font fw-bold fs-4 text-dark mt-1"><?= (int)($priceTiers['tier_thap'] ?? 0) ?></div>
                                    <span class="badge bg-secondary-subtle text-secondary mt-1">Món ăn vặt / Nước</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 bg-white rounded border">
                                    <div class="small text-muted fw-bold">50k - 100,000 ₫</div>
                                    <div class="num-font fw-bold fs-4 text-primary mt-1"><?= (int)($priceTiers['tier_trung'] ?? 0) ?></div>
                                    <span class="badge bg-primary-subtle text-primary mt-1">Combo ăn nhanh</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 bg-white rounded border">
                                    <div class="small text-muted fw-bold">Trên 100,000 ₫</div>
                                    <div class="num-font fw-bold fs-4 text-success mt-1"><?= (int)($priceTiers['tier_cao'] ?? 0) ?></div>
                                    <span class="badge bg-success-subtle text-success mt-1">Đơn nhóm / Tiện ích</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="hubTabContent_discount" class="hub-tab-pane" style="display: none;">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <div class="hub-metric-tile">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted fw-bold">TỔNG NGÂN SÁCH KHUYẾN MÃI</small>
                                        <div class="num-font fw-bold text-success fs-3 mt-1">- <?= number_format($tongGiamGia, 0, ',', '.') ?> ₫</div>
                                    </div>
                                    <i class="fa-solid fa-gift fs-1 text-success opacity-50"></i>
                                </div>
                                <div class="small text-muted mt-2">Bao gồm ưu đãi từ Voucher và chiết khấu phân hạng thành viên Circle K.</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="hub-metric-tile">
                                <small class="text-muted fw-bold">QUY TẮC CHIẾT KHẤU THÀNH VIÊN</small>
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    <span class="tier-badge tier-dong">[Đồng] 0%</span>
                                    <span class="tier-badge tier-bac">[Bạc] 2%</span>
                                    <span class="tier-badge tier-vang">[Vàng] 5%</span>
                                    <span class="tier-badge tier-kimcuong">[Kim Cương] 10%</span>
                                </div>
                                <div class="small text-muted mt-2">Hạng thẻ tự động kích hoạt chiết khấu trực tiếp trên giỏ hàng POS.</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border">
                        <div class="fw-bold text-dark mb-2 small"><i class="fa-solid fa-user-tag me-1 text-warning"></i> PHÂN BỔ ƯU ĐÃI THEO HẠNG KHÁCH HÀNG</div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered bg-white mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hạng thành viên</th>
                                        <th class="text-center">Số lượng đơn hàng</th>
                                        <th class="text-end">Tổng tiền đã chiết khấu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tierStats as $ts): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($ts['hang']) ?></strong></td>
                                        <td class="text-center fw-bold"><?= $ts['so_don'] ?> đơn</td>
                                        <td class="text-end num-font text-success fw-bold">- <?= number_format($ts['tong_giam'], 0, ',', '.') ?> ₫</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="hubTabContent_cancel" class="hub-tab-pane" style="display: none;">
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-triangle-exclamation text-danger me-1"></i> Kiểm toán & Phòng ngừa rủi ro đơn hủy</h6>
                                <small class="text-muted">Các đơn hủy đã được hoàn trả toàn bộ số lượng vào tồn kho tự động.</small>
                            </div>
                            <span class="badge bg-danger fs-6"><?= $tongDonHuy ?> ĐƠN HỦY</span>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-sm btn-danger px-3" onclick="filterOnlyCanceled()">
                                <i class="fa-solid fa-filter me-1"></i> Lọc danh sách đơn hủy
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-dark px-3" onclick="exportOrdersToCSV()">
                                <i class="fa-solid fa-file-csv me-1"></i> Xuất file báo cáo Excel (CSV)
                            </button>
                        </div>
                    </div>

                    <div class="small text-muted p-2 bg-white rounded border">
                        <i class="fa-solid fa-circle-info text-primary me-1"></i> Khi thu ngân thực hiện hủy hóa đơn, giao dịch PDO Transaction sẽ tự động cập nhật trạng thái đơn thành "Đã hủy" và kích hoạt lệnh <code>UPDATE tonkho SET so_luong = so_luong + ?</code> để tránh sai lệch kiểm kê hàng hóa tại chi nhánh.
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn-ck btn-ck-primary px-4" onclick="exportOrdersToCSV()">
                    <i class="fa-solid fa-download me-1"></i> Tải toàn bộ dữ liệu (CSV)
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalViewDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-receipt text-primary me-2"></i>Chi tiết Hóa đơn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-3">
                <div class="receipt-print-box" id="printableArea">
                    <div class="text-center">
                        <div class="brand-logo">CIRCLE K</div>
                        <div class="fw-bold">Tiện lợi 24/7</div>
                        <div class="dashed-line"></div>
                        <div class="fw-bold" id="mStoreName"></div>
                        <div id="mStoreAddr"></div>
                        <div>Điện thoại: <span id="mStorePhone"></span></div>
                    </div>
                    <div class="dashed-line"></div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Ngày: <span id="mOrderTime"></span></span>
                        <span>Số: <b id="mOrderCode"></b></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Thu ngân:</span>
                        <span id="mCashier"></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Khách hàng:</span>
                        <span id="mCustomer"></span>
                    </div>
                    <div class="dashed-line"></div>
                    <div class="fw-bold d-flex justify-content-between mb-1">
                        <span style="width: 50%">Tên Món</span>
                        <span style="width: 15%" class="text-center">SL</span>
                        <span style="width: 35%" class="text-end">T.Tiền</span>
                    </div>
                    <div id="mItemsContainer"></div>
                    <div class="dashed-line"></div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Cộng tiền hàng:</span>
                        <span id="mSubtotal"></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Khuyến mãi / Chiết khấu:</span>
                        <span id="mDiscount"></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Tiền thuế GTGT (8%):</span>
                        <span id="mVat"></span>
                    </div>
                    <div class="dashed-line"></div>
                    <div class="d-flex justify-content-between fw-bold fs-6 mb-2">
                        <span>TỔNG THANH TOÁN:</span>
                        <span class="fs-5" id="mGrandTotal"></span>
                    </div>
                    <div class="dashed-line"></div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Hình thức:</span>
                        <span class="fw-bold text-uppercase" id="mPayMethod"></span>
                    </div>
                    <div id="mCashRows" style="display:none;">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Khách đưa:</span>
                            <span class="fw-bold" id="mGiven"></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Tiền thối lại:</span>
                            <span class="fw-bold" id="mChange"></span>
                        </div>
                    </div>
                    <div class="dashed-line"></div>
                    <div class="text-center mt-3">
                        <div class="mb-2" id="mBarcodeBox"></div>
                        <div class="fw-bold mt-2">CẢM ƠN QUÝ KHÁCH!</div>
                        <div>Hẹn gặp lại</div>
                        <div class="mt-1" style="font-size: 0.75rem; font-style: italic;">(Mật khẩu Wifi: circlek123)</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 px-4">
                <button type="button" class="btn btn-light fw-bold border" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-danger fw-bold px-4" onclick="window.print()">
                    <i class="fa-solid fa-print me-2"></i>In phiếu thu
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const fVND = n => Math.round(n).toLocaleString('vi-VN') + ' ₫';

    document.querySelectorAll('.stat-float-wrapper').forEach(card => {
        card.addEventListener('click', function(e) {
            const circle = document.createElement('span');
            const diameter = Math.max(this.clientWidth, this.clientHeight);
            const radius = diameter / 2;
            const rect = this.getBoundingClientRect();
            circle.style.width = circle.style.height = `${diameter}px`;
            circle.style.left = `${e.clientX - rect.left - radius}px`;
            circle.style.top = `${e.clientY - rect.top - radius}px`;
            circle.classList.add('ripple');
            const ripple = this.querySelector('.ripple');
            if (ripple) ripple.remove();
            this.appendChild(circle);
        });
    });

    function openInvoiceQuickInsight(type) {
        const headings = {
            revenue: 'Doanh thu hệ thống',
            orders: 'Tình trạng hóa đơn',
            discount: 'Hiệu quả khuyến mãi',
            cancel: 'Theo dõi đơn hủy'
        };
        if (!Object.prototype.hasOwnProperty.call(headings, type)) return;
        document.getElementById('invoiceQuickInsightTitle').textContent = headings[type];
        document.querySelectorAll('[data-invoice-insight]').forEach(panel => {
            panel.hidden = panel.dataset.invoiceInsight !== type;
        });
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalInvoiceQuickInsight')).show();
    }

    function filterInvoicesFromInsight(status) {
        const modalEl = document.getElementById('modalInvoiceQuickInsight');
        const modal = bootstrap.Modal.getInstance(modalEl);
        const viewList = () => {
            document.getElementById('filterKeyword').value = '';
            document.getElementById('filterStore').value = '';
            document.getElementById('filterMethod').value = '';
            document.getElementById('filterStatus').value = status;
            filterInvoices();
            document.querySelector('#invoiceTableBody').closest('.table-responsive').scrollIntoView({behavior:'smooth',block:'start'});
        };
        if (modal && modalEl.classList.contains('show')) {
            modalEl.addEventListener('hidden.bs.modal', viewList, {once: true});
            modal.hide();
        } else {
            viewList();
        }
    }

    function openExecutiveHub(tabName) {
        new bootstrap.Modal(document.getElementById('modalExecutiveHub')).show();
        const navBtns = document.querySelectorAll('#hubTabsNav .hub-nav-pill');
        let targetBtn = navBtns[0];
        if (tabName === 'orders') targetBtn = navBtns[1];
        else if (tabName === 'discount') targetBtn = navBtns[2];
        else if (tabName === 'cancel') targetBtn = navBtns[3];
        switchHubTab(tabName, targetBtn);
    }

    function switchHubTab(tabName, btnEl) {
        document.querySelectorAll('#hubTabsNav .hub-nav-pill').forEach(b => b.classList.remove('active'));
        if (btnEl) btnEl.classList.add('active');
        document.querySelectorAll('.hub-tab-pane').forEach(p => p.style.display = 'none');
        const activePane = document.getElementById('hubTabContent_' + tabName);
        if (activePane) {
            activePane.style.display = '';
            setTimeout(() => {
                activePane.querySelectorAll('.bar-anim').forEach(bar => {
                    bar.style.width = bar.getAttribute('data-width');
                });
            }, 50);
        }
    }

    function filterOnlyCanceled() {
        const modalEl = document.getElementById('modalExecutiveHub');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        document.getElementById('filterStatus').value = 'Đã hủy';
        filterInvoices();
        document.querySelector('.table-responsive').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function exportOrdersToCSV() {
        const rows = document.querySelectorAll('.invoice-item-row');
        if (rows.length === 0) return alert('Không có dữ liệu hóa đơn để xuất!');

        let csv = "\uFEFFMã HĐ,Thời gian,Chi nhánh,Thu ngân,Khách hàng,Tổng thanh toán (VNĐ),Hình thức,Trạng thái\n";

        rows.forEach(r => {
            const code = r.querySelector('td:nth-child(1)').innerText.trim();
            const time = r.querySelector('td:nth-child(2)').innerText.replace(/\n/g, ' ').trim();
            const store = r.querySelector('td:nth-child(3)').innerText.trim();
            const staff = r.querySelector('td:nth-child(4)').innerText.trim();
            const cust = r.querySelector('td:nth-child(5)').innerText.replace(/\n/g, ' ').trim();
            const amt = r.getAttribute('data-amount') || '0';
            const meth = r.querySelector('td:nth-child(7)').innerText.trim();
            const st = r.querySelector('td:nth-child(8)').innerText.trim();
            csv += `"${code}","${time}","${store}","${staff}","${cust}","${amt}","${meth}","${st}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", `Bao_cao_hoa_don_CircleK_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    const invoicePageSize = 10;
    let invoiceCurrentPage = 1;

    function changeInvoicePage(page) {
        invoiceCurrentPage = page;
        filterInvoices(false);
    }

    function renderInvoicePagination(totalItems) {
        const container = document.getElementById('invoicePagination');
        const totalPages = Math.ceil(totalItems / invoicePageSize);
        container.innerHTML = '';
        if (totalPages === 0) return;

        function addPageButton(label, page, disabled = false, active = false) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm ' + (active ? 'btn-danger' : 'btn-outline-secondary');
            btn.textContent = label;
            btn.disabled = disabled;
            btn.addEventListener('click', () => changeInvoicePage(page));
            container.appendChild(btn);
        }

        addPageButton('Trước', invoiceCurrentPage - 1, invoiceCurrentPage === 1);
        const pages = totalPages <= 10
            ? Array.from({ length: totalPages }, (_, i) => i + 1)
            : [...new Set([1, 2, 3, invoiceCurrentPage - 1, invoiceCurrentPage, invoiceCurrentPage + 1, totalPages - 1, totalPages]
                .filter(p => p >= 1 && p <= totalPages))].sort((a, b) => a - b);

        pages.forEach((page, index) => {
            if (index > 0 && page - pages[index - 1] > 1) {
                const dots = document.createElement('span');
                dots.className = 'px-1 text-muted';
                dots.textContent = '…';
                container.appendChild(dots);
            }
            addPageButton(String(page), page, false, page === invoiceCurrentPage);
        });
        addPageButton('Sau', invoiceCurrentPage + 1, invoiceCurrentPage === totalPages);
    }

    function filterInvoices(resetPage = true) {
        const kw = document.getElementById('filterKeyword').value.toLowerCase().trim();
        const store = document.getElementById('filterStore').value;
        const method = document.getElementById('filterMethod').value;
        const status = document.getElementById('filterStatus').value;
        const rows = Array.from(document.querySelectorAll('.invoice-item-row'));

        const matchingRows = rows.filter(r => {
            const mSearch = r.getAttribute('data-search').includes(kw);
            const mStore = !store || r.getAttribute('data-store') === store;
            const mMethod = !method || r.getAttribute('data-method') === method;
            const mStatus = !status || r.getAttribute('data-status') === status;
            return mSearch && mStore && mMethod && mStatus;
        });

        if (resetPage) invoiceCurrentPage = 1;
        const totalPages = Math.ceil(matchingRows.length / invoicePageSize);
        invoiceCurrentPage = Math.max(1, Math.min(invoiceCurrentPage, totalPages || 1));
        rows.forEach(r => r.style.display = 'none');
        const start = (invoiceCurrentPage - 1) * invoicePageSize;
        matchingRows.slice(start, start + invoicePageSize).forEach(r => r.style.display = '');
        document.getElementById('noMatchMsg').style.display = matchingRows.length === 0 ? '' : 'none';
        renderInvoicePagination(matchingRows.length);
    }

    filterInvoices();

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

                    document.getElementById('mStoreName').textContent = od.ten_cua_hang || 'Circle K Vietnam';
                    document.getElementById('mStoreAddr').textContent = od.dia_chi_ch || '';
                    document.getElementById('mStorePhone').textContent = od.sdt_ch || '1900 3110';
                    document.getElementById('mOrderTime').textContent = od.ngay_tao_fmt || od.ngay_tao;
                    document.getElementById('mOrderCode').textContent = code;
                    document.getElementById('mCashier').textContent   = od.ten_nv || 'Thu ngân';

                    let custText = 'Khách lẻ (Vãng lai)';
                    if (od.ten_kh) {
                        const tierTag = od.hang_the ? `[${od.hang_the}] ` : '';
                        custText = tierTag + od.ten_kh;
                    }
                    document.getElementById('mCustomer').textContent = custText;

                    let itemsHtml = '';
                    items.forEach(it => {
                        itemsHtml += `
                            <div class="mb-2">
                                <div>${it.ten_san_pham}</div>
                                <div class="d-flex justify-content-between text-muted" style="font-size: 0.8rem;">
                                    <span style="width: 50%">${fVND(it.don_gia).replace(' ₫','')}</span>
                                    <span style="width: 15%" class="text-center">x${it.so_luong}</span>
                                    <span style="width: 35%" class="text-end text-dark fw-bold">${fVND(it.thanh_tien).replace(' ₫','')}</span>
                                </div>
                            </div>`;
                    });
                    document.getElementById('mItemsContainer').innerHTML = itemsHtml;

                    const subtotal = parseFloat(od.tong_tien) || 0;
                    const discount = parseFloat(od.tien_giam_gia) || 0;
                    const total    = parseFloat(od.khach_phai_tra) || 0;
                    const vat      = Math.max(0, total - (subtotal - discount));

                    document.getElementById('mSubtotal').textContent   = fVND(subtotal).replace(' ₫','');
                    document.getElementById('mDiscount').textContent   = '- ' + fVND(discount).replace(' ₫','');
                    document.getElementById('mVat').textContent        = fVND(vat).replace(' ₫','');
                    document.getElementById('mGrandTotal').textContent = fVND(total);
                    
                    const meth = od.phuong_thuc || 'Tiền mặt';
                    document.getElementById('mPayMethod').textContent  = meth;

                    const cashRows = document.getElementById('mCashRows');
                    if (meth === 'Tiền mặt') {
                        cashRows.style.display = 'block';
                        const given = (od.tien_khach_dua !== null && od.tien_khach_dua !== undefined && parseFloat(od.tien_khach_dua) > 0)
                                      ? parseFloat(od.tien_khach_dua)
                                      : total;
                        const change = (od.tien_thoi !== null && od.tien_thoi !== undefined) 
                                      ? parseFloat(od.tien_thoi) 
                                      : Math.max(0, given - total);
                        document.getElementById('mGiven').textContent  = fVND(given);
                        document.getElementById('mChange').textContent = fVND(change);
                    } else {
                        cashRows.style.display = 'none';
                    }

                    document.getElementById('mBarcodeBox').innerHTML = `<img src="https://barcode.tec-it.com/barcode.ashx?data=${encodeURIComponent(code.replace('#',''))}&code=Code128&dpi=96" alt="Barcode" style="max-width: 90%; height: 45px;">`;

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

    document.addEventListener('DOMContentLoaded', function() {
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

        document.querySelectorAll('aside a, .sidebar a, nav a, .nav-link').forEach(link => {
            const href = link.getAttribute('href') || '';
            if (href.includes('lap_hoa_don')) {
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
<?php require ROOT_PATH . '/includes/footer.php'; ?>