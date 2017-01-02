<?php

namespace Lexiphanic\DoctrineRestDriver;

use Lexiphanic\DoctrineRestDriver\Transformers\MysqlToRequest;
use Doctrine\DBAL\Driver\Statement as StatementInterface;

/**
 * Statement, sends request to API
 */
class Statement implements \IteratorAggregate, StatementInterface
{

    /**
     * @var string
     */
    private $query;

    /**
     * @var MysqlToRequest
     */
    private $transformer;

    /**
     * @var array
     */
    private $params = [];

    /**
     * @var mixed
     */
    private $restClient;

    /**
     * @var mixed[]
     */
    private $result;

    /**
     * @var int
     */
    private $fetchMode;

    /**
     * Statement constructor
     *
     * @param string $query
     * @param mixed[] $options
     * @throws \Exception
     * @todo: Remove globals
     */
    public function __construct($query, array $options)
    {
        global $kernel;
        $this->query = $query;
        $this->transformer = $kernel->getContainer()->get($options['transformer']);
        $this->restClient = $kernel->getContainer()->get($options['client']);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = null)
    {
        $this->params[$param] = $value;
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = null, $length = null)
    {
        throw new \Exception(__METHOD__ . ' is not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        $result = $this->transformer->transform($this->query, $params ?: $this->params);
        $response = $this->restClient->send($result);
        $this->result = $this->transformer->transformBack($this->query, $this->params, $response);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        return count($this->result);
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return empty($this->result) ? 0 : count(reset($this->result));
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->fetchMode = $fetchMode;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null)
    {
        $fetchMode = empty($fetchMode) ? $this->fetchMode : $fetchMode;
        if ($fetchMode !== \PDO::FETCH_ASSOC) {
            throw new \Exception('Only FETCH_ASSOC is supported');
        }
        return count($this->result) === 0 ? false : array_pop($this->result);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null)
    {
        $result = [];
        while (($row = $this->fetch($fetchMode)) !== false) {
            array_push($result, $row);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        throw new \Exception(__METHOD__ . ' is not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return $this->result;
    }

}
