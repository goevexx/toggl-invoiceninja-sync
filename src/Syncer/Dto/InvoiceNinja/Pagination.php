<?php declare(strict_types=1);

namespace Syncer\Dto\InvoiceNinja;

class Pagination  
{
    /** @var int $count  */
    private $count;
    
    /** @var int $perPage  */
    private $perPage;

    /** @var int $currentPage  */
    private $currentPage;

    /** @var int $totalPages  */
    private $totalPages;

	/**
	 * 
	 * @return int
	 */
	function getCount() {
		return $this->count;
	}
	
	/**
	 * 
	 * @param int $count 
	 * @return Pagination
	 */
	function setCount($count): self {
		$this->count = $count;
		return $this;
	}
	/**
	 * 
	 * @return int
	 */
	function getPerPage() {
		return $this->perPage;
	}
	
	/**
	 * 
	 * @param int $perPage 
	 * @return Pagination
	 */
	function setPerPage($perPage): self {
		$this->perPage = $perPage;
		return $this;
	}
	/**
	 * 
	 * @return int
	 */
	function getCurrentPage() {
		return $this->currentPage;
	}
	
	/**
	 * 
	 * @param int $currentPage 
	 * @return Pagination
	 */
	function setCurrentPage($currentPage): self {
		$this->currentPage = $currentPage;
		return $this;
	}
	/**
	 * 
	 * @return int
	 */
	function getTotalPages() {
		return $this->totalPages;
	}
	
	/**
	 * 
	 * @param int $totalPages 
	 * @return Pagination
	 */
	function setTotalPages($totalPages): self {
		$this->totalPages = $totalPages;
		return $this;
	}
}
