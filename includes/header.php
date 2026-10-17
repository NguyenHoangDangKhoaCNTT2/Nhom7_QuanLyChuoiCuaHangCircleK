<?php
// Đầu mỗi trang khai báo: $pageTitle, $activeMenu (key trong menu.php), tuỳ chọn $pageCss = ['ten.css']
$activeMenu = $activeMenu ?? '';
require __DIR__ . '/head.php';
?>
<body>

<?php require __DIR__ . '/sidebar.php'; ?>
<div class="backdrop-ck" id="backdrop"></div>

<div id="content-wrapper">
    <?php require __DIR__ . '/topbar.php'; ?>

    <main class="container-fluid px-3 px-lg-4 py-4">
