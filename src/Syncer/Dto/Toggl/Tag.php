<?php declare(strict_types=1);

namespace Syncer\Dto\Toggl;

/**
 * Class Tag
 * @package Syncer\Dto\Toggl
 *
 */
class Tag
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $workspaceId;

    /**
     * @var string
     */
    private $name;

	/**
	 * 
	 * @return int
	 */
	function getId() {
		return $this->id;
	}
	
	/**
	 * 
	 * @param int $id 
	 * @return Tag
	 */
	function setId($id): self {
		$this->id = $id;
		return $this;
	}
	/**
	 * 
	 * @return string
	 */
	function getWorkspaceId() {
		return $this->workspaceId;
	}
	
	/**
	 * 
	 * @param string $workspaceId 
	 * @return Tag
	 */
	function setWorkspaceId($workspaceId): self {
		$this->workspaceId = $workspaceId;
		return $this;
	}
	/**
	 * 
	 * @return string
	 */
	function getName() {
		return $this->name;
	}
	
	/**
	 * 
	 * @param string $name 
	 * @return Tag
	 */
	function setName($name): self {
		$this->name = $name;
		return $this;
	}
}
