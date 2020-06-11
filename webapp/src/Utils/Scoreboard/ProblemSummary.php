<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

class ProblemSummary
{
    /**
     * @var int[]
     */
    public $numSubmissions = [];

    /**
     * @var int[]
     */
    public $numSubmissionsPending = [];

    /**
     * @var int[]
     */
    public $numSubmissionsCorrect = [];

    /**
     * @var float[]
     */
    public $bestTimes = [];

    /**
     * @param int $sortorder
     * @return int
     */
    public function getNumberOfSubmissions(int $sortorder): int
    {
        return $this->numSubmissions[$sortorder] ?? 0;
    }

    /**
     * @param int $sortorder
     * @return int
     */
    public function getNumberOfPendingSubmissions(int $sortorder): int
    {
        return $this->numSubmissionsPending[$sortorder] ?? 0;
    }

    /**
     * @param int $sortorder
     * @return int
     */
    public function getNumberOfCorrectSubmissions(int $sortorder): int
    {
        return $this->numSubmissionsCorrect[$sortorder] ?? 0;
    }

    /**
     * @param int $sortorder
     * @param int $numSubmissions
     * @param int $numSubmissionsPending
     * @param int $numSubmissionsCorrect
     */
    public function addSubmissionCounts(
        int $sortorder,
        int $numSubmissions,
        int $numSubmissionsPending,
        int $numSubmissionsCorrect
    ) {
        if (!isset($this->numSubmissions[$sortorder])) {
            $this->numSubmissions[$sortorder] = 0;
        }
        if (!isset($this->numSubmissionsPending[$sortorder])) {
            $this->numSubmissionsPending[$sortorder] = 0;
        }
        if (!isset($this->numSubmissionsCorrect[$sortorder])) {
            $this->numSubmissionsCorrect[$sortorder] = 0;
        }
        $this->numSubmissions[$sortorder]        += $numSubmissions;
        $this->numSubmissionsPending[$sortorder] += $numSubmissionsPending;
        $this->numSubmissionsCorrect[$sortorder] += $numSubmissionsCorrect;
    }

    /**
     * @return float[]
     */
    public function getBestTimes(): array
    {
        return $this->bestTimes;
    }

    /**
     * Get the best time in minutes for the given sortorder
     * @param int $sortorder
     * @return int|null
     */
    public function getBestTimeInMinutes(int $sortorder)
    {
        if (isset($this->bestTimes[$sortorder])) {
            return ((int)($this->bestTimes[$sortorder] / 60));
        }
        return null;
    }

    /**
     * @param int   $sortorder
     * @param float $bestTime
     */
    public function updateBestTime(int $sortorder, $bestTime)
    {
        $this->bestTimes[$sortorder] = $bestTime;
    }
}
