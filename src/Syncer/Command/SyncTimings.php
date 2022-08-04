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
use DateTime;

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
        $roundtimings
    ) {
        $this->togglClient = $togglClient;
        $this->reportsClient = $reportsClient;
        $this->invoiceNinjaClient = $invoiceNinjaClient;
        $this->clients = $clients;
        $this->projects = $projects;
        $this->roundingMinutes = $roundtimings;

        parent::__construct();
    }

    // TODO: Add sync to those time entries, which already exist in invoice ninja
    // TODO: Add since - until functionality for time entry snyc
    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('sync:timings')
            ->setDescription('Syncs timings from toggl to invoiceninja')
            ->addArgument('since', InputArgument::OPTIONAL, 'NO FUNCTION -- Date from which timings get synced')
            ->addArgument('until', InputArgument::OPTIONAL, 'NO FUNCTION -- Date to which timings get synced (including)')
            ->addArgument('round', InputArgument::OPTIONAL, 'Minutes a time log\'s duration is rounded to and the end time is be adaptet to')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setArguments($input);

        $this->io = new SymfonyStyle($input, $output);
        $workspaces = $this->togglClient->getWorkspaces();

        if (!is_array($workspaces) || count($workspaces) === 0) {
            $this->io->error('No workspaces to sync.');

            return;
        }

        foreach ($workspaces as $workspace) {
            $detailedReport = $this->reportsClient->getDetailedReport($workspace->getId());

            foreach($detailedReport->getData() as $timeEntry) {
                $timeEntrySent = false;

                // Log the entry if the client key exists
                if ($this->timeEntryCanBeLoggedByConfig($this->clients, $timeEntry->getClient(), $timeEntrySent)) {
                    $this->logTask($timeEntry, $this->clients, $timeEntry->getClient());

                    $timeEntrySent = true;
                }

                // Log the entry if the project key exists
                if ($this->timeEntryCanBeLoggedByConfig($this->projects, $timeEntry->getProject(), $timeEntrySent)) {
                    $this->logTask($timeEntry, $this->projects, $timeEntry->getProject());

                    $timeEntrySent = true;
                }

                if ($timeEntrySent) {
                    $this->io->success('TimeEntry ('. $timeEntry->getDescription() . ') sent to InvoiceNinja');
                }
            }
        }
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
     * @param TimeEntry $entry
     * @param array $config
     * @param string $key
     *
     * @return void
     */
    private function logTask(TimeEntry $entry, array $config, string $key)
    {
        $task = new Task();

        $task->setDescription($this->buildTaskDescription($entry));
        $task->setTimeLog($this->buildTimeLog($entry));
        $task->setClientId($config[$key]);

        $this->invoiceNinjaClient->saveNewTask($task);
    }

    /**
     * @param TimeEntry $entry
     *
     * @return string
     */
    private function buildTaskDescription(TimeEntry $entry): string
    {
        $description = '';

        if ($entry->getProject()) {
            $description .= $entry->getProject() . ': ';
        }

        $description .= $entry->getDescription();

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
        $end = $this->maybeExtendDuration($entry->getStart(), $entry->getEnd());

        $timeLog = [[
            $start,
            $end,
        ]];

        return \GuzzleHttp\json_encode($timeLog);
    }

    /**
     * Extends duration to a multiple of $roundingMinutes
     * 
     * Rounds duration between $start and $end up to $roundingMinutes 
     * and returns $start including the rounded duration added
     *
     * @param DateTime $start Duration start time
     * @param DateTime $end Duration end time
     * @return DateTime $start + rounded duration
     **/
    public function maybeExtendDuration(DateTime $start, DateTime $end): DateTime
    {
        $duration  = $start->diff($end);
        if ($this->roundingMinutes !== 0){
            $duration = $this::roundDateIntervalMinutes($duration, $this->roundingMinutes);
        }
        return $start->add($duration);
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
