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

$posVoucherStoreCodes = [
    'WELCOME10' => 'CK005',
    'GIAM5K' => 'CK007',
    'GIAM10K' => 'CK008',
    'HAPPY15' => 'CK009',
    'NHAGIAO20' => 'CK001',
    'BF15' => 'CK002',
    'NOEL10' => 'CK003',
    'DEMO26CK01' => 'CK009',
    'DEMO26CK02' => 'CK006',
    'DEMO26CK03' => 'CK007',
    'DEMO26CK04' => 'CK010',
    'DEMO26CK05' => 'CK010',
    'DEMO26CK06' => 'CK002',
    'DEMO26CK07' => 'CK011',
    'DEMO26CK08' => 'CK001',
    'DEMO26CK09' => 'CK010',
    'DEMO26CK10' => 'CK003',
    'DEMO26CK11' => 'CK007',
    'DEMO26CK12' => 'CK003',
    'DEMO26CK13' => 'CK006',
    'DEMO26CK14' => 'CK008',
    'DEMO26CK15' => 'CK012',
    'DEMO26CK16' => 'CK001',
    'DEMO26CK17' => 'CK002',
    'DEMO26CK18' => 'CK005',
    'DEMO26CK19' => 'CK004',
    'DEMO26CK20' => 'CK008',
    'DEMO26CK21' => 'CK011',
    'DEMO26CK22' => 'CK009',
    'DEMO26CK23' => 'CK002',
    'DEMO26CK24' => 'CK001',
    'DEMO26CK25' => 'CK011',
    'DEMO26CK26' => 'CK004',
    'DEMO26CK27' => 'CK006',
    'DEMO26CK28' => 'CK003',
    'DEMO26CK29' => 'CK012',
    'DEMO26CK30' => 'CK004',
    'DEMO26CK31' => 'CK005',
];

function resolvePosVoucherStoreId($voucher, $storeCodeIds, $voucherAssignments) {
    if ($voucher['cuahang_id'] !== null) return (int)$voucher['cuahang_id'];
    $voucherCode = strtoupper(trim((string)($voucher['ma_voucher'] ?? '')));
    $branchCode = $voucherAssignments[$voucherCode] ?? '';
    return $branchCode !== '' ? (int)($storeCodeIds[$branchCode] ?? 0) : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pos_action']) && $_POST['pos_action'] === 'create_order_pending') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $cart           = json_decode($_POST['cart'] ?? '[]', true);
        $cuahang_id     = (int)($_POST['cuahang_id'] ?? 1);
        $nhanvien_id    = (int)($_POST['nhanvien_id'] ?? 1);
        $khachhang_id   = !empty($_POST['khachhang_id']) ? (int)$_POST['khachhang_id'] : null;
        $khuyenmai_id   = !empty($_POST['khuyenmai_id']) ? (int)$_POST['khuyenmai_id'] : null;
        $tong_tien      = (float)($_POST['tong_tien'] ?? 0);
        $tien_giam_gia  = (float)($_POST['tien_giam_gia'] ?? 0);
        $khach_phai_tra = (float)($_POST['khach_phai_tra'] ?? 0);

        if (empty($cart)) { 
            echo json_encode(['success' => false, 'message' => 'Giỏ hàng trống!']); 
            exit; 
        }

        if ($khuyenmai_id !== null) {
            $stmtPromo = $pdo->prepare("SELECT id, cuahang_id, ma_voucher FROM khuyenmai WHERE id = ? AND thoi_gian_bat_dau <= ? AND thoi_gian_ket_thuc >= ? AND (so_luong IS NULL OR so_luong > 0) LIMIT 1");
            $promoNow = date('Y-m-d H:i:s');
            $stmtPromo->execute([$khuyenmai_id, $promoNow, $promoNow]);
            $promo = $stmtPromo->fetch();
            $storeCodeIds = $pdo->query("SELECT ma_cua_hang, id FROM cuahang")->fetchAll(PDO::FETCH_KEY_PAIR);
            $promoStoreId = $promo ? resolvePosVoucherStoreId($promo, $storeCodeIds, $posVoucherStoreCodes) : -1;
            if (!$promo || ($promoStoreId !== 0 && $promoStoreId !== $cuahang_id)) {
                echo json_encode(['success' => false, 'message' => 'Mã khuyến mãi không áp dụng cho cửa hàng này hoặc đã hết hiệu lực.']);
                exit;
            }
        }

        $pdo->beginTransaction();
        
        $stmtHd = $pdo->prepare("INSERT INTO hoadon (cuahang_id, nhanvien_id, khachhang_id, khuyenmai_id, ngay_tao, tong_tien, tien_giam_gia, khach_phai_tra, trang_thai) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, 'Chờ thanh toán')");
        $stmtHd->execute([$cuahang_id, $nhanvien_id, $khachhang_id, $khuyenmai_id, $tong_tien, $tien_giam_gia, $khach_phai_tra]);
        $hoadon_id = (int)$pdo->lastInsertId();

        $stmtCt = $pdo->prepare("INSERT INTO chitiethoadon (hoadon_id, sanpham_id, so_luong, don_gia, thanh_tien) VALUES (?, ?, ?, ?, ?)");
        $stmtTk = $pdo->prepare("UPDATE tonkho SET so_luong = GREATEST(0, so_luong - ?) WHERE sanpham_id = ? AND cuahang_id = ?");

        foreach ($cart as $item) {
            $stmtCt->execute([$hoadon_id, (int)$item['id'], (int)$item['qty'], (float)$item['price'], (int)$item['qty'] * (float)$item['price']]);
            $stmtTk->execute([(int)$item['qty'], (int)$item['id'], $cuahang_id]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'hoadon_id' => $hoadon_id]); 
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]); 
        exit;
    }
}

