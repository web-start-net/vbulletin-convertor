<?php
// migrate_forums_ajax.php
// XenForo -> vBulletin 6 forums migration (categories + channels)
// Requirements:
//  - db_connection.php must exist and set $xf_conn (mysqli connected to XenForo DB).
//  - vBulletin root path must be set in $vb_root and vB6 init.php must be available.
//  - PHP session enabled.

session_start();
header('Content-Type: application/json; charset=utf-8');

// ----------------------
// Configuration
// ----------------------
$vb_root = '/home/ayvsiwyc/public_html/vb6/'; // <-- set your vBulletin root here
$init_path = $vb_root . 'core/includes/init.php';
$log_file = __DIR__ . '/migration.log';

// simple logger helper
function log_msg($msg) {
    global $log_file;
    file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

// check vBulletin init
if (!file_exists($init_path)) {
    log_msg("vBulletin init.php not found at $init_path");
    echo json_encode(['error' => 'vBulletin init.php not found at ' . $init_path]);
    exit;
}

// init vBulletin environment
define('VB_ENTRY', 1);
define('VB_AREA', 'Forum');
require_once($init_path);

// include XenForo DB connection (must set $xf_conn)
include __DIR__ . '/db_connection.php';
if (!isset($xf_conn) || !($xf_conn instanceof mysqli)) {
    log_msg('XenForo DB connection ($xf_conn) not available.');
    echo json_encode(['error' => 'XenForo DB connection not available.']);
    exit;
}

// ----------------------
// Input (offset/limit)
// ----------------------
$input = json_decode(file_get_contents('php://input'), true);
$offset = intval($input['offset'] ?? 0);
$limit = intval($input['limit'] ?? 50);
if ($offset < 0) $offset = 0;
if ($limit <= 0) $limit = 50;

// initialize session maps/counters
if (!isset($_SESSION['migration_map'])) $_SESSION['migration_map'] = []; // xf_node_id => vb_nodeid
if (!isset($_SESSION['migration_offset'])) $_SESSION['migration_offset'] = [];
if (!isset($_SESSION['migration_offset']['forums'])) $_SESSION['migration_offset']['forums'] = 0;

// Use session map for parent linking
$node_map = &$_SESSION['migration_map'];

// ----------------------
// Helpers
// ----------------------

/**
 * Generate a safe urlident and ensure uniqueness by appending -1,-2... if needed
 * Uses vB_String::getUrlIdent() if available.
 */
function make_unique_urlident($title, &$used_cache) {
    if (function_exists('vB_String::getUrlIdent')) {
        $base = vB_String::getUrlIdent($title);
    } else {
        // fallback: simple slug
        $base = preg_replace('/[^a-z0-9]+/i', '-', trim(mb_strtolower($title)));
        $base = trim($base, '-');
        if ($base === '') $base = 'node';
    }

    $slug = $base;
    $i = 1;
    while (in_array($slug, $used_cache)) {
        $slug = $base . '-' . $i;
        $i++;
    }
    $used_cache[] = $slug;
    return $slug;
}

/**
 * Try to add a channel, with retry on route-duplicate by changing urlident
 */
function try_add_channel($channelLib, $data, &$used_urlidents) {
    // attempt max N times
    $attempt = 0;
    $maxAttempts = 5;
    $lastException = null;

    while ($attempt < $maxAttempts) {
        $attempt++;
        // ensure urlident unique in-this-batch
        if (empty($data['urlident'])) {
            $data['urlident'] = make_unique_urlident($data['title'], $used_urlidents);
        }

        try {
            $result = $channelLib->add($data, ['nodeonly' => false, 'skipFloodCheck' => true, 'skipTransaction' => false]);
            return $result;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            // detect duplicate route data or invalid route errors
            if (stripos($msg, 'Duplicate route data') !== false || stripos($msg, 'duplicate route') !== false || stripos($msg, 'invalid_data') !== false || stripos($msg, 'invalid_data_requested') !== false) {
                // modify urlident and retry
                $data['urlident'] = $data['urlident'] . '-' . $attempt;
                // also record to used list
                $used_urlidents[] = $data['urlident'];
                $lastException = $e;
                // small pause not necessary here
                continue;
            } else {
                // unrecoverable
                throw $e;
            }
        }
    }

    // if we exhausted attempts, rethrow last exception
    if ($lastException) throw $lastException;
    return null;
}

/**
 * Update a channel's parent using content_channel update if available.
 */
function update_channel_parent($channelLib, $nodeid, $newParentId) {
    // The vB library usually offers update($nodeid, $data)
    if (method_exists($channelLib, 'update')) {
        try {
            $updateData = ['parentid' => intval($newParentId)];
            $res = $channelLib->update($nodeid, $updateData);
            return $res;
        } catch (Exception $e) {
            throw $e;
        }
    } else {
        // fallback: try direct DB update (risky) - prefer library method. We'll log and skip if not available.
        throw new Exception('channelLib->update() not available; cannot change parent via API.');
    }
}

// ----------------------
// Fetch nodes (XenForo)
// ----------------------
function fetch_nodes($xf_conn, $offset, $limit) {
    // fetch nodes (both categories and forums). We fetch all node types so migration can decide type.
    $sql = "SELECT node_id, title, description, parent_node_id, node_type_id, display_order FROM xf_node ORDER BY node_id ASC LIMIT ?, ?";
    $stmt = $xf_conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('XenForo query prepare failed: ' . $xf_conn->error);
    }
    $stmt->bind_param('ii', $offset, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'node_id' => intval($r['node_id']),
            'title' => $r['title'],
            'description' => $r['description'] ?? '',
            'parent_node_id' => intval($r['parent_node_id']),
            'node_type_id' => $r['node_type_id'],
            'display_order' => intval($r['display_order']),
        ];
    }
    $stmt->close();
    return $rows;
}

