<?php declare(strict_types=1);

namespace Syncer\Dto\Toggl;

/**
 * Class DetailedReport
 * @package Syncer\Dto\Toggl
 *
 * @author Matthieu Calie <matthieu@calie.be>
 */
class DetailedReport
{
    /**
     * @var integer
     */
    private $totalGrand;

    /**
     * @var integer
     */
    private $totalBillable;

    /**
     * @var integer
     */
    private $totalCount;

    /**
     * @var integer
     */
    private $perPage;

    /**
     * @var TimeEntry[]
     */
    private $data;

    /**
     * @return int
     */
    public function getTotalGrand(): int
    {
        return $this->totalGrand;
    }

    /**
     * @param int $totalGrand
     */
    public function setTotalGrand(int $totalGrand)
    {
        $this->totalGrand = $totalGrand;
    }

    /**
     * @return int
     */
    public function getTotalBillable(): int
    {
        return $this->totalBillable;
    }

    /**
     * @param int $totalBillable
     */
    public function setTotalBillable(int $totalBillable)
    {
        $this->totalBillable = $totalBillable;
    }

    /**
     * @return int
     */
    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * @param int $totalCount
     */
    public function setTotalCount(int $totalCount)
    {
        $this->totalCount = $totalCount;
    }

    /**
     * @return int
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * @param int $perPage
     */
    public function setPerPage(int $perPage)
    {
        $this->perPage = $perPage;
    }

    /**
     * @return array|TimeEntry[]
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }
}
