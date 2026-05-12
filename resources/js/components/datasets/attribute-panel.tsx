import { AlertTriangle, CheckCircle2 } from 'lucide-react';
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
    columns: DatasetColumnProfile[];
    selectedColumn: string | null;
    onSelectColumn: (column: string) => void;
}

export default function AttributePanel({
    columns,
    selectedColumn,
    onSelectColumn,
}: Props) {
    return (
        <Card className="h-full max-h-[calc(100vh-16rem)]">
            <CardHeader className="shrink-0">
                <CardTitle>Columns</CardTitle>
                <CardDescription>
                    Select a column to inspect its
                    quality and chart recommendation.
                </CardDescription>
            </CardHeader>

            <CardContent className="flex-1 space-y-2 overflow-y-auto min-h-0">
                {columns.length > 0 ? (
                    columns.map((column) => {
                        const hasIssue = column.missing_count > 0;
                        const isSelected = column.name === selectedColumn;

                        return (
                            <button
                                key={column.name}
                                type="button"
                                className={[
                                    'w-full rounded-xl border p-3 text-left transition-colors',
                                    isSelected
                                        ? 'border-[#284B63] bg-[#E7F0F5]'
                                        : 'hover:bg-[#F7F9FA]',
                                ].join(' ')}
                                onClick={() => onSelectColumn(column.name)}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium text-[#353535]">
                                            {column.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {column.unique_count} unique ·{' '}
                                            {column.missing_count} missing
                                        </p>
                                    </div>

                                    {hasIssue ? (
                                        <AlertTriangle className="size-4 shrink-0 text-[#F59E0B]" />
                                    ) : (
                                        <CheckCircle2 className="size-4 shrink-0 text-[#2E7D32]" />
                                    )}
                                </div>

                                <div className="mt-3 flex flex-wrap gap-2">
                                    <Badge variant="secondary">
                                        {column.type}
                                    </Badge>
                                    {hasIssue ? (
                                        <Badge variant="outline">
                                            Needs review
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline">Clean</Badge>
                                    )}
                                </div>
                            </button>
                        );
                    })
                ) : (
                    <div className="rounded-lg bg-[#F7F9FA] p-4 text-sm text-muted-foreground">
                        No columns are available. Upload a dataset with a header
                        row first.
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
