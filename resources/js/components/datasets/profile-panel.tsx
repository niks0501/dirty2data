import { AlertTriangle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

    return (
        <Card>
            <CardHeader>
                <CardTitle>Data Profile</CardTitle>
                <CardDescription>
                    Rows, columns, types, missing values, uniqueness,
                    duplicates, and basic statistics.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                {profile.duplicate_count > 0 && (
                    <div className="flex items-center gap-2 rounded-lg bg-[#FEF3C7] p-3 text-sm text-[#92400E]">
                        <AlertTriangle className="size-4" />
                        {profile.duplicate_count} duplicate rows detected.
                    </div>
                )}

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-[#F1F3F4]">
                            <tr>
                                <th className="px-3 py-2 text-left">Column</th>
                                <th className="px-3 py-2 text-left">Type</th>
                                <th className="px-3 py-2 text-left">Missing</th>
                                <th className="px-3 py-2 text-left">Unique</th>
                                <th className="px-3 py-2 text-left">Min</th>
                                <th className="px-3 py-2 text-left">Max</th>
                                <th className="px-3 py-2 text-left">Avg</th>
                                <th className="px-3 py-2 text-left">Median</th>
                                <th className="px-3 py-2 text-left">
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
                                        {column.missing_percentage}%)
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
