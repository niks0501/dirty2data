import {
    AlertTriangle,
    ChevronRight,
    Clock,
    Gem,
    Info,
    Lightbulb,
    Loader2,
    Sparkles,
    TrendingUp,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DatasetInsight, DatasetInsightsPayload } from '@/types/datasets';

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

interface Props {
    datasetId: number;
    version?: number;
}

const categoryConfig: Record<
    string,
    { icon: typeof Lightbulb; label: string; color: string }
> = {
    business_opportunity: {
        icon: TrendingUp,
        label: 'Opportunity',
        color: 'text-[#2E7D32]',
    },
    data_action: {
        icon: ChevronRight,
        label: 'Action Needed',
        color: 'text-[#0284C7]',
    },
    risk_flag: {
        icon: AlertTriangle,
        label: 'Watch Out',
        color: 'text-[#F59E0B]',
    },
    trend_interpretation: {
        icon: TrendingUp,
        label: 'What This Means',
        color: 'text-[#6D5BD0]',
    },
    anomaly_alert: {
        icon: AlertTriangle,
        label: 'Unusual Finding',
        color: 'text-[#C62828]',
    },
    general: {
        icon: Lightbulb,
        label: 'General',
        color: 'text-[#353535]',
    },
    ai_general: {
        icon: Sparkles,
        label: 'AI Insight',
        color: 'text-[#6D5BD0]',
    },
};

const severityStyles: Record<string, string> = {
    info: 'border-l-[#0284C7]',
    warning: 'border-l-[#F59E0B]',
    positive: 'border-l-[#2E7D32]',
    neutral: 'border-l-[#D9D9D9]',
    critical: 'border-l-[#C62828]',
};

const severityBadgeStyles: Record<string, string> = {
    info: 'bg-[#E0F2FE] text-[#0284C7]',
    warning: 'bg-[#FEF3C7] text-[#F59E0B]',
    positive: 'bg-[#E8F5E9] text-[#2E7D32]',
    neutral: 'bg-[#F7F9FA] text-[#6B7280]',
    critical: 'bg-[#FDECEC] text-[#C62828]',
};

