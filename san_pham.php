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

$search   = trim($_GET['q'] ?? '');
$filterDm = (int)($_GET['dm'] ?? 0);

$where  = "WHERE sp.trang_thai = 1";
$params = [];
if ($search !== '') { $where .= " AND sp.ten_san_pham LIKE ?"; $params[] = "%$search%"; }
if ($filterDm > 0)  { $where .= " AND sp.danhmuc_id = ?"; $params[] = $filterDm; }

$stmt = $pdo->prepare("SELECT sp.*, dm.ten_danh_muc FROM sanpham sp LEFT JOIN danhmuc dm ON sp.danhmuc_id = dm.id $where ORDER BY sp.ten_san_pham ASC");
$stmt->execute($params);
$dsSanPham = $stmt->fetchAll();

$dsDanhMuc = $pdo->query("SELECT id, ten_danh_muc FROM danhmuc ORDER BY id ASC")->fetchAll();

$pageTitle  = 'Sản phẩm';
$activeMenu = 'sanpham_xem';

require ROOT_PATH . '/includes/header.php';
?>

<section class="hero-banner mb-4 fade-up">
    <div class="hero-content">
        <div class="hero-badge"><span class="pulse-dot" style="width:7px;height:7px;"></span><span>Danh mục sản phẩm</span></div>
        <h2 class="hero-title">Sản phẩm tại Circle K</h2>
        <p class="hero-desc">Xem các sản phẩm đang có tại cửa hàng.</p>
    </div>
</section>

<div class="bento-panel fade-up">
    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2 mb-4">
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass s-ico"></i>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Tìm tên sản phẩm...">
        </div>
        <select name="dm" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="0">Tất cả loại sản phẩm</option>
            <?php foreach ($dsDanhMuc as $dm): ?>
                <option value="<?= $dm['id'] ?>" <?= $filterDm === (int)$dm['id'] ? 'selected' : '' ?>><?= e($dm['ten_danh_muc']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($search !== '' || $filterDm > 0): ?>
            <a href="san_pham.php" class="btn-ck btn-ck-soft text-danger"><i class="fa-solid fa-rotate-left"></i> Đặt lại</a>
        <?php endif; ?>
    </form>

    <div class="row g-3">
        <?php if (empty($dsSanPham)): ?>
        <div class="col-12 text-center py-5 text-muted"><i class="fa-solid fa-box-open fs-2 d-block mb-2"></i>Không tìm thấy sản phẩm nào.</div>
        <?php else: foreach ($dsSanPham as $sp):
            $img = asset('images/' . strtoupper($sp['ma_san_pham']) . '.jpg');
        ?>
        <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
            <div class="h-100 border rounded-4 p-2 text-center" style="border-color: var(--border-light) !important;">
                <img src="<?= e($img) ?>" alt="<?= e($sp['ten_san_pham']) ?>" class="w-100 mb-2 rounded-3" style="aspect-ratio:1/1; object-fit:cover; background:#f1f5f9;" onerror="this.onerror=null;this.src='';">
                <div class="fw-semibold" style="font-size:0.84rem;"><?= e($sp['ten_san_pham']) ?></div>
                <div class="small text-muted mb-1"><?= e($sp['ten_danh_muc'] ?? '') ?></div>
                <div class="fw-bold" style="color: var(--ck-red); font-size:0.9rem;"><?= vnd($sp['gia_ban']) ?> ₫</div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>