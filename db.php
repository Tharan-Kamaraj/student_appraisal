<?php
// DB connection and session bootstrap
session_start();

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'student_appraisal';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

function require_login($role = null) {
    if (!isset($_SESSION['user'])) {
        // Default unauthenticated redirect: combined login page
        header('Location: /student-appraisal/index.php');
        exit;
    }
    if ($role && $_SESSION['user']['role'] !== $role) {
        // Role mismatch: send to combined login
        header('Location: /student-appraisal/index.php');
        exit;
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
            http_response_code(400);
            echo 'Invalid CSRF token';
            exit;
        }
    }
}

function set_flash($type, $msg) {
    $_SESSION['flash'][] = ['type'=>$type,'msg'=>$msg];
}
function consume_flash() {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function is_role($role) { return isset($_SESSION['user']) && $_SESSION['user']['role'] === $role; }
function is_any_role($roles) { return isset($_SESSION['user']) && in_array($_SESSION['user']['role'], (array)$roles); }
?>
