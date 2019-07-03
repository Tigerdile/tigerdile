<?php
/*
 * ModelTable.php
 *
 * Base class for model tables.
 *
 * @author sconley
 */
namespace Swaggerdile\Model;

use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\TableGateway\Feature;

abstract class ModelTable extends AbstractTableGateway
{
    /*
     * What is my table name?
     *
     * This is the 'table' variable defined in the parent.
     *
     * @var string
     */

    /*
     * What is my model class name?
     *
     * If not set, the constructor will attempt to 'figure out'
     * our class name.
     *
     * @var string
     */
    protected $_modelClass = false;

    /*
     * What order-by criteria have been provided?
     *
     * @var array
     */
    protected $_orderBy = array();

    /*
     * Are we returning as an array instead of as objects?
     *
     * @var boolean
     */
    protected $_returnArrays = false;

    /*
     * Limit -- if false, we're not set.
     *
     * @var integer
     */
    protected $_limit = false;

    /*
     * Offset -- if false, we're not set.
     *
     * @var integer
     */
    protected $_offset = false;

    /*
     * Is adult?  This allows methods to filter by adult
     * if applicable.
     *
     * @var boolean
     */
    static protected $_isAdult = false;

    /*
     *__construct
     *
     *
     *Construtor
     * ------------------------------------------------------
     * Set _modelClass and add feature to featureset
     *
     *
     */
    public function __construct()
    {
        if($this->_modelClass === false) {
            $this->_modelClass = substr(get_class($this), 0, -5);
        }

        $this->featureSet = new Feature\FeatureSet();
        $this->featureSet->addFeature(new Feature\GlobalAdapterFeature());
        $this->initialize();
    }
  
    /*
     * insert
     *
     * Wrapper for 'insert'.
     *
     * Handle the insertion of model objects in addition to the
     * traditional stuff you could pass into TableGateway's insert.
     *
     * @param array|object $set
     * @return int 
     */
    public function insert($set)
    {
        if(is_object($set)) {
            return parent::insert($set->toArray());
        } else {
            return parent::insert($set);
        }
    }


    /*
     * update
     *
     * Wrapper for 'update'
     *
     * Handle the insertion of model objects in addition to the
     * traditional stuff you could pass into TableGateway's insert.
     * 
     * @param array|object $set
     * @param string $where
     *
     * @return int
     */
    public function update($set, $where = null, $joins=NULL)
    {
        if(is_object($set)) {
            // clear out 'id' if we need to
            $data = $set->toArray();

            if(array_key_exists('id', $data)) {
                unset($data['id']);
            }

            return parent::update($set->toArray(), $where);
        } else {
            return parent::update($set, $where);
        }
    }

    /*
     * beginTransaction
     *
     * Wrapper to start a transaction.
     * 
     * Transactions work across differnet DB tables, you can start
     * with one table and end with any other table.
     */
    public function beginTransaction()
    {
        $this->getAdapter()->getDriver()->getConnection()->beginTransaction();
    }

    /*
     * inTransaction
     *
     * Are we in a transaction?
     *
     * @return boolean
     */
    public function inTransaction()
    {
        return $this->getAdapter()->getDriver()->getConnection()->inTransaction();
    }

    /*
     * commitTransaction
     *
     * Wrapper to end a transaction.
     *
     * Commits a transaction.
     */
    public function commitTransaction()
    {
        $this->getAdapter()->getDriver()->getConnection()->commit();
    }

    /*
     * rollbackTransaction
     *
     * Wrapper to rollback a transaction.
     */
    public function rollbackTransaction()
    {
        $this->getAdapter()->getDriver()->getConnection()->rollback();
    }

    /*
     * fetchById
     *
     * Fetches a single row by primary key
     *
     * Or multiple rows by primary keys if array is passed 
     * Returns null on failure.
     * ALL Ids must load for success.
     * ##### THIS RESPECTS SORT ORDER
     * 
     * 
     * @param integer|array $id
     *
     * @return Model|array of Model
     */
    public function fetchById($id)
    {
        $order = $this->getOrderBy();

        $rowset = $this->select(function($select) use ($order, $id) {
            $select->where(array('id' => $id));

            if(!empty($order)) {
                $select->order($order);
            }

            return $select;
        });

        if(is_array($id)) {
            if(count($id) != $rowset->count()) {
                // we didn't fetch all ID's.
                return null;
            }

            $ret = array();

            foreach($rowset as $row) {
                $ret[] = new $this->_modelClass($row->getArrayCopy());
            }
            
            return $ret;
        }

        $row = $rowset->current();

        if(!$row) {
            return null;
        }

        return new $this->_modelClass($row->getArrayCopy());
    }
   
