<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle = 'Đăng nhập';

require ROOT_PATH . '/includes/head.php';
?>
<body>
<main class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="bento-panel fade-up" style="max-width: 420px; width: 100%; height: auto;">
        <div class="text-center mb-4">
            <div class="brand-icon mx-auto mb-3">K</div>
            <h3 class="fw-bold mb-1">Đăng nhập</h3>
            <div class="panel-subtitle">Hệ thống quản trị chuỗi Circle K</div>
        </div>

        <div class="d-grid gap-3">
            <div>
                <label class="form-label" for="dnTaiKhoan">Tên đăng nhập</label>
                <input type="text" class="form-control" id="dnTaiKhoan" placeholder="Nhập tên đăng nhập">
            </div>
            <div>
                <label class="form-label" for="dnMatKhau">Mật khẩu</label>
                <input type="password" class="form-control" id="dnMatKhau" placeholder="Nhập mật khẩu">
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="dnNho">
                    <label class="form-check-label" for="dnNho" style="font-size: 0.86rem;">Ghi nhớ đăng nhập</label>
                </div>
                <a href="#" style="font-size: 0.86rem; color: var(--ck-red); text-decoration: none;">Quên mật khẩu?</a>
            </div>
            <button type="button" class="btn-ck btn-ck-primary w-100"><i class="fa-solid fa-right-to-bracket"></i> Đăng nhập</button>
        </div>
    </div>
</main>
</body>
</html>
