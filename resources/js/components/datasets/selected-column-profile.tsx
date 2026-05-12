import { BarChart3 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DatasetColumnProfile } from '@/types/datasets';

interface Props {
    column: DatasetColumnProfile | null;
}

function formatValue(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

export default function SelectedColumnProfile({ column }: Props) {
    if (!column) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Column Profile</CardTitle>
                    <CardDescription>
                        Select a column to view type, missing values, distinct
                        values, samples, and statistics.
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const stats = [
        ['Missing', `${column.missing_count} (${column.missing_percentage}%)`],
        ['Unique', column.unique_count.toLocaleString()],
        ['Minimum', formatValue(column.minimum)],
        ['Maximum', formatValue(column.maximum)],
        ['Mean', formatValue(column.average)],
        ['Median', formatValue(column.median)],
        ['Mode', formatValue(column.mode)],
    ];

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle>{column.name}</CardTitle>
                        <CardDescription>
                            Selected-column profile from the cleaned working
                            dataset.
                        </CardDescription>
                    </div>
                    <Badge variant="secondary">{column.type}</Badge>
                </div>
            </CardHeader>

            <CardContent className="space-y-5">
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {stats.map(([label, value]) => (
                        <div key={label} className="rounded-xl border p-3">
                            <p className="text-xs text-muted-foreground">
                                {label}
                            </p>
                            <p className="mt-1 font-medium text-[#353535]">
                                {value}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-xl border p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <BarChart3 className="size-4 text-[#284B63]" />
                            <h3 className="font-semibold">Distinct values</h3>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {column.distinct_values.length > 0 ? (
                                column.distinct_values.map((value) => (
                                    <Badge key={value.value} variant="outline">
                                        {value.value} ({value.count})
                                    </Badge>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No distinct values found.
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="rounded-xl border p-4">
                        <h3 className="mb-3 font-semibold">Sample values</h3>
                        <div className="flex flex-wrap gap-2">
                            {column.sample_values.length > 0 ? (
                                column.sample_values.map((value) => (
                                    <Badge key={value} variant="secondary">
                                        {value}
                                    </Badge>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No sample values found.
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
