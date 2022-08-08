<?php declare(strict_types=1);

namespace Syncer\Toggl;

use Carbon\Carbon;
use GuzzleHttp\Client;
use JMS\Serializer\SerializerInterface;
use Syncer\Dto\Toggl\DetailedReport;
use DateTime;

/**
 * Class ReportsClient
 * @package Syncer\Toggl
 *
 * @author Matthieu Calie <matthieu@calie.be>
 */
class ReportsClient
{
    const VERSION = 'v2';

    /**
     * @var Client;
     */
    private $client;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var string
     */
    private $api_key;

    /**
     * TogglClient constructor.
     * @param Client $client
     * @param SerializerInterface $serializer
     * @param $api_key
     */
    public function __construct(Client $client, SerializerInterface $serializer, $api_key)
    {
        $this->client = $client;
        $this->serializer = $serializer;
        $this->api_key = $api_key;
    }

    /**
     * Get detailed report from since yesterday
     *
     * @param int $workspaceId
     * @return DetailedReport
     */
    public function getDetailedReport(int $workspaceId, DateTime $since, DateTime $until)
    {
        $response = $this->client->request('GET', self::VERSION . '/details', [
            'auth' => [$this->api_key, 'api_token'],
            'query' => [
                'user_agent' => 'info@programmierschmiede.de',
                'workspace_id' => $workspaceId,
                'since' => $since->format('Y-m-d'),
                'until' => $until->format('Y-m-d')
            ]
        ]);

        $responseBody = $response->getBody();
        $detailedResponse = $this->serializer->deserialize($responseBody, DetailedReport::class, 'json');
        return $detailedResponse;
    }
}
