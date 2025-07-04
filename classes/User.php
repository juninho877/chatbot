<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $name;
    public $email;
    public $password;
    public $phone;
    public $plan_id;
    public $role;
    public $whatsapp_instance;
    public $whatsapp_connected;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET name=:name, email=:email, password=:password, phone=:phone, plan_id=:plan_id, role=:role";
        
        $stmt = $this->conn->prepare($query);
        
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        
        // Definir role padrão como 'user' se não especificado
        if (empty($this->role)) {
            $this->role = 'user';
        }
        
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":plan_id", $this->plan_id);
        $stmt->bindParam(":role", $this->role);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function login($email, $password) {
        // Log de depuração
        error_log("=== LOGIN DEBUG ===");
        error_log("Attempting login for email: " . $email);
        error_log("Provided password: " . $password);
        
        $query = "SELECT id, name, email, password, plan_id, role, whatsapp_instance, whatsapp_connected 
                  FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        error_log("Query executed. Row count: " . $stmt->rowCount());
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("User found in database");
            error_log("Stored password hash: " . $row['password']);
            error_log("Password hash length: " . strlen($row['password']));
            
            // Verificar se a senha está correta
            $password_check = password_verify($password, $row['password']);
            error_log("Password verification result: " . ($password_check ? 'SUCCESS' : 'FAILED'));
            
            if($password_check) {
                $this->id = $row['id'];
                $this->name = $row['name'];
                $this->email = $row['email'];
                $this->plan_id = $row['plan_id'];
                $this->role = $row['role'] ?? 'user'; // Fallback para compatibilidade
                $this->whatsapp_instance = $row['whatsapp_instance'];
                $this->whatsapp_connected = $row['whatsapp_connected'];
                error_log("Login successful for user ID: " . $this->id . ", Role: " . $this->role);
                return true;
            } else {
                error_log("Password verification failed");
                // Teste adicional: verificar se a senha é exatamente "102030"
                if($password === '102030' && $email === 'admin@clientmanager.com') {
                    error_log("Testing direct password comparison for admin user");
                    error_log("Direct comparison result: " . ($row['password'] === '102030' ? 'MATCH' : 'NO MATCH'));
                }
            }
        } else {
            error_log("User not found for email: " . $email);
        }
        
        error_log("=== END LOGIN DEBUG ===");
        return false;
    }

    public function readAll() {
        $query = "SELECT id, name, email, plan_id, role, whatsapp_instance, whatsapp_connected 
                  FROM " . $this->table_name . " 
                  WHERE whatsapp_instance IS NOT NULL AND whatsapp_connected = 1
                  ORDER BY id ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function updateWhatsAppInstance($instance_name) {
        $query = "UPDATE " . $this->table_name . " 
                  SET whatsapp_instance=:instance, whatsapp_connected=1 
                  WHERE id=:id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":instance", $instance_name);
        $stmt->bindParam(":id", $this->id);
        
        return $stmt->execute();
    }

    public function isAdmin() {
        return $this->role === 'admin';
    }

    public function updateRole($user_id, $new_role) {
        $query = "UPDATE " . $this->table_name . " SET role = :role WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':role', $new_role);
        $stmt->bindParam(':id', $user_id);
        return $stmt->execute();
    }
}
?>