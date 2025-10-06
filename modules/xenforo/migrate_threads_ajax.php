<?php
// modules/xenforo/migrate_threads_ajax.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Include DB connection
include __DIR__ . '/db_connection.php';

// Mapping forums and users
$forum_mapping = $_SESSION['forum_mapping'] ?? [];
$user_mapping  = $_SESSION['user_mapping'] ?? [];

// Batch parameters
$input  = json_decode(file_get_contents('php://input'), true);
$limit  = intval($input['limit'] ?? 100);

// Initialize offset in session if not exists
if(!isset($_SESSION['migration_offset']['threads'])){
    $_SESSION['migration_offset']['threads'] = 0;
}
$offset = $_SESSION['migration_offset']['threads'];

// =======================
// Fetch threads from XenForo
// =======================
function fetch_threads($xf_conn, $offset, $limit) {
    $sql = "SELECT thread_id, node_id AS forum_id, title, user_id, post_date, reply_count, view_count, sticky
            FROM xf_thread
            ORDER BY thread_id ASC
            LIMIT $offset, $limit";
    $res = $xf_conn->query($sql);
    $threads = [];
    while ($row = $res->fetch_assoc()) {
        $threads[] = [
            'xf_thread_id' => intval($row['thread_id']),
            'xf_forum_id'  => intval($row['forum_id']),
            'title'        => $row['title'],
            'xf_user_id'   => intval($row['user_id']),
            'post_date'    => intval($row['post_date']),
            'reply_count'  => intval($row['reply_count']),
            'view_count'   => intval($row['view_count']),
            'sticky'       => intval($row['sticky'])
        ];
    }
    return $threads;
}

// =======================
// Insert threads to VB6
// =======================
function insert_threads($vb_conn, $threads, $forum_mapping, $user_mapping) {
    $count = 0;
    $mapping = $_SESSION['thread_mapping'] ?? [];

    foreach ($threads as $t) {
        $forumid = $forum_mapping[$t['xf_forum_id']] ?? 0;
        $userid  = $user_mapping[$t['xf_user_id']] ?? 0;
        $title   = $vb_conn->real_escape_string($t['title']);
        $dateline = $t['post_date'];
        $views    = $t['view_count'];
        $sticky   = $t['sticky'];

        $sql = "INSERT INTO thread
                (title, forumid, postuserid, dateline, views, sticky)
                VALUES
                ('$title', $forumid, $userid, $dateline, $views, $sticky)";

        if ($vb_conn->query($sql)) {
            $vb_thread_id = $vb_conn->insert_id;
            $mapping[$t['xf_thread_id']] = $vb_thread_id;
            $count++;
        }
    }

    // Save mapping for posts migration
    $_SESSION['thread_mapping'] = $mapping;

    return $count;
}

// =======================
// Run batch
// =======================
$threads = fetch_threads($xf_conn, $offset, $limit);
$migrated = insert_threads($vb_conn, $threads, $forum_mapping, $user_mapping);

// Update offset in session
$_SESSION['migration_offset']['threads'] += $migrated;

// Total threads for progress (optional)
$total_res = $xf_conn->query("SELECT COUNT(*) as total FROM xf_thread");
$total = $total_res ? intval($total_res->fetch_assoc()['total']) : $migrated;

// Return JSON
echo json_encode([
    'migrated' => $migrated,
    'total'    => $total
]);
exit;
