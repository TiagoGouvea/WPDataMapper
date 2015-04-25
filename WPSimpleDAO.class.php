<?php

namespace TiagoGouvea\WPDataMapper;

use wpdb;

class WPSimpleDAO
{
    private static $instance;

    protected $tableName;
    protected $singleClass;
    protected $dataFields;
    protected $fieldOrder;
    protected $fixedWhere;
    protected $initialized=false;

    static function getInstance(){
        $class = get_called_class();
        if (self::$instance[$class]==null){
            self::$instance[$class]=new $class();
            self::$instance[$class]->init();
        }
        return self::$instance[$class];
    }

    function getById($id,$getMeta=false)
    {
        /* @var $wpdb wpdb */
        global $wpdb;
        $record = $wpdb->get_row('SELECT * FROM ' . $this->tableName . ' WHERE ID = ' . (int)$id, ARRAY_A);
        $record = stripslashes_deep($record);
        if ($record)
            return static::populate($record);
    }

    function getBy($by,$value,$single=true)
    {
        /* @var $wpdb wpdb */
        global $wpdb;
        $sql = 'SELECT * FROM ' . $this->tableName . ' WHERE '.$by.' = \''.$value.'\'';
//        var_dump($sql,"getBy");
//        var_dump($single,"Single");
        if ($single){
            $result = $wpdb->get_row($sql , ARRAY_A);
            $result = stripslashes_deep($result);
//            \var_dump($result,"get_row");
            if ($result){
                $record = $this->populate($result);
                return $record;
            }
        } else {
            $results = $wpdb->get_results($sql , ARRAY_A);
            $results = stripslashes_deep($results);
//            var_dump(count($results),"count(result)");
            if (count($results)>0){
                $records = $this->toObject($results);
                return $records;
            }
        }
    }

    function has($by,$value)
    {
        global $wpdb;
        $sql = 'SELECT coalesce(count(*),0) as count FROM ' . $this->tableName . ' WHERE '.$by.' = \''.$value.'\'';
        $result = $wpdb->get_row($sql , ARRAY_A);
        return $result['count']>0;
    }

    public function hasId($id)
    {
        return $this->getById($id)!=null;
    }

    function getAll()
    {
        /* @var $wpdb wpdb */
        global $wpdb;
        $result = $wpdb->get_results('SELECT * FROM ' . $this->tableName . (isset($this->fixedWhere)? ' where '.$this->fixedWhere : ''). ' order by ' . $this->fieldOrder, ARRAY_A);
        $records = $this->toObject($result);
        return $records;
    }

    function insert($obj)
    {
        /* @var $wpdb wpdb */
        global $wpdb;
        $values = $this->getDataValues($obj,true);
//        $mascaras = array_fill(0, count($values) + 1, '%s');
        add_filter( 'query', 'wp_db_null_value' );
        $ok = $wpdb->insert($this->tableName, $values);
        $id = $wpdb->insert_id;
        remove_filter( 'query', 'wp_db_null_value' );
        return $this->getById($id);
    }

    function save($id, $obj)
    {
        $values = $this->getDataValues($obj);
        if (array_key_exists('id',$values))
            unset($values['id']);
//        $mascaras = array_fill(0, count($values) + 1, 'NULL');

//        var_dump($values,"Valores de save em ".$this->tableName);
        /** @var $wpdb wpdb */
        global $wpdb;
        add_filter( 'query', 'wp_db_null_value' );
        $ok = $wpdb->update($this->tableName, $values, array('id' => $id),$mascaras);
        remove_filter( 'query', 'wp_db_null_value' );
        return $this->getById($id);
    }

    function delete($id)
    {
        /* @var $wpdb wpdb */
        global $wpdb;
        $ok = $wpdb->delete($this->tableName, array('id' => $id));
        return $ok;
    }

    public function populate($data,$obj=null)
    {
        if ($obj==null)
            $obj = new $this->singleClass;
        foreach ($data as $key => $value){
//            echo "$key = $value<BR>";
            if (in_array($key, $this->dataFields))
                $obj->$key = $value;
        }
        return $obj;
    }

    protected function _init($tableName, $singleClass, $fieldOrder, $fixedWhere=null)
    {
        $this->tableName = $tableName;
        $this->fieldOrder = $fieldOrder;
        $this->singleClass = $singleClass;
        $this->dataFields = $this->getDataFields();
        $this->fixedWhere = $fixedWhere;
        $this->initialized=true;
    }

    private function getDataFields()
    {
        $class_vars = get_class_vars($this->singleClass);
        $fields = array();
        foreach ($class_vars as $name => $value) {
            if (strrpos($name, "_") === 0) continue;
            $fields[] = $name;
        }
        return $fields;
    }

    private function getDataValues($obj,$ignoreId=false)
    {
        $values = array();
        foreach ($this->dataFields as $field) {
            if ($ignoreId && strtolower($field)=="id") continue;
            $values[$field] = $obj->$field;
            if ($values[$field]==null || $values[$field]=='')
                $values[$field]='NULL';
        }
        return $values;
    }

    public function toObject($data)
    {
        if ($data != null && count($data) > 0) {
            $return = array();
            foreach ($data as $row) {
                $return[] = static::populate($row);
            }
            return $return;
        }
    }

    public function __call($name, $arguments)
    {
        if (strpos(strtolower($name),"getby")!==0 && strpos(strtolower($name),"has")!==0 && count($arguments)==0) return;

        $get = strpos(strtolower($name),"getby")===0;
        if ($get){
            $method = "getBy";
            $field = substr($name,5);
        } else {
            $method = "has";
            $field = substr($name,3);
        }


//        \var_dump($arguments,"arguments");
        if ($arguments[1]===null)
            $single=true;
        else
            $single = $arguments[1]==true;
//        \var_dump($single,"Single");

        if (in_array($field,$this->dataFields) || in_array(strtolower($field),$this->dataFields))
            return $this->$method($field,$arguments[0],$single);

        if (in_array("id_".$field,$this->dataFields) || in_array("id_".strtolower($field),$this->dataFields))
            return $this->$method("id_".$field,$arguments[0],$single);

        throw new \Exception("Field $field not exists on ".$this->tableName);
    }
}