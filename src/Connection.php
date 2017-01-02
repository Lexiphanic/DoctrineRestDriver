<?php

namespace Lexiphanic\DoctrineRestDriver;

use Doctrine\DBAL\Connection as AbstractConnection;

/**
 * Restful Doctrine connection
 */
class Connection extends AbstractConnection
{

    /**
     * @var Statement
     */
    private $statement;

    /**
     * prepares the statement execution
     *
     * @param string $statement
     * @return Statement
     */
    public function prepare($statement)
    {
        $this->connect();

        $this->statement = new Statement($statement, $this->getParams());
        $this->statement->setFetchMode($this->defaultFetchMode);

        return $this->statement;
    }

    /**
     * Get the last insert id, always null as RESTful APIs do not
     * have AUTO_INCREMENT meaningfully
     *
     * @param string|null $seqName
     * @return null
     */
    public function lastInsertId($seqName = null)
    {
        return null;
    }

    /**
     * Executes a query, returns a statement
     *
     * @return Statement
     */
    public function query()
    {
        $statement = call_user_func_array([$this, 'prepare'], func_get_args());
        $statement->execute();

        return $statement;
    }

}
