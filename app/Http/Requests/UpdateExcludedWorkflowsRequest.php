<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExcludedWorkflowsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'workflow_ids' => ['present', 'array'],
            'workflow_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function workflowIds(): array
    {
        return array_values(array_map(intval(...), $this->validated('workflow_ids')));
    }
}
