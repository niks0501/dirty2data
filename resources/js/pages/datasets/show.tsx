import { Head, Link, router, usePage } from '@inertiajs/react';
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
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AiCleaningRecommendationsPanel from '@/components/datasets/ai-cleaning-recommendations-panel';
import AttributePanel from '@/components/datasets/attribute-panel';
import ChartPanel from '@/components/datasets/chart-panel';
import CleanedDatasetExportPanel from '@/components/datasets/cleaned-dataset-export-panel';
import CleaningAuditLog from '@/components/datasets/cleaning-audit-log';
import CleaningPanel from '@/components/datasets/cleaning-panel';
import ComparisonPanel from '@/components/datasets/comparison-panel';
import DatasetPreviewTable from '@/components/datasets/dataset-preview-table';
import InsightsPanel from '@/components/datasets/insights-panel';
import ProfilePanel from '@/components/datasets/profile-panel';
import QualityScoreCard from '@/components/datasets/quality-score-card';
import RecipePanel from '@/components/datasets/recipe-panel';
import SelectedColumnProfile from '@/components/datasets/selected-column-profile';
import UndoToolbar from '@/components/datasets/undo-toolbar';
import WorkflowGuide from '@/components/datasets/workflow-guide';
import WorkflowSteps from '@/components/datasets/workflow-steps';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { DatasetPageProps, DatasetQualityScore } from '@/types/datasets';

const TAB_STEPS = ['profile', 'clean', 'analyze', 'visualize'] as const;
type TabStep = (typeof TAB_STEPS)[number];

function tabToStepIndex(tab: TabStep): number {
    return TAB_STEPS.indexOf(tab) + 1; // 1 = Profile, 2 = Clean, 3 = Analyze, 4 = Visualize
}

function stepIndexToTab(step: number): TabStep {
    return TAB_STEPS[Math.min(Math.max(step - 1, 0), TAB_STEPS.length - 1)];
}

