<?php
// modules/xenforo/migrate_users_ajax.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Include database connection
include __DIR__ . '/db_connection.php';

// Get group mapping from session
$group_mapping = $_SESSION['group_mapping'] ?? [];

// Get input from POST JSON
$input  = json_decode(file_get_contents('php://input'), true);
$offset = intval($input['offset'] ?? 0);
$limit  = intval($input['limit'] ?? 500);

// Define directories
define('VB_AVATAR_DIR', __DIR__ . '/../../core/images/avatars/'); // VB6 avatar folder
define('XF_AVATAR_DIR', __DIR__ . '/../../data/avatars/');        // XenForo avatar folder

// =======================
// Fetch users from XenForo
// =======================
function fetch_users($xf_conn, $offset, $limit) {
    $sql = "SELECT u.user_id, u.username, u.email, u.register_date, u.user_group_id,
                   u.custom_title, p.signature
            FROM xf_user u
            LEFT JOIN xf_user_profile p ON u.user_id = p.user_id
            ORDER BY u.user_id ASC
            LIMIT $offset, $limit";
    $res = $xf_conn->query($sql);
    $users = [];
    while ($row = $res->fetch_assoc()) {
        // Determine avatar file path in XenForo
        $avatar_file = XF_AVATAR_DIR . 'l/' . ($row['user_id'] % 1000) . '/' . $row['user_id'] . '.jpg';
        if (!file_exists($avatar_file)) $avatar_file = '';

        $users[] = [
            'user_id'     => $row['user_id'],
            'username'    => $row['username'],
            'displayname' => $row['username'], // displayname same as username
            'email'       => $row['email'],
            'usertitle'   => $row['custom_title'] ?? '',
            'signature'   => $row['signature'] ?? '',
            'regdate'     => intval($row['register_date']),
            'xf_groupid'  => intval($row['user_group_id']),
            'password'    => '',
            'avatar_path' => $avatar_file,
            'avatar_date' => 0
        ];
    }
    return $users;
}

// =======================
// Copy avatar to VB6
// =======================
function copy_avatar_to_vb6($src_path, $vb_userid) {
    if (!$src_path) return 0;

    $ext = pathinfo($src_path, PATHINFO_EXTENSION);
    if (!$ext) $ext = 'jpg';
    $dst_file = VB_AVATAR_DIR . $vb_userid . '.' . $ext;

    if (file_exists($src_path)) {
        if (@copy($src_path, $dst_file)) return 1; // return revision
    }
    return 0;
}

// =======================
// Insert users into VB6
// =======================
function insert_users($vb_conn, $users, $group_mapping) {
    $count = 0;
    foreach ($users as $u) {
        $username    = $vb_conn->real_escape_string($u['username']);
        $displayname = $username;
        $email       = $vb_conn->real_escape_string($u['email']);
        $usertitle   = $vb_conn->real_escape_string($u['usertitle']);
        $signature   = $vb_conn->real_escape_string($u['signature']);
        $regdate     = intval($u['regdate']);
        $password    = $vb_conn->real_escape_string($u['password']); 

        // Map XenForo group to VB6 group
        $usergroupid = $group_mapping[$u['xf_groupid']] ?? 2;

        // Insert user
        $sql = "INSERT INTO user 
                (username, displayname, email, usertitle, sigpicrevision, joindate, usergroupid, passworddate, posts, avatarid, avatarrevision)
                VALUES
                ('$username', '$displayname', '$email', '$usertitle', 0, $regdate, $usergroupid, '1000-01-01', 0, 0, 0)";

        if ($vb_conn->query($sql)) {
            $vb_userid = $vb_conn->insert_id;

            // Copy avatar if exists
            if ($u['avatar_path']) {
                $avatar_revision = copy_avatar_to_vb6($u['avatar_path'], $vb_userid);
                if ($avatar_revision) {
                    $vb_conn->query("UPDATE user SET avatarid=1, avatarrevision=$avatar_revision WHERE userid=$vb_userid");
                }
            }

            // Update signature if exists
            if ($signature) {
                $sig_safe = $vb_conn->real_escape_string($signature);
                $vb_conn->query("UPDATE user SET signature='$sig_safe' WHERE userid=$vb_userid");
            }

            $count++;
        }
    }
    return $count;
}

// =======================
// Run batch migration
// =======================
$users = fetch_users($xf_conn, $offset, $limit);
$migrated = insert_users($vb_conn, $users, $group_mapping);

// Return JSON response
echo json_encode([
    'migrated' => $migrated
]);
exit;
