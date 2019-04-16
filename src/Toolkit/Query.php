<?php

namespace Kirby\Toolkit;

/**
 * The Query class can be used to
 * query arrays and objects, including their
 * methods with a very simple string-based syntax.
 *
 * @package   Kirby Toolkit
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      http://getkirby.com
 * @copyright Bastian Allgeier
 */
class Query
{
    const REGEX_PARTS      = '!([a-zA-Z_]*(\(.*\))?)\.|\([^\(]+\((*SKIP)(*FAIL)|\"[^"]+\"(*SKIP)(*FAIL)|\'[^\']+\'(*SKIP)(*FAIL)!';
    const REGEX_METHOD     = '!\((.*)\)!';
    const REGEX_PARAMETERS = '!,|\[[^]]+\](*SKIP)(*FAIL)|\"[^"]+\"(*SKIP)(*FAIL)|\'[^\']+\'(*SKIP)(*FAIL)!';

    /**
     * The query string
     *
     * @var string
     */
    protected $query;

    /**
     * Queryable data
     *
     * @var array
     */
    protected $data;

    /**
     * Creates a new Query object
     *
     * @param string $query
     * @param array  $data
     */
    public function __construct(string $query = null, array $data = [])
    {
        $this->query = $query;
        $this->data  = $data;
    }

    /**
     * Returns the query result if anything
     * can be found. Otherwise returns null.
     *
     * @return mixed
     */
    public function result()
    {
        if (empty($this->query) === true) {
            return $this->data;
        }

        return $this->resolve($this->query);
    }

    /**
     * Resolves the query if anything
     * can be found. Otherwise returns null.
     *
     * @param string $query
     * @return mixed
     */
    protected function resolve(string $query)
    {
        $parts = $this->parts($query);
        $data  = $this->data;
        $value = null;

        while (count($parts)) {
            $part   = array_shift($parts);
            $info   = $this->info($part);
            $method = $info['method'];
            $value  = null;

            if (is_array($data)) {
                $value = $data[$method] ?? null;
            } elseif (is_object($data)) {
                if (method_exists($data, $method) || method_exists($data, '__call')) {
                    $value = $data->$method(...$info['args']);
                }
            } elseif (is_scalar($data)) {
                return $data;
            } else {
                return null;
            }

            if (is_array($value) || is_object($value)) {
                $data = $value;
            }
        }

        return $value;
    }

    /**
     * Breaks the query string down into its components
     *
     * @param  string $query
     * @return array
     */
    protected function parts(string $query): array
    {
        $query = trim($query);

        // match all parts but the last
        preg_match_all(self::REGEX_PARTS, $query, $match);

        // remove all matched parts from the query to retrieve last part
        foreach ($match[0] as $part) {
            $query = Str::after($query, $part);
        }

        array_push($match[1], $query);
        return $match[1];
    }

    /**
     * Analyzes each part of the query string and
     * extracts methods and method arguments.
     *
     * @param  string $part
     * @return array
     */
    protected function info(string $part): array
    {
        $args   = [];
        $method = preg_replace_callback(self::REGEX_METHOD, function ($match) use (&$args) {
            $args = array_map(
                'self::sanitize',
                preg_split(self::REGEX_PARAMETERS, $match[1])
            );
        }, $part);

        return [
            'method' => $method,
            'args'   => $args
        ];
    }

    /**
     * Converts a parameter of query to
     * proper type.
     *
     * @param  mixed $arg
     * @return mixed
     */
    protected function sanitize($arg)
    {
        $arg = trim($arg);

        if (substr($arg, 0, 1) === '"') {
            return trim($arg, '"');
        }

        if (substr($arg, 0, 1) === '\'') {
            return trim($arg, '\'');
        }

        switch ($arg) {
            case 'null':
                return null;
            case 'false':
                return false;
            case 'true':
                return true;
        }

        if (is_numeric($arg) === true) {
            return (float)$arg;
        }

        if (substr($arg, 0, 1) === '[' && substr($arg, -1) === ']') {
            $arg = substr($arg, 1, -1);
            return array_map(
                'self::sanitize',
                preg_split(self::REGEX_PARAMETERS, $arg)
            );
        }

        return $this->resolve($arg);
    }
}
