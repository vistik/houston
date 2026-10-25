<?php

use App\Http\Middleware\AuthenticateMcpToken;
use App\Mcp\Servers\HoustonServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('houston', HoustonServer::class);
Mcp::web('/mcp', HoustonServer::class)->middleware(AuthenticateMcpToken::class);
