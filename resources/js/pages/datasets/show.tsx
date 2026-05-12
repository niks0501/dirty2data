import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Clock,
    Columns3,
    Database,
    HardDrive,
    Rows3,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import AttributePanel from '@/components/datasets/attribute-panel';
import ChartPanel from '@/components/datasets/chart-panel';
import CleaningPanel from '@/components/datasets/cleaning-panel';
import DatasetPreviewTable from '@/components/datasets/dataset-preview-table';
import ProfilePanel from '@/components/datasets/profile-panel';
import SelectedColumnProfile from '@/components/datasets/selected-column-profile';
import WorkflowSteps from '@/components/datasets/workflow-steps';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { DatasetPageProps } from '@/types/datasets';

interface Props {
    dataset: DatasetPageProps;
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

export default function Show({ dataset }: Props) {
    const [selectedColumn, setSelectedColumn] = useState(
        dataset.selectedColumn ?? dataset.headers[0] ?? null,
    );
    const selectedColumnProfile = useMemo(
        () =>
            dataset.profile?.columns.find(
                (column) => column.name === selectedColumn,
            ) ?? dataset.selectedColumnProfile,
        [
            dataset.profile?.columns,
            dataset.selectedColumnProfile,
            selectedColumn,
        ],
    );
    const summaryCards = [
        {
            label: 'Rows',
            value: dataset.rowCount.toLocaleString(),
            icon: Rows3,
        },
        {
            label: 'Columns',
            value: dataset.columnCount.toString(),
            icon: Columns3,
        },
        {
            label: 'Size',
            value: formatBytes(dataset.sizeBytes),
            icon: HardDrive,
        },
        {
            label: 'Type',
            value: (dataset.extension ?? 'file').toUpperCase(),
            icon: Database,
        },
        {
            label: 'Uploaded',
            value: formatDate(dataset.createdAt),
            icon: Clock,
        },
    ];

    return (
        <>
            <Head title={dataset.originalName} />

            <div className="flex flex-col space-y-6">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                    <Heading
                        variant="small"
                        title={dataset.originalName}
                        description="Workspace flow: inspect columns, clean the working copy, and generate recommended charts while preserving the original upload."
                    />

                    <Button asChild variant="secondary">
                        <Link href="/datasets">
                            <ArrowLeft className="mr-2 size-4" />
                            Back to datasets
                        </Link>
                    </Button>
                </div>

                <WorkflowSteps current="Visualize" />

                <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-5">
                    {summaryCards.map((card) => (
                        <Card key={card.label}>
                            <CardContent className="flex items-center gap-3 p-4">
                                <card.icon className="size-5 shrink-0 text-[#284B63]" />
                                <div className="min-w-0">
                                    <p className="text-xs text-muted-foreground">
                                        {card.label}
                                    </p>
                                    <p className="truncate text-sm font-medium text-[#353535]">
                                        {card.value}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">
                    <AttributePanel
                        columns={dataset.profile?.columns ?? []}
                        selectedColumn={selectedColumn}
                        onSelectColumn={setSelectedColumn}
                    />
                    <SelectedColumnProfile column={selectedColumnProfile} />
                </div>

                <DatasetPreviewTable dataset={dataset} />
                <ProfilePanel profile={dataset.profile} />
                <CleaningPanel dataset={dataset} />
                <ChartPanel dataset={dataset} />
            </div>
        </>
    );
}

Show.layout = (props: { dataset: DatasetPageProps }) => ({
    breadcrumbs: [
        {
            title: 'Datasets',
            href: '/datasets',
        },
        {
            title: props.dataset.originalName,
            href: '#',
        },
    ],
});
