<?php declare(strict_types=1);

namespace Syncer\Dto\Toggl;

use Syncer\Dto\Toggl\TimeEntry;

/**
 * Response of creating a Task
 */
class PutTimeEntryResponse  
{
    /** @var TimeEntry $data Created task */
    private $data;
	
    /**
	 * 
	 * @return TimeEntry
	 */
	function getData() {
		return $this->data;
	}
	
	/**
	 * 
	 * @param TimeEntry $data 
	 */
	function setData($data) {
		$this->data = $data;
	}
}