$sanpham_list = $pdo->query("SELECT sp.*, dm.ten_danh_muc FROM sanpham sp LEFT JOIN danhmuc dm ON sp.danhmuc_id = dm.id WHERE sp.trang_thai = 1 ORDER BY sp.id ASC")->fetchAll();
$cuahang_list = $pdo->query("SELECT id, ma_cua_hang, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();
$posStoreCodeIds = array_column($cuahang_list, 'id', 'ma_cua_hang');
$posStoreNames = array_column($cuahang_list, 'ten_cua_hang', 'id');
$all_nv = $pdo->query("SELECT id, ho_ten, cuahang_id FROM nhanvien ORDER BY id ASC")->fetchAll();

$mgr_nam   = null;
$mgr_phu   = null;
$mgr_thai  = null;
$branch_staff = [];

foreach ($all_nv as $nv) {
    $id = (int)$nv['id'];
    $name = trim($nv['ho_ten']);
    $lower = mb_strtolower($name, 'UTF-8');

    if ($id === 1 || (strpos($lower, 'khoa') !== false && (strpos($lower, 'đăng khoa') !== false || strpos($lower, 'nguyễn hoàng') !== false))) {
        continue;
    }

    if ($id === 3 || strpos($lower, 'nguyễn phương nam') !== false || strpos($lower, 'phương nam') !== false) {
        $mgr_nam = ['id' => $nv['id'], 'ho_ten' => 'Nguyễn Phương Nam [Quản lý]'];
        continue;
    }
    if ($id === 2 || strpos($lower, 'nguyễn dương bảo phú') !== false || ($id <= 4 && strpos($lower, 'bảo phú') !== false)) {
        $mgr_phu = ['id' => $nv['id'], 'ho_ten' => 'Nguyễn Dương Bảo Phú [Quản lý]'];
        continue;
    }
    if ($id === 4 || strpos($lower, 'đoàn anh thái') !== false || ($id <= 4 && strpos($lower, 'thái') !== false)) {
        $mgr_thai = ['id' => $nv['id'], 'ho_ten' => 'Đoàn Anh Thái [Quản lý]'];
        continue;
    }

    $ch_id = (int)$nv['cuahang_id'];
    $branch_staff[$ch_id][] = [
        'id' => $nv['id'],
        'ho_ten' => $name
    ];
}

if (!$mgr_nam)  $mgr_nam  = ['id' => 3, 'ho_ten' => 'Nguyễn Phương Nam [Quản lý]'];
if (!$mgr_phu)  $mgr_phu  = ['id' => 2, 'ho_ten' => 'Nguyễn Dương Bảo Phú [Quản lý]'];
if (!$mgr_thai) $mgr_thai = ['id' => 4, 'ho_ten' => 'Đoàn Anh Thái [Quản lý]'];

$managers_list = [$mgr_nam, $mgr_phu, $mgr_thai];
$branch_staff_map = [];

foreach ($cuahang_list as $index => $ch) {
    $ch_id = (int)$ch['id'];
    $list = [];
    if (!empty($managers_list)) {
        $list[] = $managers_list[$index % count($managers_list)];
    }
    if (!empty($branch_staff[$ch_id])) {
        foreach ($branch_staff[$ch_id] as $st) {
            $list[] = $st;
        }
    }
    $branch_staff_map[$ch_id] = $list;
}

$khachhang_list = $pdo->query("SELECT kh.*, ttd.hang_the, ttd.ti_le_giam_gia FROM khachhang kh LEFT JOIN thetichdiem ttd ON kh.thetichdiem_id = ttd.id ORDER BY kh.id ASC")->fetchAll();
$khuyenmai_list = $pdo->query("SELECT id, cuahang_id, ten_km, ma_voucher, loai_km, gia_tri, so_luong, thoi_gian_bat_dau, thoi_gian_ket_thuc FROM khuyenmai ORDER BY id ASC")->fetchAll();
foreach ($khuyenmai_list as &$km) {
    $km['pos_store_id'] = resolvePosVoucherStoreId($km, $posStoreCodeIds, $posVoucherStoreCodes);
}
unset($km);
$now = date('Y-m-d H:i:s');

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
    
    .tier-badge { font-weight: 700; border-radius: 6px; padding: 3px 8px; font-size: 0.75rem; text-transform: uppercase; }
    .tier-bac { background: #94a3b8; color: #fff; }
    .tier-vang { background: #f59e0b; color: #fff; }
    .tier-kimcuong { background: #06b6d4; color: #fff; }
    .tier-none { background: #e2e8f0; color: #475569; }
    
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

                <select class="form-select flex-shrink-0" id="posStaffSelect" style="width: auto; min-width: 220px; height: 44px; border-radius: 10px; font-weight: 500;">
                </select>
            </div>

            <div class="prod-scroll-wrap" id="productScrollWrap">
                <div class="row g-3" id="productGrid">
                    <?php foreach($sanpham_list as $sp): 
                        $catName = $sp['ten_danh_muc'] ?? 'Khác';
                        $v = getVisual($sp['ten_san_pham'], $catName);
                        $imgSrc = "../../../assets/images/" . $sp['ma_san_pham'] . ".jpg";
                    ?>
                    <div class="col-6 col-md-4 col-lg-3 prod-item-col" data-cat="<?=$catName?>" data-search="<?=mb_strtolower($sp['ma_san_pham'].' '.$sp['ten_san_pham'])?>">
                        <div id="prod-<?=$sp['id']?>" onclick="addToCart(<?=$sp['id']?>,'<?=addslashes($sp['ten_san_pham'])?>',<?=$sp['gia_ban']?>,'<?=$sp['ma_san_pham']?>')" class="prod-row">
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
                    <i class="fa-solid fa-chevron-left" style="font-size: 0.75rem;"></i> Prev
                </button>
                <div id="pageNumbers"></div>
                <button type="button" class="page-nav-btn" id="btnNextPage" onclick="changePage(1)">
                    Next <i class="fa-solid fa-chevron-right" style="font-size: 0.75rem;"></i>
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
                <div class="input-group input-group-sm mb-2 shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-user-pen"></i></span>
                    <input type="text" class="form-control border-start-0 fw-medium" id="posCustomerInput" placeholder="Nhập tên khách hàng..." list="customerSuggestList" autocomplete="off" oninput="handleCustomerInput()">
                    <datalist id="customerSuggestList">
                        <option value="Khách lẻ (Vãng lai)"></option>
                        <?php foreach($khachhang_list as $kh): 
                            $tag = !empty($kh['hang_the']) ? '[' . $kh['hang_the'] . ']' : '[Bạc]';
                        ?>
                            <option value="<?= htmlspecialchars($kh['ho_ten']) ?>"><?= $tag ?> - SĐT: <?= htmlspecialchars($kh['so_dien_thoai'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </datalist>
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
                                    data-name="<?=htmlspecialchars($kh['ho_ten'])?>" 
                                    data-disc="<?=$disc?>" 
                                    data-tier="<?=htmlspecialchars($tier)?>">
                                <?= $badgeLabel ?> <?= htmlspecialchars($kh['ho_ten']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span id="tierBadgeEl" class="tier-badge tier-none">Khách lẻ</span>
                </div>

                <select class="form-select form-select-sm border-0 text-danger fw-bold shadow-sm" id="posPromoSelect" onchange="onPromoSelectChange()" style="height: 36px;">
                    <option value="" data-val="0" data-type="">-- Áp dụng Voucher / Khuyến mãi --</option>
                    <?php foreach ($khuyenmai_list as $km):
                        $isUpcoming = $km['thoi_gian_bat_dau'] > $now;
                        $isExpired = $km['thoi_gian_ket_thuc'] < $now || ($km['so_luong'] !== null && (int)$km['so_luong'] <= 0);
                        $promoStatus = $isExpired ? ' (Đã hết hạn / hết lượt)' : ($isUpcoming ? ' (Sắp diễn ra)' : '');
                    ?>
                    <option value="<?=(int)$km['id']?>" data-code="<?=htmlspecialchars($km['ma_voucher'] ?? '', ENT_QUOTES, 'UTF-8')?>" data-store="<?=(int)$km['pos_store_id']?>" data-store-name="<?=htmlspecialchars($posStoreNames[$km['pos_store_id']] ?? 'Toàn hệ thống', ENT_QUOTES, 'UTF-8')?>" data-status="<?=$isExpired ? 'expired' : ($isUpcoming ? 'upcoming' : 'active')?>" data-val="<?=(float)$km['gia_tri']?>" data-type="<?=htmlspecialchars($km['loai_km'], ENT_QUOTES, 'UTF-8')?>" <?=($isUpcoming || $isExpired) ? 'disabled' : ''?>>[<?=htmlspecialchars($km['ma_voucher'] ?? '', ENT_QUOTES, 'UTF-8')?>] <?=htmlspecialchars($km['ten_km'], ENT_QUOTES, 'UTF-8')?><?=$promoStatus?></option>
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

<script>
    const branchStaffData = <?= json_encode($branch_staff_map, JSON_UNESCAPED_UNICODE) ?>;
    const allPromoOptions = Array.from(document.querySelectorAll('#posPromoSelect option')).slice(1);

    function updatePromoByBranch() {
        const select = document.getElementById('posPromoSelect');
        const branchId = Number(document.getElementById('posStoreSelect').value);
        const previousPromo = select.value || localStorage.getItem('ck_pos_promo') || '';
        select.length = 1;
        allPromoOptions.forEach(option => {
            const storeId = Number(option.dataset.store || 0);
            if ((storeId === 0 || storeId === branchId) && option.dataset.status === 'active' && !option.disabled) select.appendChild(option);
        });
        const validSelection = Array.from(select.options).find(option => option.value === previousPromo && !option.disabled);
        select.value = validSelection ? previousPromo : '';
        localStorage.setItem('ck_pos_promo', select.value);
        const codeInput = document.getElementById('posPromoCodeInput');
        codeInput.value = validSelection ? (validSelection.dataset.code || '') : '';
        clearPromoFeedback();
        calcTotal();
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
        if (!code) {
            showPromoFeedback('Vui lòng nhập mã voucher.', false);
            input.focus();
            return;
        }
        const option = allPromoOptions.find(item => (item.dataset.code || '').toUpperCase() === code);
        if (!option) {
            showPromoFeedback('Không tìm thấy mã voucher ' + code + '.', false);
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
        showPromoFeedback('Đã áp dụng mã ' + code + ' thành công.', true);
    }

    function updateStaffByBranch() {
        const branchSelect = document.getElementById('posStoreSelect');
        const staffSelect = document.getElementById('posStaffSelect');
        const currentBranchId = branchSelect.value;
        const staffList = branchStaffData[currentBranchId] || [];

        staffSelect.innerHTML = '';
        staffList.forEach(st => {
            const opt = document.createElement('option');
            opt.value = st.id;
            opt.textContent = '👤 ' + st.ho_ten;
            staffSelect.appendChild(opt);
        });

        localStorage.setItem('ck_pos_store', currentBranchId);
        localStorage.setItem('ck_pos_staff', staffSelect.value);
        updatePromoByBranch();
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
        localStorage.setItem('ck_pos_promo', document.getElementById('posPromoSelect').value);
        localStorage.setItem('ck_pos_staff', document.getElementById('posStaffSelect').value);
    }

    function addToCart(id, name, price, sku) {
        beep(); 
        const i = cart.findIndex(x => x.id === id);
        if (i > -1) { 
            let item = cart.splice(i, 1)[0]; 
            item.qty++; 
            cart.unshift(item); 
        } else { 
            cart.unshift({id, name, price, sku: sku, qty:1}); 
        }
        drawCart();
        document.getElementById('cartBox').scrollTop = 0;
    }

    function uQty(id, d) {
        const i = cart.findIndex(x => x.id === id);
        if(i > -1) { 
            let item = cart.splice(i, 1)[0];
            item.qty += d; 
            if(item.qty > 0) {
                cart.unshift(item);
            }
            drawCart(); 
            document.getElementById('cartBox').scrollTop = 0;
        }
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
                        <div class="qty-stepper mt-1"><button class="qty-btn" onclick="uQty(${i.id},-1)"><i class="fa-solid fa-minus" style="font-size:10px"></i></button><span class="small fw-bold px-2">${i.qty}</span><button class="qty-btn" onclick="uQty(${i.id},1)"><i class="fa-solid fa-plus" style="font-size:10px"></i></button></div>
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
            let discRate = parseFloat(c.dataset.disc) || 0;
            if (discRate === 0) {
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

    function handleCustomerSelect() {
        const sel = document.getElementById('posCustomerSelect');
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('posCustomerInput').value = opt.dataset.name || '';
        updateTierBadge(opt.dataset.tier || '');
        calcTotal();
    }

    function handleCustomerInput() {
        const val = document.getElementById('posCustomerInput').value.toLowerCase().trim();
        const sel = document.getElementById('posCustomerSelect');
        let matched = false;

        for (let i = 0; i < sel.options.length; i++) {
            const opt = sel.options[i];
            const name = (opt.dataset.name || '').toLowerCase();
            if (val !== '' && name.includes(val)) {
                sel.selectedIndex = i;
                updateTierBadge(opt.dataset.tier || '');
                matched = true;
                break;
            }
        }
        if (!matched) {
            sel.selectedIndex = 0;
            updateTierBadge('none');
        }
        calcTotal();
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

    function goToCheckout() {
        if (cart.length === 0) return alert('Vui lòng quét hoặc chọn ít nhất 1 sản phẩm để thanh toán!');
        const btn = document.getElementById('btnGoCheckout');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Đang chốt đơn...';

        const fd = new FormData();
        fd.append('pos_action', 'create_order_pending');
        fd.append('cart', JSON.stringify(cart));
        fd.append('cuahang_id', document.getElementById('posStoreSelect').value);
        fd.append('nhanvien_id', document.getElementById('posStaffSelect').value);
        fd.append('khachhang_id', document.getElementById('posCustomerSelect').value);
        fd.append('khuyenmai_id', document.getElementById('posPromoSelect').value);
        fd.append('tong_tien', calc.s);
        fd.append('tien_giam_gia', calc.d);
        fd.append('khach_phai_tra', calc.t);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    window.location.replace('../thanh_toan_don_hang/index.php?id=' + d.hoadon_id);
                } else {
                    alert('Lỗi: ' + d.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-credit-card me-2"></i>Thanh toán đơn hàng';
                }
            })
            .catch(err => {
                alert('Lỗi kết nối máy chủ: ' + err);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-credit-card me-2"></i>Thanh toán đơn hàng';
            });
    }

    function clearCartConfirm() {
        openPosNotice({
            title: 'Xóa giỏ hàng hiện tại?',
            message: 'Toàn bộ sản phẩm và khuyến mãi đã chọn sẽ được xóa khỏi giỏ hàng.',
            mode: 'danger',
            confirmText: 'Xóa giỏ hàng',
            cancelText: 'Giữ giỏ hàng',
            onConfirm: () => {
                cart = [];
                document.getElementById('posCustomerInput').value = '';
                document.getElementById('posCustomerSelect').selectedIndex = 0;
                updateTierBadge('none');
                document.getElementById('posPromoSelect').selectedIndex = 0;
                document.getElementById('posPromoCodeInput').value = '';
                clearPromoFeedback();
                localStorage.removeItem('ck_pos_cart');
                localStorage.removeItem('ck_pos_cus');
                localStorage.removeItem('ck_pos_promo');
                drawCart();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.has('clear') || urlParams.has('clear_cart')) {
            cart = [];
            localStorage.removeItem('ck_pos_cart');
            localStorage.removeItem('ck_pos_cus');
            localStorage.removeItem('ck_pos_promo');
            window.history.replaceState({}, document.title, window.location.pathname);
        } else {
            cart = JSON.parse(localStorage.getItem('ck_pos_cart')) || [];
        }

        const savedStore = localStorage.getItem('ck_pos_store');
        if(savedStore) {
            document.getElementById('posStoreSelect').value = savedStore;
        }
        updateStaffByBranch();

        const savedStaff = localStorage.getItem('ck_pos_staff');
        if(savedStaff) {
            document.getElementById('posStaffSelect').value = savedStaff;
        }

        document.getElementById('posStaffSelect').addEventListener('change', function() {
            localStorage.setItem('ck_pos_staff', this.value);
        });

        const savedCus = localStorage.getItem('ck_pos_cus');
        const savedPromo = localStorage.getItem('ck_pos_promo');
        
        if(savedCus) {
            document.getElementById('posCustomerSelect').value = savedCus;
            handleCustomerSelect();
        }
        if(savedPromo) {
            const select = document.getElementById('posPromoSelect');
            const option = Array.from(select.options).find(opt => opt.value === savedPromo && !opt.disabled);
            select.value = option ? savedPromo : '';
            document.getElementById('posPromoCodeInput').value = option ? (option.dataset.code || '') : '';
        }
        drawCart();
        triggerSearch('', false);

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
                        const btn = document.getElementById('btnGoCheckout');
                        if(btn) btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Đang chốt đơn...';
                        
                        const fd = new FormData();
                        fd.append('pos_action', 'create_order_pending');
                        fd.append('cart', JSON.stringify(cart));
                        fd.append('cuahang_id', document.getElementById('posStoreSelect').value);
                        fd.append('nhanvien_id', document.getElementById('posStaffSelect').value);
                        fd.append('khachhang_id', document.getElementById('posCustomerSelect').value);
                        fd.append('khuyenmai_id', document.getElementById('posPromoSelect').value);
                        fd.append('tong_tien', calc.s);
                        fd.append('tien_giam_gia', calc.d);
                        fd.append('khach_phai_tra', calc.t);

                        fetch(window.location.href, { method: 'POST', body: fd })
                            .then(r => r.json())
                            .then(d => {
                                if (d.success) {
                                    window.location.replace(link.href.split('?')[0] + '?id=' + d.hoadon_id);
                                } else {
                                    window.location.href = link.href;
                                }
                            }).catch(() => {
                                window.location.href = link.href;
                            });
                    }
                });
            }
        });
    });
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>