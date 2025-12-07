<?php

class Subject {

    private $conn;
    private $table = "subjects";

    public $id;
    public $subject_code;
    public $subject_name;
    public $category;
    public $class_level;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    // CREATE SUBJECT
    public function create() {
        $query = "INSERT INTO {$this->table}
                 (subject_code, subject_name, category, class_level, status)
                 VALUES (:code, :name, :category, :class, :status)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":code", $this->subject_code);
        $stmt->bindParam(":name", $this->subject_name);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":class", $this->class_level);
        $stmt->bindParam(":status", $this->status);

        return $stmt->execute();
    }

    // READ ALL SUBJECTS
    public function read() {
        $query = "SELECT * FROM {$this->table} ORDER BY subject_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // UPDATE SUBJECT
    public function update() {
        $query = "UPDATE {$this->table}
                  SET subject_code = :code,
                      subject_name = :name,
                      category = :category,
                      class_level = :class,
                      status = :status
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":code", $this->subject_code);
        $stmt->bindParam(":name", $this->subject_name);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":class", $this->class_level);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    // DELETE SUBJECT
    public function delete() {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        return $stmt->execute();
    }
}
