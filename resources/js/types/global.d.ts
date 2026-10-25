import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** The lookbacks in days a repository can sync, with 0 for its whole history. */
            lookbackOptions: number[];
            /** The timezone reports are shown in, from the report defaults. */
            timezone: string;
            /** Whether this is the read-only public demo, which offers nothing that changes Houston. */
            demo: boolean;
            [key: string]: unknown;
        };
    }
}
