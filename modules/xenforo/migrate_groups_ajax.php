<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Include database connection
include __DIR__ . '/db_connection.php';

// Get input from POST JSON
$input  = json_decode(file_get_contents('php://input'), true);
$offset = intval($input['offset'] ?? 0);
$limit  = intval($input['limit'] ?? 50); // batch size

// =======================
// Fetch groups from XenForo
// =======================
function fetch_groups($xf_conn, $offset, $limit) {
    // Prepare SQL to get groups with pagination
    $sql = "SELECT user_group_id, title FROM xf_user_group
            ORDER BY user_group_id ASC
            LIMIT $offset, $limit";
    $res = $xf_conn->query($sql);
    $groups = [];
    while ($row = $res->fetch_assoc()) {
        $groups[] = [
            'xf_groupid' => intval($row['user_group_id']),
            'title'      => $row['title']
        ];
    }
    return $groups;
}

// =======================
// Insert groups into vBulletin 6
// =======================
function insert_groups($vb_conn, $groups) {
    $count = 0;
    foreach ($groups as $g) {
        $title = $vb_conn->real_escape_string($g['title']);

        // Insert group with default VB6 columns
        $sql = "INSERT INTO usergroup (
            title, description, usertitle, passwordexpires, passwordhistory,
            pmquota, pmsendmax, opentag, closetag, canoverride,
            forumpermissions, forumpermissions2, pmpermissions, wolpermissions, adminpermissions,
            genericpermissions, genericpermissions2, genericoptions, signaturepermissions, visitormessagepermissions,
            attachlimit, avatarmaxwidth, avatarmaxheight, avatarmaxsize, sigpicmaxwidth,
            sigpicmaxheight, sigpicmaxsize, sigmaximages, sigmaxsizebbcode, sigmaxchars,
            sigmaxrawchars, sigmaxlines, usercsspermissions, albumpermissions, albumpicmaxwidth,
            albumpicmaxheight, albummaxpics, albummaxsize, socialgrouppermissions, pmthrottlequantity,
            groupiconmaxsize, maximumsocialgroups, systemgroupid
        ) VALUES (
            '$title', '', '', '0', '', 0, 0, '', '', 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0
        )";

        if ($vb_conn->query($sql)) {
            $count++;
        }
    }
    return $count;
}

// =======================
// Get total groups count
// =======================
$totalRes = $xf_conn->query("SELECT COUNT(*) AS cnt FROM xf_user_group");
$total = $totalRes->fetch_assoc()['cnt'];

// =======================
// Run batch migration
// =======================
$groups = fetch_groups($xf_conn, $offset, $limit);
$migrated = insert_groups($vb_conn, $groups);

// Return result as JSON
echo json_encode([
    'migrated' => $migrated,
    'total'    => $total
]);
exit;
