import { Form, Head } from '@inertiajs/react';
import { store } from '@/routes/login';
import { buttonClass } from '@/components/ui/button';
import { Wordmark } from '@/components/wordmark';

const INPUT =
    'h-10 w-full rounded-lg border border-line bg-page px-3 text-sm placeholder:text-ink-muted focus:border-accent focus:ring-2 focus:ring-accent-soft focus:outline-none';

export default function Login() {
    return (
        <div className="flex min-h-screen items-center justify-center px-4">
            <Head title="Log in" />

            <div className="w-full max-w-sm">
                <div className="mb-6 flex flex-col items-center gap-2">
                    <Wordmark tagline={false} />
                    <p className="font-mono text-[11px] tracking-widest text-ink-muted uppercase">
                        Mission control · Authorized personnel only
                    </p>
                </div>

                <Form
                    action={store()}
                    resetOnError={['password']}
                    className="flex flex-col gap-4 rounded-xl border border-line bg-surface p-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex flex-col gap-1.5">
                                <label
                                    htmlFor="email"
                                    className="text-xs font-medium text-ink-secondary"
                                >
                                    Email
                                </label>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    autoComplete="username"
                                    autoFocus
                                    required
                                    className={INPUT}
                                />
                                {errors.email && (
                                    <p className="text-sm text-bad">
                                        {errors.email}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label
                                    htmlFor="password"
                                    className="text-xs font-medium text-ink-secondary"
                                >
                                    Password
                                </label>
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    autoComplete="current-password"
                                    required
                                    className={INPUT}
                                />
                                {errors.password && (
                                    <p className="text-sm text-bad">
                                        {errors.password}
                                    </p>
                                )}
                            </div>

                            <label className="flex items-center gap-2 text-sm text-ink-secondary">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                    defaultChecked
                                    className="size-4 cursor-pointer rounded accent-[var(--accent)]"
                                />
                                Keep me logged in
                            </label>

                            <button
                                type="submit"
                                disabled={processing}
                                className={buttonClass({
                                    variant: 'primary',
                                    size: 'md',
                                })}
                            >
                                {processing
                                    ? 'Requesting clearance…'
                                    : 'Log in'}
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </div>
    );
}
