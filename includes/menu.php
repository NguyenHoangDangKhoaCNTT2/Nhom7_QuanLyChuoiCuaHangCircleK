<?php
$vaiTro = $_SESSION['vai_tro'] ?? null; 

$full = require __DIR__ . '/chucnang.php';
array_unshift($full, ['label' => 'Điều hành chuỗi', 'items' => [
    ['key' => 'home', 'label' => 'Trang chủ', 'icon' => 'fa-house', 'color' => '#ff4d6d', 'href' => 'index.php'],
]]);

if ($vaiTro === null) {
    return [
        ['items' => [
            ['key' => 'home', 'label' => 'Trang chủ', 'icon' => 'fa-house', 'color' => '#ff4d6d', 'href' => 'index.php'],
            ['key' => 'sanpham_xem', 'label' => 'Sản phẩm', 'icon' => 'fa-boxes-stacked', 'color' => '#fbbf24', 'href' => 'san_pham.php'],
            ['key' => 'khuyenmai_xem', 'label' => 'Khuyến mãi', 'icon' => 'fa-ticket', 'color' => '#38bdf8', 'href' => 'khuyen_mai.php'],
        ]],
    ];
}

$ketQua = [];
foreach ($full as $nhom) {
    if (!isset($nhom['nhom'])) { 
        $ketQua[] = $nhom;
        continue;
    }
    $itemsConLai = array_values(array_filter($nhom['items'], function ($item) use ($vaiTro) {
        return in_array($item['key'], $vaiTro, true);
    }));
    if (!empty($itemsConLai)) {
        $nhom['items'] = $itemsConLai;
        $ketQua[] = $nhom;
    }
}
return $ketQua;