// ----------------------
// Main migration logic
// ----------------------
try {
    log_msg("Starting forums migration batch offset={$offset} limit={$limit}");

    // get total for progress
    $total_res = $xf_conn->query("SELECT COUNT(*) AS total FROM xf_node");
    $total = $total_res ? intval($total_res->fetch_assoc()['total']) : null;

    $nodes = fetch_nodes($xf_conn, $offset, $limit);
    $fetched = count($nodes);
    log_msg("Fetched {$fetched} nodes from XenForo at offset {$offset}");

    if ($fetched === 0) {
        echo json_encode(['migrated' => 0, 'fetched' => 0, 'total' => $total]);
        exit;
    }

    // prepare vB channel library
    if (!class_exists('vB_Library')) {
        throw new Exception('vB_Library class not loaded.');
    }
    $channelLib = vB_Library::instance('content_channel');
    if (!$channelLib) throw new Exception('content_channel library not available.');

    $used_urlidents = []; // to avoid duplicates within this batch
    $created_map = []; // xf_node_id => generated vb nodeid for nodes created this batch
    $created_nodes_info = []; // store node info for later parent-fix

    $migrated_count = 0;
    $errors = [];

    // Two-pass within batch:
    // Pass A: try to create each node. If parent mapping exists in session/node_map, use that parent. Otherwise use 0 (root).
    foreach ($nodes as $n) {
        $xf_id = $n['node_id'];
        $title = trim($n['title'] ?? "Node {$xf_id}");
        $description = trim($n['description'] ?? '');
        $xf_parent = $n['parent_node_id'];

        $parentid_for_vb = 0;
        if ($xf_parent > 0 && isset($node_map[$xf_parent])) {
            $parentid_for_vb = intval($node_map[$xf_parent]);
        } else {
            $parentid_for_vb = 0; // will try to fix later if parent is migrated later
        }

        // Decide channel type: 2 for category (no content), 1 for forum (channel)
        // Heuristic: if node has children (cannot know here) or node_type_id indicates Category
        // Try using node_type_id guess: commonly 'Category' -> bytes representing 'Category' or hex value.
        $channeltypeid = 1; // default to channel
        $node_type = $n['node_type_id'];
        if (is_string($node_type) && stripos($node_type, 'category') !== false) {
            $channeltypeid = 2;
        }
        // Also, if description empty and parent == 0, we may treat as category
        if ($xf_parent == 0 && $description === '') {
            $channeltypeid = 2;
        }

        // prepare data
        $data = [
            'title' => $title,
            'description' => $description,
            'parentid' => $parentid_for_vb,
            'displayorder' => $n['display_order'] ?: 1,
            'userid' => 1, // admin user id; change if needed
            'authorname' => 'Migration Script',
            'created' => time(),
            'publishdate' => time(),
            'publish_now' => 1,
            'viewperms' => 2,
            'commentperms' => 1,
            'htmltitle' => $title,
            'urlident' => make_unique_urlident($title, $used_urlidents),
            'oldid' => $xf_id,
            'open' => 1,
            'approved' => 1,
            'showpublished' => 1,
            'inlist' => 1,
            'nodeoptions' => 1,
            'channeltypeid' => $channeltypeid,
        ];

        log_msg("Trying to migrate XF node {$xf_id} => data: " . json_encode($data, JSON_UNESCAPED_UNICODE));

        try {
            $result = try_add_channel($channelLib, $data, $used_urlidents);

            if ($result && isset($result['nodeid']) && !empty($result['nodeid'])) {
                $vb_nodeid = intval($result['nodeid']);
                $node_map[$xf_id] = $vb_nodeid;
                $created_map[$xf_id] = $vb_nodeid;
                $created_nodes_info[$xf_id] = [
                    'vb_nodeid' => $vb_nodeid,
                    'xf_parent' => $xf_parent,
                    'title' => $title
                ];
                $migrated_count++;
                log_msg("Successfully migrated XF node {$xf_id} => vB node {$vb_nodeid}");
            } else {
                $errors[] = "Invalid API response for XF node {$xf_id}";
                log_msg("Invalid API response for XF node {$xf_id}: " . print_r($result, true));
            }
        } catch (Exception $e) {
            $errors[] = "Exception migrating node {$xf_id}: " . $e->getMessage();
            log_msg("Exception migrating node {$xf_id}: " . $e->getMessage());
        }
    }

    // Pass B: fix parents for created nodes where parent was not mapped at creation time
    foreach ($created_nodes_info as $xf_id => $info) {
        $vb_nodeid = $info['vb_nodeid'];
        $xf_parent = $info['xf_parent'];
        if ($xf_parent > 0 && isset($node_map[$xf_parent])) {
            $resolved_parent_vb = intval($node_map[$xf_parent]);
            // if parent mismatch (i.e., we created with parent=0), update parent
            // We attempt update via library
            try {
                // call update; some vB versions expect different method signatures
                $updateData = ['parentid' => $resolved_parent_vb];
                if (method_exists($channelLib, 'update')) {
                    $channelLib->update($vb_nodeid, $updateData);
                    log_msg("Updated parent for vB node {$vb_nodeid} -> parent {$resolved_parent_vb}");
                } else {
                    log_msg("channelLib->update() not available; cannot update parent for vB node {$vb_nodeid}");
                }
            } catch (Exception $e) {
                $errors[] = "Exception updating parent for vB node {$vb_nodeid}: " . $e->getMessage();
                log_msg("Exception updating parent for vB node {$vb_nodeid}: " . $e->getMessage());
            }
        }
    }

    // Clean caches / datastore that might affect visibility
    if (class_exists('vB_Cache')) {
        try {
            vB_Cache::instance()->cleanNow('vb_forum', 'node');
        } catch (Exception $e) {
            log_msg('vB_Cache clean exception: ' . $e->getMessage());
        }
    }
    if (class_exists('vB_Datastore')) {
        try {
            vB_Datastore::instance()->clean('channelpermissions');
        } catch (Exception $e) {
            log_msg('vB_Datastore clean exception: ' . $e->getMessage());
        }
    }

    // update offset in session
    $_SESSION['migration_offset']['forums'] = $offset + $fetched;

    // respond
    $resp = [
        'migrated' => $migrated_count,
        'fetched' => $fetched,
        'total' => $total,
        'offset' => $_SESSION['migration_offset']['forums'],
        'status' => ($offset + $fetched >= ($total ?? 0)) ? 'complete' : 'in_progress',
        'errors' => $errors,
        'map_added' => $created_map // for debug
    ];

    echo json_encode($resp);

} catch (Exception $e) {
    log_msg("Fatal exception: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    // close xf connection if available
    if (isset($xf_conn) && ($xf_conn instanceof mysqli)) {
        @$xf_conn->close();
    }
}
