import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login, register } from '@/routes';

const workflowSteps = [
    {
        step: 1,
        title: 'Upload',
        description: 'Import CSV or Excel files through a simple drag-and-drop interface.',
        icon: (
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12" />
        ),
    },
    {
        step: 2,
        title: 'Profile',
        description: 'Automatically detect data types, missing values, and quality issues.',
        icon: (
            <>
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.35-4.35M11 8v6M8 11h6" />
            </>
        ),
    },
    {
        step: 3,
        title: 'Clean',
        description: 'Remove duplicates, fix formats, and standardize your data with guided actions.',
        icon: (
            <path d="M12 3l1.88 5.79L20 10.5l-5 1.62L12 17l-3-4.88L4 10.5l6.12-1.71zM18 14l.85 2.62L21 17l-1.62.47L19 20l-.62-1.89L16 17.5l2.15-.38z" />
        ),
    },
    {
        step: 4,
        title: 'Analyze',
        description: 'Generate meaningful statistical summaries and discover patterns in your data.',
        icon: (
            <path d="m23 6-9.5 9.5-5-5L1 18M17 6h6v6" />
        ),
    },
    {
        step: 5,
        title: 'Visualize',
        description: 'Create interactive bar, line, and pie charts with user-selectable variables.',
        icon: (
            <>
                <path d="M18 20V10M12 20V4M6 20v-6" />
            </>
        ),
    },
];

