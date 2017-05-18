<?php defined("ALT_PATH") or die("No direct script access.");

class Alt_Dbo {
    // database instance for this class
    public $db;
    // database instance to use
    public $db_instance;
    // autoincrement flag
    public $autoinc = true;
    // primary key for the table
    public $pkey;
    // table name in database
    public $table_name;
    // table fields
    protected $table_fields = array();
    // table dynamic fields data
    protected $table_dynfields = array();
    // view name in database
    public $view_name;
    // view fields
    protected $view_fields = array();
    // view dynamic fields data
    protected $view_dynfields = array();
    // label
    public $label = "";

    public static function instance($db_instance = null){
        $classname = get_called_class();
        $self = new $classname();
        if($db_instance) $self->reinstance($db_instance);
        return $self;
    }

    /**
     * Constructing class
     * @return void
     */
    public function __construct() {
        $this->table_name   = $this->table_name ? $this->table_name : get_class($this);
        $this->pkey         = $this->pkey ? $this->pkey : $this->table_name ."id";
        $this->db           = Alt_Db::instance($this->db_instance);
    }

    /**
     * Creating column_create query dynamic column
     * @param $field
     * @param $value
     * @return array
     */
    protected function column_create($field, $value, $isquote = true){
        $field = $isquote ? $this->quote($field) : $field;

        switch(gettype($value)){
            case "array":
            case "object":
                $dyncol = array();
                foreach($value as $key => $val){
                    list($key, $val) = $this->column_create($key, $val);
                    $dyncol[] = $key;
                    $dyncol[] = $val;
                }
                $value = count($dyncol) > 0 ? "COLUMN_CREATE (".implode(",",$dyncol).")" : "\"\"";
                break;
            default:
                $value = $this->quote($value);
                break;
        }
        return array($field, $value);
    }

    /**
     * Creating column_get query for dynamic column
     * @param $column
     * @param bool $isbinary
     * @return string
     */
    protected function column_get($column, $isbinary = false){
        $str = "COLUMN_GET(";

        if(count($column) == 2){
            $str .= $column[0] . ", " . $this->quote(str_replace(")", "", str_replace("(", "", $column[1])));
        }else{
            $str .= $this->column_get(array_splice($column, 0, count($column)-1), true) . ", " . $this->quote(str_replace(")", "", str_replace("(", "", $column[count($column)-1])));
        }

        $str .=  " AS " . ($isbinary ? "BINARY" : "CHAR") . ")";
        return $str;
    }

    protected function arrfields($key, $values, $prev = array()){
        $res = array();

        foreach($values as $k => $v){
            switch(gettype($v)){
                case "array":
                case "object":
                    $prev[] = $key;
                    $tmp = $this->arrfields($k, $v, $prev);
                    $str = $tmp[0];
                    break;
                default:
                    // set value
                    $tmp = explode(" ", $v);
                    if(in_array(trim($tmp[0]), array("like", "=", "<", ">", "<>", "<=", ">=", "in", "not", "is", "between"))){
                        $v = $tmp[0] . " " . substr($v, strlen($tmp[0])+1);
                    }else{
                        $v = "like " . $this->quote("%" . $v . "%");
                    }

                    // set dynfields string
                    $dynfields = "dynfields(";
                    for($i=0; $i<count($prev); $i++){
                        $dynfields .= ($i == 0 ? "" : ".") . $prev[$i];
                    }
                    $dynfields .= (count($prev) > 0 ? "." : "") . $key . "." . $k . ") " . $v;
                    $str = $this->dynfields($dynfields);
                    break;
            }

            $res[] = $str;
        }

        return $res;
    }

    /**
     * Support dynamic field selection using dot in any string, e.g. select dynfields(x.y)
     * @param $field
     * @return mixed
     */
    protected function dynfields($field){
        if(count($this->get_dynfields()) > 0){
            $dynfields = $this->get_dynfields();

            $regex = "/dynfields\(([a-zA-z\[\](0-9)*.\*]*)\)/i";
            preg_match_all($regex, $field, $match, PREG_PATTERN_ORDER);

            if(count($match) > 0) foreach($match[1] as $i => $item){
                $item = str_replace("..", ".", str_replace("[", ".", str_replace("]", ".", $item)));
                if($item[strlen($item)-1] == ".")
                    $item = substr($item, 0, strlen($item)-1);

                $column = explode(".", $item);

                if($column[count($column)-1] == "*") {
                    unset($column[count($column) - 1]);
                    $format = "COLUMN_JSON(" . $this->column_get($column, true) . ")";
                }else{
                    $format = $this->column_get($column);
                }

                $field = str_replace($match[0][$i], $format, $field);
            }
        }

        return $field;
    }

