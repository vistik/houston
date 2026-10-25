<?php

namespace App\Enums;

/**
 * What a team's card on the front page and the team list shows. The front end keeps the metrics of each
 * case, keyed by its value.
 */
enum TeamCardView: string
{
    /** How much the team merges and how fast. */
    case Full = 'full';

    /** How fast the team's pull requests get reviewed. */
    case Review = 'review';

    /** How much the team opens and merges, and what stays open. */
    case Throughput = 'throughput';
}
