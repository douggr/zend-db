<?php
/**
 * https://dl2.tech - DL2 IT Services
 * Owlsome solutions. Owltstanding results.
 */

namespace DL2\Zend\Db\Table;

use DateTime;
use function Stringy\create as Str;
use Zend_Db_Expr;
use Zend_Db_Table_Abstract;
use Zend_Db_Table_Row;
use Zend_Db_Table_Row_Exception;

/**
 * Contains an individual row of a `DL2\Zend\Db\Table` object.
 *
 * This is a class that contains an individual row of
 * a `DL2\Zend\Db\Table` object.
 *
 * When you run a query against a Table class, the result is
 * returned in a set of `DL2\Zend\Db\Table\Row` objects. You can
 * also use this object to create new rows and add them to the
 * database table.
 */
class Row extends Zend_Db_Table_Row
{
    /**
     * The resource name. e.g.: User, Organization, etc.
     */
    const RESOURCE_NAME = null;

    /**
     * This means a required resource does not exist.
     */
    const ERR_MISSING = 'missing';

    /**
     * This means a required field on a resource has not been set.
     */
    const ERR_MISSING_FIELD = 'missing_field';

    /**
     * This means the formatting of a field is invalid. The
     * documentation for that resource should be able to give you
     * more specific information.
     */
    const ERR_INVALID = 'invalid';

    /**
     * This means another resource has the same value as this
     * field. This can happen in resources that must have some
     * unique key (such as Label or Locale names).
     */
    const ERR_ALREADY_EXISTS = 'already_exists';

    /**
     * This means an uncommon error.
     */
    const ERR_UNCATEGORIZED = 'uncategorized';

    /**
     * For the cases this model is set as read only.
     */
    const ERR_READ_ONLY = 'read_only';

    /**
     * For the rare case an exception occurred and we
     * couldn't recover.
     */
    const ERR_UNKNOWN = 'unknown';

    /**
     * Hold mandatory fields.
     *
     * @var array
     */
    const REQUIRED_FIELDS = [];

    /**
     * Hold the errors while saving this object.
     *
     * @var array
     */
    private static $errors = [];

    /**
     * Constructor.
     *
     * Supported params for $config are:-
     * - table: class name or object of type Zend_Db_Table_Abstract
     * - data: values of columns in this row.
     *
     * @param array $config? array of user-specified config options
     *
     * @throws Zend_Db_Table_Row_Exception
     */
    final public function __construct(array $config = [])
    {
        if (!static::RESOURCE_NAME) {
            /** @var string $message */
            $message = implode(' ', [
                'Mandatory resource name (RESOURCE_NAME) is missing for class ',
                \get_class($this),
            ]);

            throw new Zend_Db_Table_Row($message);
        }

        parent::__construct($config);
    }

    /**
     * Retrieve a row field value.
     *
     * @param string the user-specified column name
     *
     * @throws Zend_Db_Table_Row_Exception if the $columnName is
     *      not a column in the row
     *
     * @return mixed the corresponding column value
     *
     * @internal
     */
    public function __get($column)
    {
        if (null === $this->_data[$column]) {
            return null;
        }

        $getter = Str($column)->upperCamelize();

        if (method_exists($this, $getter = "get{$getter}")) {
            return \call_user_func_array([$this, $getter], []);
        } else {
            // @todo(douggr): review weight of this feature

            /** @var string $columnType */
            $columnType = $this
                ->_table
                ->info('metadata')[$column]['DATA_TYPE'];

            if ('bool' === $columnType) {
                return (bool) $this->_data[$column];
            }

            // if ('date' === $columnType || 'timestamp' === $columnType) {
            //     return new DateTime($this->_data[$column]);
            // }
        }

        return parent::__get($column);
    }

    /**
     * Set a row field value.
     *
     * @internal
     */
    public function __set($column, $value)
    {
        $setter = Str($column)->upperCamelize();

        if (method_exists($this, $setter = "set{$setter}")) {
            $value = \call_user_func_array([$this, $setter], [$value]);
        }

        return parent::__set($column, $value);
    }

