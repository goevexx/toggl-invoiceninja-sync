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

define('OPTION_SINCE', 'since');
define('OPTION_SINCE_SHORT', 's');
define('OPTION_UNTIL', 'until');
define('OPTION_UNTIL_SHORT', 'u');
define('OPTION_BILLABLE', 'billable-only');
define('OPTION_BILLABLE_SHORT', 'b');
define('OPTION_ROUND', 'round');
define('OPTION_ROUND_SHORT', 'r');

define('TIMEZONE', 'Europe/Berlin');

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

    /** @var array $users  */
    private $users;

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

    /** @var \DateTime $since Since when time entries got to be synced */
    private $since;

    /** @var \DateTime $until Until when time entries got to be synced */
    private $until;

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
        $users,
        $roundtimings,
        $billableOnly
    ) {
        $this->togglClient = $togglClient;
        $this->reportsClient = $reportsClient;
        $this->users = $users;
        $this->invoiceNinjaClient = $invoiceNinjaClient;
        $this->clients = $clients;
        $this->projects = $projects;
        $this->roundingMinutes = $roundtimings;
        $this->billableOnly = $billableOnly;

        parent::__construct();
    }

    // TODO: Add Logging
    /**
     * Configure the command
     */
    protected function configure()
    {
        $timezone = new DateTimeZone(TIMEZONE);
        $date7daysAgo = (new DateTime('7 days ago', $timezone));
        $dateNow = (new DateTime('now', $timezone));
        $this
            ->setName('sync:timings')
            ->setDescription('Syncs timings from toggl to invoiceninja')
            ->addOption(OPTION_SINCE, OPTION_SINCE_SHORT, InputOption::VALUE_REQUIRED, 'Date from which timings get synced (See https://www.php.net/manual/de/datetime.formats.date.php)', $date7daysAgo->format(\DateTimeInterface::W3C))
            ->addOption(OPTION_UNTIL, OPTION_UNTIL_SHORT, InputOption::VALUE_REQUIRED, 'Date to which timings get synced (including this day) (See https://www.php.net/manual/de/datetime.formats.date.php)', $dateNow->format(\DateTimeInterface::W3C))
            ->addOption(OPTION_ROUND, OPTION_ROUND_SHORT, InputOption::VALUE_OPTIONAL, 'Minutes a time log\'s duration is rounded to and the end time is be adaptet to')
            ->addOption(OPTION_BILLABLE, OPTION_BILLABLE_SHORT, InputOption::VALUE_OPTIONAL, 'Transfer only billable timelogs')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        
        $this->getOptions($input);

        $workspaces = $this->getWorkspacesOrExit();

        foreach ($workspaces as $workspace) {
            $reportTimeEntries = $this->reportsClient->getTimeEntries($workspace->getId(), $this->since, $this->until);

            $logFilteredTimeEntries = $this->filterNotYetLoggedTimeEntries($reportTimeEntries);

            foreach($logFilteredTimeEntries as $timeEntry) {
                if(!$this->billableOnly | ($this->billableOnly && $timeEntry->isBillable())){
                    $createdTask = null;

                    $clientExists =$this->doesConfigKeyExist($this->clients, $timeEntry->getClient());
                    $projectExists = $this->doesConfigKeyExist($this->projects, $timeEntry->getProject());
                    $userExists = $this->doesConfigKeyExist($this->users, $timeEntry->getUser());

                    // Log the entry if all keys exist
                    if ($clientExists && $projectExists && $userExists) {
                        $createdTask = $this->logTask($timeEntry);
                    }

                    if(isset($createdTask)){
                        $this->io->success('TimeEntry successfully sent to InvoiceNinja. (' . $this->buildTaskDescription($timeEntry) . ')[' . $createdTask->getId() . ']');
                    } else {
                        $this->io->error('Error sending TimeEntry. [' . $timeEntry->getId() . ']' . '(' . $timeEntry->getDescription() . ')');
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
            $invoiceNinjaTaskTags = preg_grep(InvoiceNinjaClient::getTaskRefLabelRegexp(), $entry->getTags());
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

        $roundOption = $input->getOption('round');
        if(isset($roundOption) && is_int($roundOption)){
            $this->roundingMinutes = $roundOption;
        } 

        $billOption = $input->getOption('billable-only');
        if(isset($billOption)){
            $this->billableOnly = $billOption;
        } 
    }

    /**
     * @param array $config
     * @param string $entryKey
     * @param bool $hasAlreadyBeenSent
     *
     * @return bool
     */
    private function doesConfigKeyExist(array $config, string $entryKey): bool
    {
        return (is_array($config) && array_key_exists($entryKey, $config));
    }

    /**
     * Logs task and refs time entry
     * 
     * @param TimeEntry $entry
     * @return Task
     */
    private function logTask(TimeEntry $entry): Task
    {
        $task = $this->createTask($entry);
        $this->refTimeEntry($entry, $task);
        return $task;
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
        array_push($tags, InvoiceNinjaClient::createTaskRefLabel($task->getId()));
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
        $task->setTogglUser($entry->getUser());
        $task->setUserId($this->users[$entry->getUser()]);

        $newTask = $this->invoiceNinjaClient->createTask($task);
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
    public function maybeExtendDuration(DateTime $start, DateTime $end): DateTime
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
     * @param \int $roundToMinutes Minutes to be rounded to
     * @return \DateInterval
     **/
    private static function roundDateIntervalMinutes(DateInterval $interval, int $roundToMinutes): DateInterval
    {
            // Take date parts off duration
            $partialDaysStr = $interval->format('%d');
            $partialHoursStr = $interval->format('%h');
            $partialMinutesStr = $interval->format('%i');

            $partialDays = intval($partialDaysStr);
            $partialHours =   intval($partialHoursStr);
            $partialMinutes = intval($partialMinutesStr);

            // If the interval contains no days, hours or minutes
            // then it will be counted as one rounding
            if($partialDays == 0 
            && $partialHours == 0 
            && $partialMinutes == 0){
                return new DateInterval('PT'.$roundToMinutes.'M');
            } 

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
            $roundedDuration = new DateInterval($intervalFormat);

            return $roundedDuration;
    }
}
