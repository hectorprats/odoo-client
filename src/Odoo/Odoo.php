<?php

/**
 * (c) Jacob Steringa <jacobsteringa@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spejder\Odoo;

use Laminas\Http\Client as HttpClient;
use Laminas\XmlRpc\Client as XmlRpcClient;

/**
 * Odoo is an PHP client for the xmlrpc api of Odoo, formerly known as OpenERP.
 * This client should be compatible with version 6 and up of Odoo/OpenERP.
 *
 * This client is inspired on the OpenERP api from simbigo and uses a more or
 * less similar API. Instead of an own XmlRpc class, it relies on the XmlRpc
 * and Xml libraries from ZF.
 *
 * @author  Jacob Steringa <jacobsteringa@gmail.com>
 */
class Odoo
{
    /**
     * Host to connect to
     *
     * @var string
     */
    protected $host;

    /**
     * Unique identifier for current user
     *
     * @var integer
     */
    protected $uid;

    /**
     * Current users username
     *
     * @var string
     */
    protected $user;

    /**
     * Current database
     *
     * @var string
     */
    protected $database;

    /**
     * Password for current user
     *
     * @var string
     */
    protected $password;

    /**
     * XmlRpc Client
     *
     * @var XmlRpcClient
     */
    protected $client;

    /**
     * XmlRpc endpoint
     *
     * @var string
     */
    protected $path;

    /**
     * Optional custom http client to initialize the XmlRpcClient with
     *
     * @var HttpClient|null
     */
    protected $httpClient;

    /**
     * Odoo constructor
     *
     * @param string $host The url
     * @param string $database The database to log into
     * @param string $user The username
     * @param string $password Password of the user
     * @param HttpClient $httpClient An optional custom http client to initialize the XmlRpcClient with
     */
    public function __construct(
        string $host,
        string $database,
        string $user,
        string $password,
        ?HttpClient $httpClient = null
    ) {
        $this->host = $host;
        $this->database = $database;
        $this->user = $user;
        $this->password = $password;
        $this->httpClient = $httpClient;
    }

    /**
     * Get version
     *
     * @return array<mixed> Odoo version
     */
    public function version(): array
    {
        $response = $this->getClient('common')->call('version');

        return $response;
    }

    /**
     * Get timezone
     *
     * @return string Current timezone
     */
    public function timezone(): string
    {
        $params = array(
            $this->database,
            $this->user,
            $this->password
        );

        return $this->getClient('common')->call('timezone_get', $params);
    }

    /**
     * Search models
     *
     * @param string $model Model
     * @param array<mixed> $data Array of criteria
     * @param integer $offset Offset
     * @param integer $limit Max results
     *
     * @return array<integer> Array of model id's
     */
    public function search(string $model, array $data, int $offset = 0, int $limit = 100): array
    {
        $params = $this->buildParams(array(
            $model,
            'search',
            $data,
            $offset,
            $limit
        ));

        $response = $this->getClient('object')->call('execute', $params);

        return $response;
    }

    /**
     * Create model
     *
     * @param string $model Model
     * @param array<mixed> $data Array of fields with data (format: ['field' => 'value'])
     *
     * @return integer Created model id
     */
    public function create(string $model, array $data): int
    {
        $params = $this->buildParams(array(
            $model,
            'create',
            $data
        ));

        $response = $this->getClient('object')->call('execute', $params);

        return $response;
    }

    /**
     * Read model(s)
     *
     * @param string $model  Model
     * @param array<int> $ids Array of model id's
     * @param array<mixed> $fields Index array of fields to fetch, an empty array fetches all fields
     *
     * @return array<mixed> An array of models
     */
    public function read(string $model, array $ids, array $fields = array()): array
    {
        $params = $this->buildParams(array(
            $model,
            'read',
            $ids,
            $fields
        ));

        $response = $this->getClient('object')->call('execute', $params);

        return $response;
    }