    /**
     * Returns the errors found while saving this object.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Returns true if the given column was modified since this
     * object was loaded from the database.
     *
     * @param string $column
     *
     * @return bool
     */
    public function isDirty(string $column): bool
    {
        return \array_key_exists($column, $this->_modifiedFields);
    }

    /**
     * Returns true if this is a new record on the database.
     *
     * @return bool
     */
    public function isNewRecord(): bool
    {
        return empty($this->_cleanData);
    }

    /**
     * {@inheritdoc}
     */
    public function listTables()
    {
        /** @var string $sql */
        $sql = 'SELECT c.relname as "TABLE_NAME" FROM pg_catalog.pg_class c'
            . ' LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace'
            . ' WHERE c.relkind IN (\'r\',\'v\')'
            . ' AND n.nspname <> \'pg_catalog\''
            . ' AND n.nspname <> \'information_schema\''
            . ' AND n.nspname !~ \'^pg_toast\''
            . ' AND pg_catalog.pg_table_is_visible(c.oid)';

        return $this->fetchCol($sql);
    }

    /**
     * Reset the value to the given column to its defaults.
     *
     * @param string $column
     */
    final public function reset(string $column): self
    {
        if ($this->isDirty($column)) {
            $this->_data[$column] = $this->_cleanData[$column];
            unset($this->_modifiedFields[$column]);
        }

        return $this;
    }

