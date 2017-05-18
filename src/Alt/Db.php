<?php defined("ALT_PATH") or die("No direct script access.");

define("DB_PARAM_SCALAR", 1);
define("DB_PARAM_OPAQUE", 2);
define("DB_PARAM_MISC",   3);

class Alt_Db {
    public $db;
    private $history = array();
    public static $instances = array();
    private $trans_started = false;

    /**
     * @param null $name
     * @param null $config
     * @return Alt_Db
     */
    public static function instance($name = NULL,$config = NULL) {
        $name = $name == NULL? "default" : $name;
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = new self($name,$config);
        }
        return self::$instances[$name];
    }

    public function __construct($name = NULL,$config = NULL) {
        $this->db = Alt_Database::instance($name,$config);
    }

    public function __destruct() {
        $this->db->disconnect();
    }

    public function query($sql,$rtype = "object") {
        // determine what kind of query
        $asql = preg_split("/ \s*/",$sql);
        switch (strtolower($asql[0])) {
            case "select" :
                $qtype = Alt_Database ::SELECT; break;
            case "insert" :
                $qtype = Alt_Database ::INSERT; break;
            case "update" :
                $qtype = Alt_Database ::UPDATE; break;
            case "delete" :
                $qtype = Alt_Database ::DELETE; break;
            default :
                $qtype = null;
        }

        if ($qtype == null) {
            return FALSE;
        }
        else if ($qtype == Alt_Database ::SELECT) {
            $name = $this->db->get_instance();
            $config = Alt::$config["database"][$name];

            if (strpos($config["connection"]["dsn"], "sqlsrv") === 0)
                $sql = $this->mssql_emulate_limit($sql);

            switch ($rtype) {
                case "array" :
                    $result = $this->db->query($qtype,$sql,false)->as_array(); break;
                case "object" :
                default :
                    $result = $this->db->query($qtype,$sql,true)->as_array();
            }
            $this->record("select",$sql,count($result));
            return $result;
        }
        else if ($qtype == Alt_Database ::INSERT) {
            $result = $this->db->query($qtype,$sql,true);
            $this->record($asql[0],$sql,$result[1]);
            return $result[0];
        }
        else {
            $result = $this->db->query($qtype,$sql,true);
            $this->record($asql[0],$sql,$result);
            return $result;
        }
    }

    public function queries($data,$statement) {
        // determine what kind of query
        $asql = preg_split("/ \s*/",$statement);
        switch (strtolower($asql[0])) {
            case "select" :
                $qtype = null; break;
            case "insert" :
                $qtype = Alt_Database ::INSERT; break;
            case "update" :
                $qtype = Alt_Database ::UPDATE; break;
            case "delete" :
                $qtype = Alt_Database ::DELETE; break;
            default :
                $qtype = null;
        }

        // prepare query
        $tokens   = preg_split("/((?<!\\\)[&?!])/", $statement, -1, PREG_SPLIT_DELIM_CAPTURE);
        $token     = 0;
        $types     = array();
        $newtokens = array();

        foreach ($tokens as $val) {
            switch ($val) {
                case "?":
                    $types[$token++] = DB_PARAM_SCALAR;
                    break;
                case "&":
                    $types[$token++] = DB_PARAM_OPAQUE;
                    break;
                case "!":
                    $types[$token++] = DB_PARAM_MISC;
                    break;
                default:
                    $newtokens[] = preg_replace("/\\\([&?!])/", "\\1", $val);
            }
        }

        // traversing on data
        $affrow = 0;

        foreach ($data as $item) {
            $aquery = "";
            $idx = 0;
            // on each item, traverse element
            foreach ($item as $elm) {
                $aquery .= $newtokens[$idx];
                if ($types[$idx] == DB_PARAM_SCALAR) {
                    $aquery .= $this->db->quote($elm);
                }
                else if ($types[$idx] == DB_PARAM_OPAQUE) {
                    $fp = @fopen($value, "rb");
                    if (!$fp) {
                        return FALSE;
                    }
                    $aquery .= $this->db->quote(fread($fp, filesize($elm)));
                    fclose($fp);
                }
                else {
                    $aquery .= $elm;
                }
                $idx++;
            }
            $aquery .= $newtokens[$idx];
            // execute the query
            if ($qtype == Alt_Database ::INSERT) {
                $result = $this->db->query($qtype,$aquery,true);
                $this->record($asql[0],$aquery,$result[1]);
                if ($result[1] > 0) $affrow += $result[1];
                else $affrow += $result[1];
            }
            else {
                $result = $this->db->query($qtype,$aquery,true);
                $this->record($asql[0],$aquery,$result);
                if ($result > 0) $affrow+= $result;
                else $affrow += 0;
            }
        }
        return $affrow;
    }

    public function quote($value) {
        return $this->db->quote($value);
    }

    public function get_history() {
        return $this->history;
    }

    private function record($qtype,$sql,$result) {
        // array of type,sql statement,num of result
        $this->history[] = array($qtype,$sql,$result);
    }

    public function begin() {
        if (!$this->trans_started) {
            $this->trans_started = true;
            return $this->db->begin();
        }
        else return false;
    }

    public function commit() {
        $this->trans_started = false;
        return $this->db->commit();
    }

    public function rollback() {
        $this->trans_started = false;
        return $this->db->rollback();
    }


    private function mssql_emulate_limit($sql) {
        if (preg_match("/OFFSET ([0-9]+)/i", $sql, $matches)) {
            list($replace, $offset) = $matches;
            $sql = str_replace($replace, "", $sql);
        }

        if (preg_match("/LIMIT ([0-9]+)/i", $sql, $matches)) {
            list($replace, $limit) = $matches;
            $sql = str_replace($replace, "", $sql);
        }

        if (isset($limit) || isset($offset)) {
            if (!isset($offset)) {
                $sql = preg_replace("/^(SELECT|DELETE|UPDATE)\s/i", "$1 TOP " . $limit . " ", $sql);
            } else {
                $ob_count = (int)preg_match_all("/ORDER BY/i", $sql, $ob_matches, PREG_OFFSET_CAPTURE);

                if ($ob_count < 1) {
                    $over = "ORDER BY (SELECT 0)";
                } else {
                    $ob_last = array_pop($ob_matches[0]);
                    //$orderby = strrchr($sql, $ob_last[0]);
                    $orderby = substr($sql, strpos($sql, $ob_last[0]));
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
        return $sql;
    }
}