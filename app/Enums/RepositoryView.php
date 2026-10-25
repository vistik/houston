<?php

namespace App\Enums;

/**
 * Which view of a repository its page shows. The front end keeps a view for each case, keyed by its value.
 */
enum RepositoryView: string
{
    /** Everything Houston measures: flow, delivery, CI, people and more. */
    case Full = 'full';

    /** What an open source project cares about: contributions, how fast they get a response, and releases. */
    case OpenSource = 'open-source';

    /** The DORA metrics and what feeds them. */
    case Delivery = 'delivery';

    /** How long pull requests wait on reviewers, and how reviews go. */
    case Review = 'review';

    /** How the pipeline is doing: failures, red time, speed and flakiness. */
    case Ci = 'ci';
}
