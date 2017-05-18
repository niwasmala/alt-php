<?php defined("ALT_PATH") or die("No direct script access.");

/**
 * Mysql Alt_Database connection.
 *
 * @package    Alt/Alt_Database
 * @category   Drivers
 * @author     Alt Team
 * @copyright  (c) 2008-2009 Alt Team
 * @license    http://Altphp.com/license
 */
class Alt_Database_Mysql extends Alt_Database {

	// Alt_Database in use by each connection
	protected static $_current_databases = array();

	// Use SET NAMES to set the character set
	protected static $_set_names;

	// Identifier for this connection within the PHP driver
	protected $_connection_id;

	// Mysql uses a backtick for identifiers
	protected $_identifier = "`";

	public function connect()
	{
		if ($this->_connection)
			return;

		if (Alt_Database_Mysql::$_set_names === NULL)
		{
			// Determine if we can use mysql_set_charset(), which is only
			// available on PHP 5.2.3+ when compiled against Mysql 5.0+
			Alt_Database_Mysql::$_set_names = ! function_exists("mysqli_set_charset");
		}

		// Extract the connection parameters, adding required variabels
		extract(array_union($this->_config["connection"], array(
			"database"   => "",
			"hostname"   => "",
			"username"   => "",
			"password"   => "",
            "port"       => 3306,
			"persistent" => FALSE,
		)));

		// Prevent this information from showing up in traces
		unset($this->_config["connection"]["username"], $this->_config["connection"]["password"]);

        try
		{
			if ($persistent)
			{
				// Create a persistent connection
				$this->_connection = mysqli_connect("p:".$hostname, $username, $password, null, $port);
			}
			else
			{
				// Create a connection and force it to be a new link
				$this->_connection = mysqli_connect($hostname, $username, $password, null, $port);
			}
            if(!$this->_connection) throw new Alt_Exception(mysqli_connect_error());
		}
		catch (Exception $e)
		{
			// No connection exists
			$this->_connection = NULL;

			throw new Alt_Exception($e->getMessage());
		}

		// \xFF is a better delimiter, but the PHP driver uses underscore
		$this->_connection_id = sha1($hostname."_".$username."_".$password);

		$this->_select_db($database);

		if ( ! empty($this->_config["charset"]))
		{
			// Set the character set
			$this->set_charset($this->_config["charset"]);
		}

		if ( ! empty($this->_config["connection"]["variables"]))
		{
			// Set session variables
			$variables = array();

			foreach ($this->_config["connection"]["variables"] as $var => $val)
			{
				$variables[] = "SESSION ".$var." = ".$this->quote($val);
			}

			mysqli_query($this->_connection, "SET ".implode(", ", $variables));
		}
	}

	/**
	 * Select the Alt_Database
	 *
	 * @param   string  $database Alt_Database
	 * @return  void
	 */
	protected function _select_db($database)
	{
		if ( ! mysqli_select_db($this->_connection, $database))
		{
			// Unable to select Alt_Database
			throw new Alt_Exception(mysqli_error($this->_connection));
		}

		Alt_Database_Mysql::$_current_databases[$this->_connection_id] = $database;
	}

	public function disconnect()
	{
		try
		{
			// Alt_Database is assumed disconnected
			$status = TRUE;

			if (is_object($this->_connection))
			{
				if ($status = mysqli_close($this->_connection))
				{
					// Clear the connection
					$this->_connection = NULL;

					// Clear the instance
					parent::disconnect();
				}
			}
		}
		catch (Exception $e)
		{
			// Alt_Database is probably not disconnected
			$status = ! is_object($this->_connection);
		}

		return $status;
	}

	public function set_charset($charset)
	{
		// Make sure the Alt_Database is connected
		$this->_connection or $this->connect();

		if (Alt_Database_Mysql::$_set_names === TRUE)
		{
			// PHP is compiled against Mysql 4.x
			$status = (bool) mysqli_query($this->_connection, "SET NAMES ".$this->quote($charset));
		}
		else
		{
			// PHP is compiled against Mysql 5.x
			$status = mysqli_set_charset($this->_connection, $charset);
		}

		if ($status === FALSE)
		{
			throw new Alt_Exception(mysqli_error($this->_connection));
		}
	}

