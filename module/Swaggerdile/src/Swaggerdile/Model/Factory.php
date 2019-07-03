<?php
/*
 * Factory.php
 *
 * This works by caching the model tables so there is only one copy out there.
 * Singleton
 *
 * @author sconley
 */


namespace Swaggerdile\Model;


class Factory
{
    /*
     * The instance of our singleton.
     *
     * @var Factory
     */
    static protected $_instance = null;

    /*
     * Keep a dictionary mapping table names to instances.
     *
     * @var array
     */
    protected $_tableObjectMap = array();

 
    /*
     * getInstance
     *
     * Return an instance of the singleton.
     *
     * @static
     *
     * @return Factory
     */
    static public function getInstance()
    {
        if(self::$_instance == null) {
            self::$_instance = new Factory();
        }

        return self::$_instance;
    }

    /*
     * get
     *
     * Get a table object.
     *
     * @param string $table
     *
     * @return Object $table
     */
    public function get($table)
    {
        if(!array_key_exists($table, $this->_tableObjectMap)) {
            $objName = "\\Swaggerdile\\Model\\{$table}Table";
            $this->_tableObjectMap[$table] = new $objName();
        }

        return $this->_tableObjectMap[$table];
    }
}
