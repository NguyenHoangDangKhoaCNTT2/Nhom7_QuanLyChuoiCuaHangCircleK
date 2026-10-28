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

try { $pdo->exec("ALTER TABLE khuyenmai ADD COLUMN so_luong_da_dung INT DEFAULT 0"); } catch (Exception $e) { }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $act = $_POST['action'];

    if ($act === 'save_promo') {
        try {
            $id          = (int)($_POST['id'] ?? 0);
            $ten_km      = trim($_POST['ten_km'] ?? '');
            $ma_voucher  = strtoupper(trim($_POST['ma_voucher'] ?? ''));
            $loai_km     = trim($_POST['loai_km'] ?? 'Giảm %');
            $gia_tri     = (float)($_POST['gia_tri'] ?? 0);
            $so_luong    = $_POST['so_luong'] !== '' ? (int)$_POST['so_luong'] : null;
            $cuahang_id  = !empty($_POST['cuahang_id']) ? (int)$_POST['cuahang_id'] : null;
            $bat_dau     = str_replace('T', ' ', trim($_POST['thoi_gian_bat_dau'] ?? ''));
            $ket_thuc    = str_replace('T', ' ', trim($_POST['thoi_gian_ket_thuc'] ?? ''));

            if ($ten_km === '' || $ma_voucher === '' || $gia_tri <= 0) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin khuyến mãi hợp lệ!']);
                exit;
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE khuyenmai SET cuahang_id = ?, ten_km = ?, ma_voucher = ?, loai_km = ?, gia_tri = ?, so_luong = ?, thoi_gian_bat_dau = ?, thoi_gian_ket_thuc = ? WHERE id = ?");
                $stmt->execute([$cuahang_id, $ten_km, $ma_voucher, $loai_km, $gia_tri, $so_luong, $bat_dau, $ket_thuc, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO khuyenmai (cuahang_id, ten_km, ma_voucher, loai_km, gia_tri, so_luong, thoi_gian_bat_dau, thoi_gian_ket_thuc) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$cuahang_id, $ten_km, $ma_voucher, $loai_km, $gia_tri, $so_luong, $bat_dau, $ket_thuc]);
            }

            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Mã voucher đã tồn tại hoặc dữ liệu không hợp lệ: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($act === 'delete_promo') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE hoadon SET khuyenmai_id = NULL WHERE khuyenmai_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM khuyenmai WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

$cuahang_list = $pdo->query("SELECT id, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();
$promos = $pdo->query("SELECT km.*, ch.ten_cua_hang, 
                              (SELECT COUNT(*) FROM hoadon hd WHERE hd.khuyenmai_id = km.id AND hd.trang_thai IN ('Hoàn thành', 'Đã thanh toán')) AS luot_dung_hd
                       FROM khuyenmai km 
                       LEFT JOIN cuahang ch ON km.cuahang_id = ch.id 
                       ORDER BY km.id DESC")->fetchAll();

$now = time();
$totalPromos = count($promos);
$activePromos = 0;
$upcomingPromos = 0;
$expiredPromos = 0;
$totalUsedCount = 0;

foreach ($promos as &$p) {
    $start = strtotime($p['thoi_gian_bat_dau']);
    $end   = strtotime($p['thoi_gian_ket_thuc']);
    $qty   = $p['so_luong'];
    $used  = max((int)($p['so_luong_da_dung'] ?? 0), (int)($p['luot_dung_hd'] ?? 0));
    $p['used_count'] = $used;
    $totalUsedCount += $used;

    if ($qty !== null && (int)$qty <= 0) {
        $p['status_key'] = 'expired';
        $p['status_label'] = 'Hết lượt';
        $expiredPromos++;
    } elseif ($now < $start) {
        $p['status_key'] = 'upcoming';
        $p['status_label'] = 'Sắp diễn ra';
        $upcomingPromos++;
    } elseif ($now > $end) {
        $p['status_key'] = 'expired';
        $p['status_label'] = 'Đã kết thúc';
        $expiredPromos++;
    } else {
        $p['status_key'] = 'active';
        $p['status_label'] = 'Đang chạy';
        $activePromos++;
    }
}
unset($p);

$pageTitle  = 'Quản lý Khuyến mãi & Voucher';
$activeMenu = 'khuyenmai';

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

    .stat-float-wrapper { background: rgba(255, 255, 255, 0.5); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 24px; padding: 24px; position: relative; transition: all 0.35s ease; height: 100%; box-shadow: 0 10px 25px rgba(0,0,0,0.02); }
    .stat-float-wrapper:hover { background: #ffffff; transform: translateY(-4px); box-shadow: 0 18px 36px rgba(0,0,0,0.06); }
    .sf-header-row { display: inline-flex; align-items: center; position: relative; margin-bottom: 20px; }
    .sf-pill { background: #ffffff; border-radius: 50px; padding: 8px 36px 8px 18px; font-size: 0.72rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); position: relative; z-index: 2; }
    .sf-icon-box { width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; position: absolute; right: -12px; z-index: 3; box-shadow: 0 6px 15px rgba(0,0,0,0.06); }
    .sf-body-row { display: flex; align-items: baseline; line-height: 1; }
    .sf-num { font-size: 2.1rem; font-weight: 900; font-family: 'Nunito', sans-serif; }
    .sf-unit { font-size: 0.95rem; font-weight: 700; margin-left: 6px; color: #64748b; }
    .sf-footer-row { margin-top: 12px; font-size: 0.82rem; font-weight: 600; color: #94a3b8; display: flex; align-items: center; gap: 6px; }

    .th-red .sf-icon-box { background: #ffe4e6; color: #e11d48; } .th-red .sf-num { color: #e11d48; }
    .th-green .sf-icon-box { background: #d1fae5; color: #10b981; } .th-green .sf-num { color: #10b981; }
    .th-blue .sf-icon-box { background: #e0f2fe; color: #0ea5e9; } .th-blue .sf-num { color: #0ea5e9; }
    .th-amber .sf-icon-box { background: #fef3c7; color: #d97706; } .th-amber .sf-num { color: #d97706; }

    .voucher-pill { font-family: monospace; font-weight: 800; font-size: 0.88rem; padding: 5px 12px; border-radius: 8px; background: #fff1f2; color: #e11d48; border: 1px dashed #fda4af; display: inline-block; letter-spacing: 0.5px; }
    .promo-status { padding: 4px 10px; border-radius: 99px; font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 5px; }
    .promo-status.active { background: #dcfce7; color: #15803d; }
    .promo-status.upcoming { background: #dbeafe; color: #1d4ed8; }
    .promo-status.expired { background: #f1f5f9; color: #64748b; }

    .table-promo th { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 700; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .table-promo td { font-size: 0.88rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }

    .promo-stat-interactive { cursor: pointer; outline: none; isolation: isolate; }
    .promo-stat-interactive::after { content: '↗'; position: absolute; right: 19px; bottom: 17px; width: 27px; height: 27px; display: grid; place-items: center; background: rgba(255,255,255,.84); border: 1px solid #e2e8f0; color: #64748b; border-radius: 10px; font-size: 16px; font-weight: 800; transition: transform .24s, background .24s, color .24s; }
    .promo-stat-interactive:hover::after, .promo-stat-interactive:focus-visible::after { transform: translate(3px,-3px); color: #e11d48; background: #fff; }
    .promo-stat-interactive:focus-visible { outline: 3px solid rgba(225,29,72,.45); outline-offset: 3px; }
    .promo-insight-dialog { max-width: 990px; }
    .promo-insight-shell { border: none; border-radius: 23px; overflow: hidden; box-shadow: 0 35px 80px rgba(15,23,42,.25); }
    .promo-insight-banner { --insight-color: #e11d48; padding: 26px 30px; background: linear-gradient(118deg, var(--insight-color), #202a47); color: #fff; display: flex; align-items: flex-start; gap: 17px; }
    .promo-insight-banner .insight-icon { width: 53px; height: 53px; flex: 0 0 53px; border: 1px solid rgba(255,255,255,.25); background: rgba(255,255,255,.16); display: grid; place-items: center; border-radius: 16px; font-size: 23px; }
    .promo-insight-eyebrow { font-size: 11px; text-transform: uppercase; font-weight: 800; opacity: .85; letter-spacing: 1.2px; }
    .promo-insight-title { font-size: 23px; font-weight: 850; margin: 4px 0 5px; }
    .promo-insight-banner .btn-close { flex-shrink: 0; margin-left: auto; margin-top: 2px; opacity: .9; }
    .promo-insight-body { padding: 23px 27px 25px; background: #f8fafc; }
    .insight-note { background: #fff; border-radius: 13px; padding: 13px 15px; color: #475569; border: 1px solid #e2e8f0; display: flex; align-items: flex-start; gap: 10px; font-size: 13px; line-height: 1.55; margin-bottom: 18px; }
    .insight-note i { color: #0ea5e9; margin-top: 2px; }
    .insight-stat-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 12px; margin-bottom: 17px; }
    .insight-stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 15px; padding: 15px 17px; min-width: 0; }
    .insight-stat .name { color: #64748b; font-size: 12px; font-weight: 750; margin-bottom: 5px; }
    .insight-stat .number { color: #0f172a; font-size: 27px; font-weight: 900; letter-spacing: -.7px; line-height: 1.15; }
    .insight-stat .foot { color: #94a3b8; font-size: 11px; margin-top: 6px; }
    .insight-block { background: #fff; border: 1px solid #e2e8f0; border-radius: 17px; padding: 18px; height: 100%; }
    .insight-block-title { color: #0f172a; font-size: 14px; font-weight: 850; display: flex; align-items: center; gap: 8px; margin-bottom: 15px; }
    .insight-block-title i { color: #e11d48; }
    .insight-bar-row { margin-bottom: 16px; }
    .insight-bar-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; color: #475569; font-size: 12px; margin-bottom: 7px; }
    .insight-bar-top strong { color: #0f172a; }
    .insight-bar-track { width: 100%; background: #edf2f7; height: 9px; overflow: hidden; border-radius: 99px; }
    .insight-bar-value { background: var(--bar-color, #e11d48); height: 100%; border-radius: 99px; transition: width .3s ease; }
    .insight-row-button { border: 1px solid #f1f5f9; background: #fff; width: 100%; text-align: left; padding: 11px 12px; border-radius: 12px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; gap: 9px; transition: background .2s,border-color .2s,transform .2s; }
    .insight-row-button:hover, .insight-row-button:focus-visible { background: #fff8f8; border-color: #fecdd3; transform: translateX(2px); }
    .insight-row-button .insight-code { font-family: monospace; font-size: 12px; font-weight: 900; color: #e11d48; }
    .insight-row-button .insight-name { display: block; font-size: 12px; font-weight: 650; color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 360px; }
    .insight-row-button .insight-meta { color: #64748b; font-size: 11px; white-space: nowrap; text-align: right; }
    .insight-chip { display: inline-block; background: #f1f5f9; border-radius: 8px; padding: 4px 8px; font-size: 11px; color: #475569; font-weight: 750; }
    .insight-link { color: #e11d48; border: 0; background: none; font-weight: 750; font-size: 12px; padding: 5px; }
    .insight-empty { color: #94a3b8; text-align: center; font-size: 13px; padding: 22px 8px; }
    .insight-action-row { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; padding: 14px 25px; }
    .insight-action { border: none; border-radius: 10px; padding: 10px 17px; background: #e11d48; color: #fff; font-size: 13px; font-weight: 800; }
    .insight-action:hover { background: #be123c; }
    .insight-sub-action { padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 9px; background: #fff; font-weight: 750; color: #475569; font-size: 12px; }
    .insight-sub-action:hover { background: #f1f5f9; }
    @media (max-width: 767px) { .promo-insight-banner { padding: 20px 18px; } .promo-insight-title { font-size: 19px; } .promo-insight-body { padding: 16px; } .insight-stat-grid { gap: 7px; } .insight-stat { padding: 10px; } .insight-stat .number { font-size: 22px; } .insight-stat .name { font-size: 10px; } .insight-row-button .insight-name { max-width: 175px; } }

    .ck-confirm-dialog { width: min(430px, calc(100vw - 30px)); max-width: 100%; margin: auto; padding: 0; border: 0; border-radius: 20px; background: #fff; color: #1e293b; box-shadow: 0 24px 75px rgba(15,23,42,.23); overflow: visible; }
    .ck-confirm-dialog[open] { animation: ck-confirm-show .2s ease-out; }
    .ck-confirm-dialog::backdrop { background: rgba(15,23,42,.48); backdrop-filter: blur(3px); }
    @keyframes ck-confirm-show { from { opacity: 0; transform: translateY(12px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
    .ck-confirm-content { position: relative; padding: 30px 28px 25px; text-align: center; }
    .ck-confirm-close { position: absolute; right: 13px; top: 12px; width: 32px; height: 32px; display: grid; place-items: center; border: 0; background: #f1f5f9; border-radius: 10px; color: #64748b; cursor: pointer; }
    .ck-confirm-close:hover { background: #e2e8f0; color: #334155; }
    .ck-confirm-symbol { width: 60px; height: 60px; margin: 0 auto 17px; border-radius: 18px; display: grid; place-items: center; background: #fff1f2; color: #e11d48; font-size: 24px; }
    .ck-confirm-heading { margin: 0 0 9px; font-size: 1.2rem; font-weight: 800; color: #172033; }
    .ck-confirm-message { margin: 0; color: #64748b; font-size: .91rem; line-height: 1.6; white-space: pre-line; overflow-wrap: anywhere; }
    .ck-confirm-buttons { display: flex; gap: 10px; margin-top: 25px; }
    .ck-confirm-buttons button { flex: 1; min-height: 43px; padding: 9px 12px; border-radius: 11px; font-size: .88rem; font-weight: 750; cursor: pointer; transition: background .15s, transform .15s; }
    .ck-confirm-buttons button:hover { transform: translateY(-1px); }
    .ck-confirm-keep { border: 1px solid #e2e8f0; color: #334155; background: #f1f5f9; }
    .ck-confirm-remove { border: 1px solid transparent; color: white; background: linear-gradient(105deg,#e11d48,#fb573d); }
    .ck-confirm-remove:hover { filter: brightness(.97); }
    @media (max-width: 480px) { .ck-confirm-content { padding: 28px 19px 21px; } }

</style>

<script>document.querySelector('body').classList.add('layout-fixed');</script>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-float-wrapper th-red promo-stat-interactive" role="button" tabindex="0" aria-haspopup="dialog" title="Bấm để xem phân tích chi tiết" onclick="openPromoInsight('total')" onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openPromoInsight('total'); }">
            <div class="sf-header-row">
                <div class="sf-pill">TỔNG CHIẾN DỊCH</div>
                <div class="sf-icon-box"><i class="fa-solid fa-ticket"></i></div>
            </div>
            <div class="sf-body-row">
                <span class="sf-num"><?= number_format($totalPromos) ?></span>
                <span class="sf-unit">MÃ KM</span>
            </div>
            <div class="sf-footer-row"><i class="fa-solid fa-layer-group"></i> Toàn hệ thống Circle K</div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-float-wrapper th-green promo-stat-interactive" role="button" tabindex="0" aria-haspopup="dialog" title="Bấm để xem phân tích chi tiết" onclick="openPromoInsight('active')" onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openPromoInsight('active'); }">
            <div class="sf-header-row">
                <div class="sf-pill">ĐANG KÍCH HOẠT</div>
                <div class="sf-icon-box"><i class="fa-solid fa-bolt"></i></div>
            </div>
            <div class="sf-body-row">
                <span class="sf-num"><?= number_format($activePromos) ?></span>
                <span class="sf-unit">CHƯƠNG TRÌNH</span>
            </div>
            <div class="sf-footer-row"><i class="fa-solid fa-circle-check"></i> Áp dụng trực tiếp tại POS</div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-float-wrapper th-blue promo-stat-interactive" role="button" tabindex="0" aria-haspopup="dialog" title="Bấm để xem phân tích chi tiết" onclick="openPromoInsight('used')" onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openPromoInsight('used'); }">
            <div class="sf-header-row">
                <div class="sf-pill">LƯỢT ÁP DỤNG</div>
                <div class="sf-icon-box"><i class="fa-solid fa-users-viewfinder"></i></div>
            </div>
            <div class="sf-body-row">
                <span class="sf-num"><?= number_format($totalUsedCount) ?></span>
                <span class="sf-unit">LƯỢT DÙNG</span>
            </div>
            <div class="sf-footer-row"><i class="fa-solid fa-receipt"></i> Ghi nhận từ hóa đơn bán hàng</div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-float-wrapper th-amber promo-stat-interactive" role="button" tabindex="0" aria-haspopup="dialog" title="Bấm để xem phân tích chi tiết" onclick="openPromoInsight('lifecycle')" onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openPromoInsight('lifecycle'); }">
            <div class="sf-header-row">
                <div class="sf-pill">CHỜ / ĐÃ KẾT THÚC</div>
                <div class="sf-icon-box"><i class="fa-solid fa-clock-rotate-left"></i></div>
            </div>
            <div class="sf-body-row">
                <span class="sf-num"><?= number_format($upcomingPromos + $expiredPromos) ?></span>
                <span class="sf-unit">MÃ KM</span>
            </div>
            <div class="sf-footer-row"><i class="fa-solid fa-calendar-days"></i> <?= $upcomingPromos ?> sắp mở · <?= $expiredPromos ?> hết hạn</div>
        </div>
    </div>
</div>


<div class="modal fade" id="promoInsightModal" tabindex="-1" aria-labelledby="promoInsightTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable promo-insight-dialog">
        <div class="modal-content promo-insight-shell">
            <div class="promo-insight-banner" id="promoInsightBanner">
                <div class="insight-icon"><i id="promoInsightIcon" class="fa-solid fa-chart-pie"></i></div>
                <div>
                    <div class="promo-insight-eyebrow">Circle K · PHÂN TÍCH KHUYẾN MÃI</div>
                    <h4 class="promo-insight-title" id="promoInsightTitle">Phân tích khuyến mãi</h4>
                    <div style="font-size: 12px; opacity: .9;" id="promoInsightSubtitle"></div>
                </div>
                <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body promo-insight-body">
                <div class="insight-note"><i class="fa-solid fa-wand-magic-sparkles"></i><span id="promoInsightNote"></span></div>
                <div id="promoInsightMetrics" class="insight-stat-grid"></div>
                <div class="row g-3">
                    <div class="col-12 col-lg-5"><div class="insight-block" id="promoInsightLeft"></div></div>
                    <div class="col-12 col-lg-7"><div class="insight-block" id="promoInsightRight"></div></div>
                </div>
            </div>
            <div class="modal-footer border-0 insight-action-row">
                <button type="button" class="insight-sub-action" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="insight-action" id="promoInsightMainAction">Xem trong danh sách <i class="fa-solid fa-arrow-right ms-1"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="bento-panel fade-up">
    <div class="panel-header flex-wrap gap-2">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), var(--ck-orange))"><i class="fa-solid fa-tags"></i></span>
                Quản lý Chương trình Khuyến mãi & Voucher
            </h5>
            <div class="panel-subtitle ms-5">Thiết lập mã giảm giá theo phần trăm hoặc tiền mặt đồng bộ trực tiếp với màn hình thu ngân POS</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="../man_hinh_ban_hang/index.php" class="btn-ck btn-ck-soft text-decoration-none"><i class="fa-solid fa-cash-register me-1"></i> Mở màn hình POS</a>
            <button type="button" class="btn-ck btn-ck-primary" onclick="openPromoModal()"><i class="fa-solid fa-plus me-1"></i> Thêm Voucher mới</button>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-12 col-md-5">
            <div class="search-bar w-100">
                <i class="fa-solid fa-magnifying-glass s-ico"></i>
                <input type="text" id="promoSearch" placeholder="Tìm theo mã voucher (VD: WELCOME10), tên chương trình..." oninput="filterPromos()">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <select class="form-select" id="promoTypeFilter" onchange="filterPromos()" style="height: 44px; border-radius: 10px;">
                <option value="">Loại ưu đãi: Tất cả</option>
                <option value="Giảm %">Giảm theo phần trăm (%)</option>
                <option value="Giảm tiền">Giảm tiền trực tiếp (VNĐ)</option>
            </select>
        </div>
        <div class="col-6 col-md-4">
            <select class="form-select" id="promoStatusFilter" onchange="filterPromos()" style="height: 44px; border-radius: 10px;">
                <option value="">Trạng thái: Tất cả</option>
                <option value="active">Đang chạy</option>
                <option value="upcoming">Sắp diễn ra</option>
                <option value="expired">Đã kết thúc / Hết lượt</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-promo align-middle">
            <thead>
                <tr>
                    <th>Mã Voucher</th>
                    <th>Tên chương trình khuyến mãi</th>
                    <th>Phạm vi áp dụng</th>
                    <th>Loại & Giá trị</th>
                    <th class="text-center">Số lượng còn</th>
                    <th>Thời gian hiệu lực</th>
                    <th class="text-center">Trạng thái</th>
                    <th class="text-center">Thao tác</th>
                </tr>
            </thead>
            <tbody id="promoTableBody">
                <?php foreach ($promos as $km): 
                    $isPercent = (mb_stripos($km['loai_km'], '%') !== false || mb_stripos($km['loai_km'], 'phần trăm') !== false);
                ?>
                <tr class="promo-row"
                    data-search="<?= mb_strtolower($km['ma_voucher'] . ' ' . $km['ten_km'] . ' ' . ($km['ten_cua_hang'] ?? 'Toàn hệ thống')) ?>"
                    data-type="<?= $isPercent ? 'Giảm %' : 'Giảm tiền' ?>"
                    data-status="<?= $km['status_key'] ?>">
                    <td><span class="voucher-pill"><?= htmlspecialchars($km['ma_voucher']) ?></span></td>
                    <td>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($km['ten_km']) ?></div>
                        <small class="text-muted">Đã áp dụng: <strong><?= (int)$km['used_count'] ?></strong> đơn hàng</small>
                    </td>
                    <td>
                        <?php if (!empty($km['ten_cua_hang'])): ?>
                            <span class="badge bg-light text-dark border"><i class="fa-solid fa-store text-danger me-1"></i><?= htmlspecialchars($km['ten_cua_hang']) ?></span>
                        <?php else: ?>
                            <span class="badge bg-primary-subtle text-primary"><i class="fa-solid fa-globe me-1"></i>Toàn hệ thống (12 CN)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isPercent): ?>
                            <span class="num-font fw-bold text-danger fs-6">-<?= number_format($km['gia_tri']) ?>%</span>
                            <div class="small text-muted">Chiết khấu %</div>
                        <?php else: ?>
                            <span class="num-font fw-bold text-success fs-6">-<?= number_format($km['gia_tri']) ?> ₫</span>
                            <div class="small text-muted">Trừ tiền mặt</div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="num-font fw-bold text-dark"><?= $km['so_luong'] !== null ? number_format($km['so_luong']) : '∞' ?></span>
                    </td>
                    <td>
                        <div class="small fw-semibold text-dark"><i class="fa-regular fa-calendar-check text-success me-1"></i><?= date('d/m/Y H:i', strtotime($km['thoi_gian_bat_dau'])) ?></div>
                        <div class="small text-muted"><i class="fa-regular fa-calendar-xmark text-danger me-1"></i><?= date('d/m/Y H:i', strtotime($km['thoi_gian_ket_thuc'])) ?></div>
                    </td>
                    <td class="text-center">
                        <span class="promo-status <?= $km['status_key'] ?>">
                            <i class="fa-solid <?= $km['status_key'] === 'active' ? 'fa-circle-check' : ($km['status_key'] === 'upcoming' ? 'fa-clock' : 'fa-circle-stop') ?>"></i>
                            <?= $km['status_label'] ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-light border text-primary me-1" title="Chỉnh sửa"
                                onclick='editPromo(<?= json_encode($km, JSON_UNESCAPED_UNICODE) ?>)'>
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-light border text-danger" title="Xóa voucher"
                                onclick="deletePromo(<?= (int)$km['id'] ?>, '<?= htmlspecialchars(addslashes($km['ma_voucher'])) ?>')">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div id="emptyPromoMsg" class="text-center py-4 text-muted" style="display: none;">
            <i class="fa-solid fa-ticket fs-2 mb-2 d-block opacity-25"></i> Không tìm thấy chương trình khuyến mãi phù hợp.
        </div>
    </div>
    <div id="promoPagination" class="d-flex flex-wrap justify-content-center align-items-center gap-2 mt-3" aria-label="Phân trang khuyến mãi"></div>
</div>

<div class="modal fade" id="modalPromoForm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-dark" id="promoModalTitle"><i class="fa-solid fa-tags text-danger me-2"></i>Thêm Chương trình Khuyến mãi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formPromo" onsubmit="submitPromoForm(event)">
                    <input type="hidden" id="promo_id" value="0">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">TÊN CHƯƠNG TRÌNH KHUYẾN MÃI</label>
                        <input type="text" class="form-control" id="ten_km" required placeholder="VD: Giảm 15% mừng lễ / Happy Hour...">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">MÃ VOUCHER (IN HOA)</label>
                            <input type="text" class="form-control fw-bold text-uppercase font-monospace" id="ma_voucher" required placeholder="VD: SUMMER15">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">CHI NHÁNH ÁP DỤNG</label>
                            <select class="form-select" id="cuahang_id">
                                <option value="">🌐 Toàn hệ thống (12 Chi nhánh)</option>
                                <?php foreach ($cuahang_list as $ch): ?>
                                    <option value="<?= $ch['id'] ?>">🏪 <?= htmlspecialchars($ch['ten_cua_hang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">LOẠI KHUYẾN MÃI</label>
                            <select class="form-select" id="loai_km">
                                <option value="Giảm %">Giảm %</option>
                                <option value="Giảm tiền">Giảm tiền (VNĐ)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">GIÁ TRỊ GIẢM</label>
                            <input type="number" step="0.01" min="1" class="form-control num-font fw-bold" id="gia_tri" required placeholder="10 hoặc 5000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">SỐ LƯỢNG MÃ</label>
                            <input type="number" min="0" class="form-control num-font" id="so_luong" placeholder="Để trống = Vô hạn">
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">THỜI GIAN BẮT ĐẦU</label>
                            <input type="datetime-local" class="form-control" id="thoi_gian_bat_dau" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">THỜI GIAN KẾT THÚC</label>
                            <input type="datetime-local" class="form-control" id="thoi_gian_ket_thuc" required>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn-ck btn-ck-primary px-4" id="btnSavePromo"><i class="fa-solid fa-floppy-disk me-1"></i> Lưu Voucher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<dialog class="ck-confirm-dialog" id="ckConfirmDialog" aria-labelledby="ckConfirmTitle" aria-describedby="ckConfirmMessage">
    <div class="ck-confirm-content">
        <button type="button" class="ck-confirm-close" id="ckConfirmClose" aria-label="Đóng"><i class="fa-solid fa-xmark"></i></button>
        <div class="ck-confirm-symbol"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h4 class="ck-confirm-heading" id="ckConfirmTitle"></h4>
        <p class="ck-confirm-message" id="ckConfirmMessage"></p>
        <div class="ck-confirm-buttons">
            <button type="button" class="ck-confirm-keep" id="ckConfirmKeep">Giữ lại</button>
            <button type="button" class="ck-confirm-remove" id="ckConfirmRemove">Xác nhận</button>
        </div>
    </div>
</dialog>

<script>

    function openCkConfirm(title, message, confirmLabel, onConfirm) {
        const dialog = document.getElementById('ckConfirmDialog');
        document.getElementById('ckConfirmTitle').textContent = title;
        document.getElementById('ckConfirmMessage').textContent = message;
        const confirmButton = document.getElementById('ckConfirmRemove');
        confirmButton.textContent = confirmLabel;
        document.getElementById('ckConfirmKeep').onclick = () => dialog.close();
        document.getElementById('ckConfirmClose').onclick = () => dialog.close();
        confirmButton.onclick = () => {
            dialog.close();
            onConfirm();
        };
        if (!dialog.open) dialog.showModal();
    }

    const promoInsightData = <?= json_encode(array_map(static function ($p) {
        return [
            'id' => (int)$p['id'],
            'code' => (string)$p['ma_voucher'],
            'name' => (string)$p['ten_km'],
            'type' => (string)$p['loai_km'],
            'value' => (float)$p['gia_tri'],
            'scope' => $p['ten_cua_hang'] ?: 'Toàn hệ thống',
            'quantity' => $p['so_luong'] === null ? null : (int)$p['so_luong'],
            'used' => (int)$p['used_count'],
            'status' => (string)$p['status_key'],
            'statusLabel' => (string)$p['status_label'],
            'start' => (string)$p['thoi_gian_bat_dau'],
            'end' => (string)$p['thoi_gian_ket_thuc']
        ];
    }, $promos), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const promoInsightNum = new Intl.NumberFormat('vi-VN');

    function promoInsightEscape(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[ch]));
    }

    function promoInsightDate(s) {
        if (!s) return new Date(NaN);
        return new Date(String(s).replace(' ', 'T'));
    }

    function promoInsightDateText(s) {
        const d = promoInsightDate(s);
        return isNaN(d.getTime()) ? 'Chưa rõ' : d.toLocaleString('vi-VN', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
    }

    function promoInsightTile(label, value, detail) {
        return `<div class="insight-stat"><div class="name">${promoInsightEscape(label)}</div><div class="number">${promoInsightEscape(value)}</div><div class="foot">${promoInsightEscape(detail)}</div></div>`;
    }

    function promoInsightBar(label, count, total, color) {
        const width = total > 0 ? Math.max(0, Math.min(100, count / total * 100)) : 0;
        return `<div class="insight-bar-row"><div class="insight-bar-top"><span>${promoInsightEscape(label)}</span><strong>${promoInsightNum.format(count)}</strong></div><div class="insight-bar-track"><div class="insight-bar-value" style="width:${width.toFixed(2)}%;--bar-color:${color}"></div></div></div>`;
    }

    function promoInsightItem(item, meta) {
        return `<button type="button" class="insight-row-button" data-promo-code="${promoInsightEscape(item.code)}" title="Tìm mã này trong danh sách"><span style="min-width:0"><span class="insight-code">${promoInsightEscape(item.code)}</span><span class="insight-name">${promoInsightEscape(item.name)}</span></span><span class="insight-meta">${promoInsightEscape(meta)}<br><i class="fa-solid fa-magnifying-glass" style="font-size:10px"></i> Xem mã</span></button>`;
    }

    function promoInsightBlockTitle(icon, label) {
        return `<div class="insight-block-title"><i class="fa-solid ${icon}"></i>${promoInsightEscape(label)}</div>`;
    }

    function promoInsightEmpty(message) {
        return `<div class="insight-empty"><i class="fa-solid fa-circle-info d-block fs-4 mb-2"></i>${promoInsightEscape(message)}</div>`;
    }

    function promoInsightGoToList(status = '', code = '') {
        document.getElementById('promoSearch').value = code;
        document.getElementById('promoTypeFilter').value = '';
        document.getElementById('promoStatusFilter').value = status;
        filterPromos();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('promoInsightModal')).hide();
        document.getElementById('promoSearch').closest('.bento-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function openPromoInsight(kind) {
        const list = promoInsightData;
        const now = new Date();
        const active = list.filter(p => p.status === 'active');
        const upcoming = list.filter(p => p.status === 'upcoming');
        const expired = list.filter(p => p.status === 'expired');
        const hasUses = list.filter(p => p.used > 0);
        const usages = list.reduce((sum, p) => sum + p.used, 0);
        const soon = active.filter(p => promoInsightDate(p.end).getTime() - now.getTime() <= 7 * 86400000 && promoInsightDate(p.end).getTime() >= now.getTime());
        const lowStock = active.filter(p => p.quantity !== null && p.quantity <= 20);
        const definition = {
            total: {title:'Toàn cảnh chiến dịch', sub:'Phân bổ chương trình và phạm vi áp dụng', icon:'fa-chart-pie', color:'#e11d48', action:'Xem tất cả khuyến mãi', status:''},
            active: {title:'Trung tâm chiến dịch đang chạy', sub:'Ưu đãi đang có hiệu lực và những điểm cần chú ý', icon:'fa-bolt', color:'#059669', action:'Lọc khuyến mãi đang chạy', status:'active'},
            used: {title:'Hiệu quả sử dụng voucher', sub:'Xếp hạng theo số lượt đã áp dụng', icon:'fa-chart-line', color:'#0284c7', action:'Xem danh sách voucher', status:''},
            lifecycle: {title:'Lịch khuyến mãi & vòng đời', sub:'Theo dõi chương trình sắp diễn ra và đã dừng', icon:'fa-calendar-days', color:'#d97706', action:'Lọc chương trình sắp diễn ra', status:'upcoming'}
        }[kind];
        if (!definition) return;
        document.getElementById('promoInsightBanner').style.setProperty('--insight-color', definition.color);
        document.getElementById('promoInsightTitle').textContent = definition.title;
        document.getElementById('promoInsightSubtitle').textContent = definition.sub;
        document.getElementById('promoInsightIcon').className = 'fa-solid ' + definition.icon;
        const metric = document.getElementById('promoInsightMetrics');
        const left = document.getElementById('promoInsightLeft');
        const right = document.getElementById('promoInsightRight');
        const note = document.getElementById('promoInsightNote');
        const action = document.getElementById('promoInsightMainAction');
        action.innerHTML = `${promoInsightEscape(definition.action)} <i class="fa-solid fa-arrow-right ms-1"></i>`;
        action.onclick = () => promoInsightGoToList(definition.status);

        if (kind === 'total') {
            const percentage = list.length ? Math.round(active.length / list.length * 100) : 0;
            const global = list.filter(p => p.scope === 'Toàn hệ thống').length;
            const percentType = list.filter(p => String(p.type).includes('%')).length;
            note.textContent = `${percentage}% chương trình đang hoạt động. Thống kê này được tính từ danh sách khuyến mãi hiện có, không phải số liệu mô phỏng.`;
            metric.innerHTML = promoInsightTile('Tổng chiến dịch',promoInsightNum.format(list.length),'Tất cả trạng thái') + promoInsightTile('Đang hoạt động',promoInsightNum.format(active.length),`${percentage}% tổng số`) + promoInsightTile('Toàn hệ thống',promoInsightNum.format(global),'Áp dụng nhiều chi nhánh');
            left.innerHTML = promoInsightBlockTitle('fa-chart-simple','Cơ cấu trạng thái') + promoInsightBar('Đang chạy',active.length,list.length,'#10b981') + promoInsightBar('Sắp diễn ra',upcoming.length,list.length,'#3b82f6') + promoInsightBar('Kết thúc / hết lượt',expired.length,list.length,'#f59e0b') + `<div class="insight-chip">${percentType} giảm % · ${list.length-percentType} giảm tiền</div>`;
            const recent = [...list].sort((a,b) => b.id-a.id).slice(0,5);
            right.innerHTML = promoInsightBlockTitle('fa-clock-rotate-left','5 chương trình tạo gần đây') + (recent.length ? recent.map(p => promoInsightItem(p,p.statusLabel)).join('') : promoInsightEmpty('Chưa có khuyến mãi'));
        } else if (kind === 'active') {
            const nearest = [...active].sort((a,b) => promoInsightDate(a.end)-promoInsightDate(b.end)).slice(0,5);
            const warnings = [...new Map([...soon,...lowStock].map(p=>[p.id,p])).values()].sort((a,b)=>promoInsightDate(a.end)-promoInsightDate(b.end)).slice(0,5);
            note.textContent = soon.length || lowStock.length ? `Cần chú ý: ${soon.length} mã sẽ hết hạn trong 7 ngày và ${lowStock.length} mã còn tối đa 20 lượt. Một mã có thể thuộc cả hai nhóm.` : 'Chưa phát hiện ưu đãi đang chạy nào sắp hết hạn trong 7 ngày hoặc chỉ còn tối đa 20 lượt.';
            metric.innerHTML = promoInsightTile('Đang chạy',promoInsightNum.format(active.length),'Có hiệu lực hiện tại') + promoInsightTile('Hết hạn ≤ 7 ngày',promoInsightNum.format(soon.length),'Cần kiểm tra thời hạn') + promoInsightTile('Sắp hết lượt',promoInsightNum.format(lowStock.length),'Còn tối đa 20 lượt');
            left.innerHTML = promoInsightBlockTitle('fa-triangle-exclamation','Danh sách cần chú ý') + (warnings.length ? warnings.map(p=>promoInsightItem(p,p.quantity !== null && p.quantity <=20 ? `Còn ${p.quantity} lượt` : `Hết ${promoInsightDateText(p.end)}`)).join('') : promoInsightEmpty('Chưa có cảnh báo cần xử lý'));
            right.innerHTML = promoInsightBlockTitle('fa-hourglass-half','5 ưu đãi gần hết hạn nhất') + (nearest.length ? nearest.map(p=>promoInsightItem(p,`Đến ${promoInsightDateText(p.end)}`)).join('') : promoInsightEmpty('Hiện chưa có khuyến mãi đang chạy'));
        } else if (kind === 'used') {
            const mostUsed = [...list].sort((a,b)=>b.used-a.used).filter(p=>p.used>0).slice(0,5);
            const neverUsedActive = active.filter(p=>p.used===0).slice(0,5);
            const peak = mostUsed.length ? mostUsed[0].used : 0;
            note.textContent = 'Số lượt sử dụng lấy từ bộ đếm voucher hoặc hóa đơn đã hoàn thành (giữ nguyên cách tính hiện tại của hệ thống). Đây không phải tỷ lệ chuyển đổi khách hàng.';
            metric.innerHTML = promoInsightTile('Tổng lượt áp dụng',promoInsightNum.format(usages),'Cộng các voucher') + promoInsightTile('Mã đã có lượt dùng',promoInsightNum.format(hasUses.length),'Ít nhất 1 lần') + promoInsightTile('Đang chạy chưa dùng',promoInsightNum.format(active.filter(p=>p.used===0).length),'Có thể cần quảng bá thêm');
            left.innerHTML = promoInsightBlockTitle('fa-ranking-star','Top 5 voucher được dùng') + (mostUsed.length ? mostUsed.map(p=>promoInsightBar(p.code,p.used,peak,'#0ea5e9')).join('') : promoInsightEmpty('Chưa có dữ liệu lượt sử dụng'));
            right.innerHTML = promoInsightBlockTitle('fa-lightbulb','Mã đang chạy chưa có lượt sử dụng') + (neverUsedActive.length ? neverUsedActive.map(p=>promoInsightItem(p,'0 lượt')).join('') : promoInsightEmpty('Tất cả mã đang chạy đều đã được dùng hoặc không có mã đang chạy'));
        } else {
            const soonest = [...upcoming].sort((a,b)=>promoInsightDate(a.start)-promoInsightDate(b.start)).slice(0,5);
            const ended = [...expired].sort((a,b)=>promoInsightDate(b.end)-promoInsightDate(a.end)).slice(0,5);
            const exhausted = expired.filter(p=>p.statusLabel==='Hết lượt').length;
            note.textContent = `Có ${upcoming.length} chương trình sắp bắt đầu, ${expired.length} chương trình đã dừng, trong đó ${exhausted} mã hết lượt. Chọn từng mã để tìm nhanh hoặc dùng nút lọc trạng thái.`;
            metric.innerHTML = promoInsightTile('Sắp diễn ra',promoInsightNum.format(upcoming.length),'Chờ đến ngày bắt đầu') + promoInsightTile('Đã dừng',promoInsightNum.format(expired.length),'Kết thúc hoặc hết lượt') + promoInsightTile('Hết lượt',promoInsightNum.format(exhausted),'Cần cân nhắc bổ sung');
            left.innerHTML = promoInsightBlockTitle('fa-calendar-plus','5 chương trình sắp mở') + (soonest.length ? soonest.map(p=>promoInsightItem(p,`Từ ${promoInsightDateText(p.start)}`)).join('') : promoInsightEmpty('Không có chương trình sắp diễn ra')) + '<button type="button" class="insight-sub-action mt-2" data-promo-filter="upcoming">Xem tất cả sắp diễn ra</button>';
            right.innerHTML = promoInsightBlockTitle('fa-calendar-xmark','5 chương trình đã dừng') + (ended.length ? ended.map(p=>promoInsightItem(p,p.statusLabel)).join('') : promoInsightEmpty('Chưa có chương trình kết thúc')) + '<button type="button" class="insight-sub-action mt-2" data-promo-filter="expired">Xem tất cả đã dừng</button>';
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('promoInsightModal')).show();
    }

    document.getElementById('promoInsightModal').addEventListener('click', function(event) {
        const codeButton = event.target.closest('[data-promo-code]');
        const filterButton = event.target.closest('[data-promo-filter]');
        if (codeButton) promoInsightGoToList('',codeButton.dataset.promoCode);
        else if (filterButton) promoInsightGoToList(filterButton.dataset.promoFilter);
    });
    const promoPageSize = 10;
    let promoCurrentPage = 1;

    function changePromoPage(page) {
        promoCurrentPage = page;
        filterPromos(false);
    }

    function renderPromoPagination(totalItems) {
        const container = document.getElementById('promoPagination');
        const totalPages = Math.ceil(totalItems / promoPageSize);
        container.innerHTML = '';
        if (totalPages === 0) return;

        function addPageButton(label, page, disabled = false, active = false) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm ' + (active ? 'btn-danger' : 'btn-outline-secondary');
            btn.textContent = label;
            btn.disabled = disabled;
            btn.addEventListener('click', () => changePromoPage(page));
            container.appendChild(btn);
        }

        addPageButton('Trước', promoCurrentPage - 1, promoCurrentPage === 1);
        const pages = totalPages <= 10
            ? Array.from({ length: totalPages }, (_, i) => i + 1)
            : [...new Set([1, 2, 3, promoCurrentPage - 1, promoCurrentPage, promoCurrentPage + 1, totalPages - 1, totalPages]
                .filter(p => p >= 1 && p <= totalPages))].sort((a, b) => a - b);

        pages.forEach((page, index) => {
            if (index > 0 && page - pages[index - 1] > 1) {
                const dots = document.createElement('span');
                dots.className = 'px-1 text-muted';
                dots.textContent = '…';
                container.appendChild(dots);
            }
            addPageButton(String(page), page, false, page === promoCurrentPage);
        });
        addPageButton('Sau', promoCurrentPage + 1, promoCurrentPage === totalPages);
    }

    function filterPromos(resetPage = true) {
        const kw = document.getElementById('promoSearch').value.toLowerCase().trim();
        const type = document.getElementById('promoTypeFilter').value;
        const st = document.getElementById('promoStatusFilter').value;
        const rows = Array.from(document.querySelectorAll('.promo-row'));

        const matchingRows = rows.filter(r => {
            const mKw = r.dataset.search.includes(kw);
            const mType = !type || r.dataset.type === type;
            const mSt = !st || r.dataset.status === st;
            return mKw && mType && mSt;
        });

        if (resetPage) promoCurrentPage = 1;
        const totalPages = Math.ceil(matchingRows.length / promoPageSize);
        promoCurrentPage = Math.max(1, Math.min(promoCurrentPage, totalPages || 1));
        rows.forEach(r => r.style.display = 'none');
        const start = (promoCurrentPage - 1) * promoPageSize;
        matchingRows.slice(start, start + promoPageSize).forEach(r => r.style.display = '');
        document.getElementById('emptyPromoMsg').style.display = matchingRows.length === 0 ? '' : 'none';
        renderPromoPagination(matchingRows.length);
    }

    filterPromos();

    function openPromoModal() {
        document.getElementById('promoModalTitle').innerHTML = '<i class="fa-solid fa-tags text-danger me-2"></i>Thêm Chương trình Khuyến mãi';
        document.getElementById('promo_id').value = 0;
        document.getElementById('ten_km').value = '';
        document.getElementById('ma_voucher').value = '';
        document.getElementById('cuahang_id').value = '';
        document.getElementById('loai_km').value = 'Giảm %';
        document.getElementById('gia_tri').value = '';
        document.getElementById('so_luong').value = 200;
        document.getElementById('thoi_gian_bat_dau').value = '2026-10-01T00:00';
        document.getElementById('thoi_gian_ket_thuc').value = '2026-12-31T23:59';
        new bootstrap.Modal(document.getElementById('modalPromoForm')).show();
    }

    function editPromo(km) {
        document.getElementById('promoModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-primary me-2"></i>Chỉnh sửa Voucher [' + km.ma_voucher + ']';
        document.getElementById('promo_id').value = km.id;
        document.getElementById('ten_km').value = km.ten_km;
        document.getElementById('ma_voucher').value = km.ma_voucher;
        document.getElementById('cuahang_id').value = km.cuahang_id || '';
        document.getElementById('loai_km').value = (km.loai_km && km.loai_km.includes('%')) ? 'Giảm %' : 'Giảm tiền';
        document.getElementById('gia_tri').value = parseFloat(km.gia_tri);
        document.getElementById('so_luong').value = km.so_luong !== null ? km.so_luong : '';
        document.getElementById('thoi_gian_bat_dau').value = km.thoi_gian_bat_dau.replace(' ', 'T').slice(0, 16);
        document.getElementById('thoi_gian_ket_thuc').value = km.thoi_gian_ket_thuc.replace(' ', 'T').slice(0, 16);
        new bootstrap.Modal(document.getElementById('modalPromoForm')).show();
    }

    function submitPromoForm(e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('action', 'save_promo');
        fd.append('id', document.getElementById('promo_id').value);
        fd.append('ten_km', document.getElementById('ten_km').value);
        fd.append('ma_voucher', document.getElementById('ma_voucher').value);
        fd.append('cuahang_id', document.getElementById('cuahang_id').value);
        fd.append('loai_km', document.getElementById('loai_km').value);
        fd.append('gia_tri', document.getElementById('gia_tri').value);
        fd.append('so_luong', document.getElementById('so_luong').value);
        fd.append('thoi_gian_bat_dau', document.getElementById('thoi_gian_bat_dau').value);
        fd.append('thoi_gian_ket_thuc', document.getElementById('thoi_gian_ket_thuc').value);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) location.reload();
                else alert(d.message);
            })
            .catch(err => alert('Lỗi kết nối: ' + err));
    }

    function deletePromo(id, code) {
        openCkConfirm(
            'Xóa mã voucher ' + code + '?',
            'Mã khuyến mãi này sẽ bị xóa khỏi hệ thống. Thao tác này không thể hoàn tác.',
            'Xóa voucher',
            () => {
                const fd = new FormData();
                fd.append('action', 'delete_promo');
                fd.append('id', id);

                fetch(window.location.href, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) location.reload();
                        else alert('Lỗi xóa: ' + d.message);
                    })
                    .catch(err => alert('Lỗi kết nối: ' + err));
            }
        );
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
            if (href.includes('quan_ly_khuyen_mai')) {
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