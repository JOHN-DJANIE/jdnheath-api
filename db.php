<?php
$host = getenv("DB_HOST") ?: "ep-proud-thunder-a471usl4-pooler.us-east-1.aws.neon.tech";
$port = 5432;
$db   = getenv("DB_NAME") ?: "neondb";
$user = getenv("DB_USER") ?: "neondb_owner";
$pass = getenv("DB_PASS") ?: "npg_wYIl1tr8KTWg";

$connStr = "host=$host port=$port dbname=$db user=$user password=$pass sslmode=require options='endpoint=ep-proud-thunder-a471usl4'";
$pgConn  = pg_connect($connStr);

if (!$pgConn) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

class NeonPDO {
    private $conn;
    public function __construct($conn) { $this->conn = $conn; }
    public function prepare($sql) { return new NeonStatement($sql, $this->conn); }
    public function lastInsertId($seq = null) {
        $r = pg_query($this->conn, "SELECT lastval() as id");
        $row = pg_fetch_assoc($r);
        return $row["id"] ?? null;
    }
}

class NeonStatement {
    private $sql;
    private $conn;
    private $result;
    public function __construct($sql, $conn) {
        $i = 0;
        $this->sql  = preg_replace_callback('/\?/', function() use (&$i) { return '$' . ++$i; }, $sql);
        $this->conn = $conn;
    }
    public function execute($params = []) {
        $params = array_values($params);
        $this->result = empty($params)
            ? pg_query($this->conn, $this->sql)
            : pg_query_params($this->conn, $this->sql, $params);
        if ($this->result === false) throw new Exception(pg_last_error($this->conn));
        return true;
    }
    public function fetch() {
        if (!$this->result) return false;
        $row = pg_fetch_assoc($this->result);
        return $row ?: false;
    }
    public function fetchAll() {
        if (!$this->result) return [];
        $rows = [];
        while ($row = pg_fetch_assoc($this->result)) { $rows[] = $row; }
        return $rows;
    }
    public function fetchColumn() {
        $row = $this->fetch();
        return $row ? array_values($row)[0] : false;
    }
}

$pdo = new NeonPDO($pgConn);