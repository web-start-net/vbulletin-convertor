<?php
// modules/xenforo/migrate_posts_ajax.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Include DB connection
include __DIR__ . '/db_connection.php';

// Mapping users and threads
$user_mapping   = $_SESSION['user_mapping'] ?? [];
$thread_mapping = $_SESSION['thread_mapping'] ?? [];

// Batch parameters
$input  = json_decode(file_get_contents('php://input'), true);
$limit  = intval($input['limit'] ?? 200);

// Initialize offset in session if not exists
if(!isset($_SESSION['migration_offset']['posts'])){
    $_SESSION['migration_offset']['posts'] = 0;
}
$offset = $_SESSION['migration_offset']['posts'];

// =======================
// Fetch posts from XenForo
// =======================
function fetch_posts($xf_conn, $offset, $limit) {
    $sql = "SELECT post_id, thread_id, user_id, post_date, message
            FROM xf_post
            ORDER BY post_id ASC
            LIMIT $offset, $limit";
    $res = $xf_conn->query($sql);
    $posts = [];
    while ($row = $res->fetch_assoc()) {
        $posts[] = [
            'xf_post_id'   => intval($row['post_id']),
            'xf_thread_id' => intval($row['thread_id']),
            'xf_user_id'   => intval($row['user_id']),
            'post_date'    => intval($row['post_date']),
            'message'      => $row['message']
        ];
    }
    return $posts;
}

// =======================
// Insert posts into VB6
// =======================
function insert_posts($vb_conn, $posts, $user_mapping, $thread_mapping) {
    $count = 0;
    $mapping = $_SESSION['post_mapping'] ?? [];

    foreach ($posts as $p) {
        $threadid = $thread_mapping[$p['xf_thread_id']] ?? 0;
        $userid   = $user_mapping[$p['xf_user_id']] ?? 0;
        $message  = $vb_conn->real_escape_string($p['message']);
        $dateline = $p['post_date'];

        if ($threadid && $userid) {
            $sql = "INSERT INTO post
                    (threadid, userid, dateline, pagetext)
                    VALUES
                    ($threadid, $userid, $dateline, '$message')";
            if ($vb_conn->query($sql)) {
                $vb_post_id = $vb_conn->insert_id;
                $mapping[$p['xf_post_id']] = $vb_post_id;
                $count++;
            }
        }
    }

    // Save mapping for any future reference
    $_SESSION['post_mapping'] = $mapping;

    return $count;
}

// =======================
// Run batch
// =======================
$posts = fetch_posts($xf_conn, $offset, $limit);
$migrated = insert_posts($vb_conn, $posts, $user_mapping, $thread_mapping);

// Update offset in session
$_SESSION['migration_offset']['posts'] += $migrated;

// Optional: total posts for progress tracking
$total_res = $xf_conn->query("SELECT COUNT(*) as total FROM xf_post");
$total = $total_res ? intval($total_res->fetch_assoc()['total']) : $migrated;

// Return JSON
echo json_encode([
    'migrated' => $migrated,
    'total'    => $total
]);
exit;
