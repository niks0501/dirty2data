import { AlertTriangle, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface BoxPlotColumn {
    name: string;
    min: number;
    q1: number;
    median: number;
    q3: number;
    max: number;
    iqr: number;
    lowerFence: number;
    upperFence: number;
    outliers: number[];
}

interface BoxPlotData {
    columns: BoxPlotColumn[];
}

interface Props {
    datasetId: number;
}

const chartColors = [
    '#284B63',
    '#3C6E71',
    '#2E7D32',
    '#F59E0B',
    '#6D5BD0',
    '#C2416D',
];

export default function BoxPlotChart({ datasetId }: Props) {
    const [data, setData] = useState<BoxPlotData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function fetchData() {
            setLoading(true);
            setError(null);

            try {
                const res = await fetch(`/datasets/${datasetId}/box-plot`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!res.ok) {
                    throw new Error('Failed to load box plot data');
                }

                const json = (await res.json()) as BoxPlotData;

                if (!cancelled) {
                    setData(json);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err instanceof Error
                            ? err.message
                            : 'Failed to load box plot data',
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

    if (!data || data.columns.length === 0) {
        return (
            <Card>
                <CardContent className="flex h-[340px] items-center justify-center p-6">
                    <div className="text-center">
                        <AlertTriangle className="mx-auto mb-3 size-8 text-[#F59E0B]" />
                        <p className="text-muted-foreground">
                            No numeric columns found for box plots.
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    // Build chart data for box plots
    // Each column becomes a "box" rendered as a stacked bar
    const boxData = data.columns.map((col, idx) => ({
        name: col.name,
        // Box body (Q1 to Q3)
        boxBottom: 0, // placeholder, we shift via Y positioning
        boxHeight: col.q3 - col.q1,
        q1: col.q1,
        median: col.median,
        q3: col.q3,
        min: col.min,
        max: col.max,
        lowerWhisker: col.lowerFence,
        upperWhisker: col.upperFence,
        // Whiskers: segments from min to Q1 and Q3 to max
        whiskerLow: col.q1 - col.lowerFence,
        whiskerHigh: col.upperFence - col.q3,
        color: chartColors[idx % chartColors.length],
        outliers: col.outliers.map((v) => ({ y: v, x: idx })),
    }));

    const maxValue = Math.max(
        ...data.columns.map((c) => c.upperFence + c.iqr * 0.3),
        1,
    );
    const minValue = Math.min(
        ...data.columns.map((c) => c.lowerFence - c.iqr * 0.3),
        0,
    );

    // Build scatter data for outliers
    const outlierData = data.columns.flatMap((col, colIdx) =>
        col.outliers
            .filter((v) => v < col.lowerFence || v > col.upperFence)
            .map((v, i) => ({
                x: colIdx,
                y: v,
                colName: col.name,
                outlierIdx: i,
            })),
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>Box Plots</CardTitle>
                <CardDescription>
                    Distribution of numeric columns with quartiles and outlier
                    detection (IQR method).
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="h-[340px]">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={boxData}
                            margin={{
                                top: 20,
                                right: 30,
                                bottom: 40,
                                left: 60,
                            }}
                        >
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis
                                dataKey="name"
                                tick={{ fontSize: 11 }}
                                angle={-30}
                                textAnchor="end"
                                height={60}
                            />
                            <YAxis
                                tick={{ fontSize: 11 }}
                                domain={[minValue, maxValue]}
                            />
                            <Tooltip
                                content={({ active, payload }) => {
                                    if (
                                        !active ||
                                        !payload ||
                                        payload.length === 0
                                    ) {
                                        return null;
                                    }

                                    const item = payload[0]
                                        .payload as (typeof boxData)[0];

                                    return (
                                        <div className="rounded-lg border bg-white p-3 text-xs shadow-md">
                                            <p className="font-medium text-[#353535]">
                                                {item.name}
                                            </p>
                                            <p>Max: {item.max.toFixed(2)}</p>
                                            <p>Q3: {item.q3.toFixed(2)}</p>
                                            <p className="font-semibold text-[#284B63]">
                                                Median: {item.median.toFixed(2)}
                                            </p>
                                            <p>Q1: {item.q1.toFixed(2)}</p>
                                            <p>Min: {item.min.toFixed(2)}</p>
                                            <p className="text-[#C62828]">
                                                Outliers: {item.outliers.length}
                                            </p>
                                        </div>
                                    );
                                }}
                            />
                            {/* Q1→Q3 box */}
                            <Bar
                                dataKey="boxHeight"
                                stackId="box"
                                radius={[0, 0, 0, 0]}
                                barSize={30}
                                isAnimationActive={false}
                            >
                                {boxData.map((entry, idx) => (
                                    <Cell
                                        key={`cell-${idx}`}
                                        fill={entry.color}
                                        fillOpacity={0.6}
                                    />
                                ))}
                            </Bar>
                            {/* Invisible spacer below Q1 for positioning */}
                            <Bar
                                dataKey="q1"
                                stackId="box"
                                fill="transparent"
                                isAnimationActive={false}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
                {outlierData.length > 0 && (
                    <p className="mt-2 text-xs text-muted-foreground">
                        {outlierData.length} outlier(s) detected across all
                        columns using the IQR method (1.5× IQR beyond
                        quartiles).
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
