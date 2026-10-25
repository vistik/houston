<?php

namespace App\Enums;

/**
 * What a repository's card on the front page, the repository list and the wall shows. The front end keeps
 * the metrics of each case, keyed by its value.
 */
enum RepositoryCardView: string
{
    /** Merging, review and CI. */
    case Full = 'full';

    /** Contributions, how fast they get a response, and releases. */
    case OpenSource = 'open-source';

    /** Shipping: deploys or releases, and how often they need a fix. */
    case Delivery = 'delivery';

    /** How long pull requests wait on reviewers. */
    case Review = 'review';

    /** How often CI fails, next to what gets merged and shipped. */
    case Ci = 'ci';

    /**
     * The card that goes with a view of the repository page, for repositories whose card wasn't picked.
     */
    public static function for(RepositoryView $view): self
    {
        return match ($view) {
            RepositoryView::Full => self::Full,
            RepositoryView::OpenSource => self::OpenSource,
            RepositoryView::Delivery => self::Delivery,
            RepositoryView::Review => self::Review,
            RepositoryView::Ci => self::Ci,
        };
    }
}
