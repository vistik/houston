<?php

namespace App\Http\Controllers;

use App\GitHub\RepositorySuggestions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepositorySuggestionController extends Controller
{
    /**
     * Suggest repositories to watch for what has been typed so far.
     */
    public function index(Request $request, RepositorySuggestions $suggestions): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:200']]);

        return response()->json($suggestions->for($request->string('q')->toString()));
    }
}
