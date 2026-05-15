import { AlertTriangle, BarChart3, Info } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { DatasetProfile } from '@/types/datasets';

interface Props {
    profile: DatasetProfile | null;
}

function formatStat(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return String(value);
}

function heatmapColor(pct: number): string {
    if (pct > 50) {
        return 'bg-[#C62828]';
    }

    if (pct > 20) {
        return 'bg-[#F97316]';
    }

    if (pct > 5) {
        return 'bg-[#F59E0B]';
    }

    if (pct > 0) {
        return 'bg-[#0284C7]';
    }

    return 'bg-[#2E7D32]';
}

export default function ProfilePanel({ profile }: Props) {
    if (!profile) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Profile</CardTitle>
                    <CardDescription>
                        Upload a dataset first to view profiling results.
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const columnsWithOutliers = profile.columns.filter(
        (col) =>
            col.type === 'numeric' &&
            col.outliers_iqr &&
            col.outliers_iqr.count > 0,
    );

    const columnsWithMissing = profile.columns.filter(
        (col) => col.missing_count > 0,
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>Data Profile</CardTitle>
                <CardDescription>
                    Rows, columns, types, missing values, uniqueness,
                    duplicates, and basic statistics.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-5">
                {profile.duplicate_count > 0 && (
                    <div className="flex items-center gap-2 rounded-lg bg-[#FEF3C7] p-3 text-sm text-[#92400E]">
                        <AlertTriangle className="size-4" />
                        {profile.duplicate_count} duplicate row
                        {profile.duplicate_count !== 1 ? 's' : ''} detected. Use
                        the Clean panel to remove them.
                    </div>
                )}

                {columnsWithOutliers.length > 0 && (
                    <div className="rounded-lg bg-[#E0F2FE] p-3 text-sm">
                        <div className="mb-2 flex items-center gap-2 font-semibold text-[#0284C7]">
                            <Info className="size-4" />
                            Outliers Detected (IQR Method)
                        </div>
                        <div className="grid gap-2 md:grid-cols-2">
                            {columnsWithOutliers.map((col) => (
                                <div
                                    key={col.name}
                                    className="rounded-lg bg-white p-3"
                                >
                                    <p className="font-medium text-[#353535]">
                                        {col.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {col.outliers_iqr!.count} outlier
                                        {col.outliers_iqr!.count !== 1
                                            ? 's'
                                            : ''}{' '}
                                        outside [{col.outliers_iqr!.lower_bound}{' '}
                                        – {col.outliers_iqr!.upper_bound}]
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Q1: {col.outliers_iqr!.q1} | Q3:{' '}
                                        {col.outliers_iqr!.q3}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {columnsWithMissing.length > 0 && (
                    <div className="space-y-2">
                        <div className="flex items-center gap-2 text-sm font-medium text-[#353535]">
                            <BarChart3 className="size-4 text-[#284B63]" />
                            Missing Values Overview
                        </div>
                        <div className="space-y-1.5">
                            {profile.columns.map((col) => {
                                const pct = col.missing_percentage;

                                return (
                                    <div
                                        key={col.name}
                                        className="flex items-center gap-3"
                                    >
                                        <span className="w-32 shrink-0 truncate text-xs text-[#353535]">
                                            {col.name}
                                        </span>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <div className="h-3 flex-1 overflow-hidden rounded-full bg-[#E8EDF1]">
                                                    <div
                                                        className={`h-full rounded-full ${heatmapColor(pct)}`}
                                                        style={{
                                                            width: `${Math.max(pct, 2)}%`,
                                                        }}
                                                    />
                                                </div>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p className="text-xs">
                                                    {col.missing_count} missing
                                                    of{' '}
                                                    {col.missing_count +
                                                        col.unique_count}{' '}
                                                    rows (
                                                    {col.missing_percentage}
                                                    %)
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>
                                        <span
                                            className={`w-12 text-right text-xs font-medium tabular-nums ${
                                                pct > 0
                                                    ? 'text-[#F59E0B]'
                                                    : 'text-[#2E7D32]'
                                            }`}
                                        >
                                            {pct}%
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {columnsWithMissing.length === 0 &&
                    columnsWithOutliers.length === 0 &&
                    profile.duplicate_count === 0 && (
                        <div className="rounded-lg bg-[#E8F5E9] p-3 text-sm text-[#2E7D32]">
                            Your data looks clean! No missing values,
                            duplicates, or outliers detected.
                        </div>
                    )}

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-[#F1F3F4]">
                            <tr>
                                <th className="sticky top-0 bg-[#F1F3F4] px-3 py-2 text-left">
                                    Column
                                </th>
                                <th className="sticky top-0 bg-[#F1F3F4] px-3 py-2 text-left">
                                    Type
                                </th>
                                <th className="sticky top-0 bg-[#F1F3F4] px-3 py-2 text-left">
                                    Missing
                                </th>
                                <th className="sticky top-0 bg-[#F1F3F4] px-3 py-2 text-left">
                                    Unique
                                </th>
                                <th className="sticky top-0 bg-[#F1F3F4] px-3 py-2 text-left">
                                    Min
                                </th>
                                <th className="sticky top-0 bg-[#F1F3F4] px-3 py-2 text-left">
                                    Max
                                </th>
                                <th className="sticky top-0 bg-[#F1F3F4] px-3 py-2 text-left">
                                    Avg
                                </th>
                                <th className="sticky top-0 bg-[#F1F3F4] px-3 py-2 text-left">
                                    Median
                                </th>
                                <th className="sticky top-0 bg-[#F1F3F4] px-3 py-2 text-left">
                                    Most Frequent
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {profile.columns.map((column) => (
                                <tr key={column.name} className="border-t">
                                    <td className="px-3 py-2 font-medium whitespace-nowrap">
                                        {column.name}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge variant="secondary">
                                            {column.type}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2">
                                        {column.missing_count} (
                                        {column.missing_percentage}
                                        %)
                                    </td>
                                    <td className="px-3 py-2">
                                        {column.unique_count}
                                    </td>
                                    <td className="px-3 py-2">
                                        {formatStat(column.minimum)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {formatStat(column.maximum)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {formatStat(column.average)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {formatStat(column.median)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {column.most_frequent
                                            ? `${column.most_frequent.value} (${column.most_frequent.count})`
                                            : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}
