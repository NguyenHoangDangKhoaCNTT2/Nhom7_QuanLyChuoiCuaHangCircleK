<?php
require_once __DIR__ . '/includes/config.php';

$host = '127.0.0.1'; $db = 'circlek'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('<div style="padding:20px;color:#e11d48;font-family:sans-serif;">Không thể kết nối CSDL.</div>');
}

$dsKhuyenMai = $pdo->query("SELECT km.*, ch.ten_cua_hang
                             FROM khuyenmai km
                             LEFT JOIN cuahang ch ON km.cuahang_id = ch.id
                             WHERE NOW() BETWEEN km.thoi_gian_bat_dau AND km.thoi_gian_ket_thuc
                             ORDER BY km.thoi_gian_bat_dau DESC")->fetchAll();

$pageTitle  = 'Khuyến mãi';
$activeMenu = 'khuyenmai_xem';

require ROOT_PATH . '/includes/header.php';
?>

<section class="hero-banner mb-4 fade-up">
    <div class="hero-content">
        <div class="hero-badge"><span class="pulse-dot" style="width:7px;height:7px;"></span><span>Ưu đãi đang diễn ra</span></div>
        <h2 class="hero-title">Khuyến mãi Circle K</h2>
        <p class="hero-desc">Các chương trình khuyến mãi đang áp dụng tại cửa hàng.</p>
    </div>
</section>

<div class="row g-3">
    <?php if (empty($dsKhuyenMai)): ?>
    <div class="col-12 bento-panel text-center py-5 text-muted"><i class="fa-solid fa-ticket fs-2 d-block mb-2"></i>Hiện không có khuyến mãi nào đang áp dụng.</div>
    <?php else: foreach ($dsKhuyenMai as $km):
        $noiDung = $km['loai_km'] === 'Giảm %' ? 'Giảm ' . (int)$km['gia_tri'] . '%' : 'Giảm ' . vnd($km['gia_tri']) . ' ₫';
        $phamVi  = $km['ten_cua_hang'] ?: 'Toàn hệ thống';
        $icoPham = $km['ten_cua_hang'] ? 'fa-store' : 'fa-earth-asia';
    ?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="bento-panel fade-up h-100" role="button" data-bs-toggle="modal" data-bs-target="#modalKm<?= (int)$km['id'] ?>" style="cursor:pointer;">
            <span class="badge bg-light text-dark border mb-2"><i class="fa-solid <?= $icoPham ?> text-danger me-1"></i><?= e($phamVi) ?></span>
            <div class="d-flex align-items-start gap-3">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), var(--ck-orange));"><i class="fa-solid fa-ticket"></i></span>
                <div>
                    <div class="fw-bold mb-1"><?= e($km['ten_km']) ?></div>
                    <div class="small text-muted"><?= e($noiDung) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalKm<?= (int)$km['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><?= e($km['ten_km']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><span class="text-muted">Phạm vi áp dụng:</span> <span class="badge bg-light text-dark border"><i class="fa-solid <?= $icoPham ?> text-danger me-1"></i><?= e($phamVi) ?></span></div>
                    <?php if (!empty($km['ma_voucher'])): ?>
                    <div class="mb-2"><span class="text-muted">Mã voucher:</span> <span class="order-code"><?= e($km['ma_voucher']) ?></span></div>
                    <?php endif; ?>
                    <div class="mb-2"><span class="text-muted">Nội dung:</span> <strong><?= e($noiDung) ?></strong></div>
                    <div class="mb-2"><span class="text-muted">Bắt đầu:</span> <?= date('d/m/Y H:i', strtotime($km['thoi_gian_bat_dau'])) ?></div>
                    <div><span class="text-muted">Kết thúc:</span> <?= date('d/m/Y H:i', strtotime($km['thoi_gian_ket_thuc'])) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>