import {
    AlertTriangle,
    ArrowLeftRight,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Info,
    Loader2,
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
import type { ComparisonPayload, ComparisonRow } from '@/types/datasets';

interface Props {
    datasetId: number;
    version?: number;
}

interface StatCardData {
    label: string;
    value: number;
    icon: typeof ArrowLeftRight;
    color: string;
    bg: string;
}

function buildStatCards(
    summary: ComparisonPayload['summary'] | null,
): StatCardData[] {
    if (!summary) {
        return [];
    }

    return [
        {
            label: 'Rows Modified',
            value: summary.rowsModified,
            icon: ArrowLeftRight,
            color: 'text-[#92400E]',
            bg: 'bg-[#FEF3C7]',
        },
        {
            label: 'Cells Changed',
            value: summary.cellsChanged,
            icon: CheckCircle2,
            color: 'text-[#2E7D32]',
            bg: 'bg-[#E8F5E9]',
        },
        {
            label: 'Duplicates Removed',
            value: summary.duplicatesRemoved,
            icon: AlertTriangle,
            color: 'text-[#C62828]',
            bg: 'bg-[#FDECEC]',
        },
        {
            label: 'Missing Filled',
            value: summary.missingValuesFilled,
            icon: Info,
            color: 'text-[#0284C7]',
            bg: 'bg-[#E0F2FE]',
        },
    ];
}

function extractColumnNames(rows: ComparisonRow[]): string[] {
    if (rows.length === 0) {
        return [];
    }

    return rows[0].cells.map((cell) => cell.header);
}

function getCellValue(
    row: ComparisonRow,
    columnName: string,
): string {
    const cell = row.cells.find((c) => c.header === columnName);

    if (!cell || cell.value === null || cell.value === undefined) {
        return '—';
    }

    return String(cell.value);
}

function isCellChanged(row: ComparisonRow, columnName: string): boolean {
    const cell = row.cells.find((c) => c.header === columnName);

    return cell?.changed ?? false;
}

export default function ComparisonPanel({ datasetId, version = 0 }: Props) {
    const [comparison, setComparison] = useState<ComparisonPayload | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [page, setPage] = useState(1);

    useEffect(() => {
        let cancelled = false;

        const load = async () => {
            setLoading(true);
            setError(null);

            try {
                const response = await fetch(
                    `/datasets/${datasetId}/comparison?page=${page}`,
                );

                if (!response.ok) {
                    throw new Error(
                        `Failed to load comparison data (${response.status})`,
                    );
                }

                const data: ComparisonPayload = await response.json();

                if (!cancelled) {
                    setComparison(data);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err instanceof Error
                            ? err.message
                            : 'Failed to load comparison data',
                    );
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
    }, [datasetId, page, version]);

    const statCards = buildStatCards(comparison?.summary ?? null);
    const columnNames = comparison ? extractColumnNames(comparison.rows) : [];
    const totalPages = comparison?.pagination.lastPage ?? 1;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Original vs Cleaned Comparison</CardTitle>
                <CardDescription>
                    Compare the original data with the cleaned result. Changed
                    cells are highlighted with a green background.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-5">
                {loading && (
                    <div className="flex items-center gap-3 py-8">
                        <Loader2 className="size-5 animate-spin text-[#284B63]" />
                        <p className="text-sm text-muted-foreground">
                            Loading comparison data...
                        </p>
                    </div>
                )}

                {!loading && error && (
                    <div className="flex items-center gap-2 rounded-lg bg-[#FDECEC] p-4 text-sm text-[#C62828]">
                        <AlertTriangle className="size-4 shrink-0" />
                        <span>{error}</span>
                    </div>
                )}

                {!loading &&
                    !error &&
                    comparison &&
                    comparison.rows.length === 0 && (
                        <div className="rounded-lg bg-[#F1F3F4] p-6 text-center text-sm text-muted-foreground">
                            <p>No comparison data available yet. Apply cleaning operations first.</p>
                        </div>
                    )}

                {!loading && !error && comparison && comparison.rows.length > 0 && (
                    <>
                        {/* Summary stats */}
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {statCards.map((card) => (
                                <div
                                    key={card.label}
                                    className="flex items-center gap-3 rounded-lg border p-4"
                                >
                                    <div
                                        className={`flex size-10 shrink-0 items-center justify-center rounded-full ${card.bg}`}
                                    >
                                        <card.icon
                                            className={`size-5 ${card.color}`}
                                        />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-xs text-muted-foreground">
                                            {card.label}
                                        </p>
                                        <p className="text-lg font-semibold text-[#353535] tabular-nums">
                                            {card.value.toLocaleString()}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Comparison table */}
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 z-10 bg-[#F1F3F4]">
                                    <tr>
                                        <th className="w-12 px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                                            #
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                                            Status
                                        </th>
                                        {columnNames.map((colName) => (
                                            <th
                                                key={colName}
                                                className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium text-muted-foreground"
                                            >
                                                {colName}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {comparison.rows.map((row) => {
                                        const rowStyle =
                                            row.status === 'modified'
                                                ? 'bg-[#FEF3C7]/30'
                                                : row.status === 'removed'
                                                  ? 'bg-[#FDECEC]/30'
                                                  : row.status === 'added'
                                                    ? 'bg-[#E8F5E9]/30'
                                                    : '';

                                        return (
                                            <tr
                                                key={`${row.rowNumber}-${row.status}`}
                                                className={`border-t transition-colors hover:bg-[#F7F9FA] ${rowStyle}`}
                                            >
                                                <td className="px-3 py-2 text-xs text-muted-foreground">
                                                    {row.rowNumber}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <span
                                                        className="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                                        style={{
                                                            backgroundColor:
                                                                row.status ===
                                                                    'modified'
                                                                    ? '#FEF3C7'
                                                                    : row.status ===
                                                                        'removed'
                                                                      ? '#FDECEC'
                                                                      : row.status ===
                                                                          'added'
                                                                        ? '#E8F5E9'
                                                                        : '#E0F2FE',
                                                            color:
                                                                row.status ===
                                                                    'modified'
                                                                    ? '#92400E'
                                                                    : row.status ===
                                                                        'removed'
                                                                      ? '#C62828'
                                                                      : row.status ===
                                                                          'added'
                                                                        ? '#2E7D32'
                                                                        : '#0284C7',
                                                        }}
                                                    >
                                                        {row.status}
                                                    </span>
                                                </td>
                                                {columnNames.map(
                                                    (colName) => {
                                                        const changed =
                                                            isCellChanged(
                                                                row,
                                                                colName,
                                                            );
                                                        const value =
                                                            getCellValue(
                                                                row,
                                                                colName,
                                                            );

                                                        return (
                                                            <td
                                                                key={colName}
                                                                className={`max-w-[260px] truncate whitespace-nowrap px-3 py-2 ${
                                                                    changed
                                                                        ? 'border border-[#2E7D32]/20 bg-[#E8F5E9]'
                                                                        : ''
                                                                }`}
                                                            >
                                                                {value}
                                                            </td>
                                                        );
                                                    },
                                                )}
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {totalPages > 1 && (
                            <div className="flex items-center justify-between">
                                <p className="text-xs text-muted-foreground">
                                    Page {page} of {totalPages}
                                </p>
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={page <= 1}
                                        onClick={() =>
                                            setPage((prev) =>
                                                Math.max(prev - 1, 1),
                                            )
                                        }
                                    >
                                        <ChevronLeft className="size-4" />
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={page >= totalPages}
                                        onClick={() =>
                                            setPage((prev) =>
                                                Math.min(
                                                    prev + 1,
                                                    totalPages,
                                                ),
                                            )
                                        }
                                    >
                                        Next
                                        <ChevronRight className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    );
}
