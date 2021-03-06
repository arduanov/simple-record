<?php

namespace SimpleRecord;

use Doctrine\DBAL;
use Doctrine\Common\Inflector\Inflector;

/**
 * The Record class represents a single database record.
 */
class Record
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected static $CONN;
    protected static $QB;
//    public static $EVENT_MANAGER;

    /**
     * Sets a static reference for the connection to the database.
     *
     * @param \Doctrine\DBAL\Connection $connection
     */
    final public static function connection(DBAL\Connection $connection)
    {
        self::$CONN = $connection;
    }

    /**
     * Returns a reference to a database connection.
     *
     * @return \Doctrine\DBAL\Connection
     */
    final public static function getConnection()
    {
        return self::$CONN;
    }

    /**
     * Returns a database table name.
     *
     * The name that is returned is based on the classname or on the TABLE_NAME
     * constant in that class if that constant exists.
     *
     * @param string $class_name
     * @return string Database table name.
     */
    final public function tableName($class_name = null)
    {
        if (!$class_name) {
            $class_name = get_class($this);
        }

        if (defined($class_name . '::TABLE_NAME')) {
            return constant($class_name . '::TABLE_NAME');
        }

        $reflection = new \ReflectionClass($class_name);
        $class_name = $reflection->getShortName();

        return Inflector::tableize($class_name);
    }

    /**
     * Constructor for the Record class.
     *
     * If the $data parameter is given and is an array, the constructor sets
     * the class's variables based on the key=>value pairs found in the array.
     *
     * @param array $data An array of key,value pairs.
     * @param boolean $is_pdo_fetch
     */
    public function __construct(array $data = null, $is_pdo_fetch = false)
    {
        if ($is_pdo_fetch) {
            $this->afterFetch();
        }
        if (is_array($data)) {
            $this->setFromData($data);
        }
    }

    /**
     * Sets the class's variables based on the key=>value pairs in the given array.
     *
     * @param array $data An array of key,value pairs.
     */
    public function setFromData(array $data)
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    private function getValuesForDb()
    {
        $value_of = [];
        $columns = $this->getColumns();

        foreach ($columns as $column) {
            if (!empty($this->$column) || is_numeric($this->$column)) { // Do include 0 as value
                $value_of[$column] = $this->$column;
            }
        }

        // Make sure we don't try to add "id" field;
        if (isset($value_of['id'])) {
            unset($value_of['id']);
        }
        return $value_of;
    }

    /**
     * Generates an insert or update string from the supplied data and executes it
     *
     * @return boolean True when the insert or update succeeded.
     */
    public function save()
    {
        if (!$this->beforeSave()) {
            return false;
        }

        if (empty($this->id)) {
            if (!$this->beforeInsert()) {
                return false;
            }
            $value_of = $this->getValuesForDb();
            $return = (bool)self::$CONN->insert($this->tableName(), $value_of);
            if (in_array('id', $this->getColumns())) {
                $this->id = self::$CONN->lastInsertId();
            }
            if (!$this->afterInsert()) {
                return false;
            }
        } else {
            if (!$this->beforeUpdate()) {
                return false;
            }
            
            $value_of = $this->getValuesForDb();
            $return = (bool)self::$CONN->update($this->tableName(), $value_of, ['id' => $this->id]);

            if (!$this->afterUpdate()) {
                return false;
            }
        }
        if (!$this->afterSave()) {
            return false;
        }

        return $return;
    }

    /**
     * Generates a delete string and executes it.
     *
     * @throws \Exception
     * @return boolean True if delete was successful.
     */
    public function delete()
    {
        if (!$this->beforeDelete()) {
            return false;
        }

        if (!isset($this->id)) {
            throw new \Exception('cant delete without id');
        }
        $return = (bool)self::$CONN->delete($this->tableName(), ['id' => $this->id]);

        if (!$this->afterDelete()) {
            $this->save();
            return false;
        }

        return $return;
    }

    public function deleteBy(array $criteria)
    {

    }

    /**
     * Returns an array of all columns in the table.
     *
     * It is a good idea to rewrite this method in all your model classes.
     * This function is used in save() for creating the insert and/or update
     * sql query.
     *
     * @return array
     */
    public function getColumns()
    {
        return array_keys(get_object_vars($this));
    }

    /**
     * @param $id
     * @return $this
     * @throws \Exception
     */
    public function find($id)
    {
        return $this->findOneBy(['id' => $id]);
    }

    /**
     * @return array
     */
    public function findAll()
    {
        return $this->findBy([]);
    }

    /**
     * @param array $criteria Options array containing parameters for the query
     * @param array $orderBy
     * @param integer $limit
     * @param integer $offset
     * @return array
     */
    public function findBy(array $criteria, array $orderBy = [], $limit = null, $offset = null)
    {
        $qb = $this->getQueryBuilder();

        foreach ($criteria as $key => $value) {
            $type = null;
            if (is_array($value)) {
                $type = DBAL\Connection::PARAM_STR_ARRAY;
                $where = $key . ' IN (:' . $key . ')';
            } else {
                $where = $key . ' = :' . $key;
            }
            $qb->andWhere($where)
               ->setParameter(':' . $key, $value, $type);
        }
        foreach ($orderBy as $sort => $order) {
            $qb->addOrderBy($sort, $order);
        }
        if ($limit) {
            $qb->setMaxResults($limit);
        }
        if ($offset) {
            $qb->setFirstResult($offset);
        }
        return $this->findByQueryBuilder($qb);
    }

//    public function findBySql($sql)
//    {
//        $qb = self::$CONN->query();
//        return '';
//    }

    public function countLastQuery()
    {
        $qb = self::$QB;
        $qb->select('count(id)')
           ->resetQueryPart('orderBy')
           ->setMaxResults(null)
           ->setFirstResult(null);
        return $qb->execute()->fetchColumn(0);
    }

    /**
     * Returns a single object, retrieved from the database.
     *
     * @param array $criteria Options array containing parameters for the query
     * @throws \Exception
     * @return $this
     */
    public function findOneBy(array $criteria)
    {
        $items = $this->findBy($criteria, [], 2);

        if (!$items) {
            return false;
        }

        if (count($items) > 1) {
            throw new \Exception('finded more than one');
        }

        return array_shift($items);
    }


    public function getQueryBuilder()
    {
        return self::$CONN->createQueryBuilder()->select('*')->from($this->tableName());
    }

    /**
     * @param DBAL\Query\QueryBuilder $qb
     * @return array
     */
    public function findByQueryBuilder(DBAL\Query\QueryBuilder $qb)
    {
        self::$QB = $qb;
        return $qb->execute()->fetchAll(\PDO::FETCH_CLASS, get_class($this), [null, true]);
    }

    /**
     * Allows sub-classes do stuff before a Record is saved.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function beforeSave()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff before a Record is inserted.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function beforeInsert()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff before a Record is updated.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function beforeUpdate()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff before a Record is deleted.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function beforeDelete()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is fetched.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterFetch()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is saved.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterSave()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is inserted.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterInsert()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is updated.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterUpdate()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is deleted.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterDelete()
    {
        return true;
    }
}

