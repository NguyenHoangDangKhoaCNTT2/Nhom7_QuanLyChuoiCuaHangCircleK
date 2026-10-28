<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle   = 'Đăng nhập';
$loiDangNhap = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taiKhoan = trim($_POST['username'] ?? '');
    $matKhau  = $_POST['password'] ?? '';

    $host = '127.0.0.1'; $db = 'circlek'; $user = 'root'; $pass = '';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // join nhanvien để lấy họ tên + chức vụ hiển thị sau khi đăng nhập
        $stmt = $pdo->prepare("SELECT nd.*, nv.ho_ten, nv.chuc_vu, nv.cuahang_id
                                FROM nguoidung nd
                                LEFT JOIN nhanvien nv ON nv.nguoidung_id = nd.id
                                WHERE nd.username = ?");
        $stmt->execute([$taiKhoan]);
        $acc = $stmt->fetch();

        if (!$acc || $matKhau !== $acc['password']) {
            $loiDangNhap = 'Sai tên đăng nhập hoặc mật khẩu!';
        } elseif ((int)$acc['trang_thai'] !== 1) {
            $loiDangNhap = 'Tài khoản đã bị khóa, liên hệ quản trị viên!';
        } else {
            $_SESSION['user_id']      = (int)$acc['id'];
            $_SESSION['username']     = $acc['username'];
            $_SESSION['ho_ten']       = $acc['ho_ten'] ?: $acc['username'];
            $_SESSION['chuc_vu']      = $acc['chuc_vu'] ?: '';
            $_SESSION['phanquyen_id'] = (int)$acc['phanquyen_id'];
            $_SESSION['cuahang_id']   = $acc['cuahang_id'];
            $_SESSION['vai_tro']      = layVaiTro($pdo, (int)$acc['id'], (int)$acc['phanquyen_id'], $acc['username']);
            header('Location: ' . url('index.php'));
            exit;
        }
    } catch (PDOException $e) {
        $loiDangNhap = 'Không thể kết nối CSDL.';
    }
}

require ROOT_PATH . '/includes/head.php';
?>
<body>
<a href="<?= e(url('index.php')) ?>" class="action-icon-btn" style="position:fixed;top:20px;left:20px;" title="Về trang chủ">
    <i class="fa-solid fa-arrow-left"></i>
</a>
<main class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="bento-panel fade-up" style="max-width: 420px; width: 100%; height: auto;">
        <div class="text-center mb-4">
            <div class="brand-icon mx-auto mb-3">K</div>
            <h3 class="fw-bold mb-1">Đăng nhập</h3>
            <div class="panel-subtitle">Hệ thống quản trị chuỗi Circle K</div>
        </div>

        <?php if ($loiDangNhap !== ''): ?>
<div class="mb-3 py-2 px-3 rounded-3" style="background:#fff1f2;border:1px solid #fecdd3;color:var(--ck-red);font-size:0.86rem;">
    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= e($loiDangNhap) ?>
</div>
<?php endif; ?>

        <form method="POST" action="" class="d-grid gap-3">
            <div>
                <label class="form-label" for="dnTaiKhoan">Tên đăng nhập</label>
                <input type="text" name="username" class="form-control" id="dnTaiKhoan" placeholder="Nhập tên đăng nhập" required>
            </div>
            <div>
                <label class="form-label" for="dnMatKhau">Mật khẩu</label>
                <input type="password" name="password" class="form-control" id="dnMatKhau" placeholder="Nhập mật khẩu" required>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="dnNho">
                    <label class="form-check-label" for="dnNho" style="font-size: 0.86rem;">Ghi nhớ đăng nhập</label>
                </div>
                <a href="#" style="font-size: 0.86rem; color: var(--ck-red); text-decoration: none;">Quên mật khẩu?</a>
            </div>
            <button type="submit" class="btn-ck btn-ck-primary w-100"><i class="fa-solid fa-right-to-bracket"></i> Đăng nhập</button>
        </form>
    </div>
</main>
</body>
</html>