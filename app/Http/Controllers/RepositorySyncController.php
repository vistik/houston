<?php

namespace App\Http\Controllers;

use App\Jobs\SyncRepository;
use App\Models\Repository;
use Illuminate\Http\RedirectResponse;

class RepositorySyncController extends Controller
{
    public function store(Repository $repository): RedirectResponse
    {
        SyncRepository::trigger($repository, 'sync now');

        return back();
    }
}
