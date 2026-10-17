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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pos_action']) && $_POST['pos_action'] === 'checkout') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $cart           = json_decode($_POST['cart'] ?? '[]', true);
        $cuahang_id     = (int)($_POST['cuahang_id'] ?? 1);
        $nhanvien_id    = (int)($_POST['nhanvien_id'] ?? 1);
        $khachhang_id   = !empty($_POST['khachhang_id']) ? (int)$_POST['khachhang_id'] : null;
        $khuyenmai_id   = !empty($_POST['khuyenmai_id']) ? (int)$_POST['khuyenmai_id'] : null;
        $phuong_thuc    = trim($_POST['phuong_thuc'] ?? 'Tiền mặt');
        $tong_tien      = (float)($_POST['tong_tien'] ?? 0);
        $tien_giam_gia  = (float)($_POST['tien_giam_gia'] ?? 0);
        $khach_phai_tra = (float)($_POST['khach_phai_tra'] ?? 0);

        if (empty($cart)) { echo json_encode(['success' => false, 'message' => 'Giỏ hàng trống!']); exit; }

        $pdo->beginTransaction();
        $stmtHd = $pdo->prepare("INSERT INTO hoadon (cuahang_id, nhanvien_id, khachhang_id, khuyenmai_id, ngay_tao, tong_tien, tien_giam_gia, khach_phai_tra, trang_thai) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, 'Hoàn thành')");
        $stmtHd->execute([$cuahang_id, $nhanvien_id, $khachhang_id, $khuyenmai_id, $tong_tien, $tien_giam_gia, $khach_phai_tra]);
        $hoadon_id = (int)$pdo->lastInsertId();

        $stmtCt = $pdo->prepare("INSERT INTO chitiethoadon (hoadon_id, sanpham_id, so_luong, don_gia, thanh_tien) VALUES (?, ?, ?, ?, ?)");
        $stmtTk = $pdo->prepare("UPDATE tonkho SET so_luong = GREATEST(0, so_luong - ?) WHERE sanpham_id = ? AND cuahang_id = ?");

        foreach ($cart as $item) {
            $stmtCt->execute([$hoadon_id, (int)$item['id'], (int)$item['qty'], (float)$item['price'], (int)$item['qty'] * (float)$item['price']]);
            $stmtTk->execute([(int)$item['qty'], (int)$item['id'], $cuahang_id]);
        }

        $pdo->prepare("INSERT INTO thanhtoan (hoadon_id, phuong_thuc, so_tien_thanh_toan, thoi_gian_thanh_toan) VALUES (?, ?, ?, NOW())")->execute([$hoadon_id, $phuong_thuc, $khach_phai_tra]);
        if ($khuyenmai_id) $pdo->prepare("UPDATE khuyenmai SET so_luong = so_luong - 1 WHERE id = ? AND so_luong IS NOT NULL AND so_luong > 0")->execute([$khuyenmai_id]);
        if ($khachhang_id) $pdo->prepare("UPDATE khachhang SET diem_tich_luy = diem_tich_luy + ? WHERE id = ?")->execute([(int)floor($khach_phai_tra / 1000), $khachhang_id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'order_code' => '#HD' . (1024 + $hoadon_id), 'created_at' => date('d/m/Y H:i:s')]); exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit;
    }
}

$sanpham_list = $pdo->query("SELECT sp.*, dm.ten_danh_muc FROM sanpham sp LEFT JOIN danhmuc dm ON sp.danhmuc_id = dm.id WHERE sp.trang_thai = 1 ORDER BY sp.id")->fetchAll();
$cuahang_list = $pdo->query("SELECT id, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id")->fetchAll();
$nhanvien_list = $pdo->query("SELECT id, ho_ten FROM nhanvien ORDER BY id")->fetchAll();
$khachhang_list = $pdo->query("SELECT kh.*, ttd.ti_le_giam_gia FROM khachhang kh LEFT JOIN thetichdiem ttd ON kh.thetichdiem_id = ttd.id")->fetchAll();
$khuyenmai_active = $pdo->prepare("SELECT * FROM khuyenmai WHERE thoi_gian_bat_dau <= ? AND thoi_gian_ket_thuc >= ? AND (so_luong IS NULL OR so_luong > 0)");
$now = date('Y-m-d H:i:s'); $khuyenmai_active->execute([$now, $now]); $khuyenmai_list = $khuyenmai_active->fetchAll();

