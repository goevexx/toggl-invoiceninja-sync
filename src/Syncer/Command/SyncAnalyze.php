<?php 
declare(strict_types=1);

namespace Syncer\Command;

use Syncer\InvoiceNinja\Client as InvoiceNinjaClient;
use Syncer\Toggl\ReportsClient;
use Syncer\Toggl\TogglClient;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use \Syncer\Dto\Toggl\Workspace;
use Symfony\Component\Console\Input\InputOption;
use DateTimeZone;
use DateTime;


/**
 * Class SyncAnalyze
 * @package Syncer\Command
 *
 * @author Nicolas Morawietz <nmorawietz@programmierschmiede.de>
 */
class SyncAnalyze extends Command
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
    private $since;

    /** @var \DateTime $until Until when time entries got to be synced */
    private $until;

    /**
     * SyncAnalyze constructor.
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
        $timezone = new DateTimeZone(TIMEZONE);
        $date7daysAgo = (new DateTime('7 days ago', $timezone));
        $dateNow = (new DateTime('now', $timezone));
        $this
            ->setName('sync:analyze')
            ->setDescription('Checks consistency of toggl and invoiceninja task/time entry data') 
            ->addOption(OPTION_SINCE, OPTION_SINCE_SHORT, InputOption::VALUE_REQUIRED, 'Date from which timings get synced (See https://www.php.net/manual/de/datetime.formats.date.php)', $date7daysAgo->format(\DateTimeInterface::W3C))
            ->addOption(OPTION_UNTIL, OPTION_UNTIL_SHORT, InputOption::VALUE_REQUIRED, 'Date to which timings get synced (including this day) (See https://www.php.net/manual/de/datetime.formats.date.php)', $dateNow->format(\DateTimeInterface::W3C))
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
     * Sets console arguments
     *
     * @param InputInterface $input Argument enclosing object
     * @return void
     **/
    private function getOptions(InputInterface $input)
    {
        $timezone = new DateTimeZone(TIMEZONE);

        $sinceOption = $input->getOption('since');
        if (isset($sinceOption) && $sinceTime = new DateTime($sinceOption, $timezone)) {
            $this->since = $sinceTime;
        }

        $untilOption = $input->getOption('until');
        if (isset($untilOption) && $untilTime = new DateTime($untilOption, $timezone)) {
            $this->until = $untilTime;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->getOptions($input);

        $this->io->note('Start analyzing...');

        $workspaces = $this->getWorkspacesOrExit();
        foreach ($workspaces as $workspace) {
            // Check uniqueness of time entries IN label tags
            $hasWarnings = $this->checkTimeEntryToTaskConsistency($workspace);

            if ($hasWarnings){
                $this->io->error($workspace . ' has erroneous data. Look up.');
                return 1;
            } else {
                $this->io->success($workspace . ' has no errors.');
            }
        }

        $this->io->note('Analyzing finished.');

        return 0;
    }

    /**
     * Checks time entries integrity
     *
     * Time Entries are supposed to only have 1 tag name which is composed of IN label and its id.
     * There is only one task for every time entry and vice versa.
     *
     * @param Workspace $workspace 
     * @return boolean Has warnings
     **/
    private function checkTimeEntryToTaskConsistency(Workspace $workspace)
    {
        $this->io->comment($workspace . '! Every time entry is supposed to have one unique tag name starting with "'.INVOICENINJA_TASK_REF_LABEL.'", which identifies to one unique task in invoice ninja !');
        $hasWarnings = false;
        $workspaceId = $workspace->getId();
            // Check 1:1 integrity of tasks and time entries
            // For each tag, check if there is only one task, if not report
            $tags = $this->togglClient->getAllTags($workspaceId);
            $timeEntries = $this->reportsClient->getTimeEntries($workspaceId, $this->since, $this->until);

            foreach ($tags as $tag) {
                $tagName = $tag->getName();
                $tagNameStartsWithINLabel = strpos( $tagName, INVOICENINJA_TASK_REF_LABEL) === 0;
                if($tagNameStartsWithINLabel){
                    $matchingTimeEntries = [];
                    foreach ($timeEntries as $timeEntry) {
                        $timeEntryTags = $timeEntry->getTags();
                        $timeEntryHasTag = in_array($tagName, $timeEntryTags);
                        if($timeEntryHasTag){
                            $matchingTimeEntries[] = $timeEntry;
                        }
                    }

                    $matchingTimeEntryCount = count($matchingTimeEntries);
                    if($matchingTimeEntryCount > 1){
                        $hasWarnings = true;
                        $this->io->caution($workspace . 'Tag ' . $tagName . ' has multiple time entries referenced:\n' . implode('\n',$matchingTimeEntries));
                    }
                }
            }

        return $hasWarnings;
    }
}