    /**
     * Get tablename
     * @param bool $returnview
     * @return string
     */
    public function get_tablename($returnview = true){
        return $returnview && isset($this->view_name) ? $this->view_name : $this->table_name;
    }

    /**
     * Get table field
     * @param bool $returnview
     * @return array
     */
    public function get_fields($returnview = true){
        return $returnview && isset($this->view_name) ? $this->view_fields : $this->table_fields;
    }

    /**
     * Get dynamic fields
     * @param bool $returnview
     * @return mixed
     */
    public function get_dynfields($returnview = true){
        return $returnview && count($this->view_dynfields) > 0 ? $this->view_dynfields : $this->table_dynfields;
    }

    /**
     * Get the where clause
     * @return string SQL group clause
     */
    public function get_select($data = array()){
        $select = array();

        if($data["select"] != null && $data["select"] != ""){
            $tmp = explode(",", $data["select"]);
            $dynfields = $this->get_dynfields();

            foreach($tmp as $field){
                $field = trim($field);
                if(isset($dynfields[$field])){
                    $select[] = "CAST(COLUMN_JSON(" . $field . ") AS CHAR) AS " . $field;
                }else{
                    $dynfield   = $this->dynfields($field);
                    $tmp        = explode(".", str_replace("..", ".", str_replace("[", ".", str_replace("]", ".", $field))));
                    $dyncolumn  = count($tmp) > 1 ? str_replace("dynfields(", "", strtolower($tmp[0])) : "";
                    $tmp        = explode("(", $dyncolumn);
                    $dyncolumn  = count($tmp) > 1 ? $tmp[1] : $tmp[0];

                    if(strpos($dynfield, "COLUMN_") !== FALSE){
                        $as = explode(" as ", strtolower($field));
                        if(count($as) > 1){
                            $dynfield = str_replace(" as " . $as[1], "", str_replace(" AS " . $as[1], "", $dynfield));
                            $select[] = $dynfield . (count($tmp) > 1 ? ")" : "") . " AS " . $as[1];
                        }else{
                            $select[] = $dynfield . (count($tmp) > 1 ? ")" : "") . " AS " . $dyncolumn;
                        }
                    }else if(isset($dynfields[$dynfield])){
                        $select[] = "CAST(COLUMN_JSON(" . $field . ") AS CHAR) AS " . $field;
                    }else{
                        $select[] = $field;
                    }
                }
            }
        }else{
            $exclude = array("entrytime" => "", "entryuser" => "", "modifiedtime" => "", "modifieduser" => "", "deletedtime" => "", "deleteduser" => "", "isdeleted" => "");

            $fields = $this->get_fields();
            foreach($fields as $field => $default_value) if(!isset($exclude[$field])){
                $select[] = $field;
            }

            $dynfields = $this->get_dynfields();
            foreach($dynfields as $field => $default_value) if(!isset($exclude[$field])){
                $select[] = "CAST(COLUMN_JSON(" . $field . ") AS CHAR) AS " . $field;
            }
        }

        return count($select) > 0 ? implode(", ", $select) : "*";
    }

    /**
     * Get the where clause
     * @return string SQL group clause
     */
    public function get_where($data = array()){
        $where = array();

        if($data["where"] != null && $data["where"] != ""){
            if(gettype($data["where"]) == "array"){
                $where = $data["where"];
            }else{
                $data["where"] = $this->dynfields($data["where"]);
                $where[] = $data["where"];
            }
        }

        foreach($data as $key => $value){
            $values = $value;
            if((gettype($value) == "string" || gettype($value) == "number" || gettype($value) == "integer" || gettype($value) == "double") && $value !== ""){
                $tmp = explode(" ", $value);
                if(in_array(trim($tmp[0]), array("like", "=", "<", ">", "<>", "<=", ">=", "in", "not", "is", "between"))){
                    $values = array($tmp[0], substr($value, strlen($tmp[0])+1));
                }else{
                    $values = array("like", $this->quote("%" . $value . "%"));
                }
            }

            if($this->table_fields[$key] !== null || $this->view_fields[$key] !== null){
                $where[] = $key . " " . $values[0] . " " . ($values[1] === null ? "IS NULL" : $values[1]);
            }else if($this->table_dynfields[$key] !== null){
                $where = array_merge($where, $this->arrfields($key, $values));
            }
        }

        $fields = $this->get_fields();
        if(!isset($data["from"]) && ($fields["isdeleted"] !== null && ($data["isdeleted"] == null || $data["isdeleted"] == ""))){
            $where[] = "isdeleted = 0";
        }

        return count($where) > 0 ? " where " . implode(" and ", $where) : "";
    }

