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

define('INVOICENINJA_REF_LABEL', 'IN Task: ');

/**
 * Class SyncTimings
 * @package Syncer\Command
 *
 * @author Matthieu Calie <matthieu@calie.be>
 */
class SyncTimings extends Command
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
     * @var array
     */
    private $clients;

    /**
     * @var array
     */
    private $projects;

    /** 
     * @var int $roundingMinutes 
     * 
     * Says up to how many minutes the duration of one time log 
     * should be rounded and end time adapted for it to fit.
     * 
     * default = 0 <=> disabled
     *
     * */
    private $roundingMinutes = 0;

    /** @var bool $billableOnly Whether only billable entries should be logged */
    private $billableOnly;  

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
        InvoiceNinjaClient $invoiceNinjaClient,
        $clients,
        $projects,
        $roundtimings,
        $billableOnly
    ) {
        $this->togglClient = $togglClient;
        $this->reportsClient = $reportsClient;
        $this->invoiceNinjaClient = $invoiceNinjaClient;
        $this->clients = $clients;
        $this->projects = $projects;
        $this->roundingMinutes = $roundtimings;
        $this->billableOnly = $billableOnly;

        parent::__construct();
    }

    // TODO: Add Logging
    // TODO: Add since - until functionality for time entry snyc
    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('sync:timings')
            ->setDescription('Syncs timings from toggl to invoiceninja')
            // ->addOption('since', 's', InputOption::VALUE_OPTIONAL, 'NO FUNCTION -- Date from which timings get synced')
            // ->addOption('until', 'u', InputOption::VALUE_OPTIONAL, 'NO FUNCTION -- Date to which timings get synced (including)')
            // ->addOption('round', 'r', InputOption::VALUE_OPTIONAL, 'Minutes a time log\'s duration is rounded to and the end time is be adaptet to')
            // ->addOption('billable-only', 'b', InputOption::VALUE_OPTIONAL, 'Transfer only billable timelogs')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        
        // $this->setArguments($input);

        $workspaces = $this->getWorkspacesOrExit();

        foreach ($workspaces as $workspace) {
            $detailedReport = $this->reportsClient->getDetailedReport($workspace->getId());

            $timeEntries = $detailedReport->getData();
            $logFilteredTimeEntries = $this->filterNotYetLoggedTimeEntries($timeEntries);

            foreach($logFilteredTimeEntries as $timeEntry) {
                if(!$this->billableOnly | ($this->billableOnly && $timeEntry->isBillable())){
                    $timeEntrySent = false;

                    // Log the entry if the client key exists
                    if ($this->timeEntryCanBeLoggedByConfig($this->clients, $timeEntry->getClient(), $timeEntrySent)) {
                        $this->logTask($timeEntry);

                        $timeEntrySent = true;
                    }

                    // Log the entry if the project key exists
                    if ($this->timeEntryCanBeLoggedByConfig($this->projects, $timeEntry->getProject(), $timeEntrySent)) {
                        $this->logTask($timeEntry);

                        $timeEntrySent = true;
                    }

                    if ($timeEntrySent) {
                        $this->io->success('TimeEntry ('. $timeEntry->getDescription() . ') sent to InvoiceNinja');
                    }
                }
            }
        }
        return 0;
    }

    /**
     * Filters not yet logged time entries
     *
     * Searches time entry's tags for invoice ninja task id 
     *
     * @param TimeEntry[] $entries 
     * @return TimeEntry[]
     **/
    public function filterNotYetLoggedTimeEntries(array $entries): array
    {
        $filteredEntries = array_filter($entries, function(TimeEntry $entry){
            $invoiceNinjaTaskPattern = '/^'.INVOICENINJA_REF_LABEL.'(\w+)$/';
            $invoiceNinjaTaskTags = preg_grep($invoiceNinjaTaskPattern, $entry->getTags());
            if(count($invoiceNinjaTaskTags) > 0){
                return false;
            }
            return true;
        });
        return $filteredEntries;
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
     * Sets console arguments
     *
     * @param InputInterface $input Argument enclosing object
     * @return void
     **/
    private function setArguments(InputInterface $input)
    {
        $roundArg = $input->getArgument('round');
        if(isset($roundArg) && is_int($roundArg)){
            $this->roundingMinutes = $roundArg;
        } 


        $billArg = $input->getArgument('billable-only');
        if(isset($billArg)){
            $this->billableOnly = $billArg;
        } 
    }

    /**
     * @param array $config
     * @param string $entryKey
     * @param bool $hasAlreadyBeenSent
     *
     * @return bool
     */
    private function timeEntryCanBeLoggedByConfig(array $config, string $entryKey, bool $hasAlreadyBeenSent): bool
    {
        if ($hasAlreadyBeenSent) {
            return false;
        }

        return (is_array($config) && array_key_exists($entryKey, $config));
    }

    /**
     * Logs task and refs time entry
     * 
     * @param TimeEntry $entry
     *
     */
    private function logTask(TimeEntry $entry)
    {
        $task = $this->createTask($entry);
        $this->refTimeEntry($entry, $task);
    }

    /**
     * Refs time entry to task
     *
     * Adds Task to time entry with given task id
     *
     * @param TimeEntry $entry
     * @param Task $task
     * 
     * @return TimeEntry
     **/
    public function refTimeEntry(TimeEntry $entry, Task $task): TimeEntry
    {
        $tags = $entry->getTags();
        array_push($tags, INVOICENINJA_REF_LABEL . $task->getId());
        $newEntry = new TimeEntry();
        $newEntry->setId($entry->getId());
        $newEntry->setTags($tags);

        return $this->togglClient->updateTimeEntry($newEntry);
    }

        /**
     * @param TimeEntry $entry
     *
     * @return Task
     */
    private function createTask(TimeEntry $entry): Task
    {
        $task = new Task();

        $task->setDescription($this->buildTaskDescription($entry));
        $task->setTimeLog($this->buildTimeLog($entry));
        $task->setClientId($this->clients[$entry->getClient()]);
        $task->setProjectId($this->projects[$entry->getProject()]);
        $task->setTogglId($entry->getId());

        $newTask = $this->invoiceNinjaClient->saveNewTask($task);
        return $newTask;
    }

    /**
     * @param TimeEntry $entry
     *
     * @return string
     */
    private function buildTaskDescription(TimeEntry $entry): string
    {

        $description = $entry->getDescription();

        return $description;
    }

    /**
     * @param TimeEntry $entry
     *
     * @return string
     */
    private function buildTimeLog(TimeEntry $entry): string
    {
        $start = $entry->getStart();
        $end = $this->maybeExtendDuration($start, $entry->getEnd());

        $timeLog = [[
            $start->getTimestamp(),
            $end->getTimestamp(),
        ]];

        return \GuzzleHttp\json_encode($timeLog);
    }

    /**
     * Extends duration to a multiple of $roundingMinutes
     * 
     * Rounds duration between $start and $end up to $roundingMinutes 
     * and returns $start including the rounded duration added
     *
     * @param \DateTime $start Duration start time
     * @param \DateTime $end Duration end time
     * @return \DateTime $start + rounded duration
     **/
    public function maybeExtendDuration(\DateTime $start, \DateTime $end): \DateTime
    {
        $startClone = clone $start;
        $duration  = $startClone->diff($end);
        if ($this->roundingMinutes !== 0){
            $duration = $this::roundDateIntervalMinutes($duration, $this->roundingMinutes);
        }
        return $startClone->add($duration);
    }

    /**
     * Rounds interval minutes
     *
     * @param \DateInterval $interval Interval to be rounded
     * @param \DateInterval $roundToMinutes Minutes to be rounded to
     * @return \DateInterval
     **/
    private static function roundDateIntervalMinutes(\DateInterval $interval, int $roundToMinutes)
    {
            // Take date parts off duration
            $partialDaysStr = $interval->format('%d');
            $partialHoursStr = $interval->format('%h');
            $partialMinutesStr = $interval->format('%i');

            $partialDays = intval($partialDaysStr);
            $partialHours =   intval($partialHoursStr);
            $partialMinutes = intval($partialMinutesStr);

            // Round minutes
            $minutes = $partialDays * 24 * 60 + $partialHours * 60 + $partialMinutes;
            $roundedMinutes = ceil($minutes / $roundToMinutes) * $roundToMinutes;

            // Create date parts from rounded minutes
            $roundedPartialDays = floor($roundedMinutes / 60 / 24);
            $roundedMinutesWithoutRoundedPartialDays = $roundedMinutes - ($roundedPartialDays * 24 * 60);
            $roundedPartialHours = floor(($roundedMinutesWithoutRoundedPartialDays) / 60);
            $roundedPartialMinutes = $roundedMinutes - ($roundedPartialHours * 60);

            // Merge date parts
            $intervalFormat = 'PT';
            if($roundedPartialDays != 0){
                $intervalFormat .= $roundedPartialDays.'D';
            }
            if($roundedPartialHours != 0){
                $intervalFormat .= $roundedPartialHours.'H';
            }
            if($roundedPartialMinutes != 0){
                $intervalFormat .= $roundedPartialMinutes.'M';
            }
            $roundedDuration = new \DateInterval($intervalFormat);

            return $roundedDuration;
    }
}
