<?php defined("ALT_PATH") OR die("No direct script access.");
/**
 * MySQL Alt_Database result.   See [Results](/Alt_Database/results) for usage and examples.
 *
 * @package    Alt/Alt_Database
 * @category   Query/Result
 * @author     Alt Team
 * @copyright  (c) 2008-2009 Alt Team
 * @license    http://Altphp.com/license
 */
class Alt_Database_Mysql_Result extends Alt_Database_Result {

	protected $_internal_row = 0;

	public function __construct($result, $sql, $as_object = FALSE, array $params = NULL)
	{
		parent::__construct($result, $sql, $as_object, $params);

		// Find the number of rows in the result
		$this->_total_rows = mysqli_num_rows($result);
	}

	public function __destruct()
	{
		if (is_object($this->_result))
		{
			mysqli_free_result($this->_result);
		}
	}

	public function seek($offset)
	{
		if ($this->offsetExists($offset) AND mysqli_data_seek($this->_result, $offset))
		{
			// Set the current row to the offset
			$this->_current_row = $this->_internal_row = $offset;

			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	public function current()
	{
		if ($this->_current_row !== $this->_internal_row AND ! $this->seek($this->_current_row))
			return NULL;

		// Increment internal row for optimization assuming rows are fetched in order
		$this->_internal_row++;

		if ($this->_as_object === TRUE)
		{
			// Return an stdClass
			return mysqli_fetch_object($this->_result);
		}
		elseif (is_string($this->_as_object))
		{
			// Return an object of given class name
			return mysqli_fetch_object($this->_result, $this->_as_object, $this->_object_params);
		}
		else
		{
			// Return an array of the row
			return mysqli_fetch_assoc($this->_result);
		}
	}

} // End Alt_Database_MySQL_Result_Select
