<?php defined("ALT_PATH") OR die("No direct script access.");
/**
 * Alt_Database query builder for DELETE statements. See [Query Builder](/Alt_Database/query/builder) for usage and examples.
 *
 * @package    Alt/Alt_Database
 * @category   Query
 * @author     Alt Team
 * @copyright  (c) 2008-2009 Alt Team
 * @license    http://Altphp.com/license
 */
class Alt_Database_Query_Builder_Delete extends Alt_Database_Query_Builder_Where {

	// DELETE FROM ...
	protected $_table;

	/**
	 * Set the table for a delete.
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
		return parent::__construct(Alt_Database::DELETE, "");
	}

	/**
	 * Sets the table to delete from.
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

		// Start a deletion query
		$query = "DELETE FROM ".$db->quote_table($this->_table);

		if ( ! empty($this->_where))
		{
			// Add deletion conditions
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
		$this->_where = array();

		$this->_parameters = array();

		$this->_sql = NULL;

		return $this;
	}

} // End Alt_Database_Query_Builder_Delete
