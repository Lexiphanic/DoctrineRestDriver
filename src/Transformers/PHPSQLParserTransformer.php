<?php

namespace Lexiphanic\DoctrineRestDriver\Transformers;

use GuzzleHttp\Psr7\Request;
use PHPSQLParser\PHPSQLParser;

/**
 * Transforms an SQL query to a request
 */
class PHPSQLParserTransformer
{

    /**
     * @var PHPSQLParser
     */
    private $parser;

    /**
     * MySQL commands to HTTP verbs
     * @var string[]
     */
    private $methodMap = [
        'INSERT' => 'POST',
        'UPDATE' => 'PUT',
        'DELETE' => 'DELETE',
        'SELECT' => 'GET',
    ];

    /**
     * PHPSQLParser constructor
     */
    public function __construct(PHPSQLParser $parser)
    {
        $this->parser = $parser;

        return $this;
    }

    /**
     * Used for converting sql operations to http verbs
     * @param string[] $map
     * @return self
     */
    public function setMethodMap(array $map)
    {
        $this->methodMap = $map;
        return $this;
    }

    public function transformBack($query, array $params, $data)
    {
        $data = json_decode((string) $data->getBody(), true);

        $tokens = $this->parser->parse($query);

        $datas = $this->getBasic($tokens, $params);
        $columns = $datas['columns'];

        $singleResultSet = false;
        foreach ($data as $datum) {
            if (!is_array($datum)) {
                $singleResultSet = true;
            }
        }

        if ($singleResultSet) {
            $data = [$data];
        }

        foreach ($data as $key => $datum) {
            $data[$key] = $this->map($datum, $columns);
        }

        return $data;
    }

    private function map(array $data, array $map)
    {
        $result = [];
        foreach ($data as $key => $datum) {
            $key = isset($map[$key]) ? $map[$key] : '_' . $key;
            if (is_array($datum)) {
                $datum = $this->map($datum, $map);
            }
            $result[$key] = $datum;
        }
        return $result;
    }

    /**
     * Transforms the given query into a request object
     *
     * @param  string $query
     * @return Request
     */
    public function transform($query, array $params = [])
    {
        $tokens = $this->parser->parse($query);

        $data = $this->getBasic($tokens, $params);
        $method = $data['method'];
        $uri = $data['uri'];
        $payload = $data['payload'];

        // Add generic paging
        if (isset($tokens['LIMIT']['offset'])) {
            $querystring .= '&_offset=' . (int) $tokens['LIMIT']['offset'];
        }
        if (isset($tokens['LIMIT']['rowcount'])) {
            $querystring .= '&_limit=' . (int) $tokens['LIMIT']['rowcount'];
        }
        // Add generic ordering
        if (isset($tokens['ORDER'])) {
            $querystring .= '&_order=' . implode(',', array_map(function ($order) {
                return urlencode($order['base_expr']);
            }, $tokens['ORDER']));
        }

        // Move the id to the path
        if (preg_match('/(\?|&|^)id=([^\/\?\&]+)/', $querystring, $matches)) {
            $querystring = str_replace($matches[1] . 'id=' . $matches[2], '', $querystring);
            $uri .= '/' . $matches[2];
        }

        $querystring = ltrim($querystring, '?&');

        // Add the query
        if (trim($querystring) === '') {
            
        } elseif (strpos($uri, '?') === false) {
            $uri .= '?';
        } else {
            $uri .= '&';
        }
        $uri .= $querystring;

        return $this->createRequest($method, $uri, [], $payload);
    }

    /**
     * 
     * @param array $tokens
     * @param string[] $params
     * @return mixed[]
     */
    private function getBasic(array $tokens, array $params)
    {
        $method = $this->getMethod($tokens);
        $columns = [];
        $payload = [];
        if ($method === 'POST') {
            $uri = $this->getPath(reset($tokens));
            $payload = $this->getPayloadInsert(reset($tokens), $params);
            $this->alias = $this->getAlias($tokens);
        } elseif ($method === 'GET') {
            $columns = $this->getColumns($tokens['SELECT']);
            $uri = $this->getPath($tokens['FROM']);
            $this->alias = $this->getAlias($tokens['FROM']);
        } elseif ($method === 'DELETE') {
            $uri = $this->getPath($tokens['FROM']);
        } elseif ($method === 'PUT') {
            $uri = $this->getPath($tokens['UPDATE']);
            $payload = $this->getWhere($tokens['SET'], $params);
            parse_str($payload, $payload);
        }

        $where = null;
        if (isset($tokens['WHERE'])) {
            $where = $this->getWhere($tokens['WHERE'], $params);
        }

        return [
            'method' => $method,
            'uri' => $uri,
            'payload' => $payload,
            'alias' => $this->alias,
            'where' => $where,
            'columns' => $columns
        ];
    }

