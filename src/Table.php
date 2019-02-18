<?php
/**
 * https://dl2.tech - DL2 IT Services
 * Owlsome solutions. Owltstanding results.
 */

namespace DL2\Zend\Db;

use DL2\Zend\Db\Table\Row;
use Exception;
use Zend_Db_Adapter_Exception;
use Zend_Db_Table;

/**
 * The `DL2\Zend\Db\Table` class is an object-oriented interface to
 * database tables. It provides methods for many common operations
 * on tables.
 */
class Table extends Zend_Db_Table
{
    /**
     * The primary key column or columns.
     *
     * @var array
     */
    protected $_primary = ['id'];

    /**
     * Classname for row.
     *
     * @var string
     *
     * @see DL2\Zend\Db\Table\Row
     */
    protected $_rowClass = 'DL2\Zend\Db\Table\Row';

    /**
     * Create table rows in bulk mode.
     *
     * @param array $batch and array of column-value pairs
     *
     * @throws Zend_Db_Adapter_Exception
     *
     * @return int the number of affected rows
     */
    public static function bulkInsert(array $batch): int
    {
        /** @var DL2\Zend\Db\Table $table */
        $table = new static();

        if (1 === \count($batch)) {
            return $table->insert(array_shift($batch));
        }

        /** @var Zend_Db_Adapter_Abstract $adapter */
        $adapter = $table->getAdapter();

        /** @var int $counter */
        $counter = 0;

        /** @var array $sqlBinds */
        $sqlBinds = [];

        /** @var array $values */
        $values = [];

        foreach ($batch as $i => $row) {
            $placeholders = [];

            foreach ($row as $value) {
                ++$counter;

                if ($adapter->supportsParameters('positional')) {
                    $placeholders[] = '?';
                    $values[]       = $value;
                } elseif ($adapter->supportsParameters('named')) {
                    $name           = ":col{$i}{$counter}";
                    $placeholders[] = $name;
                    $values[$name]  = $value;
                } else {
                    throw new Zend_Db_Adapter_Exception(sprintf(
                        '%s doesn\'t support positional or named binding',
                        \get_class($table)
                    ));
                }
            }

            $sqlBinds[] = '(' . implode(',', $placeholders) . ')';
        }

        //
        // extract column names...
        $columns = array_keys($row);

        //
        // and quoteIdentifier() them.
        array_walk($columns, function (&$index) use ($adapter) {
            $index = $adapter->quoteIdentifier($index, true);
        });

        /** @var string $tableSpec */
        $tableSpec = $adapter->quoteIdentifier(
            ($table->_schema ? "{$table->_schema}." : '') . $table->_name
        );

        /** @var string $sql */
        $sql = "INSERT INTO {$tableSpec} ("
            . implode(',', $columns)
            . ')VALUES('
            . implode(',', $sqlBinds)
            . ')';

        try {
            $adapter->beginTransaction();

            /** @var Zend_Db_Statement $stmt */
            $stmt = $adapter->prepare($sql);
            $stmt->execute($values);

            $adapter->commit();
        } catch (Exception $ex) {
            $adapter->rollback();
        }

        //
        // aaaaaaand voilá!
        return $stmt->rowCount();
    }

    /**
     * Fetches a new blank row (not from the database).
     *
     * @param array $data Data to populate in the new row
     *
     * @return DL2\Zend\Db\Table\Row
     */
    public static function create(array $data = [])
    {
        return (new static())->createRow($data);
    }

    /**
     * Inserts a new row.
     *
     * This is slightly different from original and optmized.
     * Instead of returning the primary key value, it'll return
     * an array with with the inserted data.
     *
     * @param array column-value pairs
     *
     * @return array inserted data
     */
    public function insert(array $data)
    {
        /** @var Zend_Db_Adapter_Abstract $adapter */
        $adapter = $this->getAdapter();

        if ('DL2\Zend\Db\Adapter\PgSql' !== \get_class($adapter)) {
            return parent::insert($data);
        }

        $this->_setupPrimaryKey();

        // Zend_Db_Table assumes that if you have a compound
        // primary key and one of the columns in the key uses
        // a sequence, it's the _first_ column in the compound
        // key.
        /** @var array $primary */
        $primary = $this->_primary;

        /** @var string $pkIdentity */
        $pkIdentity = $primary[(int) $this->_identity];

        // If the primary key can be generated automatically, and
        // no value was specified in the user-supplied data, then
        // omit it from the tuple.
        if (\array_key_exists($pkIdentity, $data) && !$data[$pkIdentity]) {
            unset($data[$pkIdentity]);
        }

        // If this table uses a database sequence object and the
        // data does not specify a value, then get the next ID
        // from the sequence and add it to the row.  We assume
        // that only the first column in a compound primary key
        // takes a value from a sequence.
        if (\is_string($this->_sequence) && !isset($data[$pkIdentity])) {
            $data[$pkIdentity] = $adapter->nextSequenceId($this->_sequence);
        }

        /** @var string $tableSpec */
        $tableSpec = $this->_name;

        if ($this->_schema) {
            $tableSpec = "{$this->_schema}.{$this->_name}";
        }

        /** @var array $result */
        $result = $adapter->insert($tableSpec, $data);

        // Fetch the most recent ID generated by an auto-increment
        // or IDENTITY column, unless the user has specified a value,
        // overriding the auto-increment mechanism.
        if (true === $this->_sequence && !isset($data[$pkIdentity])) {
            $result[$pkIdentity] = $adapter->lastInsertId();
        }

        return $result;
    }

    /**
     * Returns an instance of a Zend_Db_Table_Select object.
     *
     * @param bool $withFromPart Whether or not to include the
     *      from part of the select based on the table
     *
     * @return Zend_Db_Table_Select
     */
    public function select($withFromPart = parent::SELECT_WITHOUT_FROM_PART)
    {
        return parent::select($withFromPart)
            ->setIntegrityCheck(false);
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
}
