import { ResponsiveHeatMap } from '@nivo/heatmap';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface CorrelationData {
    columns: string[];
    matrix: Array<Array<number | null>>;
}

interface Props {
    datasetId: number;
}

interface Props {
    datasetId: number;
}

export default function CorrelationHeatmap({ datasetId }: Props) {
    const [data, setData] = useState<CorrelationData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function fetchData() {
            setLoading(true);
            setError(null);

            try {
                const res = await fetch(`/datasets/${datasetId}/correlation`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!res.ok) {
                    throw new Error('Failed to load correlation data');
                }

                const json = (await res.json()) as CorrelationData;

                if (!cancelled) {
                    setData(json);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err instanceof Error
                            ? err.message
                            : 'Failed to load correlation data',
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

    if (!data || data.columns.length < 2 || data.matrix.length === 0) {
        return (
            <Card>
                <CardContent className="flex h-[340px] items-center justify-center p-6">
                    <div className="text-center">
                        <AlertTriangle className="mx-auto mb-3 size-8 text-[#F59E0B]" />
                        <p className="text-muted-foreground">
                            Need at least 2 numeric columns for a correlation
                            heatmap.
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    const heatmapData = data.columns.map((col, i) => ({
        id: col,
        data: data.columns.map((colJ, j) => ({
            x: colJ,
            y: data.matrix[i]?.[j] ?? null,
        })),
    }));

    const hasValues = data.matrix.some((row) =>
        row.some((v) => v !== null && v !== undefined),
    );

    if (!hasValues) {
        return (
            <Card>
                <CardContent className="flex h-[340px] items-center justify-center p-6">
                    <div className="text-center">
                        <AlertTriangle className="mx-auto mb-3 size-8 text-[#F59E0B]" />
                        <p className="text-muted-foreground">
                            Not enough numeric data to compute correlations.
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Correlation Heatmap</CardTitle>
                <CardDescription>
                    Pairwise Pearson correlation between numeric columns. Red =
                    negative, blue = positive.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="h-[340px]">
                    <ResponsiveHeatMap
                        data={heatmapData}
                        margin={{ top: 20, right: 40, bottom: 60, left: 120 }}
                        valueFormat=">+.2f"
                        axisTop={{
                            tickSize: 5,
                            tickPadding: 5,
                            tickRotation: -45,
                            legend: '',
                            legendOffset: 46,
                        }}
                        axisRight={null}
                        axisBottom={{
                            tickSize: 5,
                            tickPadding: 5,
                            tickRotation: -45,
                        }}
                        axisLeft={{
                            tickSize: 5,
                            tickPadding: 5,
                            tickRotation: 0,
                        }}
                        colors={{
                            type: 'diverging',
                            scheme: 'red_yellow_blue',
                            divergeAt: 0.5,
                            minValue: -1,
                            maxValue: 1,
                        }}
                        emptyColor="#F1F3F4"
                        borderColor="#FFFFFF"
                        borderWidth={1}
                        enableLabels
                        labelTextColor={{
                            from: 'color',
                            modifiers: [['darker', 1.4]],
                        }}
                        legends={[
                            {
                                anchor: 'right',
                                translateX: 40,
                                translateY: 0,
                                length: 200,
                                thickness: 10,
                                direction: 'column',
                                tickPosition: 'after',
                                tickSize: 3,
                                tickSpacing: 4,
                                tickOverlap: false,
                                title: 'r',
                                titleAlign: 'start',
                                titleOffset: 4,
                            },
                        ]}
                        hoverTarget="cell"
                        tooltip={({ cell }) => (
                            <div className="rounded-lg border bg-white p-2 text-xs shadow-md">
                                <p className="font-medium text-[#353535]">
                                    {cell.serieId} × {cell.data.x}
                                </p>
                                <p className="text-[#284B63]">
                                    r = {cell.data.y?.toFixed(3) ?? 'N/A'}
                                </p>
                            </div>
                        )}
                    />
                </div>
            </CardContent>
        </Card>
    );
}
