<?php

namespace Ecodev;

use mysqli_result;

/**
 * Utility class to connect to a MySQL like database.
 *
 * USAGE:
 *
 * $db = new Database("hostname","username","password");
 * $db->connect("nomDeLaBase");
 *
 * $users = $db->select('SELECT * FROM products');
 * $user = $db->selectOne('SELECT * FROM products WHERE id = 20');
 */
class Database {

	/**
	 * @var string
	 */
	protected $hostname;

	/**
	 * @var string
	 */
	protected $username;

	/**
	 * @var string
	 */
	protected $password;

	/**
	 * @var string
	 */
	protected $port;

	/**
	 * @var string
	 */
	protected $databaseName;

	/**
	 * @var resource
	 */
	protected $bdLink;

	const ERROR_CONNECT = "Impossible de se connecter a la base de donnees";
	const ERROR_SELECT_DB = "Impossible de selectionner la base de donnees";
	const ERROR_NO_CONNECTION = "Impossible car la connexion a la BD est inactive";
	const ERROR_SQL_FAILED = "La requete a echouee";

	/**
	 * Constructor
	 *
	 * @param string $host
	 * @param string $username
	 * @param string $password
	 * @param integer $port
	 */
	public function __construct($host, $username, $password, $port = 3306) {
		$this->hostname = $host;
		$this->username = $username;
		$this->password = $password;
		$this->port = $port;
		unset($this->bdLink);
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		$this->disconnect();
	}

	/**
	 * Connect to database
	 *
	 * @param string $databaseName
	 */
	public function connect($databaseName = "") {
		$this->bdLink = @ mysqli_connect($this->hostname, $this->username, $this->password, '', $this->port);
		$this->handleError(!$this->bdLink, 'Connect - ' . self::ERROR_CONNECT . ' ' . $this->hostname);
		if ($databaseName != "") {
			$this->selectBd($databaseName);
		}

		//request with UTF-8 character set according to http://se.php.net/manual/en/function.mysqli-query.php
		$this->bdLink->query("SET NAMES 'utf8'");
	}

	/**
	 * Disconnect from database
	 *
	 * @param string $errorMessage
	 */
	public function disconnect() {
		if (isset($this->bdLink)) {
			@ mysqli_close($this->bdLink);
			unset($this->bdLink);
		}
	}

	/**
	 * Select database
	 *
	 * @param string $databaseName
	 */
	public function selectBd($databaseName) {
		$this->handleError(!isset($this->bdLink), "SelectBd - " . self::ERROR_NO_CONNECTION);
		$this->databaseName = $databaseName;
		$this->handleError(!@ mysqli_select_db($this->bdLink, $databaseName), "SelectBd - " . self::ERROR_SELECT_DB . ' ' . $this->databaseName);
	}

	/**
	 * Execute SQL query
	 *
	 * @param string $query
	 * @return mysqli_result|boolean mysqli_result if results are expected, true otherwise and null if no results
	 */
	public function query($query) {
		$this->handleError(!isset($this->bdLink), "Query - " . self::ERROR_NO_CONNECTION);
		$this->handleError(!@ mysqli_real_query($this->bdLink, $query), "Query - " . self::ERROR_SQL_FAILED . ' ' . $query);

		//si c'est une requête qui n'est pas cense ramener qqchose on stop
		if (@ mysqli_field_count($this->bdLink) == 0) {
			return true;
		}

		$result = @ mysqli_store_result($this->bdLink);
		if (@ mysqli_num_rows($result) > 0) {
			return $result;
		} else {
			return null;
		}
	}

	/**
	 * Returns wether a table exists
	 *
	 * @param string $tableName
	 * @return boolean
	 */
	public function tblExist($tableName) {
		$this->handleError(!isset($this->bdLink), "TblExist - " . self::ERROR_NO_CONNECTION);

		return $this->query("SHOW TABLES FROM `" . $this->databaseName . "` LIKE '" . $tableName . "'");
	}

	/**
	 * Escape string for inclusion in SQL
	 *
	 * @param string $str
	 * @return string escaped string
	 */
	public function escapeStr($str) {
		return mysqli_real_escape_string($this->bdLink, $str);
	}

	/**
	 * Internal callback to be used with array_walk()
	 *
	 * @param string $item
	 * @param mixed $key
	 */
	protected function addQuotes(&$item, $key) {
		if (is_null($item)) {
			$item = 'NULL';
		} else {
			$item = "'" . $this->escapeStr($item) . "'";
		}
	}

	/**
	 * Get one object from a resultset
	 *
	 * @param mysqli_result $result
	 * @param string $className
	 * @return Object of type $className
	 */
	public function getObject(mysqli_result $result = null, $className) {
		$obj = null;
		if ($result != null) {
			$row = $result->fetch_assoc();
			$constructor = 'new ' . $className . '(';
			foreach ($row as $value) {
				$constructor = $constructor . '\'' . $value . '\',';
			}
			$constructor = substr($constructor, 0, strlen($constructor) - 1) . ')'; //delete the last "," and add ")"
			eval("\$obj = $constructor;"); //evalue the constructor
		}

		return $obj;
	}

