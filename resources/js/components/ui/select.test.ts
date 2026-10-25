import { describe, expect, it } from 'vitest';
import { filterOptions } from './select';

const zones = ['America/New_York', 'America/Los_Angeles', 'Europe/Copenhagen', 'UTC'].map(
    (zone) => ({ value: zone, label: zone.replaceAll('_', ' ') }),
);
const values = (query: string) =>
    filterOptions(zones, query).map((option) => option.value);

describe('filterOptions', () => {
    it('keeps every option without a query', () => {
        expect(values('')).toEqual(zones.map((zone) => zone.value));
        expect(values('   ')).toHaveLength(4);
    });

    it('reads underscores and slashes as spaces, ignoring case', () => {
        expect(values('new york')).toEqual(['America/New_York']);
        expect(values('NEW_YORK')).toEqual(['America/New_York']);
        expect(values('europe/copen')).toEqual(['Europe/Copenhagen']);
    });

    it('needs every word, in any order', () => {
        expect(values('angeles america')).toEqual(['America/Los_Angeles']);
        expect(values('america')).toEqual([
            'America/New_York',
            'America/Los_Angeles',
        ]);
        expect(values('america copenhagen')).toEqual([]);
    });
});