    /**
     * Search_read model(s)
     *
     * @param string $model Model
     * @param array<mixed> $fields Index array of fields to fetch, an empty array fetches all fields
     * @param array<mixed> $data Array of criteria
     * @param integer $offset Offset
     * @param integer $limit Max results
     *
     * @return array<mixed> An array of models
     */
    public function searchRead(
        string $model,
        array $data = array(),
        array $fields = array(),
        int $offset = 0,
        int $limit = 100
    ): array {
        $params = $this->buildParams(array(
            $model,
            'search_read',
            $data,
            $fields,
            $offset,
            $limit
        ));

        $response = $this->getClient('object')->call('execute', $params);

        return $response;
    }

    /**
     * Update model(s)
     *
     * @param string $model Model
     * @param array<int> $ids Array of model id's
     * @param array<mixed> $fields A associative array (format: ['field' => 'value'])
     *
     * @return array<mixed>
     */
    public function write(string $model, array $ids, array $fields): array
    {
        $params = $this->buildParams(array(
            $model,
            'write',
            $ids,
            $fields
        ));

        $response = $this->getClient('object')->call('execute', $params);

        return $response;
    }

    /**
     * Unlink model(s)
     *
     * @param string $model Model
     * @param array<int> $ids Array of model id's
     *
     * @return boolean True is successful
     */
    public function unlink(string $model, array $ids): bool
    {
        $params = $this->buildParams(array(
            $model,
            'unlink',
            $ids
        ));

        return $this->getClient('object')->call('execute', $params);
    }

    /**
     * Get report for model
     *
     * @param string $model Model
     * @param array<int> $ids Array of id's, for this method it should typically be an array with one id
     * @param string $type Report type
     *
     * @return mixed A report file
     */
    public function getReport(string $model, array $ids, string $type = 'qweb-pdf'): mixed
    {
        $params = $this->buildParams(array(
            $model,
            $ids,
            array(
                'model' => $model,
                'id' => $ids[0],
                'report_type' => $type
            )
        ));

        $client = $this->getClient('report');

        $reportId = $client->call('report', $params);

        $state = false;

        while (!$state) {
            $report = $client->call(
                'report_get',
                $this->buildParams(array($reportId))
            );

            $state = $report['state'];

            if (!$state) {
                sleep(1);
            }
        }

        return base64_decode($report['result']);
    }

    /**
     * Return last request
     *
     * @return string
     */
    public function getLastRequest(): string
    {
        return $this->getClient()->getLastRequest();
    }

    /**
     * Return last response
     *
     * @return string
     */
    public function getLastResponse(): string
    {
        return $this->getClient()->getLastResponse();
    }

    /**
     * Set custom http client
     *
     * @param HttpClient $httpClient
     */
    public function setHttpClient(HttpClient $httpClient): void
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Build parameters
     *
     * @param array<mixed> $params Array of params to append to the basic params
     *
     * @return array<mixed>
     */
    protected function buildParams(array $params): array
    {
        return array_merge(array(
            $this->database,
            $this->uid(),
            $this->password
        ), $params);
    }

    /**
     * Get XmlRpc Client
     *
     * This method returns an XmlRpc Client for the requested endpoint.
     * If no endpoint is specified or if a client for the requested endpoint is
     * already initialized, the last used client will be returned.
     *
     * @param null|string $path The api endpoint
     *
     * @return XmlRpcClient
     */
    protected function getClient(?string $path = null): XmlRpcClient
    {
        if ($path === null) {
            return $this->client;
        }

        if ($this->path === $path) {
            return $this->client;
        }

        $this->path = $path;

        $this->client = new XmlRpcClient($this->host . '/' . $path, $this->httpClient);

        // The introspection done by the Laminas XmlRpc client is probably specific
        // to Laminas XmlRpc servers. To prevent polution of the Odoo logs with errors
        // resulting from this introspection calls we disable it.
        $this->client->setSkipSystemLookup(true);

        return $this->client;
    }

    /**
     * Get uid
     *
     * @return int $uid
     */
    protected function uid(): int
    {
        if ($this->uid === null) {
            $client = $this->getClient('common');

            $this->uid = $client->call('login', array(
                $this->database,
                $this->user,
                $this->password
            ));
        }

        return $this->uid;
    }
}