	/**
	 * Get an array of objects from a resultset
	 *
	 * @param mysqli_result $result
	 * @param string $className
	 * @return Object[] of type $className
	 */
	public function getObjects(mysqli_result $result = null, $className) {
		$arrayObjects = array();
		if ($result != null) {
			while ($row = $result->fetch_assoc()) {
				$constructor = 'new ' . $className . '(';
				foreach ($row as $value) {
					$constructor = $constructor . '\'' . $value . '\',';
				}
				$constructor = substr($constructor, 0, strlen($constructor) - 1) . ')'; //delete the last "," and add ")"
				eval("\$obj = $constructor;"); //evalue the constructor
				$arrayObjects[] = $obj;
			}
		}

		return $arrayObjects;
	}

	/**
	 * Get all fields of all records in one single non-associative array
	 * It's basically all values available concatened in a single array
	 *
	 * @param mysqli_result $result
	 * @return array empty array if no result
	 */
	public function getRowArrays(mysqli_result $result = null) {
		$arrayFromResultSet = array();
		if ($result != null) {
			while ($row = $result->fetch_row()) {
				foreach ($row as $value) {
					$arrayFromResultSet[] = stripcslashes($value);
				}
			}
		}

		return $arrayFromResultSet;
	}

	/**
	 * Get one record as one associative array
	 *
	 * @param mysqli_result $result
	 * @return array empty array if no result
	 */
	public function getAssocArray(mysqli_result $result = null) {
		$return = array();
		if ($result != null) {
			$return = $result->fetch_assoc();
		}

		return $return;
	}

	/**
	 * Get all records as an array of associative arrays
	 *
	 * @param mysqli_result $result
	 * @return array empty array if no result
	 */
	public function getAssocArrays(mysqli_result $result = null) {
		$contentArray = array();
		if ($result != null) {
			while ($row = $result->fetch_assoc()) {
				$contentArray[] = $row;
			}
		}

		return $contentArray;
	}

	/**
	 * Insert a record from an associative array and returns the ID inserted
	 *
	 * @param string $table
	 * @param array $fields
	 * @return integer|false ID inserted or false in case of error
	 */
	public function insert($table, array $fields) {
		// protect and quote every data to insert
		array_walk($fields, array($this, 'addQuotes'));

		$query = "INSERT INTO `$table` (" . implode(',', array_keys($fields)) . ") VALUES (" . implode(',', array_values($fields)) . ")";
		$result = $this->query($query);

		// retourne l'id de la nouvelle entrée ou false si une erreur s'est produite
		if ($result) {
			return $this->bdLink->insert_id;
		} else {
			return false;
		}
	}

	/**
	 * Delete records from an associative array
	 *
	 * @param string $table
	 * @param array $clauses
	 */
	public function delete($table, array $clauses = array()) {
		// protect and quote every data to insert
		array_walk($clauses, array($this, 'addQuotes'));

		$query = "DELETE FROM `$table`";
		if (!empty($clauses)) {
			foreach ($clauses as $key => $value) {
				$clauses2Sql[] = "`$key`=$value";
			}
			$query .= " WHERE " . implode(' AND ', array_values($clauses2Sql)) . "";
		}

		return $this->query($query);
	}

	/**
	 * @return array
	 */
	public function select($query) {
		$resultSet = $this->query($query);
		return $this->getAssocArrays($resultSet);
	}

	/**
	 * @return array
	 */
	public function selectOne($query) {
		$resultSet = $this->query($query);
		return $this->getAssocArray($resultSet);
	}

	/**
	 * Update records from table
	 *
	 * @param string $table
	 * @param array $fields
	 * @param array $conditions
	 * @return boolean
	 */
	public function update($table, array $fields, array $conditions = array()) {
		if (!is_array($fields) || count($fields) == 0) {
			return false;
		} // no field to modify

		array_walk($fields, array($this, 'addQuotes'));
		array_walk($conditions, array($this, 'addQuotes'));

		$query = "UPDATE `$table` SET ";
		$params = array();
		foreach ($fields as $key => $value) {
			$params[] = "$key=$value";
		}
		$query .= implode(',', $params);

		foreach ($conditions as $key => $value) {
			$clauses[] = "$key=$value";
		}

		if (!empty($conditions)) {
			$query .= ' WHERE ' . implode(' AND ', $clauses);
		}

		return $this->query($query);
	}

	/**
	 * If $isError evaluate to true, will die and print $errorMessage
	 *
	 * @param mixed $isError
	 * @param string $errorMessage
	 */
	protected function handleError($isError, $errorMessage) {
		if (!$isError) {
			return;
		}

		// Gather error information
		if ($this->bdLink) {
			$phpError = mysqli_error($this->bdLink);
			$phpErrorNum = mysqli_errno($this->bdLink);
		} else {
			$phpError = mysqli_connect_error();
			$phpErrorNum = mysqli_connect_errno();
		}
		if ($phpErrorNum != 0) {
			$msgPhpError = 'Error n°' . $phpErrorNum . ': ' . $phpError;
		} else {
			$msgPhpError = '';
		}

		die($errorMessage . '<br/>' . PHP_EOL . $msgPhpError . PHP_EOL);
	}

	/**
	 * @return string
	 */
	public function getDatabaseName() {
		return $this->databaseName;
	}

	/**
	 * @return string
	 */
	public function getPort() {
		return $this->port;
	}

	/**
	 * @return string
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * @return string
	 */
	public function getUsername() {
		return $this->username;
	}

	/**
	 * @return string
	 */
	public function getHostname() {
		return $this->hostname;
	}
}
