import { AlertTriangle, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface MissingValueMatrixData {
    headers: string[];
    matrix: number[][];
    rowCount: number;
    totalRows: number;
}

interface Props {
    datasetId: number;
}

// Red = missing, light gray = present
const MISSING_COLOR = '#C62828';
const PRESENT_COLOR = '#E8EDF1';

export default function MissingValueHeatmap({ datasetId }: Props) {
    const [data, setData] = useState<MissingValueMatrixData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function fetchData() {
            setLoading(true);
            setError(null);

            try {
                const res = await fetch(
                    `/datasets/${datasetId}/missing-values`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (!res.ok) {
                    throw new Error('Failed to load missing value data');
                }

                const json = (await res.json()) as MissingValueMatrixData;

                if (!cancelled) {
                    setData(json);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err instanceof Error
                            ? err.message
                            : 'Failed to load missing value data',
                    );
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        }

        fetchData();

        return () => {
            cancelled = true;
        };
    }, [datasetId]);

    if (loading) {
        return (
            <Card>
                <CardContent className="flex h-[340px] items-center justify-center p-6">
                    <Loader2 className="size-6 animate-spin text-[#284B63]" />
                </CardContent>
            </Card>
        );
    }

    if (error) {
        return (
            <Card>
                <CardContent className="flex h-[340px] items-center justify-center p-6">
                    <div className="text-center">
                        <AlertTriangle className="mx-auto mb-3 size-8 text-[#F59E0B]" />
                        <p className="text-muted-foreground">{error}</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (!data || data.headers.length === 0 || data.matrix.length === 0) {
        return (
            <Card>
                <CardContent className="flex h-[340px] items-center justify-center p-6">
                    <div className="text-center">
                        <AlertTriangle className="mx-auto mb-3 size-8 text-[#F59E0B]" />
                        <p className="text-muted-foreground">
                            No data available for missing value analysis.
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    const hasAnyMissing = data.matrix.some((row) =>
        row.some((cell) => cell === 1),
    );

    if (!hasAnyMissing) {
        return (
            <Card>
                <CardContent className="flex h-[340px] items-center justify-center p-6">
                    <div className="text-center">
                        <p className="text-lg font-medium text-[#2E7D32]">
                            No missing values detected
                        </p>
                        <p className="text-sm text-muted-foreground">
                            All cells in the sampled dataset contain values.
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    const cellW = Math.min(
        20,
        Math.max(4, Math.floor(800 / data.headers.length)),
    );
    const cellH = Math.min(
        20,
        Math.max(4, Math.floor(300 / data.matrix.length)),
    );
    const totalW = cellW * data.headers.length;
    const totalH = cellH * data.matrix.length;

    const missingCount = data.matrix.reduce(
        (sum, row) => sum + row.reduce((s, cell) => s + cell, 0),
        0,
    );
    const totalCells = data.matrix.length * data.headers.length;
    const missingPct = ((missingCount / totalCells) * 100).toFixed(1);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Missing Value Matrix</CardTitle>
                <CardDescription>
                    Red cells indicate missing values.{' '}
                    {data.rowCount < data.totalRows
                        ? `Showing ${data.rowCount} of ${data.totalRows.toLocaleString()} rows. `
                        : ''}
                    {missingCount} missing ({missingPct}%).
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="overflow-auto">
                    <div style={{ minWidth: totalW }}>
                        {/* Column headers */}
                        <div className="mb-1 flex" style={{ paddingLeft: 0 }}>
                            {data.headers.map((header) => (
                                <div
                                    key={header}
                                    className="overflow-hidden text-[10px] text-ellipsis whitespace-nowrap text-muted-foreground"
                                    style={{ width: cellW }}
                                    title={header}
                                >
                                    {cellW > 30 ? header : ''}
                                </div>
                            ))}
                        </div>
                        {/* Matrix cells */}
                        <div
                            className="rounded border"
                            style={{
                                width: totalW,
                                height: totalH,
                                display: 'grid',
                                gridTemplateColumns: `repeat(${data.headers.length}, ${cellW}px)`,
                            }}
                        >
                            {data.matrix.flatMap((row, rowIdx) =>
                                row.map((cell, colIdx) => (
                                    <div
                                        key={`${rowIdx}-${colIdx}`}
                                        style={{
                                            width: cellW,
                                            height: cellH,
                                            backgroundColor:
                                                cell === 1
                                                    ? MISSING_COLOR
                                                    : PRESENT_COLOR,
                                        }}
                                        title={
                                            cell === 1
                                                ? `Row ${rowIdx + 1}, ${data.headers[colIdx]}: Missing`
                                                : `Row ${rowIdx + 1}, ${data.headers[colIdx]}: Present`
                                        }
                                    />
                                )),
                            )}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
