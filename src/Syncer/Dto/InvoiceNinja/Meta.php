<?php declare(strict_types=1);

namespace Syncer\Dto\InvoiceNinja;

class Meta {
	
    /** @var Pagination $pagination */
    private  $pagination;

	/**
	 * 
	 * @return Pagination
	 */
	function getPagination() {
		return $this->pagination;
	}
	
	/**
	 * 
	 * @param Pagination $pagination 
	 * @return Meta
	 */
	function setPagination($pagination): self {
		$this->pagination = $pagination;
		return $this;
	}
}