$nextId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM hoadon")->fetchColumn();
$nextHdCode = '#HD' . (1024 + $nextId);

function getVisual($n, $c, $i) {
    $str = mb_strtolower($n.' '.$c);
    if(strpos($str,'cà phê')!==false || strpos($str,'trà')!==false || strpos($str,'milo')!==false) return ['ic'=>'fa-mug-hot','cl'=>'#f97316'];
    if(strpos($str,'mì')!==false) return ['ic'=>'fa-bowl-food','cl'=>'#e11d48'];
    if(strpos($str,'nước')!==false || strpos($str,'aquafina')!==false || strpos($str,'pocari')!==false) return ['ic'=>'fa-bottle-water','cl'=>'#0ea5e9'];
    if(strpos($str,'bánh mì')!==false || strpos($str,'sandwich')!==false || strpos($str,'bánh bao')!==false || strpos($str,'pizza')!==false) return ['ic'=>'fa-bread-slice','cl'=>'#f59e0b'];
    if(strpos($str,'sting')!==false || strpos($str,'lực')!==false || strpos($str,'coca')!==false || strpos($str,'pepsi')!==false) return ['ic'=>'fa-bolt','cl'=>'#8b5cf6'];
    if(strpos($str,'xúc xích')!==false) return ['ic'=>'fa-hotdog','cl'=>'#ef4444'];
    if(strpos($str,'cơm')!==false || strpos($str,'onigiri')!==false) return ['ic'=>'fa-bowl-rice','cl'=>'#10b981'];
    if(strpos($str,'snack')!==false || strpos($str,'kẹo')!==false || strpos($str,'pocky')!==false || strpos($str,'chocopie')!==false || strpos($str,'hạt')!==false || strpos($str,'bò khô')!==false || strpos($str,'rong biển')!==false) return ['ic'=>'fa-cookie-bite','cl'=>'#10b981'];
    if(strpos($str,'áo mưa')!==false) return ['ic'=>'fa-cloud-rain','cl'=>'#0284c7'];
    if(strpos($str,'bật lửa')!==false) return ['ic'=>'fa-fire','cl'=>'#ea580c'];
    if(strpos($str,'pin')!==false) return ['ic'=>'fa-battery-full','cl'=>'#16a34a'];
    if(strpos($str,'khăn')!==false || strpos($str,'khẩu trang')!==false) return ['ic'=>'fa-box-tissue','cl'=>'#64748b'];
    if(strpos($str,'bàn chải')!==false) return ['ic'=>'fa-tooth','cl'=>'#06b6d4'];
    return ['ic'=>'fa-box','cl'=>'#6366f1'];
}

function getCat($n, $c) {
    $str = mb_strtolower($n.' '.$c);
    if(strpos($str,'tiện ích')!==false || strpos($str,'áo mưa')!==false || strpos($str,'bật lửa')!==false || strpos($str,'khăn')!==false || strpos($str,'khẩu trang')!==false || strpos($str,'pin')!==false || strpos($str,'bàn chải')!==false) return 'Tiện ích';
    if(strpos($str,'uống')!==false || strpos($str,'nước')!==false || strpos($str,'cà phê')!==false || strpos($str,'trà')!==false || strpos($str,'sting')!==false || strpos($str,'pepsi')!==false || strpos($str,'coca')!==false || strpos($str,'milo')!==false || strpos($str,'pocari')!==false) return 'Đồ uống';
    if(strpos($str,'snack')!==false || strpos($str,'kẹo')!==false || strpos($str,'pocky')!==false || strpos($str,'chocopie')!==false || strpos($str,'bò khô')!==false || strpos($str,'hạt')!==false || strpos($str,'rong biển')!==false) return 'Snack'; 
    return 'Đồ ăn';
}

$pageTitle = 'Màn hình bán hàng (POS)'; $activeMenu = 'pos';
require ROOT_PATH . '/includes/header.php';
?>