    /**
     * Saves the properties to the database.
     *
     * This performs an intelligent insert/update, and reloads
     * the properties with fresh data from the table on success.
     *
     * @return mixed The primary key value(s), as an associative
     *      array if the key is compound, or a scalar if the key
     *      is single-column
     */
    final public function save()
    {
        foreach (static::REQUIRED_FIELDS as $field) {
            if (!$this->_data[$field]) {
                $this->pushError([
                    'code'      => static::ERR_MISSING_FIELD,
                    'field'     => $field,
                    'message'   => 'field is mandatory',
                    'resource'  => static::RESOURCE_NAME,
                ]);
            }
        }

        // Allows pre-save logic to be applied to any row.
        //
        // Because `Zend_Db_Table_Row` only uses to do it
        // on `_insert` OR `_update` we can use the very same
        // rules to be applied in both methods.
        $this->_save();

        if (\count(self::$errors)) {
            throw new Zend_Db_Table_Row_Exception(
                'This row contain errors.',
                422,
            );
        }

        if ($this->offsetExists('updated_at')) {
            $this->updated_at = date('Y-m-d\TH:i:s');
        }

        // foreach ($this->_data as $column => &$value) {
        //     if (false === $value) {
        //         $value = new Zend_Db_Expr('FALSE');
        //     }

        //     if (true === $value) {
        //         $value = new Zend_Db_Expr('TRUE');
        //     }

        //     if ($value instanceof DateTime) {
        //         $value = $value->format('Y-m-d\TH:i:s');
        //     }
        // }

        /** @var string $adapterClass */
        $adapterClass = \get_class($this->getTable()->getAdapter());

        // Saves the properties to the database
        if (empty($this->_cleanData)) {
            if ('DL2\Zend\Db\Adapter\PgSql' === $adapterClass) {
                $result = $this->_doInsert();
            } else {
                $result = parent::_doInsert();
            }
        } else {
            if ('DL2\Zend\Db\Adapter\PgSql' === $adapterClass) {
                $result = $this->_doUpdate();
            } else {
                $result = parent::_doUpdate();
            }
        }

        // Run post-SAVE logic
        $this->_postSave();

        // reset errors
        self::$errors = [];

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setReadOnly($flag)
    {
        $this->_readOnly = $flag;

        return $this->pushError([
            'code'      => static::ERR_READ_ONLY,
            'field'     => null,
            'message'   => 'record cannot be changed',
        ]);
    }

    /**
     * Convert this object to a JSON string.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_FORCE_OBJECT | JSON_NUMERIC_CHECK,
        );
    }

    /**
     * Sets all data in the row from an array.
     *
     * @param array $data
     *
     * @return Zend_Db_Table_Row_Abstract
     */
    public function setFromArray(array $data): self
    {
        if (!$data) {
            return $this;
        }

        /** @var array $data */
        $data = array_intersect_key($data, $this->_data);

        foreach ($data as $column => $value) {
            $this->__set($column, $value);
        }

        return $this;
    }

    /**
     * Returns the column/value data as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = array_keys($this->_data);

        foreach ($data as $column) {
            $data[$column] = $this->__get($column);
        }

        return $data;
    }

    /**
     * All error objects have field and code properties so that
     * your client can tell what the problem is.
     *
     * If resources have custom validation errors, they should be
     * documented with the resource.
     *
     * @param string[] $errorData array containing the following keys:
     *      - field: The erroneous field or column
     *      - code: one of the ERR_* codes contants
     *      - message: a friendly message
     *
     * @return DL2\Zend\Db\Table\Row
     */
    protected function pushError(array $error): self
    {
        /** @var array $errorData */
        $errorData = array_replace([
            'code'      => static::ERR_UNKNOWN,
            'field'     => null,
            'message'   => null,
            'resource'  => static::RESOURCE_NAME,
        ], $error);

        /** @var string $hash */
        $hash = md5(serialize($errorData));

        if (!isset(self::$errors[$hash])) {
            self::$errors[$hash] = $errorData;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    final protected function _doInsert()
    {
        // Run pre-INSERT logic
        $this->_insert();

        if (\count(self::$errors)) {
            throw new Zend_Db_Table_Row_Exception(
                'This row contain errors.',
                422,
            );
        }

        $data = array_intersect_key($this->_data, $this->_modifiedFields);

        // Execute the INSERT (this may throw an exception)
        $this->_data = $this->_getTable()->insert($data);

        // Run post-INSERT logic
        $this->_postInsert();

        // Update the `_cleanData` to reflect that the data has
        // been inserted.
        $this->_refresh();

        return $this->_data[current($this->_primary)];
    }

    /**
     * {@inheritdoc}
     */
    final protected function _doUpdate()
    {
        // Run pre-UPDATE logic
        $this->_update();

        if (\count(self::$errors)) {
            throw new Zend_Db_Table_Row_Exception(
                'This row contain errors.',
                422,
            );
        }

        /** @var string|null $where */
        $where = $this->_getWhereQuery(false);

        // Compare the data to the modified fields array to
        // discover which columns have been changed
        $diffData = array_intersect_key($this->_data, $this->_modifiedFields);

        // Execute the UPDATE (this may throw an exception)
        if (\count($diffData)) {
            $this->_getTable()->update($diffData, $where);
        }

        // Run post-UPDATE logic. Do this before the `_refresh()`
        // so the `_postUpdate()` function can tell the difference
        // between changed data and clean (pre-changed) data.
        $this->_postUpdate();

        // Refresh the data just in case triggers in the RDBMS
        // changed any columns.
        $this->_refresh();

        return $this->_data[current($this->_primary)];
    }

    /**
     * Allows post-save logic to be applied to row.
     *
     * @throws Zend_Db_Table_Row_Exception
     */
    protected function _postSave()
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function _refresh()
    {
        /** @var string $adapterClass */
        $adapterClass = \get_class($this->getTable()->getAdapter());

        // using our custom driver, `_refresh()` is not necessary
        if ('DL2\Zend\Db\Adapter\PgSql' !== $adapterClass) {
            return parent::_refresh();
        }

        $this->_cleanData      = $this->_data;
        $this->_modifiedFields = [];
    }

    /**
     * Allows pre-save logic to be applied to row.
     *
     * @throws Zend_Db_Table_Row_Exception
     */
    protected function _save()
    {
    }
}