const features = [
    {
        title: 'Comprehensive Profiling',
        description:
            'Understand your data at a glance. Automatic type detection, completeness scoring, duplicate identification, and column-level quality metrics for every dataset.',
    },
    {
        title: 'Guided Cleaning',
        description:
            'Handle missing values, remove duplicates, standardize date and text formats, and validate data through clear, step-by-step cleaning actions with preview before applying.',
    },
    {
        title: 'Rich Analytics & Visualization',
        description:
            'Generate statistical insights, detect distributions, and create interactive bar, line, and pie charts from your cleaned data to communicate findings with clarity.',
    },
];

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;

    const scrollToFeatures = () => {
        document
            .getElementById('features')
            ?.scrollIntoView({ behavior: 'smooth' });
    };

    return (
        <>
            <Head title="Welcome" />

            <div className="min-h-screen bg-[#F7F9FA] text-[#353535] dark:bg-background dark:text-foreground">
                {/* Navigation */}
                <header className="sticky top-0 z-50 border-b border-[#E5E7EB] bg-white/80 backdrop-blur-md dark:border-border dark:bg-background/80">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <Link
                            href="/"
                            className="flex items-center gap-3 font-semibold text-xl text-[#353535] dark:text-foreground"
                        >
                            <AppLogoIcon className="size-10" />
                            <span>Dirty2Data</span>
                        </Link>
                        <nav className="flex items-center gap-3">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex items-center rounded-lg bg-[#284B63] px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#1F3A4D] dark:bg-[#3C6E71] dark:hover:bg-[#315A5D]"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="rounded-lg px-4 py-2.5 text-sm font-medium text-[#6B7280] transition-colors hover:text-[#353535] dark:text-muted-foreground dark:hover:text-foreground"
                                    >
                                        Log in
                                    </Link>
                                    {canRegister && (
                                        <Link
                                            href={register()}
                                            className="inline-flex items-center rounded-lg bg-[#284B63] px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#1F3A4D] dark:bg-[#3C6E71] dark:hover:bg-[#315A5D]"
                                        >
                                            Get Started
                                        </Link>
                                    )}
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero Section */}
                <section className="relative overflow-hidden px-6 pb-20 pt-16 lg:pb-28 lg:pt-24">
                    <div className="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(40,75,99,0.08),rgba(255,255,255,0))] dark:bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(60,110,113,0.15),rgba(0,0,0,0))]" />
                    <div className="mx-auto max-w-4xl text-center">
                        <div className="mx-auto mb-8 flex items-center justify-center">
                            <AppLogoIcon className="size-28 lg:size-32" />
                        </div>
                        <h1 className="mb-6 text-4xl font-bold tracking-tight text-[#353535] lg:text-5xl dark:text-foreground">
                            Turn Messy Data into
                            <br />
                            <span className="text-[#284B63] dark:text-[#59C7C7]">
                                Actionable Insights
                            </span>
                        </h1>
                        <p className="mx-auto mb-10 max-w-2xl text-lg leading-relaxed text-[#6B7280] dark:text-muted-foreground">
                            Dirty2Data is an intelligent data cleaning and
                            analytics platform that guides you through a simple
                            workflow &mdash; from raw spreadsheets to
                            publication-ready visualizations in minutes.
                        </p>
                        <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex items-center rounded-xl bg-[#284B63] px-8 py-3.5 text-base font-semibold text-white shadow-sm transition-all hover:bg-[#1F3A4D] hover:shadow-md dark:bg-[#3C6E71] dark:hover:bg-[#315A5D]"
                                >
                                    Go to Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={
                                        canRegister ? register() : login()
                                    }
                                    className="inline-flex items-center rounded-xl bg-[#284B63] px-8 py-3.5 text-base font-semibold text-white shadow-sm transition-all hover:bg-[#1F3A4D] hover:shadow-md dark:bg-[#3C6E71] dark:hover:bg-[#315A5D]"
                                >
                                    Get Started Free
                                </Link>
                            )}
                            <button
                                onClick={scrollToFeatures}
                                className="inline-flex items-center rounded-xl border border-[#D9D9D9] bg-white px-8 py-3.5 text-base font-medium text-[#353535] shadow-sm transition-all hover:border-[#284B63] hover:text-[#284B63] dark:border-border dark:bg-card dark:text-foreground dark:hover:border-[#3C6E71] dark:hover:text-[#59C7C7]"
                            >
                                Learn More
                            </button>
                        </div>
                    </div>
                </section>

                {/* Workflow Section */}
                <section className="bg-white px-6 py-20 dark:bg-card lg:py-28">
                    <div className="mx-auto max-w-6xl">
                        <div className="mb-14 text-center">
                            <p className="mb-3 text-sm font-semibold uppercase tracking-widest text-[#3C6E71] dark:text-[#59C7C7]">
                                How It Works
                            </p>
                            <h2 className="text-3xl font-bold text-[#353535] lg:text-4xl dark:text-foreground">
                                A Simple, Guided Workflow
                            </h2>
                            <p className="mx-auto mt-4 max-w-xl text-[#6B7280] dark:text-muted-foreground">
                                Five straightforward steps from raw data to
                                beautiful, actionable visualizations.
                            </p>
                        </div>

                        {/* Desktop workflow */}
                        <div className="relative hidden lg:block">
                            <div className="flex items-start justify-between">
                                {workflowSteps.map((s) => (
                                    <div
                                        key={s.step}
                                        className="relative flex flex-col items-center text-center"
                                        style={{ width: '180px' }}
                                    >
                                        <div className="relative z-10 flex size-16 items-center justify-center rounded-2xl bg-[#284B63] text-white shadow-lg dark:bg-[#3C6E71]">
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                strokeWidth={1.8}
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                className="size-7"
                                            >
                                                {s.icon}
                                            </svg>
                                        </div>
                                        <div className="mt-5 text-sm font-bold uppercase tracking-wider text-[#284B63] dark:text-[#59C7C7]">
                                            Step {s.step}
                                        </div>
                                        <div className="mt-1 text-lg font-semibold text-[#353535] dark:text-foreground">
                                            {s.title}
                                        </div>
                                        <p className="mt-2 text-sm leading-relaxed text-[#6B7280] dark:text-muted-foreground">
                                            {s.description}
                                        </p>
                                    </div>
                                ))}
                            </div>

                            {/* Connector line */}
                            <div className="absolute top-8 left-[90px] right-[90px]">
                                <div className="h-0.5 bg-[#E5E7EB] dark:bg-border" />
                            </div>

                            {/* Arrowheads */}
                            {[1, 2, 3, 4].map((n) => (
                                <div
                                    key={n}
                                    className="absolute top-8 text-[#D9D9D9] dark:text-muted-foreground"
                                    style={{
                                        left: `${(n / 5) * 100}%`,
                                        transform: 'translateX(-50%)',
                                    }}
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth={2}
                                        className="size-4"
                                    >
                                        <path d="M5 12h14M12 5l7 7-7 7" />
                                    </svg>
                                </div>
                            ))}
                        </div>

                        {/* Mobile/Tablet workflow */}
                        <div className="flex flex-col gap-6 lg:hidden">
                            {workflowSteps.map((s) => (
                                <div
                                    key={s.step}
                                    className="flex items-start gap-5 rounded-xl border border-[#E5E7EB] bg-[#F7F9FA] p-5 dark:border-border dark:bg-background"
                                >
                                    <div className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-[#284B63] text-white dark:bg-[#3C6E71]">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth={1.8}
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            className="size-6"
                                        >
                                            {s.icon}
                                        </svg>
                                    </div>
                                    <div>
                                        <div className="text-xs font-bold uppercase tracking-wider text-[#284B63] dark:text-[#59C7C7]">
                                            Step {s.step}
                                        </div>
                                        <div className="mt-1 font-semibold text-[#353535] dark:text-foreground">
                                            {s.title}
                                        </div>
                                        <p className="mt-1 text-sm text-[#6B7280] dark:text-muted-foreground">
                                            {s.description}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section
                    id="features"
                    className="bg-[#F7F9FA] px-6 py-20 dark:bg-background lg:py-28"
                >
                    <div className="mx-auto max-w-6xl">
                        <div className="mb-14 text-center">
                            <p className="mb-3 text-sm font-semibold uppercase tracking-widest text-[#3C6E71] dark:text-[#59C7C7]">
                                Features
                            </p>
                            <h2 className="text-3xl font-bold text-[#353535] lg:text-4xl dark:text-foreground">
                                Everything You Need for Data Quality
                            </h2>
                            <p className="mx-auto mt-4 max-w-xl text-[#6B7280] dark:text-muted-foreground">
                                Purpose-built tools for every stage of your data
                                journey, from messy imports to polished exports.
                            </p>
                        </div>

                        <div className="grid gap-8 md:grid-cols-3">
                            {features.map((f) => (
                                <div
                                    key={f.title}
                                    className="group rounded-2xl border border-[#E5E7EB] bg-white p-8 shadow-sm transition-all hover:border-[#284B63]/30 hover:shadow-md dark:border-border dark:bg-card dark:hover:border-[#3C6E71]/40"
                                >
                                    <h3 className="mb-3 text-xl font-semibold text-[#353535] dark:text-foreground">
                                        {f.title}
                                    </h3>
                                    <p className="leading-relaxed text-[#6B7280] dark:text-muted-foreground">
                                        {f.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* CTA Section */}
                <section className="bg-white px-6 py-20 dark:bg-card lg:py-28">
                    <div className="mx-auto max-w-3xl text-center">
                        <h2 className="mb-4 text-3xl font-bold text-[#353535] lg:text-4xl dark:text-foreground">
                            Ready to Transform Your Data?
                        </h2>
                        <p className="mx-auto mb-10 max-w-lg text-lg text-[#6B7280] dark:text-muted-foreground">
                            Start turning messy spreadsheets into reliable,
                            analysis-ready datasets today.
                        </p>
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex items-center rounded-xl bg-[#284B63] px-10 py-4 text-base font-semibold text-white shadow-sm transition-all hover:bg-[#1F3A4D] hover:shadow-md dark:bg-[#3C6E71] dark:hover:bg-[#315A5D]"
                            >
                                Go to Dashboard
                            </Link>
                        ) : (
                            <Link
                                href={canRegister ? register() : login()}
                                className="inline-flex items-center rounded-xl bg-[#284B63] px-10 py-4 text-base font-semibold text-white shadow-sm transition-all hover:bg-[#1F3A4D] hover:shadow-md dark:bg-[#3C6E71] dark:hover:bg-[#315A5D]"
                            >
                                Get Started Free
                            </Link>
                        )}
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-[#E5E7EB] bg-[#F7F9FA] px-6 py-8 dark:border-border dark:bg-background">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 text-center sm:flex-row">
                        <div className="flex items-center gap-2 text-sm text-[#6B7280] dark:text-muted-foreground">
                            <AppLogoIcon className="size-6" />
                            <span>Dirty2Data</span>
                        </div>
                        <p className="text-sm text-[#9CA3AF] dark:text-muted-foreground">
                            &copy; {new Date().getFullYear()} Dirty2Data. All
                            rights reserved.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
