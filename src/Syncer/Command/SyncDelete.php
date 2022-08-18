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
class SyncDelete extends Command
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

    /** @var \DateTime $since Since when time entries got to be synced */
    protected $since;

    /** @var \DateTime $until Until when time entries got to be synced */
    protected $until;

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
            ->setName('sync:delete')
            ->setDescription('Deletes tasks in invoiceninja and removes references in toggl')
            ->addOption(OPTION_SINCE, OPTION_SINCE_SHORT, InputOption::VALUE_REQUIRED, 'Date from which timings get synced (See https://www.php.net/manual/de/datetime.formats.date.php)')
            ->addOption(OPTION_UNTIL, OPTION_UNTIL_SHORT, InputOption::VALUE_REQUIRED, 'Date to which timings get synced (including this day) (See https://www.php.net/manual/de/datetime.formats.date.php)')
            ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        
        $this->getOptions($input);

        $this->io->note('Start deleting...');

        $this->io->note('Delete tasks...');
        // Delete tasks
        $deletedTaskIds = $this->handleTasksDelete();
        
        
        $this->io->note('Remove referenced tags...');
        // Dereference time entries
        $this->handleDereferenceTasks($deletedTaskIds);
        $this->io->note('Deleting finished.');

        return 0;
    }

    /**
     * Sets console arguments
     *
     * @param InputInterface $input Argument enclosing object
     * @return void
     **/
    private function getOptions(InputInterface $input)
    {
        $timezone = new DateTimeZone(TIMEZONE);

        $sinceOption = $input->getOption('since');
        if(isset($sinceOption) && $sinceTime = new DateTime($sinceOption, $timezone)){
            $this->since = $sinceTime;
        }

        $untilOption = $input->getOption('until');
        if(isset($untilOption) && $untilTime = new DateTime($untilOption, $timezone)){
            $this->until = $untilTime;
        } 
    }

    /**
     * Deletes tasks in time range
     *
     * @return string[]
     **/
    private function handleTasksDelete(): array
    {
        $deletedTaskIds = $this->invoiceNinjaClient->deleteTasksBetween($this->since, $this->until);
        if(isset($deletedTaskIds)){
            $this->io->success('Successfully deleted tasks: ' . implode(", ", $deletedTaskIds));
        } else {
            $this->io->error('Error occured deleting tasks between ' . $this->since->format('d.m.Y') . ' and ' . $this->until->format('d.m.Y'));
        }

        return $deletedTaskIds;
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
     * Dereferences tasks by deleting tags
     *
     * @param string[] $deletedTaskIds 
     **/
    private function handleDereferenceTasks(array $deletedTaskIds)
    {
        $workspaces = $this->getWorkspacesOrExit();
        foreach ($workspaces as $workspace) {
            // Convert task ids to referenced tag names
            $taskTagNames = [];
            foreach ($deletedTaskIds as $deletedTaskId) {
                array_push($taskTagNames, InvoiceNinjaClient::createTaskRefLabel($deletedTaskId));
            }

            // Get tag ids from name matching tags
            $allTags = $this->togglClient->getAllTags($workspace->getId());
            $tagIdsToBeDeleted = [];
            foreach ($allTags as $tag) {
                $tagName = $tag->getName();
                if (in_array($tagName, $taskTagNames)) {
                    $taskTagNamesKey = array_search($tagName, $taskTagNames);
                    array_push($tagIdsToBeDeleted, $tag->getId());
                    unset($taskTagNames[$taskTagNamesKey]);
                } 
            }
            if(count($taskTagNames) > 0){
                $this->io->comment($workspace . 'Following tags cannot be removed as they don\'t exist: ' . implode(', ',$taskTagNames));
            }

            // Delete tags
            $deletedTagIds = $this->togglClient->deleteTagsById($tagIdsToBeDeleted);

            if(!isset($deletedTagIds)){
                $this->io->error($workspace . 'Error deleting tags.');
            }

            if(count($deletedTagIds) == 0){
                $this->io->comment($workspace . 'No tags deleted.');
            } else if (count($deletedTagIds) > 0){
                $this->io->success($workspace . 'Successfully deleted tags: ' . implode(", ", $deletedTagIds));
            }
        }
    }
}
