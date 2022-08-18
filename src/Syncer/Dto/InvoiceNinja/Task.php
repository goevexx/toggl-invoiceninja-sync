<?php declare(strict_types=1);

namespace Syncer\Dto\InvoiceNinja;
use Carbon\Carbon;
use LitGroup\Equatable\Equatable;

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
     * @var string $description
     */
    private $description;

    /**
     * @var string $timeLog
     */
    private $timeLog;

    /**
     * @var string $clientId
     */
    private $clientId;

    /** @var string $projectId */
    private $projectId;

    /** @var int $togglId */
    private $togglId;

	/** @var string $togglUser Toggl username */
	private $togglUser;

	/** @var string $userid  */
	private $userId;

	/** @var string $number  */
	private $number;

	/** @var boolean $deleted  */
	private $deleted = false;


    /**
     * Gets an identifier string
     *
     * Consists of information for the task to be tracked
     *
     * @return string
     **/
    public function __toString()
    {
        return 'Task {'
            . 'ID: ' . $this->getId() . ', '
            . 'Number: ' . $this->getNumber() . ', ' 
            . 'Description: ' . $this->getDescription()
            . '}';
    }

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
	/**
	 * 
	 * @return boolean
	 */
	function getDeleted() {
		return $this->deleted;
	}
	
	/**
	 * 
	 * @param boolean $deleted 
	 * @return Task
	 */
	function setDeleted($deleted): self {
		$this->deleted = $deleted;
		return $this;
	}
	
	/**
	 * 
	 * @return string
	 */
	function getTogglUser() {
		return $this->togglUser;
	}
	
	/**
	 * 
	 * @param string $togglUser 
	 * @return Task
	 */
	function setTogglUser($togglUser): self {
		$this->togglUser = $togglUser;
		return $this;
	}

	/**
	 * 
	 * @return string
	 */
	function getNumber() {
		return $this->number;
	}
	
	/**
	 * 
	 * @param string $number 
	 * @return Task
	 */
	function setNumber($number): self {
		$this->number = $number;
		return $this;
	}

	/**
	 * Checks if this object is equal to another one.
	 *
	 * @param Task $another
	 * @param string[] $dontCheck
	 *
	 * @return bool
	 */
	function equals(Task $another, array $dontCheck = []): bool {
		if( !(in_array('id', $dontCheck) && $this->id === $another->getId())
		&& $this->equalsInfo($another, $dontCheck)){
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Checks if object equals in information
	 *
	 * Doesn't consider id
	 *
	 * @param Task $another 
	 * @param string[] $dontCheck
	 * @return bool
	 **/
	public function equalsInfo(Task $another, array $dontCheck)
	{
		if(		(in_array('description', $dontCheck) 		|| $this->description === $another->getDescription())
		&& 	(in_array('timeLog', $dontCheck) 		|| $this->timeLog === $another->getTimeLog())
		&& 	(in_array('clientId', $dontCheck) 		|| $this->clientId === $another->getClientId())
		&& 	(in_array('projectId', $dontCheck) 		|| $this->projectId === $another->getProjectId())
		&& 	(in_array('togglId', $dontCheck) 		|| $this->togglId === $another->getTogglId())
		&& 	(in_array('togglUser', $dontCheck) 		|| $this->togglUser === $another->getTogglUser())
		&& 	(in_array('userId', $dontCheck) 		|| $this->userId === $another->getUserId())
		&& 	(in_array('number', $dontCheck) 		|| $this->number === $another->getNumber())
		&& 	(in_array('deleted', $dontCheck) 		|| $this->deleted = false === $another->getDeleted()) ){
			return true;
		} else {
			return false;
		}
	}
}
