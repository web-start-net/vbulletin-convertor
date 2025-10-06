<?php
session_start();

class ConfigSession {
    protected $file;

    public function __construct($file = 'migration_session.json') {
        $this->file = $file;
        if(!file_exists($this->file)) file_put_contents($this->file, json_encode([]));
    }

    public function set($key, $value) {
        $data = json_decode(file_get_contents($this->file), true);
        $data[$key] = $value;
        file_put_contents($this->file, json_encode($data));
    }

    public function get($key, $default=null) {
        $data = json_decode(file_get_contents($this->file), true);
        return $data[$key] ?? $default;
    }

    public function clear() {
        file_put_contents($this->file, json_encode([]));
    }
}
