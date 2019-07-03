<?php
/*
 * Model.php
 *
 * Base model file that will allow for automation of getters / setters
 *
 * @author sconley
 */
namespace Swaggerdile\Model;

use \Zend\Db\Sql\Sql;

abstract class Model implements \JsonSerializable
{
    /*
     * Array of valid field names.  May be empty if you want to
     * not have field name checking.
     *
     * @var array
     */
    protected static $_fields = array();

    /*
     * Field values, a mapping of field keys to values.
     *
     * @var array
     */
    protected $_values = array();

    /*
     * Constructor -- by default can take an array which it will
     * push into values after validation.
     *
     * This also copies over the client and gateway statics into GDS.
     */
    public function __construct($options)
    {
        $this->fromArray($options);
    }


  
    /*
     * __call
     *
     * Catchall to handle getters / setters
     *
     *
     * @throws \Zend\Db\Exception\RuntimeException  if invalid method.
     * @param string $func
     * @param array $args
     *
     * @return void | string
     */
    public function __call($func, $args = array())
    {
        $return = NULL;

        if(substr($func, 1, 2) != 'et') { // should be get or set
            // Throw exception
            throw new \Zend\Db\Exception\RuntimeException("Invalid method : {$func}");
        }

        $field = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', substr($func, 3)));

        if((!empty($this->_fields)) && (!in_array($field, $this->_fields))) {
            // Throw exception
            throw new \Zend\Db\Exception\RuntimeException("Invalid method : {$func}");
        }

        if($func[0] == 's') {
            $this->_values[$field] = $args[0];
            $return = $this;
        } elseif(array_key_exists($field, $this->_values)) { // yes, this would return on 'bet', 'vet', etc.
                 // but exact checking is more compute cycles.
                 
            $return = $this->_values[$field];
        }
 
        return $return;
    }

  
    /*
     * toArray
     *
     * Return model object as array
     *
     * @return  object _values
     */
    public function toArray()
    {
        return $this->_values;
    }


    /*
     * fromArray
     *
     * Set from an array, with data integrity checking.
     *
     * @throws \Zend\Db\Exception\UnexpectedValueException 
     *         If an invalid field is passed.
     *
     * @param array $options
     */
    public function fromArray($options)
    {
        foreach(array_keys($options) as $key) {
            if((!empty($this->_fields)) && (!in_array($key, $this->_fields))) {
                throw new \Zend\Db\Exception\UnexpectedValueException("Unknown field: $key");
            }
        }
        
        $this->_values = $options;
    }

 
    /*
     * exchangeArray
     *
     * Implements Zend TableGateway -- sets without column check.
     *
     * @param array $data
     * @return self $this
     */
    public function exchangeArray($data)
    {
        $this->_values = $data;
        return $this;
    }

   
    /*
     * getClassName
     *
     * This strips away the namespace garbage from the class and returns it.
     *  ------------------------------------------------------
     *
     * @return string
     */
    public function getClassName()
    {
        return substr(get_class($this), 18);
    }
  
  
    /*
     * jsonSerialize
     *
     * To allow JSON serialization.
     *
     * @return array _values
     */
    public function jsonSerialize()
    {
        return $this->_values;
    }


    /*
     * getSqlForTable
     *
     * Fetches a Zend SQL object for a given table name.
     *
     * @param string
     * @return Zend\Db\Sql\Sql
     */
    protected function _getSqlForTable($table)
    {
        return new Sql(
                    Factory::getInstance()->get($this->getClassName())->getAdapter(),
                    $table
        );
    }

    /*
     * _sqlExecute
     *
     * This emulates the zend DB selectWith call
     *
     * @param Zend\Db\Sql
     * @param Zend\Db\Sql object such as Select, Insert, etc.
     * @return mixed
     */
    protected function _sqlExecute($sql, $query)
    {
        return $sql->prepareStatementForSqlObject($query)->execute();
    }
}
