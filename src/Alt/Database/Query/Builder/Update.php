<?php defined("ALT_PATH") OR die("No direct script access.");
/**
 * Alt_Database query builder for UPDATE statements. See [Query Builder](/Alt_Database/query/builder) for usage and examples.
 *
 * @package    Alt/Alt_Database
 * @category   Query
 * @author     Alt Team
 * @copyright  (c) 2008-2009 Alt Team
 * @license    http://Altphp.com/license
 */
class Alt_Database_Query_Builder_Update extends Alt_Database_Query_Builder_Where {

	// UPDATE ...
	protected $_table;

	// SET ...
	protected $_set = array();

	/**
	 * Set the table for a update.
	 *
	 * @param   mixed  $table  table name or array($table, $alias) or object
	 * @return  void
	 */
	public function __construct($table = NULL)
	{
		if ($table)
		{
			// Set the inital table name
			$this->_table = $table;
		}

		// Start the query with no SQL
		return parent::__construct(Alt_Database::UPDATE, "");
	}

	/**
	 * Sets the table to update.
	 *
	 * @param   mixed  $table  table name or array($table, $alias) or object
	 * @return  $this
	 */
	public function table($table)
	{
		$this->_table = $table;

		return $this;
	}

	/**
	 * Set the values to update with an associative array.
	 *
	 * @param   array   $pairs  associative (column => value) list
	 * @return  $this
	 */
	public function set(array $pairs)
	{
		foreach ($pairs as $column => $value)
		{
			$this->_set[] = array($column, $value);
		}

		return $this;
	}

	/**
	 * Set the value of a single column.
	 *
	 * @param   mixed  $column  table name or array($table, $alias) or object
	 * @param   mixed  $value   column value
	 * @return  $this
	 */
	public function value($column, $value)
	{
		$this->_set[] = array($column, $value);

		return $this;
	}

	/**
	 * Compile the SQL query and return it.
	 *
	 * @param   mixed  $db  Alt_Database instance or name of instance
	 * @return  string
	 */
	public function compile($db = NULL)
	{
		if ( ! is_object($db))
		{
			// Get the Alt_Database instance
			$db = Alt_Database::instance($db);
		}

		// Start an update query
		$query = "UPDATE ".$db->quote_table($this->_table);

		// Add the columns to update
		$query .= " SET ".$this->_compile_set($db, $this->_set);

		if ( ! empty($this->_where))
		{
			// Add selection conditions
			$query .= " WHERE ".$this->_compile_conditions($db, $this->_where);
		}

		if ( ! empty($this->_order_by))
		{
			// Add sorting
			$query .= " ".$this->_compile_order_by($db, $this->_order_by);
		}

		if ($this->_limit !== NULL)
		{
			// Add limiting
			$query .= " LIMIT ".$this->_limit;
		}

		$this->_sql = $query;

		return parent::compile($db);
	}

	public function reset()
	{
		$this->_table = NULL;

		$this->_set   =
		$this->_where = array();

		$this->_limit = NULL;

		$this->_parameters = array();

		$this->_sql = NULL;

		return $this;
	}


} // End Alt_Database_Query_Builder_Update
