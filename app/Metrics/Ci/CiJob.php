<?php

namespace App\Metrics\Ci;

use Illuminate\Http\Request;

/**
 * One job of one workflow, as the flaky-job metrics can be narrowed to. Jobs are told apart by name,
 * so each leg of a matrix is a job of its own.
 */
final readonly class CiJob
{
    public function __construct(
        public int $workflowId,
        public string $name,
    ) {}

    /**
     * The job a request narrows to with `workflow` and `job`, or null when it names none.
     */
    public static function fromRequest(Request $request): ?self
    {
        $workflow = $request->query('workflow');
        $name = $request->query('job');

        return is_numeric($workflow) && is_string($name) && $name !== '' ? new self((int) $workflow, $name) : null;
    }

    /**
     * Identifies the job in cache keys.
     */
    public function key(): string
    {
        return $this->workflowId.':'.md5($this->name);
    }
}
