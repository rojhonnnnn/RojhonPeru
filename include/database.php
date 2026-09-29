<?php
// ROJHON SOLUTIONS - PDO database layer
require_once(LIB_PATH.DS."config.php");

class Database {
	public $sql_string = '';
	public $error_no   = 0;
	public $error_msg  = '';
	public $query      = null;
	public $conn       = null;
	public $last_query = '';

	function __construct() {
		$this->open_connection();
	}

	public function open_connection() {
		try {
			/* charset=utf8mb4 is what makes emojis survive the round trip.
			   Without it PDO talks latin1 and every emoji is saved as "????". */
			$this->conn = new PDO(
				"mysql:host=".DB_SERVER.";dbname=".DB_NAME.";charset=utf8mb4",
				DB_USER,
				DB_PASS,
				array(
					PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
					PDO::ATTR_EMULATE_PREPARES   => true
				)
			);
			/* Same clock as PHP (Asia/Manila) so NOW() and date() agree. */
			$offset = (new DateTime('now'))->format('P');
			$this->conn->exec("SET time_zone = '".$offset."'");
		} catch (PDOException $e) {
			if (PHP_SAPI !== 'cli') { http_response_code(500); }
			$msg = $e->getMessage();
			$hint = (stripos($msg, 'Unknown database') !== false)
				? 'The database <b>'.DB_NAME.'</b> does not exist yet. Import <b>database/'.DB_NAME.'.sql</b> in phpMyAdmin first.'
				: 'Start MySQL in the XAMPP Control Panel, then reload this page.';
			if (function_exists('is_ajax_request') && is_ajax_request()) {
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode(array('error' => 'db_connection', 'message' => strip_tags($hint)));
			} else {
				echo '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #f5c2c7;background:#f8d7da;color:#842029;border-radius:8px">'
					.'<h3 style="margin-top:0">Database connection problem</h3><p>'.$hint.'</p>'
					.'<p style="font-size:12px;color:#6c757d">'.htmlspecialchars($msg).'</p></div>';
			}
			exit;
		}
	}

	/* ---------- Prepared-statement helpers (use these in new code) ---------- */

	/* Runs a query with bound parameters and returns the PDOStatement.
	   Integers are bound as integers so LIMIT ? works. */
	public function run($sql, $params = array()) {
		$this->sql_string = $sql;
		$this->last_query = $sql;
		$stmt = $this->conn->prepare($sql);
		$i = 1;
		$isList = array_keys($params) === range(0, count($params) - 1);
		foreach ($params as $key => $value) {
			$type = is_int($value) ? PDO::PARAM_INT : (is_null($value) ? PDO::PARAM_NULL : PDO::PARAM_STR);
			if ($isList) { $stmt->bindValue($i++, $value, $type); }
			else { $stmt->bindValue(is_string($key) && $key[0] !== ':' ? ':'.$key : $key, $value, $type); }
		}
		$stmt->execute();
		$this->query = $stmt;
		return $stmt;
	}
	public function fetchAll($sql, $params = array()) {
		return $this->run($sql, $params)->fetchAll(PDO::FETCH_OBJ);
	}
	public function fetchOne($sql, $params = array()) {
		$row = $this->run($sql, $params)->fetch(PDO::FETCH_OBJ);
		return $row ? $row : null;
	}
	public function fetchValue($sql, $params = array()) {
		$v = $this->run($sql, $params)->fetchColumn();
		return ($v === false) ? null : $v;
	}
	public function execute($sql, $params = array()) {
		return $this->run($sql, $params)->rowCount();
	}

	/* ---------- Legacy API kept for the existing modules ---------- */

	/* Returns true/false instead of throwing, so a foreign key block
	   (for example deleting a course that still has students) shows a
	   friendly message instead of a fatal error page. */
	function InsertThis($sql = '') {
		$this->sql_string = $sql;
		$this->last_query = $sql;
		try {
			$this->query = $this->conn->prepare($sql);
			return $this->query->execute();
		} catch (PDOException $e) {
			$this->error_no  = $e->getCode();
			$this->error_msg = $e->getMessage();
			error_log('[ROJHON] '.$e->getMessage().' | SQL: '.$sql);
			return false;
		}
	}

	function setQuery($sql = '') {
		$this->sql_string = $sql;
		$this->last_query = $sql;
		try {
			$this->query = $this->conn->prepare($sql);
			$this->query->execute();
		} catch (PDOException $e) {
			error_log('[ROJHON] '.$e->getMessage().' | SQL: '.$sql);
			if (is_ajax_request()) {
				http_response_code(500);
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode(array('error' => 'query_failed', 'message' => $e->getMessage()));
			} else {
				echo '<div class="alert alert-danger m-3">Database error: '.htmlspecialchars($e->getMessage()).'</div>';
			}
			exit;
		}
	}

	function loadResultList() {
		return $this->query ? $this->query->fetchAll(PDO::FETCH_OBJ) : array();
	}
	function loadSingleResultAssoc() {
		return $this->query ? $this->query->fetch(PDO::FETCH_ASSOC) : false;
	}
	function loadSingleResult() {
		return $this->query ? $this->query->fetch(PDO::FETCH_OBJ) : false;
	}
	public function num_rows() {
		return $this->query ? $this->query->rowCount() : 0;
	}

	function getFieldsOnOneTable($tbl_name) {
		$this->setQuery("DESC `".str_replace('`', '', $tbl_name)."`");
		$f = array();
		foreach ($this->loadResultList() as $row) { $f[] = $row->Field; }
		return $f;
	}

	/* Full column description (Field, Type, Null, Key, Default, Extra),
	   used by the generic table module. */
	function describeTable($tbl_name) {
		$this->setQuery("DESC `".str_replace('`', '', $tbl_name)."`");
		return $this->loadResultList();
	}

	function getPrimaryKey($tbl_name) {
		$cols = $this->describeTable($tbl_name);
		foreach ($cols as $c) {
			if ($c->Key === 'PRI') { return $c->Field; }
		}
		return isset($cols[0]) ? $cols[0]->Field : null;
	}

	function tableExists($tbl_name) {
		$stmt = $this->run("SHOW TABLES LIKE ?", array(str_replace(array('`', "'"), '', $tbl_name)));
		return $stmt->rowCount() > 0;
	}

	public function insert_id() {
		return $this->conn->lastInsertId();
	}

	public function affected_rows() {
		return $this->query ? $this->query->rowCount() : 0;
	}

	/* The old version called mysql_real_escape_string (removed in PHP 7)
	   and silently fell back to addslashes. PDO::quote escapes correctly
	   for the utf8mb4 connection; the outer quotes are stripped because
	   the legacy queries add their own. */
	public function escape_value($value) {
		if ($value === null) { return ''; }
		$quoted = $this->conn->quote((string)$value);
		return substr($quoted, 1, -1);
	}

	public function close_connection() {
		$this->conn = null;
	}
}

$mydb = new Database();
?>
