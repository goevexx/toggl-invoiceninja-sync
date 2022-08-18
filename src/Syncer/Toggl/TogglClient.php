<?php declare(strict_types=1);

namespace Syncer\Toggl;

use GuzzleHttp\Client;
use JMS\Serializer\SerializerInterface;
use Syncer\Dto\Toggl\Workspace;
use Syncer\Dto\Toggl\TimeEntry;
use Syncer\Dto\Toggl\PutTimeEntryResponse;
use Syncer\Dto\Toggl\GetTagsResponse;
use Syncer\Dto\Toggl\Tag;
use PhpSpec\Exception\Exception;

/**
 * Class TogglClient
 * @package Syncer\Toggl
 *
 * @author Matthieu Calie <matthieu@calie.be>
 */
class TogglClient
{
    const VERSION = 'v8';

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
     * Get's all workspaces accessible by the api token
     * 
     * @return array|Workspace[]
     */
    public function getWorkspaces()
    {
        $response = $this->client->request('GET', self::VERSION . '/workspaces', [
            'auth' => [$this->api_key, 'api_token'],
        ]);

        return $this->serializer->deserialize($response->getBody(), 'array<Syncer\Dto\Toggl\Workspace>', 'json');
    }

    /**
     * Updates time entry
     *
     *
     * @param TimeEntry $entry 
     * @return TimeEntry
     **/
    public function updateTimeEntry(TimeEntry $entry): TimeEntry
    {
        $data = $this->serializer->serialize($entry, 'json');
        
        $response = $this->client->request('PUT', self::VERSION . '/time_entries/' . $entry->getId(), [
            'auth' => [$this->api_key, 'api_token'],
            'body' => '{"time_entry": ' . $data . '}'
        ]);

        $putTimeEntryResponse = $this->serializer->deserialize($response->getBody(), PutTimeEntryResponse::class, 'json');
        return $putTimeEntryResponse->getData();
    
    }


    /**
     * Deletes a tag in toggl
     *
     * @param int $tagId
     * @return bool
     **/
    public function deleteTag(int $tagId): bool
    {
        $response = $this->client->request('DELETE', self::VERSION . '/tags/' . $tagId, [
            'auth' => [$this->api_key, 'api_token'],
        ]);

        return $response->getStatusCode() == 200;
    }

    /**
     * Get all tags
     *
     * @param int $workspaceId
     * @return Tag[]
     **/
    public function getAllTags(int $workspaceId)
    {
        $response = $this->client->request('GET', self::VERSION . '/workspaces/' . $workspaceId . '/tags', [
            'auth' => [$this->api_key, 'api_token'],
        ]);

        if($response->getStatusCode()<>200){
            throw new Exception('Get Tags StatusCode = ' . $response->getStatusCode());
        }

        try {
            $tags = $this->serializer->deserialize($response->getBody(), 'array<'.Tag::class.'>' , 'json');
        } catch(\RuntimeException $e) {
            if ($e->getMessage() == 'Expected array, but got NULL: null') {
                return [];
            }
        }
        
        return $tags;
    }

    /**
     * Deletes bulk of tags by id
     *
     * @param string[] $tagIds 
     * @param int   $deletePauseMikro    Mikrosecodns paused after delete execution
     * @return array|null
     **/
    public function deleteTagsById(array $tagIds, int $deletePauseMikro = 250000): array|null
    {
        $deletedTags = [];
        foreach($tagIds as $tagId){
            if (!$this->deleteTag($tagId)){
                return null;
            } else {
                array_push($deletedTags, $tagId);
            }
            usleep($deletePauseMikro);
        }

        return $deletedTags;
    }
}
