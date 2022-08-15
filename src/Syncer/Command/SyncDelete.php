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
        
        $this->getOptions($input);

        $workspaces = $this->getWorkspacesOrExit();

        $deletedTaskIds = $this->invoiceNinjaClient->deleteTasksBetween($this->since, $this->until);
        
        $this->io->success('Deleted tasks: ' . implode(", ", $deletedTaskIds));
        
        
        
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
                if (in_array($tag->getName(), $taskTagNames)) {
                    array_push($tagIdsToBeDeleted, $tag->getId());
                }
            }

            // Delete tags
            $this->togglClient->deleteTagsById($tagIdsToBeDeleted);

            $this->io->success('[WID:' . $workspace->getId() . '] Deleted tags: ' . implode(", ", $tagIdsToBeDeleted));

        }
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

}
