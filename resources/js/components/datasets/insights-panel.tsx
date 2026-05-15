import {
    AlertTriangle,
    BarChart3,
    GitBranch,
    Info,
    Lightbulb,
    Loader2,
    PieChart,
    Shield,
    TrendingUp,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DatasetInsight, DatasetInsightsPayload } from '@/types/datasets';

interface Props {
    datasetId: number;
}

const categoryConfig: Record<
    string,
    { icon: typeof TrendingUp; label: string; color: string }
> = {
    trend: { icon: TrendingUp, label: 'Trends & Patterns', color: 'text-[#284B63]' },
    distribution: { icon: BarChart3, label: 'Distributions', color: 'text-[#3C6E71]' },
    outlier: { icon: AlertTriangle, label: 'Outliers', color: 'text-[#F59E0B]' },
    correlation: { icon: GitBranch, label: 'Correlations', color: 'text-[#6D5BD0]' },
    segment: { icon: PieChart, label: 'Key Segments', color: 'text-[#C2416D]' },
    quality: { icon: Shield, label: 'Data Quality', color: 'text-[#0284C7]' },
    general: { icon: Lightbulb, label: 'General Insights', color: 'text-[#353535]' },
};

const severityStyles: Record<string, string> = {
    info: 'border-l-[#0284C7]',
    warning: 'border-l-[#F59E0B]',
    positive: 'border-l-[#2E7D32]',
    neutral: 'border-l-[#D9D9D9]',
};

const severityBadgeStyles: Record<string, string> = {
    info: 'bg-[#E0F2FE] text-[#0284C7]',
    warning: 'bg-[#FEF3C7] text-[#F59E0B]',
    positive: 'bg-[#E8F5E9] text-[#2E7D32]',
    neutral: 'bg-[#F7F9FA] text-[#6B7280]',
};

function groupInsightsByCategory(
    insights: DatasetInsight[],
): Map<string, DatasetInsight[]> {
    const groups = new Map<string, DatasetInsight[]>();

    for (const insight of insights) {
        const existing = groups.get(insight.category);

        if (existing) {
            existing.push(insight);
        } else {
            groups.set(insight.category, [insight]);
        }
    }

    return groups;
}

export default function InsightsPanel({ datasetId }: Props) {
    const [insightsPayload, setInsightsPayload] =
        useState<DatasetInsightsPayload | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

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
                    setInsightsPayload(data);
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
    }, [datasetId]);

    if (loading) {
        return (
            <Card>
                <CardContent className="flex items-center justify-center gap-3 py-12">
                    <Loader2 className="size-5 animate-spin text-[#284B63]" />
                    <p className="text-sm text-muted-foreground">
                        Analyzing dataset patterns...
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

    if (
        !insightsPayload ||
        insightsPayload.insights.length === 0
    ) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center gap-2 py-12 text-center">
                    <Lightbulb className="size-8 text-[#D9D9D9]" />
                    <p className="text-sm text-muted-foreground">
                        No insights available yet. Insights are generated from
                        your dataset profile and cleaned data.
                    </p>
                </CardContent>
            </Card>
        );
    }

    const grouped = groupInsightsByCategory(insightsPayload.insights);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Dataset Insights</CardTitle>
                <CardDescription>
                    Auto-generated plain-language insights derived from your
                    dataset profile and cleaned data.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-6">
                {Array.from(grouped.entries()).map(([category, insights]) => {
                    const config = categoryConfig[category] ?? categoryConfig.general;
                    const Icon = config.icon;

                    return (
                        <div key={category} className="space-y-3">
                            <div className="flex items-center gap-2">
                                <Icon className={`size-5 ${config.color}`} />
                                <h3 className={`text-sm font-semibold ${config.color}`}>
                                    {config.label}
                                </h3>
                            </div>

                            <div className="grid gap-3">
                                {insights.map((insight) => (
                                    <div
                                        key={insight.id}
                                        className={`rounded-lg border bg-white p-4 border-l-4 ${severityStyles[insight.severity] ?? severityStyles.neutral}`}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0 flex-1 space-y-1.5">
                                                <h4 className="text-sm font-semibold text-[#353535]">
                                                    {insight.title}
                                                </h4>
                                                <p className="text-sm text-muted-foreground">
                                                    {insight.description}
                                                </p>
                                                {insight.related_column && (
                                                    <span className="inline-block rounded-md bg-[#F7F9FA] px-2 py-0.5 text-xs text-[#6B7280]">
                                                        Column:{' '}
                                                        {insight.related_column}
                                                    </span>
                                                )}
                                            </div>

                                            <span
                                                className={`inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${severityBadgeStyles[insight.severity] ?? severityBadgeStyles.neutral}`}
                                            >
                                                <Info className="size-3" />
                                                {insight.severity}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}
