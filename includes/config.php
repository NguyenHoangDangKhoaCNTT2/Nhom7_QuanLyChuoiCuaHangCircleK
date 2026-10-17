<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

define('APP_NAME', 'Circle K Vietnam');
define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));

// BASE_URL tự tính: clone về để thư mục nào (htdocs/xxx hay php -S) cũng chạy
$__docRoot = rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
define('BASE_URL', ($__docRoot !== '' && strpos(ROOT_PATH, $__docRoot) === 0)
    ? substr(ROOT_PATH, strlen($__docRoot))
    : '');

function asset(string $path): string {
    $file = ROOT_PATH . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : time();
    return BASE_URL . '/assets/' . $path . '?v=' . $v;
}

function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

// LUÔN dùng e() khi in dữ liệu ra HTML (chống XSS)
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function vnd($n): string { return number_format((float)$n, 0, '.', ','); }

function initial(string $name): string {
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    $last  = $words ? end($words) : '';
    $chars = preg_split('//u', $last, -1, PREG_SPLIT_NO_EMPTY);
    $first = $chars[0] ?? '?';
    return function_exists('mb_strtoupper') ? mb_strtoupper($first) : strtoupper($first);
}

// Tạm hardcode — sau này thay bằng $_SESSION
$currentUser = ['name' => 'Nguyễn Văn A', 'role' => 'Admin Tổng'];

$thu = ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'][(int)date('w')];
$homNay = $thu . ', ' . date('d/m/Y');

// Mỗi phần tử: [icon, màu, tiêu đề, nội dung, thời gian]
$notifs = [];

$menu = require __DIR__ . '/menu.php';
