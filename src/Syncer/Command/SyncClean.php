<?php declare(strict_types=1);

namespace Syncer\Command;

use Syncer\Dto\InvoiceNinja\Task;
use Syncer\Dto\Toggl\TimeEntry;
use Syncer\InvoiceNinja\Client as InvoiceNinjaClient;
use Syncer\Toggl\ReportsClient;
use Syncer\Toggl\TogglClient;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use DateTimeZone;
use DateTime;
use DateInterval;

/**
 * Class SyncTimings
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

        $workspaces = $this->getWorkspacesOrExit();
        foreach ($workspaces as $workspace) {
            $allTags = $this->togglClient->getAllTags($workspace->getId());

            $tagIdsToBeDeleted = [];
            foreach ($allTags as $tag) {
                $taskRefMatchedCount = preg_match_all(InvoiceNinjaClient::getTaskRefLabelRegexp(), $tag->getName(), $taskRefMatch);
                if ($taskRefMatchedCount == 1) {
                    $taskId = $taskRefMatch[1][0];
                    $task = $this->invoiceNinjaClient->getTask($taskId);
                    if (!isset($task) || $task->getDeleted()) {
                        array_push($tagIdsToBeDeleted, $tag->getId());
                    }
                }
            }

            // Delete tags
            $deltedTagIds = $this->togglClient->deleteTagsById($tagIdsToBeDeleted);

            if(count($deltedTagIds) == 0){
                $this->io->success('[WID:' . $workspace->getId() . '] No tags deleted');
            } else {
                $this->io->success('[WID:' . $workspace->getId() . '] Deleted tags: ' . implode(", ", $deltedTagIds));
            }

        }
        
        return 0;
    }


}