<style>
    .pos-main-row { min-height: calc(100vh - 120px); }
    .pos-panel-stretch { height: 100%; display: flex; flex-direction: column; }
    .prod-grid-scroll { flex: 1 1 auto; overflow-y: auto; padding-right: 6px; align-content: flex-start; max-height: calc(100vh - 240px); }
    .prod-grid-scroll::-webkit-scrollbar, #cartBox::-webkit-scrollbar { width: 6px; }
    .prod-grid-scroll::-webkit-scrollbar-track, #cartBox::-webkit-scrollbar-track { background: transparent; }
    .prod-grid-scroll::-webkit-scrollbar-thumb, #cartBox::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .prod-grid-scroll::-webkit-scrollbar-thumb:hover, #cartBox::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    .prod-row { position: relative; transition: all 0.2s; cursor: pointer; user-select: none; overflow: hidden; border: 1px solid var(--border-light); border-radius: 12px; display: block; padding: 10px; }
    .prod-row:hover { transform: translateY(-3px); border-color: var(--ck-red) !important; box-shadow: 0 8px 20px rgba(238,49,36,0.12); }
    .prod-row:active { transform: scale(0.97); }
    .prod-row.in-cart { border-color: var(--ck-red) !important; background: linear-gradient(180deg, rgba(238,49,36,0.04), transparent); }
    .prod-qty-badge { position: absolute; top: 6px; right: 6px; background: var(--ck-red); color: #fff; font-size: 0.75rem; font-weight: 700; width: 24px; height: 24px; border-radius: 50%; display: none; align-items: center; justify-content: center; box-shadow: 0 3px 8px rgba(238,49,36,0.3); animation: popIn 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .prod-row.in-cart .prod-qty-badge { display: flex; }
    .prod-rank-icon { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 10px; font-size: 1.2rem; margin-bottom: 8px; }
    .sku-tag { font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; background: #f1f5f9; color: #64748b; font-family: monospace; font-weight: 600; }
    .qty-stepper { display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 2px 4px; }
    .qty-btn { width: 26px; height: 26px; border: none; background: #fff; border-radius: 6px; font-weight: 700; color: #334155; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.08); transition: 0.15s; display: flex; align-items: center; justify-content: center; }
    .qty-btn:hover { background: var(--ck-red); color: #fff; }
    .pay-method-card { border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 15px 10px; text-align: center; cursor: pointer; transition: all 0.2s; background: #fff; }
    .pay-method-card:hover { border-color: #cbd5e1; }
    .pay-method-card.active { border-color: var(--ck-red); background: rgba(238,49,36,0.05); color: var(--ck-red); font-weight: bold; }
    .quick-cash-btn { border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 8px; padding: 6px 12px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: 0.15s; }
    .quick-cash-btn:hover { background: #e2e8f0; }
    .receipt-paper { background: #fff; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 24px; font-family: 'Courier New', Courier, monospace; color: #0f172a; max-width: 400px; margin: 0 auto; }
    @keyframes popIn { 0% { transform: scale(0.5); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    @media print { body * { visibility: hidden; } #printableReceipt, #printableReceipt * { visibility: visible; } #printableReceipt { position: absolute; left: 0; top: 0; width: 100%; border: none; } }
</style>

<div class="row g-4 pos-main-row">
    <div class="col-12 col-xl-8 d-flex flex-column">
        <div class="bento-panel fade-up pos-panel-stretch">
            <div class="panel-header flex-wrap gap-2">
                <div>
                    <h5 class="panel-title"><span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-cash-register"></i></span> Chọn sản phẩm</h5>
                    <div class="panel-subtitle ms-5">Quét mã vạch hoặc bấm vào sản phẩm</div>
                </div>
                <div class="segmented-control" id="categoryTabs">
                    <button class="seg-btn active" data-cat="Tất cả">Tất cả</button>
                    <button class="seg-btn" data-cat="Đồ ăn">Đồ ăn</button>
                    <button class="seg-btn" data-cat="Đồ uống">Đồ uống</button>
                    <button class="seg-btn" data-cat="Snack">Snack</button>
                    <button class="seg-btn" data-cat="Tiện ích">Tiện ích</button>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <div class="search-bar flex-grow-1"><i class="fa-solid fa-barcode s-ico"></i><input type="text" id="posSearchInput" placeholder="Quét mã vạch (F2) hoặc tìm tên..." autocomplete="off"></div>
                <select class="form-select flex-shrink-0" id="posStoreSelect" style="width: auto; min-width: 230px; height: 44px; border-radius: 10px; font-weight: 500;">
                    <?php foreach($cuahang_list as $ch): ?><option value="<?=$ch['id']?>">🏪 <?=htmlspecialchars($ch['ten_cua_hang'])?></option><?php endforeach; ?>
                </select>
                <select class="form-select flex-shrink-0" id="posStaffSelect" style="width: auto; min-width: 220px; height: 44px; border-radius: 10px; font-weight: 500;">
                    <?php foreach($nhanvien_list as $nv): ?><option value="<?=$nv['id']?>" <?=$nv['id']==3?'selected':''?>>👤 <?=htmlspecialchars($nv['ho_ten'])?></option><?php endforeach; ?>
                </select>
            </div>

            <div class="row g-3 prod-grid-scroll" id="productGrid">
                <?php foreach($sanpham_list as $sp): 
                    $v = getVisual($sp['ten_san_pham'],$sp['ten_danh_muc'] ?? '', $sp['id']);$c = getCat($sp['ten_san_pham'],$sp['ten_danh_muc'] ?? '');
                ?>
                <div class="col-6 col-md-4 col-lg-3 prod-item-col" data-cat="<?=$c?>" data-search="<?=mb_strtolower($sp['ma_san_pham'].' '.$sp['ten_san_pham'])?>">
                    <a href="javascript:void(0)" id="prod-<?=$sp['id']?>" onclick="addToCart(<?=$sp['id']?>,'<?=addslashes($sp['ten_san_pham'])?>',<?=$sp['gia_ban']?>)" class="prod-row text-decoration-none bg-white">
                        <span class="prod-qty-badge" id="b-<?=$sp['id']?>">0</span>
                        <div class="prod-rank-icon" style="color:<?=$v['cl']?>; background: color-mix(in srgb, <?=$v['cl']?> 12%, transparent);"><i class="fa-solid <?=$v['ic']?>"></i></div>
                        <div class="fw-bold text-truncate text-dark" style="font-size: 0.9rem;" title="<?=$sp['ten_san_pham']?>"><?=$sp['ten_san_pham']?></div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <span class="num-font fw-bold" style="color: #475569; font-size: 0.95rem;"><?=number_format($sp['gia_ban'])?> ₫</span>
                            <span class="sku-tag"><?=$sp['ma_san_pham']?></span>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4 d-flex flex-column">
        <div class="bento-panel fade-up d-1 pos-panel-stretch">
            <div class="panel-header">
                <div>
                    <h5 class="panel-title"><span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), var(--ck-orange))"><i class="fa-solid fa-cart-shopping"></i></span> Giỏ hàng</h5>
                </div>
                <span class="order-code fs-6" id="currentOrderCode"><?=$nextHdCode?></span>
            </div>

            <div class="p-3 bg-light border rounded-3 mb-3">
                <select class="form-select form-select-sm mb-2 border-0 fw-medium shadow-sm" id="posCustomerSelect" onchange="calcTotal()" style="height: 36px;">
                    <option value="" data-disc="0">Khách lẻ (Vãng lai)</option>
                    <?php foreach($khachhang_list as$kh): ?><option value="<?=$kh['id']?>" data-disc="<?=(float)($kh['ti_le_giam_gia']??0)?>"><?=$kh['ho_ten']?> - <?=$kh['so_dien_thoai']?></option><?php endforeach; ?>
                </select>
                <select class="form-select form-select-sm border-0 text-danger fw-bold shadow-sm" id="posPromoSelect" onchange="calcTotal()" style="height: 36px;">
                    <option value="" data-val="0" data-type="">-- Áp dụng Voucher / Khuyến mãi --</option>
                    <?php foreach($khuyenmai_list as$km): ?><option value="<?=$km['id']?>" data-val="<?=(float)$km['gia_tri']?>" data-type="<?=$km['loai_km']?>">[<?=$km['ma_voucher']?>] <?=$km['ten_km']?></option><?php endforeach; ?>
                </select>
            </div>

            <div id="cartBox" class="flex-grow-1" style="min-height: 200px; max-height: calc(100vh - 460px); overflow-y: auto; padding-right: 5px;"></div>

            <div class="mt-auto pt-3 border-top">
                <div class="d-flex justify-content-between mb-2 small text-muted"><span class="fw-medium">Tạm tính</span><span class="num-font fw-bold text-dark" id="lSub">0 ₫</span></div>
                <div class="d-flex justify-content-between mb-2 small text-success"><span class="fw-medium">Khuyến mãi</span><span class="num-font fw-bold" id="lDisc">- 0 ₫</span></div>
                <div class="d-flex justify-content-between mb-2 small text-muted"><span class="fw-medium">VAT (8%)</span><span class="num-font fw-bold text-dark" id="lVat">0 ₫</span></div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top"><span class="fw-bold fs-6">TỔNG CỘNG</span><span class="num-font fw-bold" style="font-size: 1.75rem; color: var(--ck-red);" id="lTotal">0 ₫</span></div>
                
                <button class="btn-ck btn-ck-primary w-100 mt-3 py-2 fs-6" onclick="openPay()"><i class="fa-solid fa-credit-card me-2"></i>Thanh toán</button>
                <button class="btn-ck btn-ck-soft w-100 mt-2" onclick="if(confirm('Bạn có chắc muốn hủy đơn hàng này?')){cart=[]; drawCart();}">Hủy đơn</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mdlPay" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 18px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger"><i class="fa-solid fa-cash-register me-2"></i>Thanh toán</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="stepPay">
                    <div class="text-center p-3 rounded-3 mb-4" style="background: rgba(238,49,36,0.05); border: 1px dashed rgba(238,49,36,0.3);">
                        <div class="small fw-bold text-muted mb-1">KHÁCH PHẢI TRẢ</div>
                        <div class="num-font fw-bold text-danger" style="font-size: 2.2rem;" id="mTotal">0 ₫</div>
                    </div>
                    
                    <div class="d-flex gap-2 mb-4">
                        <div class="pay-method-card flex-grow-1 active" onclick="setMeth('Tiền mặt',this)"><i class="fa-solid fa-money-bill-wave fs-4 mb-2 d-block"></i>Tiền mặt</div>
                        <div class="pay-method-card flex-grow-1" onclick="setMeth('Chuyển khoản',this)"><i class="fa-solid fa-building-columns fs-4 mb-2 d-block"></i>Chuyển khoản</div>
                        <div class="pay-method-card flex-grow-1" onclick="setMeth('Ví điện tử',this)"><i class="fa-solid fa-qrcode fs-4 mb-2 d-block"></i>Ví điện tử</div>
                    </div>
                    
                    <div id="cashArea">
                        <label class="small fw-bold text-muted mb-2">TIỀN KHÁCH ĐƯA</label>
                        <input type="number" class="form-control form-control-lg num-font fw-bold mb-2" id="cCash" oninput="cChange()" placeholder="Nhập số tiền...">
                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <button class="quick-cash-btn" onclick="document.getElementById('cCash').value=calc.t; cChange()">Đủ tiền</button>
                            <button class="quick-cash-btn" onclick="document.getElementById('cCash').value=50000; cChange()">50,000</button>
                            <button class="quick-cash-btn" onclick="document.getElementById('cCash').value=100000; cChange()">100,000</button>
                            <button class="quick-cash-btn" onclick="document.getElementById('cCash').value=200000; cChange()">200,000</button>
                            <button class="quick-cash-btn" onclick="document.getElementById('cCash').value=500000; cChange()">500,000</button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top"><span class="fw-semibold text-muted">Tiền thừa trả khách:</span><span id="cChangeTxt" class="num-font text-primary fw-bold fs-4">0 ₫</span></div>
                    </div>
                    
                    <div id="qrArea" class="text-center py-4" style="display:none;">
                        <div class="d-inline-block p-3 border rounded-3 bg-light mb-3"><i class="fa-solid fa-qrcode text-dark" style="font-size: 5rem;"></i></div>
                        <div class="small text-muted fw-medium">Khách hàng quét mã VietQR / MoMo<br>để thanh toán đúng số tiền</div>
                    </div>
                </div>
                
                <div id="stepRcp" style="display:none;">
                    <div class="receipt-paper" id="printableReceipt">
                        <div class="text-center border-bottom pb-3 mb-3">
                            <div class="fw-bold fs-4">CIRCLE K VIETNAM</div>
                            <div class="fw-medium mt-1" id="rStore"></div>
                            <div class="small text-muted mt-1" id="rTime"></div>
                            <div class="fw-bold mt-2 border border-dark rounded d-inline-block px-3 py-1" id="rCode"></div>
                        </div>
                        <div id="rItems" class="small border-bottom pb-3 mb-3"></div>
                        <div class="small fw-medium">
                            <div class="d-flex justify-content-between mb-1"><span>Tạm tính:</span><span id="rSub">0 ₫</span></div>
                            <div class="d-flex justify-content-between mb-1"><span>Giảm giá:</span><span id="rDisc">- 0 ₫</span></div>
                            <div class="d-flex justify-content-between mb-2"><span>VAT (8%):</span><span id="rVat">0 ₫</span></div>
                            <div class="d-flex justify-content-between fw-bold fs-6 pt-2 border-top mt-2"><span>TỔNG CỘNG:</span><span id="rTotal">0 ₫</span></div>
                            <div class="d-flex justify-content-between mt-2 pt-2 border-top text-muted"><span>Phương thức:</span><span id="rMeth">Tiền mặt</span></div>
                        </div>
                        <div class="text-center small text-muted mt-4">Cảm ơn quý khách & Hẹn gặp lại!</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0" id="fPay">
                <button class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Trở lại</button>
                <button class="btn-ck btn-ck-primary px-4" id="btnSbm" onclick="sbmPay()"><i class="fa-solid fa-check me-2"></i>Hoàn tất</button>
            </div>
            <div class="modal-footer border-0 pt-0" id="fRcp" style="display:none;">
                <button class="btn-ck btn-ck-soft" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>In HĐ</button>
                <button class="btn-ck btn-ck-primary px-4" onclick="location.reload()"><i class="fa-solid fa-plus me-2"></i>Đơn mới</button>
            </div>
        </div>
    </div>
</div>

<script>
    let cart = [{ id: 2, name: 'Cà phê sữa đá Circle K', price: 20000, qty: 2 }, { id: 3, name: 'Mì trộn xúc xích trứng', price: 35000, qty: 1 }];
    let meth = 'Tiền mặt', calc = {s:0, d:0, v:0, t:0};
    const fVND = n => Math.round(n).toLocaleString() + ' ₫';
    const beep = () => { try { const x=new (window.AudioContext||window.webkitAudioContext)(), o=x.createOscillator(), g=x.createGain(); o.type='sine'; o.frequency.value=880; g.gain.value=0.05; o.connect(g); g.connect(x.destination); o.start(); o.stop(x.currentTime+0.05); } catch(e){} };

    function addToCart(id, name, price) {
        beep(); const ex = cart.find(i => i.id === id);
        if (ex) ex.qty++; else cart.push({id, name, price, qty:1});
        drawCart();
    }
    function uQty(id, d) {
        const i = cart.findIndex(x => x.id === id);
        if(i > -1) { cart[i].qty += d; if(cart[i].qty <= 0) cart.splice(i,1); drawCart(); }
    }
    function drawCart() {
        document.querySelectorAll('.prod-row').forEach(e => e.classList.remove('in-cart'));
        document.querySelectorAll('.prod-qty-badge').forEach(e => e.style.display = 'none');
        let h = '';
        if(cart.length === 0) h = '<div class="text-center text-muted py-5"><i class="fa-solid fa-cart-shopping fs-1 opacity-25 mb-3 d-block"></i>Giỏ hàng trống</div>';
        else {
            cart.forEach(i => {
                h += `<div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div class="pe-2"><div class="fw-semibold small text-dark">${i.name}</div><small class="text-muted">${i.qty} x ${fVND(i.price)}</small></div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="qty-stepper"><button class="qty-btn" onclick="uQty(${i.id},-1)"><i class="fa-solid fa-minus" style="font-size:10px"></i></button><span class="small fw-bold px-2">${i.qty}</span><button class="qty-btn" onclick="uQty(${i.id},1)"><i class="fa-solid fa-plus" style="font-size:10px"></i></button></div>
                        <span class="num-font fw-bold text-end text-dark" style="min-width:75px;">${fVND(i.price*i.qty)}</span>
                    </div></div>`;
                const cEl = document.getElementById('prod-'+i.id), bEl = document.getElementById('b-'+i.id);
                if(cEl && bEl){ cEl.classList.add('in-cart'); bEl.textContent = i.qty; bEl.style.display = 'flex'; }
            });
        }
        document.getElementById('cartBox').innerHTML = h; calcTotal();
    }
    function calcTotal() {
        let s = 0; cart.forEach(i => s += i.price * i.qty); let d = 0;
        const p = document.getElementById('posPromoSelect').options[document.getElementById('posPromoSelect').selectedIndex];
        if(p && p.value && s>0) d += (p.dataset.type === 'Phần trăm') ? s*(parseFloat(p.dataset.val)/100) : parseFloat(p.dataset.val);
        const c = document.getElementById('posCustomerSelect').options[document.getElementById('posCustomerSelect').selectedIndex];
        if(c && c.value && s>0) d += s*(parseFloat(c.dataset.disc)/100);
        if(d > s) d = s; let v = Math.round((s - d) * 0.08); let t = s - d + v;
        calc = {s, d, v, t};
        document.getElementById('lSub').textContent = fVND(s); document.getElementById('lDisc').textContent = '-'+fVND(d);
        document.getElementById('lVat').textContent = fVND(v); document.getElementById('lTotal').textContent = fVND(t);
    }
    
    document.querySelectorAll('.seg-btn').forEach(b => b.onclick = function(){
        document.querySelectorAll('.seg-btn').forEach(x => x.classList.remove('active')); this.classList.add('active'); fProd();
    });
    document.getElementById('posSearchInput').oninput = fProd;
    document.addEventListener('keydown', e => { if(e.key==='F2') { e.preventDefault(); document.getElementById('posSearchInput').focus(); } });
    document.getElementById('posSearchInput').onkeydown = function(e) {
        if(e.key==='Enter') { e.preventDefault(); const vc = Array.from(document.querySelectorAll('.prod-item-col')).filter(c=>c.style.display!=='none'); if(vc.length>0) vc[0].querySelector('.prod-row').click(); this.value=''; fProd(); }
    };
    function fProd() {
        const k = document.getElementById('posSearchInput').value.toLowerCase().trim(), cat = document.querySelector('.seg-btn.active').dataset.cat;
        document.querySelectorAll('.prod-item-col').forEach(c => {
            const mC = cat==='Tất cả' || c.dataset.cat===cat, mK = c.dataset.search.includes(k);
            c.style.display = (mC && mK) ? '' : 'none';
        });
    }

    function openPay() {
        if(cart.length===0) return alert('Vui lòng chọn sản phẩm vào giỏ!');
        document.getElementById('mTotal').textContent = fVND(calc.t);
        document.getElementById('cCash').value = calc.t; cChange();
        new bootstrap.Modal(document.getElementById('mdlPay')).show();
    }
    function setMeth(m, el) {
        meth = m; document.querySelectorAll('.pay-method-card').forEach(c => c.classList.remove('active')); el.classList.add('active');
        document.getElementById('cashArea').style.display = (m==='Tiền mặt') ? '' : 'none';
        document.getElementById('qrArea').style.display = (m==='Tiền mặt') ? 'none' : '';
    }
    function cChange() {
        const c = parseFloat(document.getElementById('cCash').value)||0, ch = Math.max(0, c - calc.t);
        document.getElementById('cChangeTxt').textContent = fVND(ch);
    }
    function sbmPay() {
        const b = document.getElementById('btnSbm'); b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Đang lưu...';
        const fd = new FormData();
        fd.append('pos_action','checkout'); fd.append('cart',JSON.stringify(cart));
        fd.append('cuahang_id', document.getElementById('posStoreSelect').value);
        fd.append('nhanvien_id', document.getElementById('posStaffSelect').value);
        fd.append('khachhang_id', document.getElementById('posCustomerSelect').value);
        fd.append('khuyenmai_id', document.getElementById('posPromoSelect').value);
        fd.append('phuong_thuc', meth); fd.append('tong_tien', calc.s);
        fd.append('tien_giam_gia', calc.d); fd.append('khach_phai_tra', calc.t);

        fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
            if(d.success) {
                const st = document.getElementById('posStoreSelect');
                document.getElementById('rStore').textContent = st.options[st.selectedIndex].text.replace('🏪 ','');
                document.getElementById('rTime').textContent = d.created_at; document.getElementById('rCode').textContent = d.order_code;
                let h = ''; cart.forEach(i => h += `<div class="d-flex justify-content-between mb-1"><span>${i.name} (x${i.qty})</span><span>${fVND(i.price*i.qty)}</span></div>`);
                document.getElementById('rItems').innerHTML = h; document.getElementById('rSub').textContent = fVND(calc.s);
                document.getElementById('rDisc').textContent = '-'+fVND(calc.d); document.getElementById('rVat').textContent = fVND(calc.v);
                document.getElementById('rTotal').textContent = fVND(calc.t); document.getElementById('rMeth').textContent = meth;
                document.getElementById('stepPay').style.display = 'none'; document.getElementById('stepRcp').style.display = '';
                document.getElementById('fPay').style.display = 'none'; document.getElementById('fRcp').style.display = '';
            } else { alert(d.message); b.disabled=false; b.innerHTML='<i class="fa-solid fa-check me-2"></i>Hoàn tất'; }
        });
    }
    document.addEventListener('DOMContentLoaded', drawCart);
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>