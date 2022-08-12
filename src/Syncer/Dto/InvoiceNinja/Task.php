<?php declare(strict_types=1);

namespace Syncer\Dto\InvoiceNinja;
use Carbon\Carbon;

/**
 * Class Task
 * @package Syncer\Dto\InvoiceNinja
 *
 * @author Matthieu Calie <matthieu@calie>
 */
class Task
{
    /** @var string $id  */
    private $id;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $timeLog;

    /**
     * @var string
     */
    private $clientId;

    /** @var string $projectId */
    private $projectId;

    /** @var int $togglId */
    private $togglId;

	/** @var string $userid  */
	private $userId;

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getTimeLog(): string
    {
        return $this->timeLog;
    }

	/**
	 * Returns the time log array with timestamps replaced by datetimes
	 * 
     * @return array
     */
    public function getTimeLogDateTime(): array
    {
		$timeLogs = json_decode($this->getTimeLog());

		$dateTimeLogs = [];
		foreach($timeLogs as $timeLog){
			$startTimeStamp = $timeLog[0];
			$endTimeStamp = $timeLog[1];
			
			$start = Carbon::createFromTimestamp($startTimeStamp)->toDateTime();
			$end = Carbon::createFromTimestamp($endTimeStamp)->toDateTime();

			array_push($dateTimeLogs, [$start, $end]);
		}

        return $dateTimeLogs;
    }

	

    /**
     * @param string $timeLog
     */
    public function setTimeLog(string $timeLog)
    {
        $this->timeLog = $timeLog;
    }

    /**
     * @return int
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @param int $clientId
     */
    public function setClientId(string $clientId)
    {
        $this->clientId = $clientId;
    }
	/**
	 * 
	 * @return string
	 */
	function getProjectId() {
		return $this->projectId;
	}
	
	/**
	 * 
	 * @param string $projectId 
	 * @return Task
	 */
	function setProjectId($projectId): self {
		$this->projectId = $projectId;
		return $this;
	}

	/**
	 * 
	 * @return int
	 */
	function getTogglId() {
		return $this->togglId;
	}
	
	/**
	 * 
	 * @param int $togglId 
	 * @return Task
	 */
	function setTogglId($togglId): self {
		$this->togglId = $togglId;
		return $this;
	}


	/**
	 * 
	 * @return string
	 */
	function getTogglIdStr() {
		return "$this->togglId";
	}
	
	/**
	 * 
	 * @param string $togglId 
	 * @return Task
	 */
	function setTogglIdStr($togglId): self {
		$this->togglId = intval($togglId);
		return $this;
	}
	/**
	 * 
	 * @return string
	 */
	function getId() {
		return $this->id;
	}
	
	/**
	 * 
	 * @param string $id 
	 * @return Task
	 */
	function setId($id): self {
		$this->id = $id;
		return $this;
	}
	/**
	 * 
	 * @return string
	 */
	function getUserId() {
		return $this->userId;
	}
	
	/**
	 * 
	 * @param string $userid 
	 * @return Task
	 */
	function setUserId($userid): self {
		$this->userId = $userid;
		return $this;
	}
}