interface Props {
    dataset: DatasetPageProps;
    beforeScore: DatasetQualityScore | null;
    afterScore: DatasetQualityScore | null;
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

function computeMaxUnlockedStep(
    status: string,
    cleaningLogLength: number,
): number {
    if (status === 'processing' || status === 'failed') {
        return 0;
    }

    // Always unlock at least Profile (step 1) when data is ready
    // Clean (step 2) is unlocked when the dataset is ready
    // Analyze (step 3) unlocks after any cleaning has been applied
    // Visualize (step 4) unlocks after cleaning log exists (same gate as analyze for now)
    if (cleaningLogLength > 0) {
        return 4; // All steps unlocked after cleaning
    }

    return 2; // Profile + Clean unlocked when dataset is ready
}

export default function Show({ dataset, beforeScore, afterScore }: Props) {
    const [currentDataset, setCurrentDataset] = useState(dataset);
    const [currentBeforeScore, setCurrentBeforeScore] = useState(beforeScore);
    const [currentAfterScore, setCurrentAfterScore] = useState(afterScore);
    const [comparisonVersion, setComparisonVersion] = useState(0);
    const [activeTab, setActiveTab] = useState<TabStep>(() => {
        // If cleaning has been applied, default to Clean tab (showing results)
        if ((dataset.cleaningLog?.length ?? 0) > 0) {
            return 'clean';
        }

        return 'profile';
    });
    const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const page = usePage<Record<string, unknown>>();
    const pageRef = useRef(page);

    useEffect(() => {
        pageRef.current = page;
    });

    const handleDatasetUpdated = useCallback((updated: DatasetPageProps) => {
        setCurrentDataset(updated);
        setCurrentBeforeScore(
            (pageRef.current.props.beforeScore as DatasetQualityScore | null) ??
                null,
        );

        const hasCleaning = (updated.cleaningLog?.length ?? 0) > 0;

        if (hasCleaning) {
            setCurrentAfterScore(null);
        } else {
            setCurrentAfterScore(
                (pageRef.current.props
                    .afterScore as DatasetQualityScore | null) ?? null,
            );
        }

        setComparisonVersion((v) => v + 1);
    }, []);

    const isProcessing = currentDataset.status === 'processing';
    const isFailed = currentDataset.status === 'failed';
    const hasCleaning = (currentDataset.cleaningLog?.length ?? 0) > 0;

    const maxUnlockedStep = computeMaxUnlockedStep(
        currentDataset.status,
        currentDataset.cleaningLog?.length ?? 0,
    );

    const handleStepClick = useCallback(
        (stepIndex: number) => {
            if (stepIndex === 0) {
                // Upload step — navigate to datasets list
                router.get('/datasets');

                return;
            }

            const tab = stepIndexToTab(stepIndex);

            if (stepIndex <= maxUnlockedStep) {
                setActiveTab(tab);
            }
        },
        [maxUnlockedStep],
    );

    // Sync activeTab → currentStep for the stepper display
    const visibleStep = tabToStepIndex(activeTab);
    const displayStep =
        activeTab === 'profile' && !hasCleaning ? 1 : visibleStep;

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
                only: ['dataset', 'beforeScore', 'afterScore'],
                onSuccess: (page) => {
                    const updated = page.props.dataset as DatasetPageProps;

                    setCurrentDataset(updated);
                    setCurrentBeforeScore(
                        (page.props
                            .beforeScore as DatasetQualityScore | null) ?? null,
                    );
                    setCurrentAfterScore(
                        (page.props.afterScore as DatasetQualityScore | null) ??
                            null,
                    );

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

    useEffect(() => {
        const needsScoreRefresh =
            hasCleaning &&
            !currentAfterScore &&
            currentDataset.status === 'ready';

        if (!needsScoreRefresh) {
            return;
        }

        let cancelled = false;
        let attempts = 0;
        const maxAttempts = 15;

        const scorePoll = setInterval(() => {
            if (cancelled) {
                clearInterval(scorePoll);

                return;
            }

            attempts++;

            if (attempts > maxAttempts) {
                clearInterval(scorePoll);

                return;
            }

            router.reload({
                only: ['beforeScore', 'afterScore'],
                onSuccess: (page) => {
                    const newBefore =
                        (page.props
                            .beforeScore as DatasetQualityScore | null) ?? null;
                    const newAfter =
                        (page.props.afterScore as DatasetQualityScore | null) ??
                        null;

                    setCurrentBeforeScore(newBefore);
                    setCurrentAfterScore(newAfter);

                    if (newAfter) {
                        clearInterval(scorePoll);
                    }
                },
            });
        }, 2000);

        return () => {
            cancelled = true;
            clearInterval(scorePoll);
        };
    }, [
        currentDataset.status,
        currentDataset.cleaningLog?.length,
        currentAfterScore,
        hasCleaning,
    ]);

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

                <WorkflowSteps
                    currentStep={displayStep}
                    maxUnlockedStep={maxUnlockedStep}
                    onStepClick={handleStepClick}
                />

                <WorkflowGuide currentStep={displayStep} />

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

                        <Tabs
                            value={activeTab}
                            onValueChange={(value) =>
                                setActiveTab(value as TabStep)
                            }
                        >
                            <TabsList className="w-full justify-start">
                                <TabsTrigger value="profile">
                                    Profile
                                </TabsTrigger>
                                <TabsTrigger value="clean">Clean</TabsTrigger>
                                <TabsTrigger
                                    value="analyze"
                                    disabled={maxUnlockedStep < 3}
                                >
                                    Analyze
                                </TabsTrigger>
                                <TabsTrigger
                                    value="visualize"
                                    disabled={maxUnlockedStep < 4}
                                >
                                    Visualize
                                </TabsTrigger>
                            </TabsList>

                            {/* ── Profile Tab ── */}
                            <TabsContent value="profile" className="space-y-6">
                                {currentBeforeScore && (
                                    <QualityScoreCard
                                        score={currentBeforeScore}
                                        label="Before Cleaning"
                                    />
                                )}

                                <div className="grid gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">
                                    <AttributePanel
                                        columns={
                                            currentDataset.profile?.columns ??
                                            []
                                        }
                                        selectedColumn={selectedColumn}
                                        onSelectColumn={setSelectedColumn}
                                    />
                                    <SelectedColumnProfile
                                        column={selectedColumnProfile}
                                    />
                                </div>

                                <DatasetPreviewTable dataset={currentDataset} />

                                <ProfilePanel
                                    profile={currentDataset.profile}
                                />
                            </TabsContent>

                            {/* ── Clean Tab ── */}
                            <TabsContent value="clean" className="space-y-6">
                                {currentAfterScore && hasCleaning && (
                                    <QualityScoreCard
                                        score={currentAfterScore}
                                        label="After Cleaning"
                                    />
                                )}

                                <UndoToolbar
                                    datasetId={currentDataset.id}
                                    cleaningLog={currentDataset.cleaningLog}
                                    snapshotCount={
                                        currentDataset.cleaningSnapshots
                                            ?.length ?? 0
                                    }
                                    onDatasetUpdated={handleDatasetUpdated}
                                />

                                <AiCleaningRecommendationsPanel
                                    dataset={currentDataset}
                                    onDatasetUpdated={handleDatasetUpdated}
                                />

                                <CleaningPanel
                                    dataset={currentDataset}
                                    onDatasetUpdated={handleDatasetUpdated}
                                />

                                <CleaningAuditLog
                                    log={currentDataset.cleaningLog}
                                />

                                <RecipePanel
                                    dataset={currentDataset}
                                    onDatasetUpdated={setCurrentDataset}
                                />
                            </TabsContent>

                            {/* ── Analyze Tab ── */}
                            <TabsContent value="analyze" className="space-y-6">
                                {currentAfterScore && hasCleaning && (
                                    <QualityScoreCard
                                        score={currentAfterScore}
                                        label="After Cleaning"
                                    />
                                )}

                                {currentBeforeScore && !hasCleaning && (
                                    <QualityScoreCard
                                        score={currentBeforeScore}
                                        label="Before Cleaning"
                                    />
                                )}

                                <ComparisonPanel
                                    datasetId={currentDataset.id}
                                    version={comparisonVersion}
                                />

                                <InsightsPanel
                                    datasetId={currentDataset.id}
                                    version={comparisonVersion}
                                />
                            </TabsContent>

                            {/* ── Visualize Tab ── */}
                            <TabsContent
                                value="visualize"
                                className="space-y-6"
                            >
                                <ChartPanel dataset={currentDataset} />

                                <CleanedDatasetExportPanel
                                    dataset={currentDataset}
                                />
                            </TabsContent>
                        </Tabs>
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
