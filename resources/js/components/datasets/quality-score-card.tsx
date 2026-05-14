import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    Info,
    ShieldAlert,
    TrendingUp,
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { DatasetQualityScore } from '@/types/datasets';

interface Props {
    score: DatasetQualityScore;
    label?: string;
}

const STATUS_STYLES: Record<
    string,
    { color: string; bg: string; icon: typeof CheckCircle2 }
> = {
    Excellent: {
        color: 'text-[#2E7D32]',
        bg: 'bg-[#E8F5E9]',
        icon: CheckCircle2,
    },
    Good: { color: 'text-[#0284C7]', bg: 'bg-[#E0F2FE]', icon: TrendingUp },
    Fair: { color: 'text-[#F59E0B]', bg: 'bg-[#FEF3C7]', icon: Info },
    Poor: { color: 'text-[#F97316]', bg: 'bg-[#FFF7ED]', icon: AlertTriangle },
    Critical: {
        color: 'text-[#C62828]',
        bg: 'bg-[#FDECEC]',
        icon: ShieldAlert,
    },
};

const BREAKDOWN_LABELS: Record<string, string> = {
    completeness: 'Completeness',
    uniqueness: 'Uniqueness',
    validity: 'Validity',
    consistency: 'Consistency',
    type_accuracy: 'Type Accuracy',
};

function getBarColor(value: number): string {
    if (value >= 90) {
        return 'bg-[#2E7D32]';
    }

    if (value >= 75) {
        return 'bg-[#0284C7]';
    }

    if (value >= 60) {
        return 'bg-[#F59E0B]';
    }

    if (value >= 40) {
        return 'bg-[#F97316]';
    }

    return 'bg-[#C62828]';
}

export default function QualityScoreCard({ score, label }: Props) {
    const statusStyle = STATUS_STYLES[score.status] ?? STATUS_STYLES.Good;
    const StatusIcon = statusStyle.icon;

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-base font-semibold text-[#353535]">
                    {label ?? 'Data Quality Score'}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-5">
                {/* Final score + status */}
                <div className="flex items-center gap-4">
                    <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border-4 border-[#D9D9D9] bg-white">
                        <span className="text-2xl font-bold text-[#353535]">
                            {score.quality_score}
                        </span>
                    </div>
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-2">
                            <span
                                className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${statusStyle.bg} ${statusStyle.color}`}
                            >
                                <StatusIcon className="size-3.5" />
                                {score.status}
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {score.quality_score}/100 — Higher is better.
                        </p>
                    </div>
                </div>

                {/* Breakdown bars */}
                <div className="space-y-3">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Breakdown
                    </p>
                    {Object.entries(score.breakdown).map(([key, value]) => (
                        <div key={key} className="space-y-1">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-[#353535]">
                                    {BREAKDOWN_LABELS[key] ?? key}
                                </span>
                                <span className="font-medium text-[#353535] tabular-nums">
                                    {value}
                                </span>
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-[#E8EDF1]">
                                <div
                                    className={`h-full rounded-full transition-all ${getBarColor(value)}`}
                                    style={{ width: `${Math.max(value, 2)}%` }}
                                />
                            </div>
                        </div>
                    ))}
                </div>

                {/* Issue summary */}
                <div className="space-y-2">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Detected Issues
                    </p>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <IssueBadge
                            label="Missing values"
                            count={score.issues_summary.missing_values}
                        />
                        <IssueBadge
                            label="Duplicate rows"
                            count={score.issues_summary.duplicate_rows}
                        />
                        <IssueBadge
                            label="Invalid values"
                            count={score.issues_summary.invalid_values}
                        />
                        <IssueBadge
                            label="Inconsistent cols"
                            count={score.issues_summary.inconsistent_columns}
                        />
                        <IssueBadge
                            label="Type issues"
                            count={score.issues_summary.type_issue_columns}
                        />
                    </div>
                </div>

                {/* Recommendations */}
                {score.recommendations && score.recommendations.length > 0 && (
                    <div className="space-y-2">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Recommendations
                        </p>
                        <ul className="space-y-1.5">
                            {score.recommendations.map((rec) => (
                                <li
                                    key={rec.code}
                                    className="flex items-start gap-2 text-sm text-[#353535]"
                                >
                                    <AlertCircle className="mt-0.5 size-3.5 shrink-0 text-[#F59E0B]" />
                                    <span>{rec.message}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function IssueBadge({ label, count }: { label: string; count: number }) {
    return (
        <div className="flex items-center justify-between rounded-lg bg-[#F7F9FA] px-3 py-2">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span
                className={`text-sm font-medium tabular-nums ${
                    count > 0 ? 'text-[#F59E0B]' : 'text-[#9CA3AF]'
                }`}
            >
                {count}
            </span>
        </div>
    );
}
