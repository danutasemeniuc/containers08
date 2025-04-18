<?php
class Database {
    private $db;

    public function __construct($path) {
        $this->db = new PDO("sqlite:$path");
    }

    public function Execute($sql) {
        return $this->db->exec($sql);
    }

    public function Fetch($sql) {
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function Create($table, $data) {
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_map(fn($k) => ":$k", array_keys($data)));
        $stmt = $this->db->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    public function Read($table, $id) {
        $stmt = $this->db->prepare("SELECT * FROM $table WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function Update($table, $id, $data) {
        $set = implode(", ", array_map(fn($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;
        $stmt = $this->db->prepare("UPDATE $table SET $set WHERE id = :id");
        return $stmt->execute($data);
    }

    public function Delete($table, $id) {
        $stmt = $this->db->prepare("DELETE FROM $table WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function Count($table) {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM $table");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
}
