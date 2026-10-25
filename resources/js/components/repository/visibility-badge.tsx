import { LockIcon } from '@/components/icons';
import { Badge } from '@/components/ui/badge';
import type { RepositoryInfo } from '@/types';

/**
 * Whether the repository is private or public on GitHub. Shows nothing until a sync has fetched it.
 */
export function VisibilityBadge({
    repository,
    className,
}: {
    repository: Pick<RepositoryInfo, 'isPrivate'>;
    className?: string;
}) {
    if (repository.isPrivate === null) {
        return null;
    }

    return (
        <Badge className={className}>
            {repository.isPrivate && <LockIcon width={10} height={10} />}
            {repository.isPrivate ? 'Private' : 'Public'}
        </Badge>
    );
}