    /**
     * 
     * @param array $tokens
     * @return string[]
     */
    private function getColumns(array $tokens)
    {
        $result = [];
        foreach ($tokens as $token) {
            if ($token['expr_type'] !== 'colref') {
                continue;
            }

            $result[end($token['no_quotes']['parts'])] = end($token['alias']['no_quotes']['parts']);
        }
        return $result;
    }

    /**
     * 
     * @param mixed[] $token
     * @return string
     */
    public function concatWhere(array $token)
    {
        if ($token['expr_type'] === 'expression') {
            $result = implode('', array_map([$this, 'concatWhere'], $token['sub_tree'])) . '&';
        } else {
            $result = str_replace(['or', 'OR'], '|', str_replace(['and', 'AND'], '&', $token['base_expr']));
            if (is_array($token['sub_tree'])) {
                $result .= '(' . implode(',', array_map([$this, 'concatWhere'], $token['sub_tree'])) . ')';
            }
        }
        return $result;
    }

    /**
     * 
     * @param array $tokens
     * @param string[] $params
     * @return string
     */
    private function getWhere(array $tokens, array &$params)
    {
        $resultPlaceholder = implode('', array_map([$this, 'concatWhere'], $tokens));
        $count = substr_count($resultPlaceholder, '?');

        $resultParams = [];
        while ($count-- > 0) {
            $resultParams[] = array_shift($params);
        }

        $result = vsprintf(str_replace(['?', $this->alias . '.'], ['%s', ''], $resultPlaceholder), array_map(function ($param) {
                    return urlencode($param);
                }, $resultParams));

        return $result;
    }

    /**
     * Creates a PSR7 request
     * @param string $method
     * @param string $uri
     * @param string[] $headers
     * @param mixed[] $payload
     * @return Request
     */
    protected function createRequest($method, $uri, array $headers, array $payload = null)
    {
        return new Request(
                $method, $uri, $headers, $payload ? json_encode($payload) : null
        );
    }

    /**
     * 
     * @param array $tokens
     * @return string
     * @throws \Exception
     */
    private function getMethod(array $tokens)
    {
        $operation = strtoupper(key($tokens));
        if (!isset($this->methodMap[$operation])) {
            throw new \Exception('Cannot convert SQL operator to HTTP verb');
        }

        return $this->methodMap[$operation];
    }

    /**
     * 
     * @param array $tokens
     * @return string
     * @throws \Exception
     */
    private function getPath(array $tokens)
    {
        foreach ($tokens as $section) {
            if ($section['expr_type'] === 'table') {
                return $section['table'];
            }
        }
        throw new \Exception('Cannot convert table to path');
    }

    /**
     * Gets the alias for the table name
     * @param array $tokens
     * @return string
     */
    private function getAlias(array $tokens)
    {
        foreach ($tokens as $section) {
            if ($section['expr_type'] === 'table') {
                return isset($section['alias']['name']) ? $section['alias']['name'] : $section['table'];
            }
        }
        return null;
    }

    /**
     * 
     * @param array $tokens
     * @return array
     * @throws \Exception
     */
    private function getPayloadInsert(array $tokens, array $params)
    {
        $columns = null;
        foreach ($tokens as $section) {
            if ($section['expr_type'] === 'column-list') {
                $columns = array_map(function ($column) {
                    return $column['base_expr'];
                }, $section['sub_tree']);
            }
        }
        if ($columns === null) {
            throw new \Exception('Cannot find column names for payload');
        }

        $result = null;
        foreach (array_values($params) as $key => $value) {
            $result[$columns[$key]] = $value;
        }

        return $result;
    }

}
