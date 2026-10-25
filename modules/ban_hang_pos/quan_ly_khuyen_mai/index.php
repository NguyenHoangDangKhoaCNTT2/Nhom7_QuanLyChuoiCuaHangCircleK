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
$activeMenu = 'khuyen_mai';

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
</style>

<script>document.querySelector('body').classList.add('layout-fixed');</script>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-float-wrapper th-red">
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
        <div class="stat-float-wrapper th-green">
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
        <div class="stat-float-wrapper th-blue">
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
        <div class="stat-float-wrapper th-amber">
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

<script>
    function filterPromos() {
        const kw = document.getElementById('promoSearch').value.toLowerCase().trim();
        const type = document.getElementById('promoTypeFilter').value;
        const st = document.getElementById('promoStatusFilter').value;
        let count = 0;

        document.querySelectorAll('.promo-row').forEach(r => {
            const mKw = r.dataset.search.includes(kw);
            const mType = !type || r.dataset.type === type;
            const mSt = !st || r.dataset.status === st;
            if (mKw && mType && mSt) {
                r.style.display = '';
                count++;
            } else {
                r.style.display = 'none';
            }
        });

        document.getElementById('emptyPromoMsg').style.display = count === 0 ? '' : 'none';
    }

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
        if (!confirm('Bạn có chắc muốn xóa mã voucher [' + code + '] không?')) return;
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