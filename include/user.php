<?php
require_once(LIB_PATH.DS.'database.php');
#[\AllowDynamicProperties]
class User{
	
	protected static $tbl_name = "tblusers";
	function db_fields(){
		global $mydb;
		return $mydb->getFieldsOnOneTable(self::$tbl_name);
	}
	function listOfUsers(){
		global $mydb;
		$mydb->setQuery("Select * from ".self::$tbl_name);
		$cur = $mydb->loadResultList();
		return $cur;
	
	}
	function single_user($id=''){
			global $mydb;
			$mydb->setQuery("SELECT * FROM ".self::$tbl_name." Where UID= ".intval($id)." LIMIT 1");
			$cur = $mydb->loadSingleResult();
			return $cur;
	}
	function find_all_user($name=""){
		global $mydb;
		$stmt = $mydb->run("SELECT UID FROM ".self::$tbl_name." WHERE `USERNAME` = ?", array((string)$name));
		return $stmt->rowCount();
	}
	function find_all_user_notthis($name="", $uid=0){
		global $mydb;
		$stmt = $mydb->run("SELECT UID FROM ".self::$tbl_name." WHERE `USERNAME` = ? AND UID <> ?", array((string)$name, (int)$uid));
		return $stmt->rowCount();
	}

	/* Accepts the new password_hash() format and the old unsalted SHA1
	   hashes from the original database. A SHA1 account is upgraded to
	   bcrypt the first time it logs in, so nobody has to reset anything. */
	static function verifyPassword($plain, $stored){
		$stored = (string)$stored;
		if ($stored === '') { return false; }
		if (strlen($stored) === 40 && ctype_xdigit($stored)) {
			return hash_equals(strtolower($stored), sha1($plain));
		}
		return password_verify($plain, $stored);
	}

	static function hashPassword($plain){
		return password_hash($plain, PASSWORD_DEFAULT);
	}

	/* Prepared statement: the old version concatenated the username into
	   the SQL, so typing  ' OR '1'='1  as the username logged you in. */
	static function AuthenticateUser($username="", $password=""){
		global $mydb;
		$found_user = $mydb->fetchOne("SELECT * FROM `tblusers` WHERE `USERNAME` = ? LIMIT 1", array((string)$username));
		if (!$found_user || (int)$found_user->STATUSACTIVE !== 1) { return false; }
		if (!self::verifyPassword($password, $found_user->PASSWORD)) { return false; }

		if (strlen((string)$found_user->PASSWORD) === 40 || password_needs_rehash($found_user->PASSWORD, PASSWORD_DEFAULT)) {
			$mydb->execute("UPDATE `tblusers` SET `PASSWORD` = ? WHERE `UID` = ?", array(self::hashPassword($password), (int)$found_user->UID));
		}

		session_regenerate_id(true);
		$_SESSION['UID']         = (int)$found_user->UID;
		$_SESSION['DISPLAYNAME'] = $found_user->DISPLAYNAME;
		$_SESSION['USERNAME']    = $found_user->USERNAME;
		$_SESSION['TYPE']        = $found_user->TYPE;
		$_SESSION['PHOTO']       = isset($found_user->PHOTO) ? $found_user->PHOTO : '';
		return true;
	}

	/*---Instantiation of Object dynamically---*/
	static function instantiate($record) {
		$object = new self;

		foreach($record as $attribute=>$value){
		  if($object->has_attribute($attribute)) {
		    $object->$attribute = $value;
		  }
		} 
		return $object;
	}
	
	
	/*--Cleaning the raw data before submitting to Database--*/
	private function has_attribute($attribute) {
	  // We don't care about the value, we just want to know if the key exists
	  // Will return true or false
	  return array_key_exists($attribute, $this->attributes());
	}

	protected function attributes() { 
		// return an array of attribute names and their values
	  global $mydb;
	  $attributes = array();
	  foreach($this->db_fields() as $field) {
	    if(property_exists($this, $field)) {
			$attributes[$field] = $this->$field;
		}
	  }
	  return $attributes;
	}
	
	protected function sanitized_attributes() {
	  global $mydb;
	  $clean_attributes = array();
	  // sanitize the values before submitting
	  // Note: does not alter the actual value of each attribute
	  foreach($this->attributes() as $key => $value){
	    $clean_attributes[$key] = $mydb->escape_value($value);
	  }
	  return $clean_attributes;
	}
	
	/*--Create,Update and Delete methods--*/
	public function save() {
	  // A new record won't have an id yet.
	  return isset($this->id) ? $this->update() : $this->create();
	}
	
	public function create() {
		global $mydb;
		// Don't forget your SQL syntax and good habits:
		// - INSERT INTO table (key, key) VALUES ('value', 'value')
		// - single-quotes around all values
		// - escape all values to prevent SQL injection
		$attributes = $this->sanitized_attributes();
		$sql = "INSERT INTO ".self::$tbl_name." (`";
		$sql .= join("`, `", array_keys($attributes));
		$sql .= "`) VALUES ('";
		$sql .= join("', '", array_values($attributes));
		$sql .= "')";
		return	$mydb->InsertThis($sql);
	}

	public function update($id=0) {
	  global $mydb;
		$attributes = $this->sanitized_attributes();
		$attribute_pairs = array();
		foreach($attributes as $key => $value) {
		  $attribute_pairs[] = "{$key}='{$value}'";
		}
		$sql = "UPDATE ".self::$tbl_name." SET ";
		$sql .= join(", ", $attribute_pairs);
		$sql .= " WHERE UID =". intval($id);
		return  $mydb->InsertThis($sql);
	}

	public function delete($id=0) {
		global $mydb;
		  $sql = "DELETE FROM ".self::$tbl_name;
		  $sql .= " WHERE UID =". intval($id);
		  $sql .= " LIMIT 1 ";
		return  $mydb->InsertThis($sql);
		  
	}
		
}
?>