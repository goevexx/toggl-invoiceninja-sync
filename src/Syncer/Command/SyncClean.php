<?php 
declare(strict_types=1);

namespace Syncer\Command;

use Syncer\InvoiceNinja\Client as InvoiceNinjaClient;
use Syncer\Toggl\ReportsClient;
use Syncer\Toggl\TogglClient;
use \Syncer\Dto\Toggl\Tag;
use \Syncer\Dto\Toggl\Workspace;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class SyncClean
 * @package Syncer\Command
 *
 * @author Nicolas Morawietz <nmorawietz@programmierschmiede.de>
 */
class SyncClean extends Command
{
    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var TogglClient
     */
    private $togglClient;

    /**
     * @var ReportsClient
     */
    private $reportsClient;

    /**
     * @var InvoiceNinjaClient
     */
    private $invoiceNinjaClient;

    /**
     * SyncTimings constructor.
     *
     * @param TogglClient $togglClient
     * @param ReportsClient $reportsClient
     * @param InvoiceNinjaClient $invoiceNinjaClient
     * @param array $clients
     * @param array $projects
     */
    public function __construct(
        TogglClient $togglClient,
        ReportsClient $reportsClient,
        InvoiceNinjaClient $invoiceNinjaClient
    ) {
        $this->togglClient = $togglClient;
        $this->reportsClient = $reportsClient;
        $this->invoiceNinjaClient = $invoiceNinjaClient;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('sync:clean')
            ->setDescription('Cleans up unreferenced toggl tags') 
            ;
    }

    /**
     * Gets workspaces or else exits
     *
     * @return \Syncer\Dto\Toggl\Workspace[]
     **/
    public function getWorkspacesOrExit(): array
    {
        $workspaces = $this->togglClient->getWorkspaces();

        if (!is_array($workspaces) || count($workspaces) === 0) {
            $this->io->error('No workspaces to sync.');

            exit(1);
        }

        return $workspaces;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->note('Start cleaning up...');

        $workspaces = $this->getWorkspacesOrExit();
        foreach ($workspaces as $workspace) {
            // Get tags which dont have a task counterpart
            $tagsToBeDeleted = $this->getTagsNotFoundInTasks($workspace);
            
            // Delete tags
            $this->handleDeleteTags($workspace, $tagsToBeDeleted);
        }

        $this->io->note('Cleaning up finished.');

        return 0;
    }

    /**
     * Handles tag deletion
     *
     * @param Workspace $workspace
     * @param array<Tag> $tagsToBeDeleted
     **/
    public function handleDeleteTags(Workspace $workspace, array $tagsToBeDeleted)
    {
        $deltedTagIds = $this->togglClient->deleteTags($tagsToBeDeleted);

        if(count($deltedTagIds) == 0){
            $this->io->success($workspace . 'No tags deleted');
        } else {
            $this->io->success($workspace . 'Successfully deleted tags: ' . implode(", ", $deltedTagIds));
        }
    }

    /**
     * Returns those tag ids, which do not belong to a task
     *
     * @param Workspace $workspace
     * @return array<Tag>
     **/
    public function getTagsNotFoundInTasks(Workspace $workspace): array
    {
        // Get all tags
        $allTags = $this->togglClient->getAllTags($workspace->getId());

        // Add tag to be deleted when corresponding task cannot be found or is deleted
        $tagsToBeDeleted = [];
        foreach ($allTags as $tag) {
            $taskRefMatchedCount = preg_match_all(InvoiceNinjaClient::getTaskRefLabelRegexp(), $tag->getName(), $taskRefMatch);
            if ($taskRefMatchedCount == 1) {
                $taskId = $taskRefMatch[1][0];
                $task = $this->invoiceNinjaClient->getTask($taskId);
                if (!isset($task) || $task->getDeleted()) {
                    array_push($tagsToBeDeleted, $tag);
                }
            }
        }

        return $tagsToBeDeleted;
    }
}