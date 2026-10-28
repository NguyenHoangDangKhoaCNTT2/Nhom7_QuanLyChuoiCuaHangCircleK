<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';
$_SESSION = [];
session_destroy();
header('Location: ' . url('index.php'));
exit;