	public function query($type, $sql, $as_object = FALSE, array $params = NULL)
	{
		// Make sure the Alt_Database is connected
		$this->_connection or $this->connect();

		if ( ! empty($this->_config["connection"]["persistent"]) AND $this->_config["connection"]["Alt_Database"] !== Alt_Database_Mysql::$_current_databases[$this->_connection_id])
		{
			// Select Alt_Database on persistent connections
			$this->_select_db($this->_config["connection"]["Alt_Database"]);
		}

		// Execute the query
		if (($result = mysqli_query($this->_connection, $sql)) === FALSE)
		{

			throw new Alt_Exception(mysqli_error($this->_connection)."[".$sql."]");
		}

		// Set the last query
		$this->last_query = $sql;

		if ($type === Alt_Database::SELECT)
		{
			// Return an iterator of results
			return new Alt_Database_Mysql_Result($result, $sql, $as_object, $params);
		}
		elseif ($type === Alt_Database::INSERT)
		{
			// Return a list of insert id and rows created
			return array(
				mysqli_insert_id($this->_connection),
				mysqli_affected_rows($this->_connection),
			);
		}
		else
		{
			// Return the number of rows affected
			return mysqli_affected_rows($this->_connection);
		}
	}

	public function datatype($type)
	{
		static $types = array
		(
			"blob"                      => array("type" => "string", "binary" => TRUE, "character_maximum_length" => "65535"),
			"bool"                      => array("type" => "bool"),
			"bigint unsigned"           => array("type" => "int", "min" => "0", "max" => "18446744073709551615"),
			"datetime"                  => array("type" => "string"),
			"decimal unsigned"          => array("type" => "float", "exact" => TRUE, "min" => "0"),
			"double"                    => array("type" => "float"),
			"double precision unsigned" => array("type" => "float", "min" => "0"),
			"double unsigned"           => array("type" => "float", "min" => "0"),
			"enum"                      => array("type" => "string"),
			"fixed"                     => array("type" => "float", "exact" => TRUE),
			"fixed unsigned"            => array("type" => "float", "exact" => TRUE, "min" => "0"),
			"float unsigned"            => array("type" => "float", "min" => "0"),
			"geometry"                  => array("type" => "string", "binary" => TRUE),
			"int unsigned"              => array("type" => "int", "min" => "0", "max" => "4294967295"),
			"integer unsigned"          => array("type" => "int", "min" => "0", "max" => "4294967295"),
			"longblob"                  => array("type" => "string", "binary" => TRUE, "character_maximum_length" => "4294967295"),
			"longtext"                  => array("type" => "string", "character_maximum_length" => "4294967295"),
			"mediumblob"                => array("type" => "string", "binary" => TRUE, "character_maximum_length" => "16777215"),
			"mediumint"                 => array("type" => "int", "min" => "-8388608", "max" => "8388607"),
			"mediumint unsigned"        => array("type" => "int", "min" => "0", "max" => "16777215"),
			"mediumtext"                => array("type" => "string", "character_maximum_length" => "16777215"),
			"national varchar"          => array("type" => "string"),
			"numeric unsigned"          => array("type" => "float", "exact" => TRUE, "min" => "0"),
			"nvarchar"                  => array("type" => "string"),
			"point"                     => array("type" => "string", "binary" => TRUE),
			"real unsigned"             => array("type" => "float", "min" => "0"),
			"set"                       => array("type" => "string"),
			"smallint unsigned"         => array("type" => "int", "min" => "0", "max" => "65535"),
			"text"                      => array("type" => "string", "character_maximum_length" => "65535"),
			"tinyblob"                  => array("type" => "string", "binary" => TRUE, "character_maximum_length" => "255"),
			"tinyint"                   => array("type" => "int", "min" => "-128", "max" => "127"),
			"tinyint unsigned"          => array("type" => "int", "min" => "0", "max" => "255"),
			"tinytext"                  => array("type" => "string", "character_maximum_length" => "255"),
			"year"                      => array("type" => "string"),
		);

		$type = str_replace(" zerofill", "", $type);

		if (isset($types[$type]))
			return $types[$type];

		return parent::datatype($type);
	}

