<?php

namespace App\Enums;

/**
 * How an overview lays out its teams or repositories. The front end keeps a template for each case,
 * keyed by its value.
 */
enum OverviewLayout: string
{
    /** A grid of cards, each with its headline metrics and a trend. */
    case Cards = 'cards';

    /** A dense table, one row each, for comparing many at a glance. */
    case Table = 'table';
}
