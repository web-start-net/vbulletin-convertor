<?php
class ImportSession {
    protected $db;

    public function __construct($db) {
        $this->db = $db; // PDO یا mysqli connection
    }

    public function getSession($module) {
        $stmt = $this->db->prepare("SELECT * FROM importsession WHERE module = ?");
        $stmt->execute([$module]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            // new record if exist
            $stmt = $this->db->prepare("INSERT INTO importsession (module) VALUES (?)");
            $stmt->execute([$module]);
            $session = [
                'module' => $module,
                'offset' => 0,
                'totaldone' => 0,
                'perpage' => 500
            ];
        }
        return $session;
    }

    public function updateSession($module, $offset, $totaldone) {
        $stmt = $this->db->prepare("UPDATE importsession SET offset = ?, totaldone = ? WHERE module = ?");
        $stmt->execute([$offset, $totaldone, $module]);
    }

    public function logItem($module, $oldid, $newid) {
        $stmt = $this->db->prepare("INSERT IGNORE INTO importlog (module, oldid, newid) VALUES (?, ?, ?)");
        $stmt->execute([$module, $oldid, $newid]);
    }

    public function hasLogged($module, $oldid) {
        $stmt = $this->db->prepare("SELECT 1 FROM importlog WHERE module = ? AND oldid = ?");
        $stmt->execute([$module, $oldid]);
        return (bool) $stmt->fetchColumn();
    }
}