	/**
	 * Start a SQL transaction
	 *
	 * @link http://dev.mysql.com/doc/refman/5.0/en/set-transaction.html
	 *
	 * @param string $mode  Isolation level
	 * @return boolean
	 */
	public function begin($mode = NULL)
	{
		// Make sure the Alt_Database is connected
		$this->_connection or $this->connect();

		if ($mode AND ! mysqli_query($this->_connection, "SET TRANSACTION ISOLATION LEVEL $mode"))
		{
			throw new Alt_Exception(mysqli_error($this->_connection));
		}

		return (bool) mysqli_query($this->_connection, "START TRANSACTION");
	}

	/**
	 * Commit a SQL transaction
	 *
	 * @return boolean
	 */
	public function commit()
	{
		// Make sure the Alt_Database is connected
		$this->_connection or $this->connect();

		return (bool) mysqli_query($this->_connection, "COMMIT");
	}

	/**
	 * Rollback a SQL transaction
	 *
	 * @return boolean
	 */
	public function rollback()
	{
		// Make sure the Alt_Database is connected
		$this->_connection or $this->connect();

		return (bool) mysqli_query($this->_connection, "ROLLBACK");
	}

	public function list_tables($like = NULL)
	{
		if (is_string($like))
		{
			// Search for table names
			$result = $this->query(Alt_Database::SELECT, "SHOW TABLES LIKE ".$this->quote($like), FALSE);
		}
		else
		{
			// Find all table names
			$result = $this->query(Alt_Database::SELECT, "SHOW TABLES", FALSE);
		}

		$tables = array();
		foreach ($result as $row)
		{
			$tables[] = reset($row);
		}

		return $tables;
	}

	public function list_columns($table, $like = NULL, $add_prefix = TRUE)
	{
		// Quote the table name
		$table = ($add_prefix === TRUE) ? $this->quote_table($table) : $table;

		if (is_string($like))
		{
			// Search for column names
			$result = $this->query(Alt_Database::SELECT, "SHOW FULL COLUMNS FROM ".$table." LIKE ".$this->quote($like), FALSE);
		}
		else
		{
			// Find all column names
			$result = $this->query(Alt_Database::SELECT, "SHOW FULL COLUMNS FROM ".$table, FALSE);
		}

		$count = 0;
		$columns = array();
		foreach ($result as $row)
		{
			list($type, $length) = $this->_parse_type($row["Type"]);

			$column = $this->datatype($type);

			$column["column_name"]      = $row["Field"];
			$column["column_default"]   = $row["Default"];
			$column["data_type"]        = $type;
			$column["is_nullable"]      = ($row["Null"] == "YES");
			$column["ordinal_position"] = ++$count;

			switch ($column["type"])
			{
				case "float":
					if (isset($length))
					{
						list($column["numeric_precision"], $column["numeric_scale"]) = explode(",", $length);
					}
				break;
				case "int":
					if (isset($length))
					{
						// Mysql attribute
						$column["display"] = $length;
					}
				break;
				case "string":
					switch ($column["data_type"])
					{
						case "binary":
						case "varbinary":
							$column["character_maximum_length"] = $length;
						break;
						case "char":
						case "varchar":
							$column["character_maximum_length"] = $length;
						case "text":
						case "tinytext":
						case "mediumtext":
						case "longtext":
							$column["collation_name"] = $row["Collation"];
						break;
						case "enum":
						case "set":
							$column["collation_name"] = $row["Collation"];
							$column["options"] = explode("\",\"", substr($length, 1, -1));
						break;
					}
				break;
			}

			// Mysql attributes
			$column["comment"]      = $row["Comment"];
			$column["extra"]        = $row["Extra"];
			$column["key"]          = $row["Key"];
			$column["privileges"]   = $row["Privileges"];

			$columns[$row["Field"]] = $column;
		}

		return $columns;
	}

	public function escape($value)
	{
		// Make sure the Alt_Database is connected
		$this->_connection or $this->connect();

		if (($value = mysqli_real_escape_string($this->_connection, (string) $value)) === FALSE)
		{
			throw new Alt_Exception(mysqli_error($this->_connection));
		}

		// SQL standard is to use single-quotes for all values
		return "'$value'";
	}

} // End Alt_Database_Mysql
