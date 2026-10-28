<?php
$vaiTro = $_SESSION['vai_tro'] ?? null; 

$full = require __DIR__ . '/chucnang.php';
array_unshift($full, ['label' => 'Điều hành chuỗi', 'items' => [
    ['key' => 'home', 'label' => 'Trang chủ', 'icon' => 'fa-house', 'color' => '#ff4d6d', 'href' => 'index.php'],
]]);

// chưa đăng nhập -> chỉ Trang chủ + 2 trang xem công khai
if ($vaiTro === null) {
    return [
        ['items' => [
            ['key' => 'home', 'label' => 'Trang chủ', 'icon' => 'fa-house', 'color' => '#ff4d6d', 'href' => 'index.php'],
            ['key' => 'sanpham_xem', 'label' => 'Sản phẩm', 'icon' => 'fa-boxes-stacked', 'color' => '#fbbf24', 'href' => 'san_pham.php'],
            ['key' => 'khuyenmai_xem', 'label' => 'Khuyến mãi', 'icon' => 'fa-ticket', 'color' => '#38bdf8', 'href' => 'khuyen_mai.php'],
        ]],
    ];
}

// Giám Đốc -> xem full chức năng
if ($vaiTro === 'full') return $full;

// vai trò khác -> chỉ xem trang quản lý + Trang chủ
return array_values(array_filter($full, function ($g) use ($vaiTro) {
    return !isset($g['nhom']) || in_array($g['nhom'], $vaiTro, true);
}));