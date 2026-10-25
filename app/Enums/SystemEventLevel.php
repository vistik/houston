<?php

namespace App\Enums;

/**
 * How much attention a system event needs.
 */
enum SystemEventLevel: string
{
    case Info = 'info';

    case Warning = 'warning';

    case Error = 'error';
}
