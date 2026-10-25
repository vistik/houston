<?php

namespace App\Http\Controllers;

use App\Enums\SystemEventType;
use App\Http\Requests\UpdateExcludedWorkflowsRequest;
use App\Models\Repository;
use App\Models\SystemEvent;
use Illuminate\Http\RedirectResponse;

class RepositoryExcludedWorkflowsController extends Controller
{
    /**
     * Choose which workflows are left out of the repository's CI metrics, such as label gates that stay
     * red until someone acts. Cached reports are keyed on the change, so they recompute right away.
     */
    public function update(UpdateExcludedWorkflowsRequest $request, Repository $repository): RedirectResponse
    {
        $workflowIds = $request->workflowIds();

        $repository->update(['excluded_workflow_ids' => $workflowIds === [] ? null : $workflowIds]);

        SystemEvent::record(SystemEventType::SettingsExcludedWorkflows, 'Excluded '.count($workflowIds)." workflows from the CI metrics of {$repository->fullName()}", $repository, [
            'workflow_ids' => $workflowIds,
        ]);

        return back();
    }
}