    /**
     * Get the group clause
     * @return string SQL group clause
     */
    public function get_group($data = array()) {
        if($data["group"] != null && $data["group"] != ""){
            return " GROUP BY " . $data["group"];
        }
        return "";
    }

    /**
     * Get the order clause
     * @return string SQL order clause
     */
    public function get_order($data = array()) {
        if($data["order"] != null && $data["order"] != ""){
            return " ORDER BY " . $data["order"];
        }
        return "";
    }

    /**
     * Get the from clause
     * @return string SQL order clause
     */
    public function get_from($data = array()) {
        if($data["from"] != null && $data["from"] != ""){
            return " " . $data["from"] . " ";
        }
        return " " . $this->get_tablename() . " ";
    }

    /**
     * Get the limit clause
     * @return string SQL limit clause
     */
    public function get_limit($data = array()) {
        if($data["limit"] != null && $data["limit"] != ""){
            return " limit " . $data["limit"] . " offset " . ($data["offset"] ? $data["offset"] : 0);
        }
        return "";
    }

    /**
     * Get the join clause
     * @return string SQL join clause
     */
    public function get_join($data = array()) {
        if($data["join"] != null && $data["join"] != ""){
            return " " . $data["join"];
        }
        return "";
    }

    /**
     * @param  $instance_name
     * @return Alt_Dbo
     */
    public function reinstance($instance_name = null) {
        $this->db = Alt_Db::instance($instance_name ? $instance_name : $this->db_instance);
        return $this;
    }

    /**
     * Quote value
     * @param $string
     * @return mixed
     */
    public function quote($string){
        return $this->db->quote($string[0] == "'" ? substr($string, 1, strlen($string)-2) : $string);
    }

    /**
     * Execute a query
     * @param $string
     * @return mixed
     */
    public function query($sql, $type = "array"){
        return $this->db->query($sql, $type);
    }

    /**
     * Execute multiple queries
     * @param $string
     * @return mixed
     */
    public function queries($sql, $data){
        return $this->db->queries($data, $sql);
    }

    /**
     * count designated row
     * @param array $data
     * @param boolean $returnsql, is returning sql
     * @return int num of row
     */
    public function count($data = array(), $returnsql = false) {
        // sql query
        $sql = "select count(*) as numofrow from " . $this->get_from($data) . $this->get_join($data) . $this->get_where($data);
        if($returnsql) return $sql;

        $res = $this->query($sql);
        return !empty($res) ? $res[0]["numofrow"] : 0;
    }

    /**
     * insert into database
     * @param usedefault bool set true if you want to use default value for empty table_fields set by DBO
     * @return int inserted row
     */
    public function insert($data, $returnsql = false) {
        // constructing sql
        $sql = "insert into " . $this->table_name . " (";

        // imploding field names
        if ($this->pkey != "" && $this->autoinc)
            unset($data[$this->pkey]);

        // set field values
        $fields = $this->get_fields(false);
        $dynfields = $this->get_dynfields(false);

        // add entry time and entry user if exist
        if($fields["entrytime"] !== null)
            $data["entrytime"] = $data["entrytime"] != "" ? $data["entrytime"] : date("Y-m-d H:i:s");
        if($fields["entryuser"] !== null){
            $userdata = Alt_Auth::get_userdata();
            $data["entryuser"] = $data["entryuser"] != "" ? $data["entryuser"] : $userdata["userid"];
        }

        // set default value to data
        foreach($fields as $field => $default){
            if($default !== "" && (!isset($data[$field]) || $data[$field] === "")){
                $data[$field] = $default;
            }
        }

        // set fields and values to insert
        $fnames = array();
        $values = array();
        foreach ($data as $field => $value) {
            if(isset($fields[$field])){
                // normal table fields
                $fnames[] = $field;
                $values[] = $this->quote($value);
            }else if(isset($dynfields[$field])){
                // dynamic fields
                list($field, $value) = $this->column_create($field, $value, false);
                $fnames[] = $field;
                $values[] = $value;
            }
        }

        // forge sql
        $sql .= implode(",",$fnames) .") values (". implode(",",$values) .")";
        if($returnsql) return $sql;

        // execute or return query
        $res = $this->query($sql);
        return $res;
    }

