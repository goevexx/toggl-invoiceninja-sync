<?php declare(strict_types=1);

namespace Syncer\Dto\InvoiceNinja;

use Syncer\Dto\InvoiceNinja\Meta as INMeta;
use Syncer\Dto\InvoiceNinja\Task;

/**
 * Response of getting all tasks
 */
class GetTasksResponse  
{
    /** @var Task[] $data Collection of tasks */
    private $data = [];

	/** @var INMeta.Meta $meta  */
	private $meta;
	
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
	/**
	 * 
	 * @return Meta
	 */
	function getMeta() {
		return $this->meta;
	}
	
	/**
	 * 
	 * @param Meta $meta 
	 * @return GetTasksResponse
	 */
	function setMeta($meta): self {
		$this->meta = $meta;
		return $this;
	}
}
