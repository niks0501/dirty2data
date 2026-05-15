import { Head, Link } from '@inertiajs/react';
import {
    BarChart3,
    Database,
    FileSpreadsheet,
    HardDrive,
    Rows3,
    Sparkles,
} from 'lucide-react';
import WorkflowSteps from '@/components/datasets/workflow-steps';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface DashboardStats {
    totalDatasets: number;
    readyDatasets: number;
    totalRows: number;
    totalSizeBytes: number;
}

interface RecentDatasetItem {
    id: number;
    originalName: string;
    rowCount: number;
    columnCount: number;
    status: string;
    createdAt: string;
}

interface Props {
    stats: DashboardStats;
    recentDatasets: RecentDatasetItem[];
}

function formatBytes(bytes: number): string {
    if (bytes === 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const size = parseFloat((bytes / Math.pow(1024, i)).toFixed(1));

    return `${size} ${units[i]}`;
}

function formatDate(isoString: string): string {
    return new Date(isoString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

const kpiCards = [
    {
        key: 'totalDatasets',
        label: 'Datasets',
        icon: Database,
        format: (v: number) => v.toLocaleString(),
    },
    {
        key: 'readyDatasets',
        label: 'Ready',
        icon: FileSpreadsheet,
        format: (v: number) => v.toLocaleString(),
    },
    {
        key: 'totalRows',
        label: 'Total Rows',
        icon: Rows3,
        format: (v: number) => v.toLocaleString(),
    },
    {
        key: 'totalSizeBytes',
        label: 'Total Size',
        icon: HardDrive,
        format: (v: number) => formatBytes(v),
    },
];

export default function Dashboard({ stats, recentDatasets }: Props) {
    const featureCards = [
        {
            title: 'Upload datasets',
            description:
                'Import CSV or Excel files with validation for type, size, readability, and headers.',
            icon: FileSpreadsheet,
        },
        {
            title: 'Profile quality',
            description:
                'Review rows, columns, data types, missing values, duplicates, and statistics.',
            icon: Database,
        },
        {
            title: 'Visualize insights',
            description:
                'Generate bar, line, and pie charts from cleaned working data.',
            icon: BarChart3,
        },
    ];

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6">
                <div className="rounded-2xl bg-[#284B63] p-6 text-white shadow-sm">
                    <div className="max-w-3xl space-y-3">
                        <div className="flex items-center gap-2 text-sm text-white/80">
                            <Sparkles className="size-4" />
                            Data Cleaning and Analytics System
                        </div>
                        <h1 className="text-2xl font-bold md:text-3xl">
                            Turn messy tabular files into clean insights.
                        </h1>
                        <p className="text-white/80">
                            Upload datasets, inspect their structure, clean
                            common quality issues, and visualize patterns from
                            one guided dashboard workflow.
                        </p>
                        <Button
                            asChild
                            className="bg-white text-[#284B63] hover:bg-white/90"
                        >
                            <Link href="/datasets">Upload dataset</Link>
                        </Button>
                    </div>
                </div>

                <WorkflowSteps currentStep={0} maxUnlockedStep={0} />

                {/* KPI Summary Cards */}
                {stats.totalDatasets > 0 && (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {kpiCards.map((card) => {
                            const value =
                                stats[card.key as keyof DashboardStats];
                            const Icon = card.icon;

                            return (
                                <Card key={card.key}>
                                    <CardContent className="flex items-center gap-3 p-4">
                                        <Icon className="size-5 shrink-0 text-[#284B63]" />
                                        <div className="min-w-0">
                                            <p className="text-xs text-muted-foreground">
                                                {card.label}
                                            </p>
                                            <p className="text-lg font-semibold text-[#353535]">
                                                {card.format(value)}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}

                {/* Recent Datasets */}
                {recentDatasets.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent datasets</CardTitle>
                            <CardDescription>
                                Your most recently uploaded files.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="divide-y">
                                {recentDatasets.map((item) => (
                                    <Link
                                        key={item.id}
                                        href={`/datasets/${item.id}`}
                                        className="flex items-center justify-between rounded px-1 py-3 transition-colors hover:bg-[#F7F9FA]"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-[#353535]">
                                                {item.originalName}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.rowCount.toLocaleString()}{' '}
                                                rows · {item.columnCount}{' '}
                                                columns ·{' '}
                                                {formatDate(item.createdAt)}
                                            </p>
                                        </div>
                                        <span
                                            className={[
                                                'ml-3 shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                                                item.status === 'ready'
                                                    ? 'bg-[#E8F5E9] text-[#2E7D32]'
                                                    : item.status ===
                                                        'processing'
                                                      ? 'bg-[#E7F0F5] text-[#284B63]'
                                                      : 'bg-[#FDECEC] text-[#C62828]',
                                            ].join(' ')}
                                        >
                                            {item.status}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 md:grid-cols-3">
                    {featureCards.map((card) => (
                        <Card key={card.title}>
                            <CardHeader>
                                <card.icon className="mb-2 size-6 text-[#3C6E71]" />
                                <CardTitle>{card.title}</CardTitle>
                                <CardDescription>
                                    {card.description}
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Start with an upload</CardTitle>
                        <CardDescription>
                            No dataset is selected on the dashboard. Go to
                            Datasets to upload a CSV or Excel file, then
                            continue through Profile → Clean → Analyze →
                            Visualize.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button asChild>
                            <Link href="/datasets">Open datasets</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
    ],
};
