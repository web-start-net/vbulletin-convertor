<?php
// db_connection.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$xf_conn = null;
$vb_conn = null;

// Check if DB config exists
if (!empty($_SESSION['db_config'])) {
    $config = $_SESSION['db_config'];

    // Connect to XenForo
    $xf_conn = @new mysqli($config['xf_host'], $config['xf_user'], $config['xf_pass'], $config['xf_db']);
    if ($xf_conn->connect_error) {
        die(json_encode(['success' => false, 'message' => "XenForo connection failed: {$xf_conn->connect_error}"]));
    }
    $xf_conn->set_charset("utf8mb4");

    // Connect to vBulletin
    $vb_conn = @new mysqli($config['vb_host'], $config['vb_user'], $config['vb_pass'], $config['vb_db']);
    if ($vb_conn->connect_error) {
        die(json_encode(['success' => false, 'message' => "vBulletin connection failed: {$vb_conn->connect_error}"]));
    }
    $vb_conn->set_charset("utf8mb4");
} else {
    die(json_encode(['success' => false, 'message' => "Database configuration not found in session."]));
}
