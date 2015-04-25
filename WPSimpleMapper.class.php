<?php

namespace TiagoGouvea\WPDataMapper;

class WPSimpleMapper
{
    function __call($name, $arguments)
    {
        // Try to get its value from a method
        if (method_exists($this->_obj, $name)) {
            return call_user_func_array(
                array($this->_obj, $name), $arguments
            );
        }

        // Ops
        die("$name not found in " . __CLASS__);
    }

    public function __get($name)
    {
        // Try to get its value from a method
        if (method_exists($this, $name)) {
            return call_user_func_array(
                array($this, $name), array()
            );
        }

        // Try to get its value from a atribute
        if (property_exists($this, $name))
            return $this->$name;

        // Try to get its value from a "record" atribute
        if (property_exists($this,'record') && property_exists($this->record, $name))
            return $this->record->$name;

        // Just for debuggin
        //die("$name not found in " . __CLASS__);

        // Nothing found
        return null;
    }
}