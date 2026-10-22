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
$cuahang_list = $pdo->query("SELECT id, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();
$nhanvien_list = $pdo->query("SELECT id, ho_ten FROM nhanvien ORDER BY id ASC")->fetchAll();
$khachhang_list = $pdo->query("SELECT kh.*, ttd.hang_the, ttd.ti_le_giam_gia FROM khachhang kh LEFT JOIN thetichdiem ttd ON kh.thetichdiem_id = ttd.id ORDER BY kh.id ASC")->fetchAll();

$khuyenmai_active = $pdo->prepare("SELECT * FROM khuyenmai WHERE thoi_gian_bat_dau <= ? AND thoi_gian_ket_thuc >= ? AND (so_luong IS NULL OR so_luong > 0)");
$now = date('Y-m-d H:i:s'); 
$khuyenmai_active->execute([$now, $now]); 
$khuyenmai_list = $khuyenmai_active->fetchAll();

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
    .pos-panel-stretch { height: 100%; display: flex; flex-direction: column; }
    .prod-grid-scroll { flex: 1 1 auto; overflow-y: auto; padding-right: 6px; align-content: flex-start; max-height: calc(100vh - 240px); }
    .prod-grid-scroll::-webkit-scrollbar, #cartBox::-webkit-scrollbar { width: 6px; }
    .prod-grid-scroll::-webkit-scrollbar-track, #cartBox::-webkit-scrollbar-track { background: transparent; }
    .prod-grid-scroll::-webkit-scrollbar-thumb, #cartBox::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .prod-grid-scroll::-webkit-scrollbar-thumb:hover, #cartBox::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    .prod-row { position: relative; transition: all 0.2s; cursor: pointer; user-select: none; overflow: hidden; border: 1.5px solid var(--border-light); border-radius: 14px; display: block; padding: 10px; background: #fff; }
    .prod-row:hover { transform: translateY(-3px); border-color: var(--ck-red) !important; box-shadow: 0 8px 20px rgba(238,49,36,0.12); }
    .prod-row:active { transform: scale(0.97); }
    .prod-row.in-cart { border-color: var(--ck-red) !important; background: linear-gradient(180deg, rgba(238,49,36,0.04), transparent); }
    
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
</style>

<script>
    document.querySelector('body').classList.add('layout-fixed');
</script>

<div class="row g-4 pos-main-row">
    <div class="col-12 col-xl-8 d-flex flex-column">
        <div class="bento-panel fade-up pos-panel-stretch">
            <div class="panel-header flex-wrap gap-2">
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

            <div class="d-flex flex-wrap gap-2 mb-3">
                <div class="search-bar flex-grow-1"><i class="fa-solid fa-barcode s-ico"></i><input type="text" id="posSearchInput" placeholder="Tìm tên món (Enter xem tất cả danh mục) hoặc quét mã F2..." autocomplete="off"></div>
                <select class="form-select flex-shrink-0" id="posStoreSelect" style="width: auto; min-width: 230px; height: 44px; border-radius: 10px; font-weight: 500;">
                    <?php foreach($cuahang_list as $ch): ?><option value="<?=$ch['id']?>">🏪 <?=htmlspecialchars($ch['ten_cua_hang'])?></option><?php endforeach; ?>
                </select>
                <select class="form-select flex-shrink-0" id="posStaffSelect" style="width: auto; min-width: 220px; height: 44px; border-radius: 10px; font-weight: 500;">
                    <?php foreach($nhanvien_list as $nv): ?><option value="<?=$nv['id']?>" <?=$nv['id']==1?'selected':''?>>👤 <?=htmlspecialchars($nv['ho_ten'])?></option><?php endforeach; ?>
                </select>
            </div>

            <div class="row g-3 prod-grid-scroll" id="productGrid">
                <?php foreach($sanpham_list as $sp): 
                    $catName = $sp['ten_danh_muc'] ?? 'Khác';
                    $v = getVisual($sp['ten_san_pham'], $catName);
                    $imgSrc = "../../../assets/images/" . $sp['ma_san_pham'] . ".jpg";
                ?>
                <div class="col-6 col-md-4 col-lg-3 prod-item-col" data-cat="<?=$catName?>" data-search="<?=mb_strtolower($sp['ma_san_pham'].' '.$sp['ten_san_pham'])?>">
                    <div id="prod-<?=$sp['id']?>" onclick="addToCart(<?=$sp['id']?>,'<?=addslashes($sp['ten_san_pham'])?>',<?=$sp['gia_ban']?>,'<?=$sp['ma_san_pham']?>')" class="prod-row d-flex flex-column h-100">
                        <span class="prod-qty-badge" id="b-<?=$sp['id']?>">0</span>
                        
                        <div class="w-100 mb-2 rounded-3 overflow-hidden position-relative d-flex align-items-center justify-content-center" style="height: 120px; background: #f8fafc; border: 1px solid #f1f5f9;">
                            <img src="<?=$imgSrc?>" alt="<?=htmlspecialchars($sp['ten_san_pham'])?>" class="w-100 h-100" style="object-fit: cover; transition: transform 0.25s;" onmouseover="this.style.transform='scale(1.06)'" onmouseout="this.style.transform='scale(1)'" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="w-100 h-100 align-items-center justify-content-center" style="display:none; color:<?=$v['cl']?>; background: color-mix(in srgb, <?=$v['cl']?> 12%, transparent); font-size: 2rem;">
                                <i class="fa-solid <?=$v['ic']?>"></i>
                            </div>
                        </div>

                        <div class="fw-bold text-dark mt-auto" style="font-size: 0.88rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.25;" title="<?=htmlspecialchars($sp['ten_san_pham'])?>">
                            <?=htmlspecialchars($sp['ten_san_pham'])?>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2 pt-1 border-top border-light">
                            <span class="num-font fw-bold" style="color: var(--ck-red); font-size: 1.05rem;"><?=number_format($sp['gia_ban'])?> ₫</span>
                            <span class="sku-tag"><?=$sp['ma_san_pham']?></span>
                        </div>
                    </div>
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
                <div class="input-group input-group-sm mb-2 shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-user-pen"></i></span>
                    <input type="text" class="form-control border-start-0 fw-medium" id="posCustomerInput" placeholder="Nhập tên khách hàng..." list="customerSuggestList" autocomplete="off" oninput="handleCustomerInput()">
                    <datalist id="customerSuggestList">
                        <option value="Khách lẻ (Vãng lai)"></option>
                        <?php foreach($khachhang_list as $kh):$tag = !empty($kh['hang_the']) ? '[' .$kh['hang_the'] . ']' : '[Bạc]';
                        ?>
                            <option value="<?= htmlspecialchars($kh['ho_ten']) ?>"><?= $tag ?> - SĐT: <?= htmlspecialchars($kh['so_dien_thoai'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="d-flex align-items-center gap-2 mb-2">
                    <select class="form-select form-select-sm border-0 fw-medium shadow-sm flex-grow-1" id="posCustomerSelect" onchange="handleCustomerSelect()" style="height: 36px;">
                        <option value="" data-name="Khách lẻ (Vãng lai)" data-disc="0" data-tier="none">Khách lẻ (Vãng lai)</option>
                        <?php foreach($khachhang_list as$kh): 
                            $tier =$kh['hang_the'] ?? 'Bạc';
                            $disc = (float)($kh['ti_le_giam_gia'] ?? 0);
                            $badgeLabel = '[' .$tier . ']';
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

                <select class="form-select form-select-sm border-0 text-danger fw-bold shadow-sm" id="posPromoSelect" onchange="calcTotal()" style="height: 36px;">
                    <option value="" data-val="0" data-type="">-- Áp dụng Voucher / Khuyến mãi --</option>
                    <?php foreach($khuyenmai_list as$km): ?><option value="<?=$km['id']?>" data-val="<?=(float)$km['gia_tri']?>" data-type="<?=$km['loai_km']?>">[<?=$km['ma_voucher']?>] <?=$km['ten_km']?></option><?php endforeach; ?>
                </select>
            </div>

            <div id="cartBox" class="flex-grow-1" style="min-height: 200px; max-height: calc(100vh - 490px); overflow-y: auto; padding-right: 5px;"></div>

            <div class="mt-auto pt-3 border-top">
                <div class="d-flex justify-content-between mb-2 small text-muted"><span class="fw-medium">Tạm tính</span><span class="num-font fw-bold text-dark" id="lSub">0 ₫</span></div>
                <div class="d-flex justify-content-between mb-2 small text-success"><span class="fw-medium">Khuyến mãi & Thành viên</span><span class="num-font fw-bold" id="lDisc">- 0 ₫</span></div>
                <div class="d-flex justify-content-between mb-2 small text-muted"><span class="fw-medium">VAT (8%)</span><span class="num-font fw-bold text-dark" id="lVat">0 ₫</span></div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top"><span class="fw-bold fs-6">TỔNG CỘNG</span><span class="num-font fw-bold" style="font-size: 1.75rem; color: var(--ck-red);" id="lTotal">0 ₫</span></div>
                
                <button class="btn-ck btn-ck-primary w-100 mt-3 py-2 fs-6" id="btnGoCheckout" onclick="goToCheckout()">
                    <i class="fa-solid fa-credit-card me-2"></i>Thanh toán đơn hàng
                </button>
                <button class="btn-ck btn-ck-soft w-100 mt-2" onclick="clearCartConfirm()">Hủy & Xóa giỏ hàng</button>
            </div>
        </div>
    </div>
</div>

<script>
    let cart = JSON.parse(localStorage.getItem('ck_pos_cart')) || [];
    let calc = {s:0, d:0, v:0, t:0};
    const fVND = n => Math.round(n).toLocaleString() + ' ₫';
    const beep = () => { try { const x=new (window.AudioContext||window.webkitAudioContext)(), o=x.createOscillator(), g=x.createGain(); o.type='sine'; o.frequency.value=880; g.gain.value=0.05; o.connect(g); g.connect(x.destination); o.start(); o.stop(x.currentTime+0.05); } catch(e){} };

    function saveState() {
        localStorage.setItem('ck_pos_cart', JSON.stringify(cart));
        localStorage.setItem('ck_pos_cus', document.getElementById('posCustomerSelect').value);
        localStorage.setItem('ck_pos_promo', document.getElementById('posPromoSelect').value);
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
                        <img src="../../../assets/images/${skuImg}.jpg" style="width:44px;height:44px;min-width:44px;object-fit:cover;border-radius:10px;border:1px solid #e2e8f0;background:#f8fafc;" onerror="this.src='../../../assets/images/SP001.jpg'; this.onerror=null;">
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

    function triggerSearch(kw, forceAllCat = false) {
        if (forceAllCat) {
            document.querySelectorAll('.seg-btn').forEach(x => x.classList.remove('active'));
            const allBtn = document.querySelector('.seg-btn[data-cat="Tất cả"]');
            if (allBtn) allBtn.classList.add('active');
        }
        const activeCat = document.querySelector('.seg-btn.active').dataset.cat;
        const cleanKw = kw.toLowerCase().trim();

        document.querySelectorAll('.prod-item-col').forEach(c => {
            const matchCat = (activeCat === 'Tất cả' || c.dataset.cat === activeCat);
            const matchKw  = c.dataset.search.includes(cleanKw);
            c.style.display = (matchCat && matchKw) ? '' : 'none';
        });
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
        if(confirm('Bạn có chắc muốn hủy đơn hàng và xóa toàn bộ sản phẩm trong giỏ?')) {
            cart = [];
            document.getElementById('posCustomerInput').value = '';
            document.getElementById('posCustomerSelect').selectedIndex = 0;
            updateTierBadge('none');
            document.getElementById('posPromoSelect').selectedIndex = 0;
            
            localStorage.removeItem('ck_pos_cart');
            localStorage.removeItem('ck_pos_cus');
            localStorage.removeItem('ck_pos_promo');
            
            drawCart();
        }
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

        const savedCus = localStorage.getItem('ck_pos_cus');
        const savedPromo = localStorage.getItem('ck_pos_promo');
        
        if(savedCus) {
            document.getElementById('posCustomerSelect').value = savedCus;
            handleCustomerSelect();
        }
        if(savedPromo) {
            document.getElementById('posPromoSelect').value = savedPromo;
        }
        drawCart();

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