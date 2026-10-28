<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

define('APP_NAME', 'Circle K Vietnam');
define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));

$__curDir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
$__relPath = trim(str_ireplace(ROOT_PATH, '', $__curDir), '/');
$__depth   = $__relPath === '' ? 0 : substr_count($__relPath, '/') + 1;
$__urlDir  = explode('/', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'));
define('BASE_URL', $__depth > 0 ? implode('/', array_slice($__urlDir, 0, -$__depth)) : implode('/', $__urlDir));

function asset(string $path): string {
    $file = ROOT_PATH . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : time();
    return BASE_URL . '/assets/' . $path . '?v=' . $v;
}

function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function vnd($n): string { return number_format((float)$n, 0, '.', ','); }

function initial(string $name): string {
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    $last  = $words ? end($words) : '';
    $chars = preg_split('//u', $last, -1, PREG_SPLIT_NO_EMPTY);
    $first = $chars[0] ?? '?';
    return function_exists('mb_strtoupper') ? mb_strtoupper($first) : strtoupper($first);
}

function layVaiTro(PDO $pdo, int $nguoidung_id, int $phanquyen_id, string $username): array {
    $stmt = $pdo->prepare("SELECT nhom FROM phanquyen_tuychinh WHERE nguoidung_id = ?");
    $stmt->execute([$nguoidung_id]);
    $tuyChinh = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($tuyChinh)) return $tuyChinh;

    if ($phanquyen_id === 1) return ['nhan_su', 'hang_hoa_kho', 'ban_hang', 'thong_ke']; // Giám Đốc - full
    $theoTaiKhoan = [
        'qlt_phu'  => 'hang_hoa_kho', 
        'qlt_nam'  => 'ban_hang',     
        'qlt_thai' => 'thong_ke', 
    ];
    if (isset($theoTaiKhoan[$username])) return [$theoTaiKhoan[$username]];

    $map = [2 => 'thong_ke', 3 => 'nhan_su', 4 => 'ban_hang', 5 => 'hang_hoa_kho'];
    return [$map[$phanquyen_id] ?? 'nhan_su'];
}

$thu = ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'][(int)date('w')];
$homNay = $thu . ', ' . date('d/m/Y');

$menu = require __DIR__ . '/menu.php';