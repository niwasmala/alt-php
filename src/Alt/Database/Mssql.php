<?php defined("ALT_PATH") or die("No direct script access.");
/**
* MsSQL Alt_Database connection.
*
* @author     Alt Team, xrado
*/
class Alt_Database_MsSQL extends Alt_Database_PDO {
	
	public function query($type, $sql, $as_object = FALSE, array $params = NULL)
	{
		// Make sure the Alt_Database is connected
		$this->_connection or $this->connect();
		
		// Mssql specific
		if(preg_match("/OFFSET ([0-9]+)/i",$sql,$matches))
		{
			list($replace,$offset) = $matches;
			$sql = str_replace($replace,"",$sql);
		}

		if(preg_match("/LIMIT ([0-9]+)/i",$sql,$matches))
		{
			list($replace,$limit) = $matches;
			$sql = str_replace($replace,"",$sql);
		}

		if(isset($limit) || isset($offset))
		{
			if (!isset($offset)) 
			{
				$sql = preg_replace("/^(SELECT|DELETE|UPDATE)\s/i", "$1 TOP " . $limit . " ", $sql);
			} 
			else 
			{
				$ob_count = (int)preg_match_all("/ORDER BY/i",$sql,$ob_matches,PREG_OFFSET_CAPTURE);

				if($ob_count < 1) 
				{
					$over = "ORDER BY (SELECT 0)";
				} 
				else 
				{
					$ob_last = array_pop($ob_matches[0]);
					//$orderby = strrchr($sql, $ob_last[0]);
                    $orderby = substr($sql,strpos($sql,$ob_last[0]));
					$over = preg_replace("/[^,\s]*\.([^,\s]*)/i", "inner_tbl.$1", $orderby);
					
					// Remove ORDER BY clause from $sql
					$sql = substr($sql, 0, $ob_last[1]);
				}
				
				// Add ORDER BY clause as an argument for ROW_NUMBER()
				$sql = "SELECT ROW_NUMBER() OVER ($over) AS DB_ROWNUM, * FROM ($sql) AS inner_tbl";
			  
				$start = $offset + 1;
				$end = $offset + $limit;

				$sql = "WITH outer_tbl AS ($sql) SELECT * FROM outer_tbl WHERE DB_ROWNUM BETWEEN $start AND $end";
			}
		}

		try
		{
			$result = $this->_connection->query($sql);
		}
		catch (Exception $e)
		{

			$errArr = $this->_connection->errorInfo();
			$resultTextError = $this->_connection->query( "select * from sys.messages where  language_id=1033 and message_id=".arr::get($errArr, 1, 0) )->fetchAll();
			
			// Convert the exception in a Alt_Database exception
			throw new Alt_Exception("[".$e->getCode()."] ".$e->getMessage());
		}

		// Set the last query
		$this->last_query = $sql;

		if ($type === Alt_Database::SELECT)
		{
			// Convert the result into an array, as PDOStatement::rowCount is not reliable
			if ($as_object === FALSE)
			{
				$result->setFetchMode(PDO::FETCH_ASSOC);
			}
			elseif (is_string($as_object))
			{
				$result->setFetchMode(PDO::FETCH_CLASS, $as_object, $params);
			}
			else
			{
				$result->setFetchMode(PDO::FETCH_CLASS, "stdClass");
			}

			$result = $result->fetchAll();

			// Return an iterator of results
			return new Alt_Database_Result_Cached($result, $sql, $as_object, $params);
		}
		elseif ($type === Alt_Database::INSERT)
		{
			// Return a list of insert id and rows created
			return array(
				$this->insert_id(),
				$result->rowCount(),
			);
		}
		else
		{
			// Return the number of rows affected
			return $result->rowCount();
		}
	}
	
	public function insert_id()
	{
		$table = preg_match("/^insert\s+into\s+(.*?)\s+/i",$this->last_query,$match) ? arr::get($match,1) : NULL;
		if (!empty($table)) $query = "SELECT IDENT_CURRENT(\"" . $this->quote_identifier($table) . "\") AS insert_id";
		else $query = "SELECT SCOPE_IDENTITY() AS insert_id";

		$data = $this->query(Alt_Database::SELECT,$query,FALSE)->current();
		return $data["insert_id"];
	}
	
	public function datatype($type)
	{
		static $types = array
		(
			"nvarchar"  => array("type" => "string"),
			"ntext"     => array("type" => "string"),
			"tinyint"   => array("type" => "int", "min" => "0", "max" => "255"),
		);

		if (isset($types[$type]))
			return $types[$type];

		return parent::datatype($type);
	}
	
	public function begin($mode = NULL)
	{
		// Make sure the Alt_Database is connected
		$this->_connection or $this->connect();

		return $this->_connection->beginTransaction();
	}

	public function commit()
	{
		// Make sure the Alt_Database is connected
		$this->_connection or $this->connect();

		return $this->_connection->commit();
	}

	public function rollback()
	{
		// Make sure the Alt_Database is connected
		$this->_connection or $this->connect();

		return $this->_connection->rollBack();
	}
	
	public function list_tables($like = NULL)
	{
		if (is_string($like))
		{
			// Search for table names
			$result = $this->query(Alt_Database::SELECT, "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE ".$this->quote($like), FALSE)->as_array();
		}
		else
		{
			// Find all table names
			$result = $this->query(Alt_Database::SELECT, "SELECT * FROM INFORMATION_SCHEMA.TABLES", FALSE)->as_array();
		}

		$tables = array();
		foreach ($result as $row)
		{
			// Get the table name from the results
			$tables[] = $row["TABLE_NAME"];
		}

		return $tables;
	}
	
	public function list_columns($table, $like = NULL, $add_prefix = TRUE)
	{
		if (is_string($like))
		{
			$results = $this->query(Alt_Database::SELECT,"SELECT * FROM INFORMATION_SCHEMA.Columns WHERE TABLE_NAME LIKE ".$this->quote($table), FALSE);
		}
		else
		{
			$results = $this->query(Alt_Database::SELECT,"SELECT * FROM INFORMATION_SCHEMA.Columns WHERE TABLE_NAME = ".$this->quote($table), FALSE);
		}

		$result = array();
		foreach ($results as $row)
		{
			list($type, $length) = $this->_parse_type($row["DATA_TYPE"]);

			$column = $this->datatype($type);

			$column["column_name"]      = $row["COLUMN_NAME"];
			$column["column_default"]   = $row["COLUMN_DEFAULT"];
			$column["data_type"]        = $type;
			$column["is_nullable"]      = ($row["IS_NULLABLE"] == "YES");
			$column["ordinal_position"] = $row["ORDINAL_POSITION"];
			
			if($row["CHARACTER_MAXIMUM_LENGTH"])
			{
				$column["character_maximum_length"] = $row["CHARACTER_MAXIMUM_LENGTH"];
			}
			
			$result[$row["COLUMN_NAME"]] = $column;
		}

		return $result;
	}

	public function set_charset($charset){}

}