    public function insert_multi($data, $returnsql = false){
        if(count($data) <= 0)
            throw new Alt_Exception("Tidak ada data yang akan dimasukkan!");

        // constructing sql
        $sql = "insert into " . $this->table_name . " (";

        // set field values
        $fields = $this->get_fields(false);

        // add entry time and entry user if exist
        if($fields["entrytime"] !== null){
            $data[0]["entrytime"] = $data[0]["entrytime"] != "" ? $data[0]["entrytime"] : date("Y-m-d H:i:s");
        }
        if($fields["entryuser"] !== null){
            $userdata = Alt_Auth::get_userdata();
            $data[0]["entryuser"] = $data[0]["entryuser"] != "" ? $data[0]["entryuser"] : $userdata["userid"];
        }

        $insert = array();
        $fnames = array();
        $values = array();
        foreach($fields as $field => $defaultvalue) if(isset($data[0][$field])){
            // set fields and values to insert
            $fnames[] = $field;
            $values[] = "?";
        }

        foreach($data as $i => $item){
            $row = array();

            // add entry time and entry user if exist
            if($fields["entrytime"] !== null){
                $item["entrytime"] = $item["entrytime"] != "" ? $item["entrytime"] : date("Y-m-d H:i:s");
            }
            if($fields["entryuser"] !== null){
                $userdata = Alt_Auth::get_userdata();
                $item["entryuser"] = $item["entryuser"] != "" ? $item["entryuser"] : $userdata["userid"];
            }

            foreach($fnames as $field) {
                $row[] = $item[$field];
            }

            $insert[] = $row;
        }

        // forge sql
        $sql .= implode(",",$fnames) .") values (". implode(",",$values) .")";
        if($returnsql) return $sql;

        // execute or return query
        $res = $this->queries($sql, $insert);
        return $res;
    }

    public function update_multi($data){
        if(count($data) <= 0)
            throw new Alt_Exception("Tidak ada data yang akan dimasukkan!");

        $res = 0;
        foreach($data as $i => $item){
            $res += $this->update($item);
        }

        return $res;
    }

    /**
     * Gets data from database
     * @return array of data
     */
    public function get($data = array(), $returnsql = false) {
        if(isset($data[$this->pkey])){
            $tmp = explode(" ", $data[$this->pkey]);
            if(!in_array(trim($tmp[0]), array("like", "=", "<", ">", "<>", "<=", ">=", "in", "is", "not", "between"))){
                $data[$this->pkey] = str_replace("'", "", str_replace("= ", "", $data[$this->pkey]));
                $data["where"] = $this->pkey ." = ". $this->quote($data[$this->pkey]);
                unset($data[$this->pkey]);
            }
        }

        $sql = "select ".$this->get_select($data) . " from " . $this->get_from($data) . $this->get_join($data) . $this->get_where($data) . $this->get_group($data) . $this->get_order($data) . $this->get_limit($data);
        if($returnsql) return $sql;

        // returning data
        $res = $this->query($sql, "array");
        $dynfields = $this->get_dynfields();
        if(count($dynfields) > 0){
            for ($i = 0; $i < count($res); $i++) {
                foreach($res[$i] as $field => $value) {
                    $decoded = json_decode($res[$i][$field], true);
                    $res[$i][$field] = $decoded !== NULL && count($decoded) > 0 ? $decoded : $value;
                }
            }
        }

        return $res;
    }

    public function retrieve($data, $returnsql = false){
        $res = $this->get($data, $returnsql);

        if($returnsql) return $res;
        if(count($res) < 0 || $res == null) throw new Alt_Exception(($this->label ? $this->label : "Data") . " tidak ditemukan!");
        return $res[0];
    }