function formatGeneratedAt(iso: string): string {
    const date = new Date(iso);

    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function InsightsPanel({ datasetId, version = 0 }: Props) {
    const [aiInsights, setAiInsights] = useState<DatasetInsight[]>([]);
    const [executiveSummary, setExecutiveSummary] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [generatingAi, setGeneratingAi] = useState(false);
    const [aiError, setAiError] = useState<string | null>(null);
    const [generatedAt, setGeneratedAt] = useState<string | null>(null);
    const [hasExistingInsights, setHasExistingInsights] = useState(false);

    useEffect(() => {
        let cancelled = false;

        async function fetchInsights() {
            setLoading(true);
            setError(null);

            try {
                const response = await fetch(
                    `/datasets/${datasetId}/insights`,
                    {
                        headers: { Accept: 'application/json' },
                    },
                );

                if (!response.ok) {
                    throw new Error(
                        `Failed to load insights (${response.status})`,
                    );
                }

                const data: DatasetInsightsPayload = await response.json();

                if (!cancelled) {
                    const insights = data.ai_insights ?? [];
                    setAiInsights(insights);
                    setExecutiveSummary(data.summary ?? '');
                    setGeneratedAt(data.generated_at ?? null);
                    setHasExistingInsights(
                        data.has_insights ?? insights.length > 0,
                    );
                }
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err instanceof Error
                            ? err.message
                            : 'An unexpected error occurred while loading insights.',
                    );
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        }

        fetchInsights();

        return () => {
            cancelled = true;
        };
    }, [datasetId, version]);

    async function handleGenerateAi() {
        setGeneratingAi(true);
        setAiError(null);

        try {
            const response = await fetch(`/datasets/${datasetId}/insights/ai`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });

            if (!response.ok) {
                if (response.status === 429) {
                    throw new Error(
                        'Please wait a moment before generating again.',
                    );
                }

                const body = await response.json().catch(() => ({}));

                throw new Error(
                    (body as { message?: string }).message ??
                        `Generation failed (${response.status})`,
                );
            }

            const data: DatasetInsightsPayload = await response.json();

            const insights = data.ai_insights ?? [];
            setAiInsights(insights);
            setExecutiveSummary(data.summary ?? '');
            setGeneratedAt(data.generated_at ?? null);
            setHasExistingInsights(data.has_insights ?? insights.length > 0);
        } catch (err) {
            setAiError(
                err instanceof Error
                    ? err.message
                    : 'Something went wrong while generating insights.',
            );
        } finally {
            setGeneratingAi(false);
        }
    }

    if (loading) {
        return (
            <Card>
                <CardContent className="flex items-center justify-center gap-3 py-12">
                    <Loader2 className="size-5 animate-spin text-[#284B63]" />
                    <p className="text-sm text-muted-foreground">
                        Loading insights...
                    </p>
                </CardContent>
            </Card>
        );
    }

    if (error) {
        return (
            <Card>
                <CardContent className="flex items-center justify-center gap-3 py-12">
                    <AlertTriangle className="size-5 shrink-0 text-[#C62828]" />
                    <p className="text-sm text-[#C62828]">{error}</p>
                </CardContent>
            </Card>
        );
    }

    const hasInsights = aiInsights.length > 0;

    if (!hasExistingInsights) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Lightbulb className="size-5 text-[#F59E0B]" />
                        Business Insights
                    </CardTitle>
                    <CardDescription>
                        Get plain-English advice about what your data means for
                        your business and what to do next.
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-4">
                    <div className="flex flex-col items-center justify-center gap-3 py-8 text-center">
                        <Gem className="size-10 text-[#D9D9D9]" />
                        <div className="space-y-1">
                            <p className="text-sm font-medium text-[#353535]">
                                No insights yet
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Click the button below to get easy-to-understand
                                advice about your data.
                            </p>
                        </div>
                    </div>

                    <div className="border-t pt-4">
                        <Button
                            variant="default"
                            className="w-full"
                            disabled={generatingAi}
                            onClick={() => void handleGenerateAi()}
                        >
                            {generatingAi ? (
                                <>
                                    <Loader2 className="mr-2 size-4 animate-spin" />
                                    Analyzing your data...
                                </>
                            ) : (
                                <>
                                    <Sparkles className="mr-2 size-4" />
                                    Get Business Insights
                                </>
                            )}
                        </Button>

                        {aiError && (
                            <div className="mt-3 flex items-start gap-3 rounded-lg bg-[#FDECEC] p-3 text-sm text-[#C62828]">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                <div className="flex-1">
                                    <span>{aiError}</span>
                                    <div className="mt-2 flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                void handleGenerateAi()
                                            }
                                            disabled={generatingAi}
                                        >
                                            Try Again
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setAiError(null)}
                                        >
                                            <X className="mr-1 size-3" />
                                            Dismiss
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <CardTitle className="flex items-center gap-2">
                            <Sparkles className="size-5 text-[#6D5BD0]" />
                            Business Insights
                        </CardTitle>
                        <CardDescription>
                            Plain-English advice to help you make better
                            decisions
                        </CardDescription>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                        {generatedAt && (
                            <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                <Clock className="size-3" />
                                {formatGeneratedAt(generatedAt)}
                            </span>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={generatingAi}
                            onClick={() => void handleGenerateAi()}
                        >
                            {generatingAi ? (
                                <>
                                    <Loader2 className="mr-2 size-4 animate-spin" />
                                    Generating...
                                </>
                            ) : (
                                <>
                                    <Sparkles className="mr-2 size-4" />
                                    Regenerate
                                </>
                            )}
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <CardContent className="space-y-6">
                {aiError && (
                    <div className="flex items-start gap-3 rounded-lg bg-[#FDECEC] p-3 text-sm text-[#C62828]">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <div className="flex-1">
                            <span>{aiError}</span>
                            <div className="mt-2 flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => void handleGenerateAi()}
                                    disabled={generatingAi}
                                >
                                    Try Again
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setAiError(null)}
                                >
                                    <X className="mr-1 size-3" />
                                    Dismiss
                                </Button>
                            </div>
                        </div>
                    </div>
                )}

                {executiveSummary && (
                    <div className="rounded-lg bg-[#E0F2FE] p-4 text-sm text-[#353535]">
                        <div className="mb-1 flex items-center gap-2 font-semibold text-[#0284C7]">
                            <Info className="size-4" />
                            Quick Summary
                        </div>
                        <p>{executiveSummary}</p>
                    </div>
                )}

                {hasInsights && (
                    <div className="grid gap-3">
                        {aiInsights.map((insight) => {
                            const config =
                                categoryConfig[insight.category] ??
                                categoryConfig.general;
                            const Icon = config.icon;

                            return (
                                <div
                                    key={insight.id}
                                    className={`rounded-lg border border-l-4 bg-white p-4 ${severityStyles[insight.severity] ?? severityStyles.info}`}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1 space-y-2">
                                            <div className="flex items-center gap-2">
                                                <Icon
                                                    className={`size-4 ${config.color}`}
                                                />
                                                <span className="text-xs font-medium text-muted-foreground">
                                                    {config.label}
                                                </span>
                                            </div>

                                            <h4 className="text-sm font-semibold text-[#353535]">
                                                {insight.title}
                                            </h4>

                                            <p className="text-sm leading-relaxed text-muted-foreground">
                                                {insight.description}
                                            </p>

                                            {(insight.related_column ||
                                                insight.business_impact) && (
                                                <div className="flex flex-wrap gap-2 pt-1">
                                                    {insight.related_column && (
                                                        <span className="inline-block rounded-md bg-[#F7F9FA] px-2 py-0.5 text-xs text-[#6B7280]">
                                                            Column:{' '}
                                                            {
                                                                insight.related_column
                                                            }
                                                        </span>
                                                    )}
                                                    {insight.business_impact && (
                                                        <span className="inline-block rounded-md bg-[#FEF3C7] px-2 py-0.5 text-xs text-[#92400E]">
                                                            {
                                                                insight.business_impact
                                                            }
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        <span
                                            className={`inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${severityBadgeStyles[insight.severity] ?? severityBadgeStyles.info}`}
                                        >
                                            <Info className="size-3" />
                                            {insight.severity}
                                        </span>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
