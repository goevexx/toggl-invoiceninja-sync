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
    // TODO: Add task update sync

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

        $this->io->note('Start sync timings...');

        foreach ($workspaces as $workspace) {
            $reportTimeEntries = $this->reportsClient->getTimeEntries($workspace->getId(), $this->since, $this->until);

            $loggedTimeEntries = $this->filterLoggedTimeEntries($reportTimeEntries);
            $notLoggedTimeEntries = $this->filterLoggedTimeEntries($reportTimeEntries, false);

            $this->io->note($workspace . 'Create new tasks...');

            // Create new tasks for not yet logged time entries
            foreach($notLoggedTimeEntries as $timeEntry) {
                $this->handleNewTimeEntry($timeEntry);
            }

            $this->io->note($workspace . 'Update existing tasks...');
            // Update already logged time entries
            foreach($loggedTimeEntries as $timeEntry) {
                $this->handleReferencedTimeEntry($timeEntry);
            }
        }

        $this->io->note('Sync timings finished.');
        return 0;
    }

    /**
     * Handles a already referenced time entry
     *
     * Updates task if time entry information has updated
     *
     * @param TimeEntry $timeEntry 
     **/
    public function handleReferencedTimeEntry(TimeEntry $timeEntry)
    {
        $syncedTask = $this->syncTask($timeEntry);

        if(!isset($syncedTask)){
            $this->io->error('Error updating Task from TimeEntry. ' . $timeEntry);
        }
    }


    /**
     * Handles a new time entry
     *
     * Creates task when user, client and project exists and references the new task to the time entry
     *
     * @param TimeEntry $timeEntry 
     **/
    public function handleNewTimeEntry(TimeEntry $timeEntry)
    {
        if(!$this->billableOnly | ($this->billableOnly && $timeEntry->isBillable())){
            $clientExists =$this->doesConfigKeyExist($this->clients, $timeEntry->getClient());
            $projectExists = $this->doesConfigKeyExist($this->projects, $timeEntry->getProject());
            $userExists = $this->doesConfigKeyExist($this->users, $timeEntry->getUser());

            // Only continue if the entry of all keys exist
            if ($clientExists && $projectExists && $userExists) {
                $this->io->comment('New time entry.' . $timeEntry);

                $createdTask = $this->logTask($timeEntry);

                if(isset($createdTask)){
                    $this->io->success('Task successfully sent to InvoiceNinja. ' . $createdTask);
                } else {
                    $this->io->error('Error creating Task from TimeEntry. ' . $timeEntry);
                }
            } else {
                $this->io->error('Couldn\'t create Task from TimeEntry.' . $timeEntry);
                $this->io->error('At least one of either clients, projects or users in your config doesn\'t exist');
            }
        }
    }

    /**
     * Gets task id from time entry tags
     *
     * Returns task id if it was found, else returns empty string if it wasn't found
     *
     * @param TimeEntry $timeEntry 
     * @return string Task id
     **/
    private static function getTaskIdFromTimeEntry(TimeEntry $timeEntry)
    {
        $invoiceNinjaTaskTags = preg_grep(InvoiceNinjaClient::getTaskRefLabelRegexp(), $timeEntry->getTags());
        if(count($invoiceNinjaTaskTags) > 1){
            throw new \Exception('Time Entry has multiple Task IDs. ' .$timeEntry);
        } else if (count($invoiceNinjaTaskTags) == 1){
            $invoiceNinjaTaskTag = $invoiceNinjaTaskTags[0];
            preg_match(InvoiceNinjaClient::getTaskRefLabelRegexp(), $invoiceNinjaTaskTag, 
            $invoiceNinjaTaskIdMatch);
            $invoiceNinjaTaskId = $invoiceNinjaTaskIdMatch[1];
            return $invoiceNinjaTaskId;
        } else {
            return "";
        }
    }

    /**
     * Filters an not yet logged time entry
     *
     * @param TimeEntry $entry
     * @return bool
     **/
    static function notLoggedYetFilter(TimeEntry $entry): bool {
        $invoiceNinjaTaskTags = SyncTimings::getTaskIdFromTimeEntry($entry);
        if(strlen($invoiceNinjaTaskTags) > 0){
            return false;
        }
        return true;
    }

    /**
     * Filters an already logged time entry
     *
     * @param TimeEntry $entry
     * @return bool
     **/
    static function loggedYetFilter(TimeEntry $entry){
        $invoiceNinjaTaskTags = SyncTimings::getTaskIdFromTimeEntry($entry);
        if(strlen($invoiceNinjaTaskTags) > 0){
            return true;
        }
        return false;
    }

    /**
     * Filters time entries when they are $logged or not
     *
     * Searches time entry's tags for invoice ninja task id 
     *
     * @param TimeEntry[] $entries 
     * @return TimeEntry[]
     **/
    public function filterLoggedTimeEntries(array $entries, bool $logged = true): array
    {
        if($logged){
            $filteredEntries = array_filter($entries, array(SyncTimings::class, 'loggedYetFilter'));
        } else {
            $filteredEntries = array_filter($entries, array(SyncTimings::class, 'notLoggedYetFilter'));
        }

        return $filteredEntries;
    }

    /**
     * Filters not yet logged time entries
     *
     * Searches time entry's tags for invoice ninja task id 
     *
     * @param TimeEntry[] $entries 
     * @return TimeEntry[]
     **/
    // public function filterNotYetLoggedTimeEntries(array $entries): array
    // {
    //     $filteredEntries = array_filter($entries, function(TimeEntry $entry){
    //         $invoiceNinjaTaskTags = preg_grep(InvoiceNinjaClient::getTaskRefLabelRegexp(), $entry->getTags());
    //         if(count($invoiceNinjaTaskTags) > 0){
    //             return false;
    //         }
    //         return true;
    //     });
    //     return $filteredEntries;
    // }

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
     * Synchronizes changes in time entry
     *
     * Comapares time entry with existing task and updates conditionally
     *
     * @param TimeEntry $timeEntry 
     * @return Task
     **/
    private function syncTask(TimeEntry $timeEntry): Task
    {
        $taskId = $this->getTaskIdFromTimeEntry($timeEntry);
        $task = $this->invoiceNinjaClient->getTask($taskId);

        $taskFromTimeEntry = $this->timeEntryToTask($timeEntry, $taskId);

        $taskChanged = !$task->equals($taskFromTimeEntry, ['number']);
        
        if ($taskChanged){
            $this->io->comment('Task has changes. ' . $task . '!=' . $taskFromTimeEntry) ;
            $newTask = $this->invoiceNinjaClient->updateTask($taskFromTimeEntry);

            if(isset($newTask)){
                $this->io->success('Task successfully updated. ' . $newTask);
                return $newTask;
            }

        }
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
     * Converts time entry to task
     *
     * @param TimeEntry $entry
     * @param string $taskId
     * @return Task
     **/
    private function timeEntryToTask(TimeEntry $entry, string $taskId = null)
    {
        $task = new Task();

        if(isset($taskId)){
            $task->setId($taskId);
        }

        $task->setDescription(SyncTimings::buildTaskDescription($entry));
        $task->setTimeLog($this->buildTimeLog($entry));
        $task->setClientId($this->clients[$entry->getClient()]);
        $task->setProjectId($this->projects[$entry->getProject()]);
        $task->setTogglId($entry->getId());
        $task->setTogglUser($entry->getUser());
        $task->setUserId($this->users[$entry->getUser()]);

        return $task;
    }

    /**
     * @param TimeEntry $entry
     *
     * @return Task
     */
    private function createTask(TimeEntry $entry): Task
    {
        $task = $this->timeEntryToTask($entry);
        $newTask = $this->invoiceNinjaClient->createTask($task);
        return $newTask;
    }

    /**
     * @param TimeEntry $entry
     *
     * @return string
     */
    private static function buildTaskDescription(TimeEntry $entry): string
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
    private function maybeExtendDuration(DateTime $start, DateTime $end): DateTime
    {
        $startClone = clone $start;
        $duration  = $startClone->diff($end);
        if ($this->roundingMinutes !== 0){
            $duration = SyncTimings::roundDateIntervalMinutes($duration, $this->roundingMinutes);
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
