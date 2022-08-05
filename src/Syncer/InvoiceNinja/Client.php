<?php declare(strict_types=1);

namespace Syncer\InvoiceNinja;

use GuzzleHttp\Client as GuzzleClient;
use JMS\Serializer\SerializerInterface;
use Syncer\Dto\InvoiceNinja\Task;
use Syncer\Dto\InvoiceNinja\PostTaskResponse;

/**
 * Class Client
 * @package Syncer\InvoiceNinja
 *
 * @author Matthieu Calie <matthieu@calie.be>
 */
class Client
{
    const VERSION = 'v1';

    /**
     * @var GuzzleClient
     */
    private $client;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var string
     */
    private $api_token;

    /**
     * Client constructor.
     *
     * @param GuzzleClient $client
     * @param SerializerInterface $serializer
     * @param $api_token
     */
    public function __construct(GuzzleClient $client, SerializerInterface $serializer, $api_token)
    {
        $this->client = $client;
        $this->serializer = $serializer;
        $this->api_token = $api_token;
    }

    /**
     * @param Task $task
     *
     * @return Task
     */
    public function saveNewTask(Task $task)
    {
        $data = $this->serializer->serialize($task, 'json');

        $response = $this->client->request('POST', self::VERSION . '/tasks', [
            'allow_redirects' => ['strict'=>true],
            'body' => $data,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Token' => $this->api_token,
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        ]);

        $responseBody = $response->getBody();
        $postTaskResponse = $this->serializer->deserialize($responseBody, PostTaskResponse::class, 'json');
        return $postTaskResponse->getData();
    }
}
