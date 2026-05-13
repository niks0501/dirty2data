import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Clock,
    Columns3,
    Database,
    HardDrive,
    Loader2,
    Rows3,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AttributePanel from '@/components/datasets/attribute-panel';
import ChartPanel from '@/components/datasets/chart-panel';
import CleaningPanel from '@/components/datasets/cleaning-panel';
import DatasetPreviewTable from '@/components/datasets/dataset-preview-table';
import ProfilePanel from '@/components/datasets/profile-panel';
import QualityScoreCard from '@/components/datasets/quality-score-card';
import SelectedColumnProfile from '@/components/datasets/selected-column-profile';
import WorkflowSteps from '@/components/datasets/workflow-steps';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { DatasetPageProps, DatasetQualityScore } from '@/types/datasets';

interface Props {
    dataset: DatasetPageProps;
    qualityScore: DatasetQualityScore | null;
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

function formatDateTime(isoString: string | null): string {
    if (!isoString) {
        return '—';
    }

    return new Date(isoString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Show({ dataset, qualityScore }: Props) {
    const [currentDataset, setCurrentDataset] = useState(dataset);
    const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const isProcessing = currentDataset.status === 'processing';
    const isFailed = currentDataset.status === 'failed';

    const [selectedColumn, setSelectedColumn] = useState(
        currentDataset.selectedColumn ?? currentDataset.headers[0] ?? null,
    );
    const selectedColumnProfile = useMemo(
        () =>
            currentDataset.profile?.columns.find(
                (column) => column.name === selectedColumn,
            ) ?? currentDataset.selectedColumnProfile,
        [
            currentDataset.profile?.columns,
            currentDataset.selectedColumnProfile,
            selectedColumn,
        ],
    );

    useEffect(() => {
        if (!isProcessing) {
            return;
        }

        pollingRef.current = setInterval(() => {
            router.reload({
                only: ['dataset', 'qualityScore'],
                onSuccess: (page) => {
                    const updated = page.props.dataset as DatasetPageProps;

                    setCurrentDataset(updated);

                    if (updated.status !== 'processing') {
                        if (pollingRef.current) {
                            clearInterval(pollingRef.current);
                            pollingRef.current = null;
                        }
                    }
                },
            });
        }, 4000);

        return () => {
            if (pollingRef.current) {
                clearInterval(pollingRef.current);
                pollingRef.current = null;
            }
        };
    }, [isProcessing]);

    const summaryCards = [
        {
            label: 'Rows',
            value: currentDataset.rowCount.toLocaleString(),
            icon: Rows3,
        },
        {
            label: 'Columns',
            value: currentDataset.columnCount.toString(),
            icon: Columns3,
        },
        {
            label: 'Size',
            value: formatBytes(currentDataset.sizeBytes),
            icon: HardDrive,
        },
        {
            label: 'Type',
            value: (currentDataset.extension ?? 'file').toUpperCase(),
            icon: Database,
        },
        {
            label: 'Uploaded',
            value: formatDate(currentDataset.createdAt),
            icon: Clock,
        },
    ];

    return (
        <>
            <Head title={currentDataset.originalName} />

            <div className="flex flex-col space-y-6">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                    <Heading
                        variant="small"
                        title={currentDataset.originalName}
                        description=""
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

                {isProcessing && (
                    <Card>
                        <CardContent className="space-y-4 p-6">
                            <div className="flex items-center gap-3">
                                <Loader2 className="size-5 animate-spin text-[#284B63]" />
                                <div>
                                    <h2 className="font-semibold text-[#353535]">
                                        Processing Dataset
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Your file is being parsed and profiled
                                        in the background. This page updates
                                        automatically.
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Progress
                                    </span>
                                    <span className="font-medium text-[#353535]">
                                        {currentDataset.processing.progress}%
                                    </span>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-[#E8EDF1]">
                                    <div
                                        className="h-full rounded-full bg-[#284B63] transition-all duration-700"
                                        style={{
                                            width: `${currentDataset.processing.progress}%`,
                                        }}
                                    />
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        {currentDataset.processing.rowsProcessed.toLocaleString()}{' '}
                                        rows processed
                                    </span>
                                    <span className="text-muted-foreground">
                                        Started{' '}
                                        {formatDateTime(
                                            currentDataset.processing.startedAt,
                                        )}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {isFailed && (
                    <Card className="border-[#C62828]">
                        <CardContent className="space-y-3 p-6">
                            <div className="flex items-center gap-3">
                                <AlertTriangle className="size-5 text-[#C62828]" />
                                <div>
                                    <h2 className="font-semibold text-[#C62828]">
                                        Processing Failed
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {currentDataset.processing.error ??
                                            'An unexpected error occurred while processing your dataset.'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <span>
                                    Started{' '}
                                    {formatDateTime(
                                        currentDataset.processing.startedAt,
                                    )}
                                </span>
                                <span>·</span>
                                <span>
                                    Failed{' '}
                                    {formatDateTime(
                                        currentDataset.processing.finishedAt,
                                    )}
                                </span>
                            </div>
                            <Button asChild variant="secondary" size="sm">
                                <Link href="/datasets">
                                    Go back and upload again
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {!isProcessing && !isFailed && (
                    <>
                        {currentDataset.previewNote && (
                            <div className="rounded-lg bg-[#F7F9FA] p-3 text-sm text-muted-foreground">
                                {currentDataset.previewNote}
                            </div>
                        )}

                        {qualityScore && (
                            <QualityScoreCard score={qualityScore} />
                        )}

                        <div className="grid gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">
                            <AttributePanel
                                columns={currentDataset.profile?.columns ?? []}
                                selectedColumn={selectedColumn}
                                onSelectColumn={setSelectedColumn}
                            />
                            <SelectedColumnProfile
                                column={selectedColumnProfile}
                            />
                        </div>

                        <DatasetPreviewTable dataset={currentDataset} />
                        <ProfilePanel profile={currentDataset.profile} />
                        <CleaningPanel dataset={currentDataset} />
                        <ChartPanel dataset={currentDataset} />
                    </>
                )}
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