    /*
     * fetchAll
     *
     * Fetches everything from a given table.
     * ##### THIS RESPECTS SORT ORDER AND LIMIT/OFFSET
     *
     * @return array of model objects.
     */
    public function fetchAll()
    {
        $order = $this->getOrderBy();

        // also limit and offset
        $limit = $this->getLimit();
        $offset = $this->getOffset();

        $rowset = $this->select(function($select) use ($order, $limit, $offset) {
            if(!empty($order)) {
                $select->order($order);
            }

            if($limit !== false) {
                $select->limit($limit);
            }

            if($offset !== false) {
                $select->offset($offset);
            }

            return $select;
        });

        $ret = array();

        foreach($rowset as $row) {
            $ret[] = new $this->_modelClass($row->getArrayCopy());
        }

        return $ret;
    }

    /*
     * fetchNamesByIds
     *
     * Fetch an array of names from an array of ID's,
     * associating ID to name.
     *
     * This is used mostly by select boxes.
     * ##### THIS RESPECTS SORT ORDER AND LIMIT/OFFSET
     *
     * @param array $ids
     *
     * @return array $ret (rowset)
     */
    public function fetchNamesByIds($ids)
    {
        $order = $this->getOrderBy();

        // also limit and offset
        $limit = $this->getLimit();
        $offset = $this->getOffset();

        $rowset = $this->select(function($select) use ($ids, $order, $limit, $offset) {
            $select->columns(array('id', 'name'))
                   ->where(array('id' => $ids));

            if(!empty($order)) {
                $select->order($order);
            }

            if($limit !== false) {
                $select->limit($limit);
            }

            if($offset !== false) {
                $select->offset($offset);
            }

            return $select;
        });

        $ret = array();

        foreach($rowset as $row) {
            $ret[$row->id] = $row->name;
        }

        return $ret;
    }
 
    /*
     * fetchByTitle
     *
     * Fetch by title, used as a primary key
     *
     * Not all tables have a 'title' column, but enough do that
     * it made sense to centralize.
     * ##### THIS RESPECTS SORT ORDER and LIMIT/OFFSET
     * 
     *
     * @param string $name
     *
     * @return array (rowset)
     *
     *
     *
     */
    public function fetchByTitle($name)
    {
        $order = $this->getOrderBy();

        // also limit and offset
        $limit = $this->getLimit();
        $offset = $this->getOffset();

        $rowset = $this->select(function($select) use ($name, $order, $limit, $offset) {
            $select->where(array('title' => $name));

            if(!empty($order)) {
                $select->order($order);
            }

            if($limit !== false) {
                $select->limit($limit);
            }

            if($offset !== false) {
                $select->offset($offset);
            }

            return $select;
        });

        return $this->_returnArray($rowset);
    }

    /*
     * enableArrayReturn
     *
     * Set ourselves to use array returns instead.
     *
     * @return self $this
     */
    public function enableArrayReturn()
    {
        $this->_returnArrays = true;
        return $this;
    }

    /*
     * disableArrayReturn
     *
     * Set ourselves to use objects instead.
     *
     * @return self $this
     */
    public function disableArrayReturn()
    {
        $this->_returnArrays = false;
        return $this;
    }

    /*
     * enableAdult
     *
     * Set enable adult content
     */
    static public function enableAdult()
    {
        self::$_isAdult = true;
    }

    /*
     * disableAdult
     *
     * Disable adult content
     */
    static public function disableAdult()
    {
        self::$_isAdult = false;
    }

    /*
     * _returnArray
     *
     * Convert a Zend rowset to an array of model objects.
     *
     * This code is used everywhere, let's reduce duplication.
     * 
     *
     * @param \Zend\Db\ResultSet
     *
     * @return array of model objects
     */
    protected function _returnArray($rowset)
    {
        $ret = array();

        foreach ($rowset as $row) {
            if($this->_returnArrays) {
                $ret[] = $row->getArrayCopy();
            } else {
                $ret[] = new $this->_modelClass($row->getArrayCopy());
            }
        }

        return $ret;
    }

    /*
     * _returnSingle
     *
     * Convert a Zend rowset to a single return object.
     *
     * This is also used everywhere.
     *
     * @param \Zend\Db\ResultSet $rowset
     *
     * @return model object or null
     */
    protected function _returnSingle($rowset)
    {
        $row = $rowset->current();

        if(!$row) {
            return null;
        }

        if($this->_returnArrays) {
            return $row->getArrayCopy();
        } else {
            return new $this->_modelClass($row->getArrayCopy());
        }
    }

