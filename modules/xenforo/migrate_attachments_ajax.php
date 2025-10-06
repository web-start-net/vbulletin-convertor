<?php
// modules/xenforo/migrate_attachments_ajax.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Include DB connection
include __DIR__ . '/db_connection.php';

// Mapping posts
$post_mapping = $_SESSION['post_mapping'] ?? [];

// Batch parameters
$input  = json_decode(file_get_contents('php://input'), true);
$limit  = intval($input['limit'] ?? 100);

// Initialize offset in session if not exists
if(!isset($_SESSION['migration_offset']['attachments'])){
    $_SESSION['migration_offset']['attachments'] = 0;
}
$offset = $_SESSION['migration_offset']['attachments'];

// Directories
define('XF_ATTACHMENT_DIR', '/path/to/xenforo/data/attachments/');  // update your path
define('VB_ATTACHMENT_DIR', __DIR__ . '/../../core/images/attachments/');

// =======================
// Fetch attachments from XenForo
// =======================
function fetch_attachments($xf_conn, $offset, $limit) {
    $sql = "SELECT attach_id, content_id AS post_id, filename, file_size, file_hash, attach_date
            FROM xf_attachment
            ORDER BY attach_id ASC
            LIMIT $offset, $limit";
    $res = $xf_conn->query($sql);
    $attachments = [];
    while ($row = $res->fetch_assoc()) {
        $attachments[] = [
            'xf_attach_id' => intval($row['attach_id']),
            'xf_post_id'   => intval($row['post_id']),
            'filename'     => $row['filename'],
            'file_size'    => intval($row['file_size']),
            'file_hash'    => $row['file_hash'],
            'attach_date'  => intval($row['attach_date'])
        ];
    }
    return $attachments;
}

// =======================
// Copy attachment file
// =======================
function copy_attachment($src, $dst) {
    if (file_exists($src)) {
        return @copy($src, $dst);
    }
    return false;
}

// =======================
// Insert attachments into VB6
// =======================
function insert_attachments($vb_conn, $attachments, $post_mapping) {
    $count = 0;
    foreach ($attachments as $a) {
        $vb_post_id = $post_mapping[$a['xf_post_id']] ?? 0;
        if (!$vb_post_id) continue;

        $src_path = XF_ATTACHMENT_DIR . $a['file_hash'];
        $dst_file = VB_ATTACHMENT_DIR . $a['filename'];

        if (copy_attachment($src_path, $dst_file)) {
            $filename_safe = $vb_conn->real_escape_string($a['filename']);
            $vb_conn->query("INSERT INTO attachment
                             (postid, filename, filesize, dateline)
                             VALUES
                             ($vb_post_id, '$filename_safe', {$a['file_size']}, {$a['attach_date']})");
            $count++;
        }
    }
    return $count;
}

// =======================
// Run batch
// =======================
$attachments = fetch_attachments($xf_conn, $offset, $limit);
$migrated = insert_attachments($vb_conn, $attachments, $post_mapping);

// Update offset in session
$_SESSION['migration_offset']['attachments'] += $migrated;

// Optional: total attachments for progress tracking
$total_res = $xf_conn->query("SELECT COUNT(*) as total FROM xf_attachment");
$total = $total_res ? intval($total_res->fetch_assoc()['total']) : $migrated;

// Return JSON
echo json_encode([
    'migrated' => $migrated,
    'total'    => $total
]);
exit;
