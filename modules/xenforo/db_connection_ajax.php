<?php
header('Content-Type: application/json');

// Retrieve form inputs
$xf_host = $_POST['xf_host'] ?? '';
$xf_user = $_POST['xf_user'] ?? '';
$xf_pass = $_POST['xf_pass'] ?? '';
$xf_db   = $_POST['xf_db'] ?? '';

$vb_host = $_POST['vb_host'] ?? '';
$vb_user = $_POST['vb_user'] ?? '';
$vb_pass = $_POST['vb_pass'] ?? '';
$vb_db   = $_POST['vb_db'] ?? '';

// Validate input
if (!$xf_host || !$xf_user || !$xf_db || !$vb_host || !$vb_user || !$vb_db) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all database fields.']);
    exit;
}

$errors = [];

// Test XenForo connection
$xf_conn = @new mysqli($xf_host, $xf_user, $xf_pass, $xf_db);
if ($xf_conn->connect_error) {
    $errors[] = "❌ XenForo DB Connection Failed: " . $xf_conn->connect_error;
} else {
    $xf_conn->set_charset('utf8mb4');
}

// Test vBulletin connection
$vb_conn = @new mysqli($vb_host, $vb_user, $vb_pass, $vb_db);
if ($vb_conn->connect_error) {
    $errors[] = "❌ vBulletin DB Connection Failed: " . $vb_conn->connect_error;
} else {
    $vb_conn->set_charset('utf8mb4');
}

// Prepare result
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
} else {
    // Save connections to session for next steps
    session_start();
    $_SESSION['xf'] = [
        'host' => $xf_host,
        'user' => $xf_user,
        'pass' => $xf_pass,
        'db'   => $xf_db
    ];
    $_SESSION['vb'] = [
        'host' => $vb_host,
        'user' => $vb_user,
        'pass' => $vb_pass,
        'db'   => $vb_db
    ];

    echo json_encode(['success' => true, 'message' => '✅ Connection successful for both databases!']);
}

// Close connections
@mysqli_close($xf_conn);
@mysqli_close($vb_conn);
