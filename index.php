<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle  = 'Trang chủ';
$activeMenu = 'home';

$daDangNhap = isset($_SESSION['vai_tro']);

// 4 vai trò quản lý - 4 nhóm chức năng trong menu.php
$vaiTroList = [
    'nhan_su'      => ['label' => 'Tài khoản & Nhân sự', 'icon' => 'fa-users-gear',     'color' => 'var(--emerald), #059669', 'mau' => 'var(--emerald)', 'desc' => 'Quản lý cửa hàng, nhân viên, khách hàng',   'href' => 'modules/tai_khoan_nhan_su/quan_ly_nhan_vien/index.php'],
    'hang_hoa_kho' => ['label' => 'Hàng hóa & Kho',       'icon' => 'fa-boxes-stacked', 'color' => 'var(--ck-amber), #f97316', 'mau' => 'var(--ck-amber)', 'desc' => 'Sản phẩm, nhập kho, điều chuyển kho',       'href' => 'modules/hang_hoa_kho/quan_ly_san_pham/index.php'],
    'ban_hang'     => ['label' => 'Bán hàng (POS)',       'icon' => 'fa-cash-register', 'color' => 'var(--ocean), #6366f1',    'mau' => 'var(--ocean)',    'desc' => 'Bán hàng, hóa đơn, khuyến mãi, thanh toán', 'href' => 'modules/ban_hang_pos/man_hinh_ban_hang/index.php'],
    'thong_ke'     => ['label' => 'Thống kê & Báo cáo',   'icon' => 'fa-chart-line',    'color' => 'var(--violet), #ec4899',   'mau' => 'var(--violet)',   'desc' => 'Dashboard, so sánh, báo cáo doanh thu',     'href' => 'modules/thong_ke_bao_cao/dashboard_cua_hang/index.php'],
];

$dsKmNoiBat = [];
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=circlek;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $dsKmNoiBat = $pdo->query("SELECT ten_km FROM khuyenmai WHERE NOW() BETWEEN thoi_gian_bat_dau AND thoi_gian_ket_thuc ORDER BY thoi_gian_bat_dau DESC LIMIT 3")->fetchAll();
} catch (PDOException $e) {
}

require ROOT_PATH . '/includes/header.php';
?>

<style>
.role-card {
    border-top: 4px solid var(--mau-nhan, var(--ck-red));
    transition: transform .25s cubic-bezier(.4,0,.2,1), box-shadow .25s ease;
}
.role-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
}
.section-heading {
    display: flex;
    align-items: center;
    gap: 8px;
}
.section-heading .bar {
    width: 4px;
    height: 20px;
    border-radius: 99px;
    background: linear-gradient(135deg, var(--ck-red), var(--ck-orange));
    display: inline-block;
}
</style>

<section class="hero-banner mb-4 fade-up">
    <div class="hero-content">
        <div class="hero-badge"><span class="pulse-dot" style="width:7px;height:7px;"></span><span>Hệ thống quản trị chuỗi</span></div>
        <h2 class="hero-title">Điều hành chuỗi Circle K Việt Nam</h2>
        <p class="hero-desc">
            <?php if ($daDangNhap): ?>
                Chào mừng trở lại, <?= e($_SESSION['ho_ten']) ?>!
            <?php else: ?>
                Một nền tảng duy nhất để vận hành toàn bộ chuỗi cửa hàng Circle K.
            <?php endif; ?>
        </p>
        <?php if (!$daDangNhap): ?>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <span class="hero-badge mb-0"><i class="fa-solid fa-shield-halved me-2"></i>Bảo mật theo vai trò</span>
            <span class="hero-badge mb-0"><i class="fa-solid fa-store me-2"></i>Quản lý đa cửa hàng</span>
            <span class="hero-badge mb-0"><i class="fa-solid fa-bolt me-2"></i>Vận hành tức thời</span>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($dsKmNoiBat)): ?>
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-heading"><span class="bar"></span><h5 class="fw-bold mb-0" style="color: var(--ink-900);">Khuyến mãi đang diễn ra</h5></div>
        <a href="<?= e(url('khuyen_mai.php')) ?>" class="small fw-semibold text-decoration-none" style="color: var(--ck-red);">Xem tất cả <i class="fa-solid fa-arrow-right ms-1"></i></a>
    </div>
    <div class="row g-3">
        <?php foreach ($dsKmNoiBat as $km): ?>
        <div class="col-12 col-md-4">
            <a href="<?= e(url('khuyen_mai.php')) ?>" class="bento-panel fade-up d-flex align-items-center gap-3 text-decoration-none" style="color: inherit; padding: 16px 18px;">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), var(--ck-orange)); flex-shrink: 0;"><i class="fa-solid fa-ticket"></i></span>
                <div class="fw-semibold" style="font-size: 0.9rem;"><?= e($km['ten_km']) ?></div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="section-heading mb-3">
    <span class="bar"></span><h5 class="fw-bold mb-0" style="color: var(--ink-900);">Khu vực quản lý</h5>
