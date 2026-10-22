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

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS khuyenmai (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ma_voucher VARCHAR(50) NOT NULL UNIQUE,
        ten_km VARCHAR(255) NOT NULL,
        loai_km VARCHAR(50) NOT NULL DEFAULT 'Tiền mặt',
        gia_tri DECIMAL(12,2) NOT NULL DEFAULT 0,
        so_luong INT NULL,
        so_luong_da_dung INT DEFAULT 0,
        thoi_gian_bat_dau DATETIME NOT NULL,
        thoi_gian_ket_thuc DATETIME NOT NULL,
        cuahang_id INT NULL,
        trang_thai TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("ALTER TABLE khuyenmai ADD COLUMN trang_thai TINYINT(1) NOT NULL DEFAULT 1");
} catch (Exception $e) { }

try {
    $pdo->exec("UPDATE khuyenmai SET trang_thai = 1 WHERE trang_thai IS NULL");
} catch (Exception $e) { }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_POST['action'] === 'add_promo') {
        try {
            $code       = strtoupper(trim($_POST['ma_voucher'] ?? ''));
            $name       = trim($_POST['ten_km'] ?? '');
            $type       = trim($_POST['loai_km'] ?? 'Tiền mặt');
            $val        = (float)($_POST['gia_tri'] ?? 0);
            $qty        = !empty($_POST['so_luong']) ? (int)$_POST['so_luong'] : null;
            $start      = trim($_POST['thoi_gian_bat_dau'] ?? date('Y-m-d 00:00:00'));
            $end        = trim($_POST['thoi_gian_ket_thuc'] ?? date('Y-m-d 23:59:59'));
            $store_id   = !empty($_POST['cuahang_id']) ? (int)$_POST['cuahang_id'] : null;

            if (empty($code) || empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã và tên chương trình!']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO khuyenmai (ma_voucher, ten_km, loai_km, gia_tri, so_luong, thoi_gian_bat_dau, thoi_gian_ket_thuc, cuahang_id, trang_thai) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$code, $name, $type, $val, $qty, $start, $end, $store_id]);

            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Mã voucher đã tồn tại hoặc lỗi: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($_POST['action'] === 'delete_promo') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM khuyenmai WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

$cuahang_list = $pdo->query("SELECT id, ten_cua_hang FROM cuahang WHERE trang_thai = 1 ORDER BY id ASC")->fetchAll();

$now = date('Y-m-d H:i:s');
$promosRaw = $pdo->query("SELECT km.*, ch.ten_cua_hang 
                          FROM khuyenmai km 
                          LEFT JOIN cuahang ch ON km.cuahang_id = ch.id 
                          ORDER BY km.id DESC")->fetchAll();

$promos = [];
$cntTotal = count($promosRaw);
$cntRunning = 0;
$cntUpcoming = 0;
$cntEnded = 0;

foreach ($promosRaw as $p) {
    $stCol = isset($p['trang_thai']) ? (int)$p['trang_thai'] : 1;
    $startTime = $p['thoi_gian_bat_dau'] ?? ($p['ngay_bat_dau'] ?? '');
    $endTime   = $p['thoi_gian_ket_thuc'] ?? ($p['ngay_ket_thuc'] ?? '');

    if ($stCol === 0) {
        $statusKey = 'paused';
        $statusLabel = 'Tạm dừng';
        $statusClass = 'badge-km-paused';
    } elseif (!empty($endTime) && $endTime < $now) {
        $statusKey = 'ended';
        $statusLabel = 'Đã kết thúc';
        $statusClass = 'badge-km-ended';
        $cntEnded++;
    } elseif (!empty($startTime) && $startTime > $now) {
        $statusKey = 'upcoming';
        $statusLabel = 'Sắp diễn ra';
        $statusClass = 'badge-km-upcoming';
        $cntUpcoming++;
    } else {
        $statusKey = 'running';
        $statusLabel = 'Đang chạy';
        $statusClass = 'badge-km-running';
        $cntRunning++;
    }

    $p['status_key']   = $statusKey;
    $p['status_label'] = $statusLabel;
    $p['status_class'] = $statusClass;
    $p['start_fmt']    = !empty($startTime) ? date('d/m/Y', strtotime($startTime)) : '--';
    $p['end_fmt']      = !empty($endTime) ? date('d/m/Y', strtotime($endTime)) : '--';
    $promos[] = $p;
}

$pageTitle  = 'Quản lý Khuyến mãi';
$activeMenu = 'quan_ly_khuyen_mai';

require ROOT_PATH . '/includes/header.php';
?>

<style>
    .stat-card-km {
        border-radius: 16px;
        padding: 18px 22px;
        background: #fff;
        border: 1.5px solid var(--border-light);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .stat-card-km::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: transparent;
        transition: all 0.2s;
    }
    .stat-card-km:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.07);
    }
    .stat-card-km.c-total:hover { border-color: var(--ck-red); }
    .stat-card-km.c-total:hover::after { background: var(--ck-red); }
    .stat-card-km.c-run:hover { border-color: #10b981; }
    .stat-card-km.c-run:hover::after { background: #10b981; }
    .stat-card-km.c-upc:hover { border-color: #f59e0b; }
    .stat-card-km.c-upc:hover::after { background: #f59e0b; }
    .stat-card-km.c-end:hover { border-color: #64748b; }
    .stat-card-km.c-end:hover::after { background: #64748b; }

    .stat-icon-wrap-km {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    .stat-click-hint-km {
        font-size: 0.72rem;
        font-weight: 600;
        color: #94a3b8;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-top: 6px;
        transition: 0.15s;
    }
    .stat-card-km:hover .stat-click-hint-km { color: var(--ck-red); }

    .code-pill {
        display: inline-flex;
        align-items: center;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 3px 8px;
        font-family: monospace;
        font-weight: 700;
        font-size: 0.88rem;
        color: #1e293b;
        letter-spacing: 0.5px;
    }
    .copy-btn {
        border: none;
        background: transparent;
        color: #94a3b8;
        padding: 2px 4px;
        cursor: pointer;
        transition: 0.15s;
        border-radius: 4px;
    }
    .copy-btn:hover { color: var(--ck-red); background: #fee2e2; }

    .badge-status-km {
        padding: 5px 12px;
        border-radius: 99px;
        font-size: 0.76rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .badge-km-running { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .badge-km-upcoming { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .badge-km-ended { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
    .badge-km-paused { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

    .badge-scope {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 99px;
        font-size: 0.76rem;
        font-weight: 600;
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #334155;
    }
    .badge-km-type {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 700;
    }
    .badge-type-cash { background: #dcfce7; color: #047857; }
    .badge-type-percent { background: #e0f2fe; color: #0369a1; }
    .badge-type-voucher { background: #fef3c7; color: #b45309; }

    .hub-nav-pill-km { border: none; background: #f1f5f9; color: #475569; font-weight: 600; font-size: 0.85rem; border-radius: 10px; padding: 8px 16px; transition: all 0.2s; }
    .hub-nav-pill-km.active { background: var(--ck-red); color: #fff; box-shadow: 0 4px 12px rgba(238,49,36,0.25); }
    .hub-metric-tile-km { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; }
    .hub-progress-track { height: 8px; background: #e2e8f0; border-radius: 99px; overflow: hidden; margin-top: 6px; }
    .hub-progress-bar { height: 100%; border-radius: 99px; }

    .table-km th { font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 700; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .table-km td { font-size: 0.88rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
</style>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card-km c-total" onclick="openPromoHub('all')" title="Nhấn để xem toàn cảnh chiến dịch khuyến mãi">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <small class="text-muted fw-bold">TỔNG CHƯƠNG TRÌNH</small>
                    <div class="num-font fw-bold fs-3 mt-1 text-dark"><?= $cntTotal ?></div>
                    <div class="stat-click-hint-km"><i class="fa-solid fa-chart-pie"></i> Xem cơ cấu chiến dịch ↗</div>
                </div>
                <div class="stat-icon-wrap-km" style="background: rgba(238,49,36,0.1); color: var(--ck-red);"><i class="fa-solid fa-tag"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card-km c-run" onclick="openPromoHub('running')" title="Nhấn để xem các ưu đãi đang kích hoạt">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <small class="text-muted fw-bold">ĐANG CHẠY</small>
                    <div class="num-font fw-bold fs-3 mt-1" style="color: #10b981;"><?= $cntRunning ?></div>
                    <div class="stat-click-hint-km" style="color: #10b981;"><i class="fa-solid fa-bolt"></i> Hiệu suất sử dụng ↗</div>
                </div>
                <div class="stat-icon-wrap-km" style="background: rgba(16,185,129,0.1); color: #10b981;"><i class="fa-solid fa-bolt"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card-km c-upc" onclick="openPromoHub('upcoming')" title="Nhấn để xem kế hoạch chương trình sắp tới">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <small class="text-muted fw-bold">SẮP DIỄN RA</small>
                    <div class="num-font fw-bold fs-3 mt-1" style="color: #f59e0b;"><?= $cntUpcoming ?></div>
                    <div class="stat-click-hint-km" style="color: #f59e0b;"><i class="fa-solid fa-clock"></i> Lịch trình tung mã ↗</div>
                </div>
                <div class="stat-icon-wrap-km" style="background: rgba(245,158,11,0.1); color: #f59e0b;"><i class="fa-solid fa-clock"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card-km c-end" onclick="openPromoHub('ended')" title="Nhấn để xem kiểm toán và lịch sử kết thúc">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <small class="text-muted fw-bold">ĐÃ KẾT THÚC</small>
                    <div class="num-font fw-bold fs-3 mt-1 text-muted"><?= $cntEnded ?></div>
                    <div class="stat-click-hint-km"><i class="fa-solid fa-calendar-check"></i> Kiểm toán & Xuất CSV ↗</div>
                </div>
                <div class="stat-icon-wrap-km" style="background: rgba(148,163,184,0.15); color: #64748b;"><i class="fa-solid fa-calendar-check"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="bento-panel fade-up">
    <div class="panel-header flex-wrap gap-2">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), var(--ck-orange))"><i class="fa-solid fa-tags"></i></span>
                Danh sách Chiến dịch Khuyến mãi & Voucher
            </h5>
            <div class="panel-subtitle ms-5">Quản lý mã giảm giá, thiết lập ưu đãi cho chuỗi cửa hàng Circle K Nha Trang</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn-ck btn-ck-soft" onclick="openPromoHub('all')">
                <i class="fa-solid fa-chart-simple me-1"></i> Trung tâm Phân tích
            </button>
            <button type="button" class="btn-ck btn-ck-primary" data-bs-toggle="modal" data-bs-target="#modalAddPromo">
                <i class="fa-solid fa-plus me-1"></i> Tạo khuyến mãi mới
            </button>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-12 col-md-4">
            <div class="search-bar w-100">
                <i class="fa-solid fa-magnifying-glass s-ico"></i>
                <input type="text" id="filterPromoInput" placeholder="Tìm theo mã voucher (VD: CK20), tên chương trình..." onkeyup="filterPromotions()">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <select class="form-select" id="filterStatus" onchange="filterPromotions()" style="height: 44px; border-radius: 10px;">
                <option value="">Trạng thái: Tất cả</option>
                <option value="Đang chạy">Đang chạy</option>
                <option value="Sắp diễn ra">Sắp diễn ra</option>
                <option value="Đã kết thúc">Đã kết thúc</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select" id="filterType" onchange="filterPromotions()" style="height: 44px; border-radius: 10px;">
                <option value="">Loại: Tất cả</option>
                <option value="Tiền mặt">Tiền mặt</option>
                <option value="Phần trăm">Phần trăm</option>
                <option value="Voucher">Voucher</option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <select class="form-select" id="filterStore" onchange="filterPromotions()" style="height: 44px; border-radius: 10px;">
                <option value="">🏪 Toàn bộ chi nhánh</option>
                <option value="Toàn chuỗi">Toàn chuỗi</option>
                <?php foreach ($cuahang_list as $ch): ?>
                    <option value="<?= htmlspecialchars($ch['ten_cua_hang']) ?>"><?= htmlspecialchars($ch['ten_cua_hang']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-km align-middle">
            <thead>
                <tr>
                    <th>Mã Voucher</th>
                    <th>Tên chương trình</th>
                    <th>Phạm vi áp dụng</th>
                    <th>Loại ưu đãi</th>
                    <th class="text-end">Mức giảm</th>
                    <th class="text-center">Số lượng</th>
                    <th>Thời gian áp dụng</th>
                    <th class="text-center">Trạng thái</th>
                    <th class="text-center">Thao tác</th>
                </tr>
            </thead>
            <tbody id="promoTableBody">
                <?php if (empty($promos)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-ticket fs-2 mb-2 d-block opacity-25"></i>
                            Chưa có chương trình khuyến mãi nào trong hệ thống.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($promos as $pm): 
                        $scopeText = !empty($pm['ten_cua_hang']) ? $pm['ten_cua_hang'] : 'Toàn chuỗi';
                        $isAllStore = empty($pm['ten_cua_hang']);
                        $valDisplay = ($pm['loai_km'] === 'Phần trăm') ? ($pm['gia_tri'] . '%') : (number_format($pm['gia_tri']) . ' VNĐ');
                        $qtyDisplay = ($pm['so_luong'] === null) ? '∞ Vô hạn' : number_format($pm['so_luong']);
                        $searchMeta = mb_strtolower($pm['ma_voucher'] . ' ' . $pm['ten_km'] . ' ' . $scopeText . ' ' . $pm['loai_km'] . ' ' . $pm['status_label'] . ' ' . $pm['gia_tri']);
                    ?>
                    <tr class="promo-item-row" 
                        data-search="<?= $searchMeta ?>"
                        data-status="<?= $pm['status_label'] ?>"
                        data-type="<?= $pm['loai_km'] ?>"
                        data-store="<?= $scopeText ?>">
                        <td>
                            <div class="code-pill">
                                <span><?= htmlspecialchars($pm['ma_voucher']) ?></span>
                                <button type="button" class="copy-btn ms-1" onclick="copyVoucherCode('<?= htmlspecialchars($pm['ma_voucher']) ?>', this)" title="Sao chép mã voucher">
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark" style="font-size: 0.92rem;"><?= htmlspecialchars($pm['ten_km']) ?></div>
                        </td>
                        <td>
                            <span class="badge-scope">
                                <?php if ($isAllStore): ?>
                                    <i class="fa-solid fa-globe text-danger"></i> Toàn chuỗi
                                <?php else: ?>
                                    <i class="fa-solid fa-store text-primary"></i> <?= htmlspecialchars($pm['ten_cua_hang']) ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($pm['loai_km'] === 'Tiền mặt'): ?>
                                <span class="badge-km-type badge-type-cash"><i class="fa-solid fa-money-bill"></i> Tiền mặt</span>
                            <?php elseif ($pm['loai_km'] === 'Phần trăm'): ?>
                                <span class="badge-km-type badge-type-percent"><i class="fa-solid fa-percent"></i> Phần trăm</span>
                            <?php else: ?>
                                <span class="badge-km-type badge-type-voucher"><i class="fa-solid fa-ticket"></i> Voucher</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <span class="num-font fw-bold text-dark fs-6"><?= $valDisplay ?></span>
                        </td>
                        <td class="text-center">
                            <span class="fw-semibold <?= ($pm['so_luong'] === null) ? 'text-muted' : 'text-primary' ?>"><?= $qtyDisplay ?></span>
                        </td>
                        <td>
                            <div class="small fw-semibold text-dark">
                                <?= $pm['start_fmt'] ?> – <?= $pm['end_fmt'] ?>
                            </div>
                            <small class="text-muted" style="font-size: 0.72rem;">00:00 đến 23:59</small>
                        </td>
                        <td class="text-center">
                            <span class="badge-status-km <?= $pm['status_class'] ?>">
                                <?php if ($pm['status_key'] === 'running'): ?>
                                    <i class="fa-solid fa-circle-check"></i> Đang chạy
                                <?php elseif ($pm['status_key'] === 'upcoming'): ?>
                                    <i class="fa-solid fa-clock"></i> Sắp diễn ra
                                <?php elseif ($pm['status_key'] === 'ended'): ?>
                                    <i class="fa-solid fa-circle-stop"></i> Đã kết thúc
                                <?php else: ?>
                                    <i class="fa-solid fa-pause"></i> Tạm dừng
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-light border px-2 py-1 me-1 text-primary" title="Xem chi tiết voucher" onclick="viewPromoDetail(<?= htmlspecialchars(json_encode($pm)) ?>)">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-light border px-2 py-1 text-danger" title="Xóa khuyến mãi" onclick="deletePromo(<?= $pm['id'] ?>, '<?= htmlspecialchars($pm['ma_voucher']) ?>')">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <div id="noPromoMatchMsg" class="text-center py-5 text-muted" style="display: none;">
            <i class="fa-solid fa-search fs-2 mb-2 d-block opacity-25"></i>
            Không tìm thấy chương trình khuyến mãi nào phù hợp với từ khóa tìm kiếm.
        </div>
    </div>
</div>

<div class="modal fade" id="modalPromoHub" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div>
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                        <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), #f97316); width:36px; height:36px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; color:#fff;">
                            <i class="fa-solid fa-chart-pie"></i>
                        </span>
                        Trung tâm Phân tích & Kiểm toán Khuyến mãi Circle K
                    </h5>
                    <div class="small text-muted ms-5">Báo cáo hiệu suất voucher, ngân sách chiết khấu & chiến dịch toàn chuỗi Nha Trang</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <div class="d-flex flex-wrap gap-2 mb-4 p-1 bg-light rounded-3" id="promoHubTabsNav">
                    <button class="hub-nav-pill-km active flex-grow-1" onclick="switchPromoHubTab('all', this)"><i class="fa-solid fa-tag me-1"></i> Cơ cấu Toàn chiến dịch</button>
                    <button class="hub-nav-pill-km flex-grow-1" onclick="switchPromoHubTab('running', this)"><i class="fa-solid fa-bolt me-1"></i> Đang kích hoạt (<?= $cntRunning ?>)</button>
                    <button class="hub-nav-pill-km flex-grow-1" onclick="switchPromoHubTab('upcoming', this)"><i class="fa-solid fa-clock me-1"></i> Lịch sắp tới (<?= $cntUpcoming ?>)</button>
                    <button class="hub-nav-pill-km flex-grow-1" onclick="switchPromoHubTab('ended', this)"><i class="fa-solid fa-calendar-check me-1"></i> Lịch sử & Kiểm toán (<?= $cntEnded ?>)</button>
                </div>

                <div id="promoTabPane_all" class="promo-hub-pane">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <div class="hub-metric-tile-km text-center">
                                <small class="text-muted fw-bold">TỔNG MÃ KHUYẾN MÃI</small>
                                <div class="num-font fw-bold text-dark fs-3 mt-1"><?= $cntTotal ?> chương trình</div>
                                <span class="badge bg-primary-subtle text-primary mt-1">Đầy đủ các phân nhóm</span>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="hub-metric-tile-km text-center">
                                <small class="text-muted fw-bold">TỶ LỆ KÍCH HOẠT HIỆN TẠI</small>
                                <?php $runRate = $cntTotal > 0 ? round(($cntRunning / $cntTotal) * 100, 1) : 0; ?>
                                <div class="num-font fw-bold text-success fs-3 mt-1"><?= $runRate ?>%</div>
                                <small class="text-muted"><?= $cntRunning ?> đang chạy đồng thời</small>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="hub-metric-tile-km text-center">
                                <small class="text-muted fw-bold">PHẠM VI ÁP DỤNG</small>
                                <div class="num-font fw-bold text-primary fs-3 mt-1">10 Cửa hàng</div>
                                <small class="text-muted">Toàn chuỗi & Chi nhánh riêng</small>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border">
                        <div class="fw-bold text-dark mb-2 small"><i class="fa-solid fa-chart-column me-1 text-danger"></i> PHÂN BỔ LOẠI KHUYẾN MÃI</div>
                        <div class="d-flex justify-content-between small fw-semibold mb-1">
                            <span>Ưu đãi Tiền mặt trực tiếp</span>
                            <span>50%</span>
                        </div>
                        <div class="hub-progress-track mb-3">
                            <div class="hub-progress-bar" style="width: 50%; background: #10b981;"></div>
                        </div>
                        <div class="d-flex justify-content-between small fw-semibold mb-1">
                            <span>Giảm theo Phần trăm (%)</span>
                            <span>33.3%</span>
                        </div>
                        <div class="hub-progress-track mb-3">
                            <div class="hub-progress-bar" style="width: 33.3%; background: #0ea5e9;"></div>
                        </div>
                        <div class="d-flex justify-content-between small fw-semibold mb-1">
                            <span>Voucher giá trị tặng kèm</span>
                            <span>16.7%</span>
                        </div>
                        <div class="hub-progress-track">
                            <div class="hub-progress-bar" style="width: 16.7%; background: #f59e0b;"></div>
                        </div>
                    </div>
                </div>

                <div id="promoTabPane_running" class="promo-hub-pane" style="display: none;">
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-bolt text-success me-1"></i> Các chiến dịch đang có hiệu lực</h6>
                                <small class="text-muted">Thu ngân có thể áp dụng ngay tại màn hình bán hàng POS.</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-success px-3" onclick="filterTableByStatus('Đang chạy')">
                                <i class="fa-solid fa-filter me-1"></i> Lọc danh sách Đang chạy
                            </button>
                        </div>
                        <div class="row g-2 mt-2">
                            <?php foreach ($promos as $pm): if ($pm['status_key'] === 'running'): ?>
                            <div class="col-12 col-md-6">
                                <div class="p-2 bg-white rounded border">
                                    <div class="d-flex justify-content-between">
                                        <strong class="font-monospace text-danger"><?= htmlspecialchars($pm['ma_voucher']) ?></strong>
                                        <span class="badge bg-success-subtle text-success">Active</span>
                                    </div>
                                    <div class="small text-dark text-truncate mt-1"><?= htmlspecialchars($pm['ten_km']) ?></div>
                                    <div class="small text-muted mt-1">Hạn dùng: <?= $pm['end_fmt'] ?></div>
                                </div>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                </div>

                <div id="promoTabPane_upcoming" class="promo-hub-pane" style="display: none;">
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-clock text-warning me-1"></i> Chiến dịch đã lên lịch sắp tới</h6>
                                <small class="text-muted">Hệ thống sẽ tự động kích hoạt khi đến ngày bắt đầu.</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-warning text-dark px-3" onclick="filterTableByStatus('Sắp diễn ra')">
                                <i class="fa-solid fa-filter me-1"></i> Lọc danh sách Sắp diễn ra
                            </button>
                        </div>
                        <div class="row g-2 mt-2">
                            <?php foreach ($promos as $pm): if ($pm['status_key'] === 'upcoming'): ?>
                            <div class="col-12 col-md-6">
                                <div class="p-2 bg-white rounded border">
                                    <div class="d-flex justify-content-between">
                                        <strong class="font-monospace text-warning"><?= htmlspecialchars($pm['ma_voucher']) ?></strong>
                                        <span class="badge bg-warning-subtle text-warning">Upcoming</span>
                                    </div>
                                    <div class="small text-dark text-truncate mt-1"><?= htmlspecialchars($pm['ten_km']) ?></div>
                                    <div class="small text-muted mt-1">Bắt đầu: <?= $pm['start_fmt'] ?></div>
                                </div>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                </div>

                <div id="promoTabPane_ended" class="promo-hub-pane" style="display: none;">
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-calendar-check text-secondary me-1"></i> Lịch sử kiểm toán chiến dịch đã hoàn tất</h6>
                                <small class="text-muted">Dữ liệu lưu trữ phục vụ đối soát doanh thu và báo cáo ban giám đốc.</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary px-3" onclick="filterTableByStatus('Đã kết thúc')">
                                <i class="fa-solid fa-filter me-1"></i> Lọc danh sách Đã kết thúc
                            </button>
                        </div>
                        <div class="row g-2 mt-2">
                            <?php foreach ($promos as $pm): if ($pm['status_key'] === 'ended'): ?>
                            <div class="col-12 col-md-6">
                                <div class="p-2 bg-white rounded border">
                                    <div class="d-flex justify-content-between">
                                        <strong class="font-monospace text-muted"><?= htmlspecialchars($pm['ma_voucher']) ?></strong>
                                        <span class="badge bg-light text-muted border">Closed</span>
                                    </div>
                                    <div class="small text-dark text-truncate mt-1"><?= htmlspecialchars($pm['ten_km']) ?></div>
                                    <div class="small text-muted mt-1">Đã kết thúc: <?= $pm['end_fmt'] ?></div>
                                </div>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn-ck btn-ck-primary px-4" onclick="exportPromoToCSV()">
                    <i class="fa-solid fa-file-csv me-1"></i> Xuất danh sách khuyến mãi (CSV)
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAddPromo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 18px; border: none;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-ticket text-danger me-2"></i>Tạo chương trình Khuyến mãi mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAddPromo" onsubmit="submitAddPromo(event)">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">MÃ VOUCHER (VIẾT HOA, KHÔNG DẤU)</label>
                        <input type="text" class="form-control font-monospace fw-bold" name="ma_voucher" placeholder="VD: TET2027, HAPPYHOUR..." required style="text-transform: uppercase;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">TÊN CHƯƠNG TRÌNH KHUYẾN MÃI</label>
                        <input type="text" class="form-control fw-semibold" name="ten_km" placeholder="VD: Mừng Tết Rộn Ràng, Giảm Giá Cà Phê..." required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">HÌNH THỨC GIẢM</label>
                            <select class="form-select fw-semibold" name="loai_km" id="addTypeSelect" onchange="adjustAddValLabel()">
                                <option value="Tiền mặt">Tiền mặt (VNĐ)</option>
                                <option value="Phần trăm">Phần trăm (%)</option>
                                <option value="Voucher">Voucher tiền mặt</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted" id="addValLabel">MỨC GIẢM (VNĐ)</label>
                            <input type="number" class="form-control num-font fw-bold" name="gia_tri" placeholder="VD: 20000 hoặc 10" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">SỐ LƯỢNG MÃ (ĐỂ TRỐNG = VÔ HẠN)</label>
                            <input type="number" class="form-control" name="so_luong" placeholder="Vô hạn">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">PHẠM VI ÁP DỤNG</label>
                            <select class="form-select" name="cuahang_id">
                                <option value="">Toàn chuỗi Nha Trang</option>
                                <?php foreach ($cuahang_list as $ch): ?>
                                    <option value="<?= $ch['id'] ?>"><?= htmlspecialchars($ch['ten_cua_hang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">NGÀY BẮT ĐẦU</label>
                            <input type="date" class="form-control" name="thoi_gian_bat_dau" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">NGÀY KẾT THÚC</label>
                            <input type="date" class="form-control" name="thoi_gian_ket_thuc" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn-ck btn-ck-soft" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-ck btn-ck-primary px-4"><i class="fa-solid fa-check me-1"></i> Khởi tạo Voucher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalViewPromo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 18px; border: none;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-qrcode text-danger me-2"></i>Thẻ Voucher Điện tử</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="fw-bold fs-5 text-dark" id="vTitle">Ưu đãi Circle K</div>
                    <div class="display-6 font-monospace fw-bold text-danger my-2" id="vCode">CK20</div>
                    <div class="badge bg-danger fs-6 px-3 py-2" id="vDiscount">GIẢM 20.000 VNĐ</div>
                </div>
                <div class="text-start small p-3 bg-white rounded border">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Phạm vi:</span><strong id="vScope">Toàn chuỗi</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Thời hạn:</span><span id="vTime">17/10/2026 - 31/12/2026</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Số lượng cấp:</span><span id="vQty">Vô hạn</span>
                    </div>
                    <div class="d-flex justify-content-between pt-2 border-top">
                        <span class="text-muted">Trạng thái:</span><span id="vStatus" class="fw-bold">Đang chạy</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-ck btn-ck-primary w-100" data-bs-dismiss="modal">Đóng thẻ</button>
            </div>
        </div>
    </div>
</div>

<script>
    function filterPromotions(forcedKw) {
        let kw = '';
        if (typeof forcedKw === 'string') {
            kw = forcedKw;
        } else {
            const localInp = document.getElementById('filterPromoInput');
            if (localInp && localInp.value.trim() !== '') {
                kw = localInp.value;
            } else {
                const headerInp = document.querySelector('input[placeholder*="SKU"], input[placeholder*="hóa đơn"], input[placeholder*="cửa hàng"]');
                if (headerInp) kw = headerInp.value;
            }
        }
        kw = kw.toLowerCase().trim();

        const stVal    = document.getElementById('filterStatus').value;
        const typeVal  = document.getElementById('filterType').value;
        const storeVal = document.getElementById('filterStore').value;

        const rows = document.querySelectorAll('.promo-item-row');
        let visibleCount = 0;

        rows.forEach(r => {
            const rowSearch = r.getAttribute('data-search') || '';
            const rowStatus = r.getAttribute('data-status') || '';
            const rowType   = r.getAttribute('data-type') || '';
            const rowStore  = r.getAttribute('data-store') || '';

            const matchKw    = (kw === '' || rowSearch.includes(kw));
            const matchSt    = (!stVal || rowStatus === stVal);
            const matchType  = (!typeVal || rowType === typeVal);
            const matchStore = (!storeVal || rowStore === storeVal);

            if (matchKw && matchSt && matchType && matchStore) {
                r.style.display = '';
                visibleCount++;
            } else {
                r.style.display = 'none';
            }
        });

        document.getElementById('noPromoMatchMsg').style.display = (visibleCount === 0) ? '' : 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const localInp = document.getElementById('filterPromoInput');
        const allInputs = document.querySelectorAll('input');

        allInputs.forEach(inp => {
            const ph = (inp.getAttribute('placeholder') || '').toLowerCase();
            if (ph.includes('tìm hóa đơn') || ph.includes('sku') || ph.includes('cửa hàng')) {
                inp.addEventListener('input', function() {
                    if (localInp) localInp.value = this.value;
                    filterPromotions(this.value);
                });
                inp.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (localInp) localInp.value = this.value;
                        filterPromotions(this.value);
                    }
                });

                if (inp.value.trim() !== '') {
                    if (localInp) localInp.value = inp.value;
                    filterPromotions(inp.value);
                }
            }
        });

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey && e.key.toLowerCase() === 'k') || e.key === 'F2') {
                e.preventDefault();
                const target = localInp || document.querySelector('input[placeholder*="SKU"]');
                if (target) {
                    target.focus();
                    target.select();
                }
            }
        });
    });

    function openPromoHub(tabKey) {
        new bootstrap.Modal(document.getElementById('modalPromoHub')).show();
        const navBtns = document.querySelectorAll('#promoHubTabsNav .hub-nav-pill-km');
        let targetBtn = navBtns[0];
        if (tabKey === 'running') targetBtn = navBtns[1];
        else if (tabKey === 'upcoming') targetBtn = navBtns[2];
        else if (tabKey === 'ended') targetBtn = navBtns[3];
        switchPromoHubTab(tabKey, targetBtn);
    }

    function switchPromoHubTab(tabKey, btnEl) {
        document.querySelectorAll('#promoHubTabsNav .hub-nav-pill-km').forEach(b => b.classList.remove('active'));
        if (btnEl) btnEl.classList.add('active');
        document.querySelectorAll('.promo-hub-pane').forEach(p => p.style.display = 'none');
        const activePane = document.getElementById('promoTabPane_' + tabKey);
        if (activePane) activePane.style.display = '';
    }

    function filterTableByStatus(statusName) {
        const modalEl = document.getElementById('modalPromoHub');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        document.getElementById('filterStatus').value = statusName;
        filterPromotions();
        document.querySelector('.table-responsive').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function copyVoucherCode(code, btnEl) {
        navigator.clipboard.writeText(code).then(() => {
            const originalHtml = btnEl.innerHTML;
            btnEl.innerHTML = '<i class="fa-solid fa-check text-success"></i>';
            setTimeout(() => { btnEl.innerHTML = originalHtml; }, 1500);
        });
    }

    function viewPromoDetail(pm) {
        document.getElementById('vTitle').textContent = pm.ten_km;
        document.getElementById('vCode').textContent  = pm.ma_voucher;
        const discountStr = (pm.loai_km === 'Phần trăm') ? ('GIẢM ' + pm.gia_tri + '%') : ('GIẢM ' + parseInt(pm.gia_tri).toLocaleString() + ' VNĐ');
        document.getElementById('vDiscount').textContent = discountStr;
        document.getElementById('vScope').textContent  = pm.ten_cua_hang ? pm.ten_cua_hang : 'Toàn chuỗi Circle K';
        document.getElementById('vTime').textContent   = pm.start_fmt + ' đến ' + pm.end_fmt;
        document.getElementById('vQty').textContent    = (pm.so_luong === null) ? '∞ Vô hạn' : pm.so_luong + ' voucher';
        document.getElementById('vStatus').textContent = pm.status_label;
        new bootstrap.Modal(document.getElementById('modalViewPromo')).show();
    }

    function adjustAddValLabel() {
        const t = document.getElementById('addTypeSelect').value;
        document.getElementById('addValLabel').textContent = (t === 'Phần trăm') ? 'MỨC GIẢM (%)' : 'MỨC GIẢM (VNĐ)';
    }

    function submitAddPromo(e) {
        e.preventDefault();
        const fd = new FormData(document.getElementById('formAddPromo'));
        fd.append('action', 'add_promo');

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(err => alert('Lỗi kết nối máy chủ: ' + err));
    }

    function deletePromo(id, code) {
        if (!confirm(`Bạn có chắc chắn muốn xóa chương trình khuyến mãi ${code}?`)) return;
        const fd = new FormData();
        fd.append('action', 'delete_promo');
        fd.append('id', id);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
                else alert('Không thể xóa: ' + data.message);
            })
            .catch(err => alert('Lỗi kết nối: ' + err));
    }

    function exportPromoToCSV() {
        const rows = document.querySelectorAll('.promo-item-row');
        if (rows.length === 0) return alert('Không có dữ liệu để xuất!');

        let csv = "\uFEFF";
        csv += "Mã Voucher,Tên chương trình,Phạm vi,Loại ưu đãi,Mức giảm,Số lượng,Thời gian áp dụng,Trạng thái\n";

        rows.forEach(r => {
            const code  = r.querySelector('.code-pill span').innerText.trim();
            const name  = r.querySelector('td:nth-child(2)').innerText.trim();
            const scope = r.getAttribute('data-store') || 'Toàn chuỗi';
            const type  = r.getAttribute('data-type') || '';
            const val   = r.querySelector('td:nth-child(5)').innerText.trim();
            const qty   = r.querySelector('td:nth-child(6)').innerText.trim();
            const time  = r.querySelector('td:nth-child(7)').innerText.replace(/\n/g, ' ').trim();
            const st    = r.getAttribute('data-status') || '';

            csv += `"${code}","${name}","${scope}","${type}","${val}","${qty}","${time}","${st}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", `Bao_cao_Khuyen_Mai_CircleK_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('aside a, .sidebar a, nav a, .nav-link').forEach(link => {
            const href = link.getAttribute('href') || '';
            if (href.includes('quan_ly_khuyen_mai') || href.includes('khuyen_mai')) {
                link.classList.add('active');
                
                // Nền gradient đỏ cam & đổ bóng phát sáng giống hệt ảnh 1
                link.style.setProperty('background', 'linear-gradient(135deg, #ee3124 0%, #ff4b3a 100%)', 'important');
                link.style.setProperty('color', '#ffffff', 'important');
                link.style.setProperty('border-radius', '16px', 'important');
                link.style.setProperty('box-shadow', '0 8px 24px rgba(238, 49, 36, 0.38)', 'important');
                link.style.setProperty('border', 'none', 'important');

                // Toàn bộ chữ bên trong thành màu trắng
                link.querySelectorAll('*').forEach(el => {
                    el.style.setProperty('color', '#ffffff', 'important');
                });

                // Ô chứa icon thành nền trắng mờ (translucent)
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