    /**
     * update the data
     * @param $data
     * @param bool $returnsql
     * @return int affected row
     * @throws Alt_Exception
     */
    public function update($data, $returnsql = false) {
        // constructing sql
        $sql = "update " . $this->table_name . " set ";

        $pkey = $data[$this->pkey];
        unset($data[$this->pkey]);

        // set field values
        $table_fields = $this->get_fields(false);
        $dynfields = $this->get_dynfields(false);

        // add modified time and modified user if exist
        if($table_fields["modifiedtime"] !== null){
            $data["modifiedtime"] = $data["modifiedtime"] != "" ? $data["modifiedtime"] : date("Y-m-d H:i:s");
        }
        if($table_fields["modifieduser"] !== null){
            $userdata = Alt_Auth::get_userdata();
            $data["modifieduser"] = $data["modifieduser"] != "" ? $data["modifieduser"] : $userdata["userid"];
        }

        // set fields and values to update
        $fields = array();
        foreach ($data as $field => $value) {
            if(isset($table_fields[$field])) {
                // normal table fields
                $fields[] = $field . " = " . $this->quote($value);
            }else if(isset($dynfields[$field])){
                // dynamic column
                list($field, $value) = $this->column_create($field, $value, false);
                $fields[] = $field . " = " . $value;
            }
        }

        // forge sql
        if(count($fields) <= 0)
            throw new Alt_Exception("No field to update");

        $sql .= implode(",",$fields) . ($data["where"] ? " where " . $data["where"] : (isset($pkey) ? " where " . $this->pkey . " = ". $this->quote($pkey) : ""));

        // return sql
        if($returnsql) return $sql;

        // execute
        $res = $this->query($sql);
        return $res;
    }

    /**
     * delete the data
     * @return int num of deleted data
     */
    public function delete($data, $returnsql = false) {
        if(isset($data[$this->pkey])){
            $data["where"] = $this->pkey ." = ". $this->quote($data[$this->pkey]);
            unset($data[$this->pkey]);
        }else if(!isset($data["where"])){
            return -1;
        }

        // add modified time and modified user if exist
        $fields = $this->get_fields(false);
        if($fields["isdeleted"] !== null && (!isset($data["force"]) || !$data["force"])){
            if($fields["deletedtime"] !== null){
                $data["deletedtime"] = $data["deletedtime"] != "" ? $data["deletedtime"] : date("Y-m-d H:i:s");
            }
            if($fields["deleteduser"] !== null){
                $userdata = Alt_Auth::get_userdata();
                $data["deleteduser"] = $data["deleteduser"] != "" ? $data["deleteduser"] : $userdata["userid"];
            }
            if($fields["isdeleted"] !== null)       $data["isdeleted"] = 1;

            return self::update($data, $returnsql);
        }

        // return sql
        $sql = "delete from " . $this->table_name . $this->get_where($data);
        if($returnsql) return $sql;

        // execute
        $res = $this->query($sql);
        return $res;
    }

    public function keyvalues($data, $returnsql = false){
        $key = $data["key"] ? $data["key"] : $this->pkey;
        if(isset($data["value"])) $data["select"] = $key . ", " . $data["values"];
        $tmp = $this->get($data, $returnsql);

        if($returnsql) return $tmp;

        $ref = array();
        foreach($tmp as $item){
            $setvalue = $data["values"] ? $item[$data["values"]] : $item;

            if($data["ismulti"]){
                $ref[$item[$key]][] = $setvalue;
            }else{
                $ref[$item[$key]] = $setvalue;
            }
        }
        return $ref;
    }

    public function table($data, $returnsql = false){
        return array(
            "total" => $this->count($data, $returnsql),
            "list" => $this->get($data, $returnsql),
        );
    }

    public function save($data, $returnsql = false){
        if(isset($data[$this->pkey]) && $data[$this->pkey] != ""){
            $tmp = array();
            $tmp[$this->pkey] = $data[$this->pkey];
            $count = $this->count($tmp);

            if($count > 0)
                return $this->update($data, $returnsql);
        }

        if($this->autoinc)
            unset($data[$this->pkey]);

        return $this->insert($data, $returnsql);
    }

    public function truncate(){
        return $this->query("delete from " . $this->table_name);
    }
}