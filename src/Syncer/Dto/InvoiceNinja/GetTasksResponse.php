<?php declare(strict_types=1);

namespace Syncer\Dto\InvoiceNinja;

use Syncer\Dto\InvoiceNinja\Task;

/**
 * Response of getting all tasks
 */
class GetTasksResponse  
{
    /** @var Task[] $data Collection of tasks */
    private $data = [];
	
    /**
	 * 
	 * @return Task[]
	 */
	function getData() {
		return $this->data;
	}
	
	/**
	 * 
	 * @param Task[] $data 
	 */
	function setData($data) {
		$this->data = $data;
	}
}
