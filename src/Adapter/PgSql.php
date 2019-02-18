<?php
/**
 * https://dl2.tech - DL2 IT Services
 * Owlsome solutions. Owltstanding results.
 */

namespace DL2\Zend\Db\Adapter;

use Zend_Db_Adapter_Exception;
use Zend_Db_Adapter_Pdo_Pgsql;
use Zend_Db_Expr;

/**
 * Class for connecting to PostgreSQL databases and performing
 * common operations.
 *
 * @todo(douggr): support `savepoint`s in transactions?
 */
class PgSql extends Zend_Db_Adapter_Pdo_Pgsql
{
    /**
     * Inserts a table row with specified data.
     *
     * This is slightly different from original and optmized
     * for PostgreSQL. Instead of returning the number of
     * affected rows, it'll return an array with with the
     * inserted data.
     *
     * @param mixed the table to insert data into
     * @param array column-value pairs
     *
     * @throws Zend_Db_Adapter_Exception
     *
     * @return array the inserted data
     */
    public function insert($table, array $bind)
    {
        /** @var array $cols, $vals */
        [ $cols, $vals ] = $this->getDataFromBindedValues($bind);

        /** @var string $sql */
        $sql = "INSERT INTO {$this->quoteIdentifier($table, true)} ("
            . implode(',', $cols)
            . ')VALUES('
            . implode(',', $vals)
            . ')RETURNING *';

        return $this
            ->query($sql, array_values($bind))
            ->fetch();
    }

    /**
     * Updates table rows with specified data based on a WHERE
     * clause.
     *
     * @param mixed the table to update
     * @param array column-value pairs
     * @param mixed UPDATE WHERE clause(s)
     *
     * @throws Zend_Db_Adapter_Exception
     *
     * @return int the number of affected rows
     */
    public function update($table, array $bind, $where = '')
    {
        /** @var array $cols, $vals */
        [ $cols, $vals ] = $this->getDataFromBindedValues($bind);

        /** @var string[] $set */
        $set = [];

        foreach ($cols as $index => $column) {
            $set[] = "{$column}={$vals[$index]}";
        }

        /** @var string|null $where */
        $where = $this->_whereExpr($where) ?: '(TRUE)';

        /** @var string $sql */
        $sql = "UPDATE {$this->quoteIdentifier($table, true)} SET "
            . implode(',', $set)
            . " WHERE {$where} RETURNING *";

        return $this
            ->query($sql, array_values($bind))
            ->fetch();
    }

    /**
     * @todo(douggr): add PHPDoc
     */
    private function getDataFromBindedValues(array &$bind): array
    {
        /** @var string[] $cols */
        $cols = [];

        /** @var string[] $vals */
        $vals = [];

        foreach ($bind as $column => $value) {
            $cols[] = $this->quoteIdentifier($column, true);

            // this is UGLY, but the fastest
            if (false === $value) {
                $vals[] = 'FALSE';
                unset($bind[$column]);
            } elseif (true === $value) {
                $vals[] = 'TRUE';
                unset($bind[$column]);
            } elseif ($value instanceof DateTime) {
                $vals[] = $value->format('Y-m-d\TH:i:s');
                unset($bind[$column]);
            } elseif ($value instanceof Zend_Db_Expr) {
                $vals[] = (string) $value;
                unset($bind[$column]);
            } else {
                $vals[] = '?';
            }
        }

        return [$cols, $vals];
    }
}
