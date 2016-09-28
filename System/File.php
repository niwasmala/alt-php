<?php defined('ALT_PATH') OR exit('No direct script access allowed');

class System_File extends Alt_Dbo {

    public function __construct(){
        // call parent constructor
        parent::__construct();

        // define this class specific properties
        $this->pkey         = "fileid";
        $this->table_name   = "sys_file";
        $this->table_fields = array(
            "fileid"        => "",
            "srctable"      => "",
            "srcid"         => "",
            "location"      => "",
            "name"          => "",
            "description"   => "",
            "mime"          => "",
            "entrytime"     => "",
            "entryuser"     => "",
            "isdeleted"     => 0
        );
    }

    public function upload($data, $file){
        $this->delete(array(
            'where'     => 'srctable = ' . $this->quote($data['srctable']) . ' and srcid = ' . $this->quote($data['srcid']) . ' and name = ' . $this->quote($data['name'])
        ));

        if ($file["error"] > 0){
            throw new Exception('File error : ' . $file["error"], -1);
        }

        $uploads_dir = 'static' . DIRECTORY_SEPARATOR . $data['srctable'] . DIRECTORY_SEPARATOR . $data['srcid'] . DIRECTORY_SEPARATOR;
        @mkdir($uploads_dir, 1777, true);

        if(!move_uploaded_file($file["tmp_name"], $uploads_dir . $file["name"])){
            throw new Exception('File tidak dapat dipindah', -1);
        }

        $data['location'] = $uploads_dir . $file["name"];
        $data['mime'] = $file["type"];
        $res = $data['fileid'] ? $this->update($data) : $this->insert($data);

        return $this->retrieve(array('fileid' => $data['fileid'] ?: $res));
    }
}