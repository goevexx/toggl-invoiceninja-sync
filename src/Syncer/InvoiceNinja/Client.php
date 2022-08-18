<?php declare(strict_types=1);

namespace Syncer\InvoiceNinja;

use GuzzleHttp\Client as GuzzleClient;
use JMS\Serializer\SerializerInterface;
use Syncer\Dto\InvoiceNinja\Task;
use Syncer\Dto\InvoiceNinja\PostTaskResponse;
use Syncer\Dto\InvoiceNinja\GetTasksResponse;
use Syncer\Dto\InvoiceNinja\GetTaskResponse;
use DateInterval;
use PhpSpec\Exception\Exception;

define('INVOICENINJA_TASK_REF_LABEL', 'IN Task: ');

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
     * Create ref label for a task
     *
     * @param string $taskId 
     * @return string ref label string
     **/
    public static function createTaskRefLabel(string $taskId): string
    {
        return INVOICENINJA_TASK_REF_LABEL . $taskId;
    }

    /**
     * Get ref label regexp for a task
     * 
     * $1 match is task id
     *
     * @return string ref label regexp pattern
     **/
    public static function getTaskRefLabelRegexp(): string
    {
        return '/^'.INVOICENINJA_TASK_REF_LABEL.'(\w+)$/';
    }

    /**
     * Gets a task by id
     *
     * @param string $taskId 
     * @return Task|null
     **/
    public function getTask(string $taskId): Task
    {
        $response = $this->client->request('GET', self::VERSION . '/tasks/'. $taskId, [
            'allow_redirects' => ['strict'=>true],
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Token' => $this->api_token,
                'X-Requested-With' => 'XMLHttpRequest',
            ],
            'query' => [
                'is_deleted' => false,
            ]
        ]);

        if($response->getStatusCode() <> 200){
            throw new Exception('Fehler beim holen von Task ' . $taskId);
        }
        $getTaskResponse = $this->serializer->deserialize($response->getBody(), GetTaskResponse::class, 'json');
        return $getTaskResponse->getData();
    }

    /**
     * @param Task $task
     *
     * @return Task
     */
    public function createTask(Task $task)
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

    /**
     * Updates a task
     *
     * @param Task $task 
     * @return Task
     **/
    public function updateTask(Task $task)
    {
        $data = $this->serializer->serialize($task, 'json');

        $response = $this->client->request('PUT', self::VERSION . '/tasks/' . $task->getId(), [
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

    /**
     * @param string $task
     *
     * @return bool success
     */
    public function deleteTask(string $taskId): bool
    {

        $response = $this->client->request('DELETE', self::VERSION . '/tasks/' . $taskId, [
            'allow_redirects' => ['strict'=>true],
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Token' => $this->api_token,
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        ]);

        return $response->getStatusCode() == 200;
    }


    /**
     * Gets all tasks
     * 
     * Get all tasks, which are not deleted, recursive
     * 
     * @return Task[]
     * 
     **/
    public function getAllTasks(array $tasks = [], int $currentPage = 1): array
    {
        $res = $this->client->request('GET', self::VERSION . '/tasks', [
            'allow_redirects' => ['strict'=>true],
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Token' => $this->api_token,
                'X-Requested-With' => 'XMLHttpRequest',
            ],
            'query' => [
                'page' => $currentPage,
                'is_deleted' => false,
            ]
        ]);

        // Deserialize
        $responseBody = $res->getBody();
        $getTasksResponse = $this->serializer->deserialize($responseBody, GetTasksResponse::class, 'json');

        // Add tasks together
        $currentTasks = $getTasksResponse->getData();
        $mergedTasks = array_merge($tasks, $currentTasks);

        // Break recursion, if max page is reached
        $totalPages = $getTasksResponse->getMeta()->getPagination()->getTotalPages();
        if($currentPage == $totalPages){
            return $mergedTasks;
        }

        // Recurse with incremented page
        return $this->getAllTasks($mergedTasks, ++$currentPage);
    }

    /**
     * Deletes all tasks in timespan
     *
     * Deletes all tasks which have start time log between $since and $until.
     * Returns an array of deleted task ids.
     * Returns null if an error deleting a task occured
     *
     * @param \DateTime $since 
     * @param \DateTime $until 
     * @return string[]|null
     **/
    public function deleteTasksBetween(\DateTime $since,\DateTime $until): array|null
    {
        $aDayInterval = new DateInterval('P1D');

        $tasks = $this->getAllTasks();

        $deletedIds = [];
        foreach ($tasks as $task) {
            $timelogs = $task->getTimeLogDateTime();

            $deleteTask = false;
            foreach ($timelogs as $timelog ) {
                $startTime = $timelog[0];
                $untilsNextDay = $until->add($aDayInterval);
                if($startTime >= $since && $startTime < $untilsNextDay){
                    $deleteTask = true;
                    break;
                }
            }

            if($deleteTask){
                $success = $this->deleteTask($task->getId());
                if($success){
                    array_push($deletedIds, $task->getId());
                } else {
                    return null;
                }
            }
        }

        return $deletedIds;
    }
}
