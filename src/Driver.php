<?php

namespace Lexiphanic\DoctrineRestDriver;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Connection as AbstractConnection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\MySqlSchemaManager;

/**
 * Restful driver
 */
class Driver implements DriverInterface
{

    /**
     * @var Connection
     */
    private $connection;

    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        if (isset($params['driverOptions']['client'])) {
            $params['client'] = $params['driverOptions']['client'];
        }
        if (isset($params['driverOptions']['transformer'])) {
            $params['transformer'] = $params['driverOptions']['transformer'];
        }
        if (!isset($params['client'], $params['transformer'])) {
            throw new \Exception('client and transformer params must be set');
        }
        return $this->connection ?: $this->connection = new Connection($params, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new MySqlPlatform();
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaManager(AbstractConnection $conn)
    {
        return new MySqlSchemaManager($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'lexiphanic_rest';
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(AbstractConnection $conn)
    {
        return 'restful_database';
    }

}
