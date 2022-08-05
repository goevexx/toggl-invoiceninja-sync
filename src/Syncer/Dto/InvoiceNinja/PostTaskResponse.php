<?php declare(strict_types=1);

namespace Syncer\Dto\InvoiceNinja;

use Syncer\Dto\InvoiceNinja\Task;

/**
 * Response of creating a Task
 */
class PostTaskResponse  
{
    /** @var Task $data Created task */
    private $data;
	
    /**
	 * 
	 * @return Task
	 */
	function getData() {
		return $this->data;
	}
	
	/**
	 * 
	 * @param Task $data 
	 */
	function setData($data) {
		$this->data = $data;
	}
}