</div>

<div class="row g-4 justify-content-center">
<?php if (!$daDangNhap): ?>
    <?php $i = 0; foreach ($vaiTroList as $vt): $i++; ?>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="bento-panel role-card fade-up d-<?= $i ?> h-100 text-center" style="--mau-nhan: <?= $vt['mau'] ?>;" role="button" onclick="showToast('<?= e($vt['label']) ?>')">
            <span class="title-icon mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;background: linear-gradient(135deg, <?= $vt['color'] ?>);"><i class="fa-solid <?= e($vt['icon']) ?>"></i></span>
            <div class="fw-bold mb-1"><?= e($vt['label']) ?></div>
            <div class="small text-muted"><?= e($vt['desc']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
<?php else:
    $nhomDuocCap = $_SESSION['vai_tro'];
    $laDayDu     = count(array_intersect($nhomDuocCap, array_keys($vaiTroList))) >= count($vaiTroList);
?>
    <?php if ($laDayDu): ?>
        <?php $i = 0; foreach ($vaiTroList as $vt): $i++; ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="<?= e(url($vt['href'])) ?>" class="bento-panel role-card fade-up d-<?= $i ?> d-block h-100 text-center text-decoration-none" style="color:inherit; --mau-nhan: <?= $vt['mau'] ?>;">
                <span class="title-icon mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;background: linear-gradient(135deg, <?= $vt['color'] ?>);"><i class="fa-solid <?= e($vt['icon']) ?>"></i></span>
                <div class="fw-bold mb-1"><?= e($vt['label']) ?></div>
                <div class="small text-muted"><?= e($vt['desc']) ?></div>
            </a>
        </div>
        <?php endforeach; ?>
    <?php else: foreach ($nhomDuocCap as $key):
        $vt = $vaiTroList[$key] ?? null;
        if (!$vt) continue;
    ?>
    <div class="col-12 col-sm-6 col-xl-<?= count($nhomDuocCap) <= 2 ? 6 : 3 ?>">
        <a href="<?= e(url($vt['href'])) ?>" class="bento-panel role-card fade-up d-block h-100 text-center text-decoration-none" style="color:inherit; --mau-nhan: <?= $vt['mau'] ?>;">
            <span class="title-icon mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;background: linear-gradient(135deg, <?= $vt['color'] ?>);"><i class="fa-solid <?= e($vt['icon']) ?>"></i></span>
            <div class="fw-bold mb-1"><?= e($vt['label']) ?></div>
            <div class="small text-muted"><?= e($vt['desc']) ?></div>
        </a>
    </div>
    <?php endforeach; endif; ?>
<?php endif; ?>
</div>

<?php if (!$daDangNhap): ?>
<div class="toast align-items-center text-white border-0 position-fixed" id="loginToast" role="alert" style="bottom:20px;right:20px;z-index:1080;background: var(--ck-red);">
    <div class="d-flex">
        <div class="toast-body" id="loginToastMsg"></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
</div>
<script>
function showToast(vaiTro) {
    document.getElementById('loginToastMsg').textContent = 'Vui lòng đăng nhập để sử dụng chức năng ' + vaiTro + '!';
    new bootstrap.Toast(document.getElementById('loginToast')).show();
}
</script>
<?php endif; ?>

<?php require ROOT_PATH . '/includes/footer.php'; ?>