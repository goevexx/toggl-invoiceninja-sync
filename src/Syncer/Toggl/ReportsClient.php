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
     * Get detailed report
     * 
     * Gets a detailed report of time entries between $since and $until.
     * The detailed report limits entries to 50, so it might need a page input. 
     *
     * @param int $workspaceId
     * @return DetailedReport
     */
    public function getDetailedReport(int $workspaceId, DateTime $since, DateTime $until, int $page = 1)
    {
        $response = $this->client->request('GET', self::VERSION . '/details', [
            'auth' => [$this->api_key, 'api_token'],
            'query' => [
                'user_agent' => 'info@programmierschmiede.de',
                'workspace_id' => $workspaceId,
                'since' => $since->format('Y-m-d'),
                'until' => $until->format('Y-m-d'),
                'page'  => $page
            ]
        ]);

        $responseBody = $response->getBody();
        $detailedResponse = $this->serializer->deserialize($responseBody, DetailedReport::class, 'json');

        return $detailedResponse;
    }

    /**
     * Get TimeEntries
     *
     * Collects all TimeEntries from DetailedReports between the given timespan.
     * To achieve this all pages from the report need to be requested.
     *
     * @param String $workspaceId
     * @param DateTime $since
     * @param DateTime $until
     * @return \Syncer\Dto\Toggl\TimeEntry[]
     **/
    public function getTimeEntries(int $workspaceId, DateTime $since, DateTime $until): array
    {
        $initialReport = $this->getDetailedReport($workspaceId, $since, $until);
        $entriesPerPage = $initialReport->getPerPage();
        $totalEntryCount = $initialReport->getTotalCount();
        $maximumPage = ceil($totalEntryCount / $entriesPerPage);

        // Skip page 1, which was already requested
        $timeEntries = $initialReport->getData();
        for($page = 2 ; $page <= $maximumPage ; $page++){
            $currentReport = $this->getDetailedReport($workspaceId, $since, $until, $page);
            $currentEntries = $currentReport->getData();
            $timeEntries = array_merge($timeEntries, $currentEntries);
        }

        return $timeEntries;
    }
}