    /*
     * setOrderBy
     *
     * SET the "order by" for the queries.
     * ------------------------------------------------------
     * ##### PLEASE NOTE :
     * * This is "advisory", meaning, each fetch implementation may
     * * or may not ignore the the order by provided and it's implemented
     * * per-method.
     * * Why?  Because some methods do some pretty wacky things, and
     * * order-by doesn't always make sense. 
     * * However, I wanted to provide a means to support it if desired.
     * 
     * @param array|string
     *
     * @return $this
     */
    public function setOrderBy($order)
    {
        $this->_orderBy = $order;
        return $this;
    }

    /*
     * getOrderBy
     *
     * GET the currently set "order by" for the queries.
     *
     *  Will be an empty array if unset.
     *
     * @return array|string
     */
    public function getOrderBy()
    {
        return $this->_orderBy;
    }

    /*
     * getLimit
     *
     * @return integer|false if no limit set.
     */
    public function getLimit()
    {
        return $this->_limit;
    }

    /*
     * getOffset
     *
     * @return integer|false if no offset set.
     */
    public function getOffset()
    {
        return $this->_offset;
    }

    /*
     * setLimit
     * ##### PLEASE NOTE :
     * * This is "advisory", meaning, each fetch implementation may
     * * or may not ignore the the limit by provided and it's implemented
     * * per-method.
     * * Why?  Because some methods do some pretty wacky things, and
     * * limit doesn't always make sense. 
     * * However, I wanted to provide a means to support it if desired.
     *
     * @param integer|false
     *
     * @return $this
     */
    public function setLimit($limit)
    {
        $this->_limit = $limit;
        return $this;
    }

    /*
     * setOffset
     * ##### PLEASE NOTE :
     * * This is "advisory", meaning, each fetch implementation may
     * * or may not ignore the the offset by provided and it's implemented
     * * per-method.
     * * Why?  Because some methods do some pretty wacky things, and
     * * offset doesn't always make sense. 
     * * However, I wanted to provide a means to support it if desired.
     *
     * @param integer|false
     *
     * @return $this
     */
    public function setOffset($offset)
    {
        $this->_offset = $offset;
        return $this;
    }

    /*
     * Call to automate basic fetches.
     *
     * RESPECTS THE ORDER BY SETTING AND ORDER/LIMIT
     *
     * @param string function name
     * @param array arguments
     * @return mixed
     *
     * @throws \Zend\Db\Exception\RuntimeException  if invalid method
     * or not enough arguments.
     */
    public function __call($func, $args = array())
    {
        if(substr($func, 0, 7) != 'fetchBy') {
            throw new \Zend\Db\Exception\RuntimeException("Unknown method : {$func}");
        }

        // There better be exactly 1 arg
        if(count($args) < 1) {
            throw new \Zend\Db\Exception\RuntimeException("Requires parameters : {$func}");
        }

        // Get the column to fetch by
        $fields = explode('_and_', 
                            strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', substr($func, 7))));

        $where = array_combine($fields, $args);

        // get order by, if applicable
        $order = $this->getOrderBy();

        // also limit and offset
        $limit = $this->getLimit();
        $offset = $this->getOffset();

        // Construct the query
        $rowset = $this->select(function($select) use ($where, $args, $order, $limit, $offset) {
            $select->where($where);

            if(!empty($order)) {
                $select->order($order);
            }

            if($limit !== false) {
                $select->limit($limit);
            }

            if($offset !== false) {
                $select->offset($offset);
            }

            return $select;
        });

        return $this->_returnArray($rowset);
    }

    /*
     * Add limit, order, and offset
     *
     * @param Zend Select
     * @return Zend Select
     */
    protected function _addQueryFeatures($query)
    {
        if(!empty($this->_orderBy)) {
            $query->order($this->_orderBy);
        }

        if(!empty($this->_limit)) {
            $query->limit($this->_limit);
        }

        if(!empty($this->_offset)) {
            $query->offset($this->_offset);
        }

        return $query;
    }

    /*
     * A generic query function just to use an array
     *
     * RESPECTS ORDERBY / LIMIT / OFFSET
     *
     * @param array query
     * @return array of Model objects
     */
    public function query($query)
    {
        $rowset = $this->select(function($select) use ($query) {
            $select->where($query);
            return $this->_addQueryFeatures($select);
        });

        return $this->_returnArray($rowset);
    }
}
