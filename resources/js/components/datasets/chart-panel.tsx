import { router } from '@inertiajs/react';
import { AlertTriangle, BarChart3, TrendingUp } from 'lucide-react';
import { useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Scatter,
    ScatterChart,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { DatasetChartMetadata, DatasetPageProps } from '@/types/datasets';

const chartColors = [
    '#284B63',
    '#3C6E71',
    '#2E7D32',
    '#F59E0B',
    '#6D5BD0',
    '#C2416D',
];

interface Props {
    dataset: DatasetPageProps;
}

const SINGLE_COLUMN_CHARTS = ['pie', 'histogram'];

function needsYColumn(chartType: string): boolean {
    return !SINGLE_COLUMN_CHARTS.includes(chartType);
}

function toPercent(decimal: number): string {
    return `${(decimal * 100).toFixed(1)}%`;
}

function formatNumber(value: number): string {
    if (Number.isInteger(value)) {
        return value.toLocaleString();
    }

    return value.toFixed(2);
}

function CustomTooltip({
    active,
    payload,
    label,
}: {
    active?: boolean;
    payload?: Array<{
        name: string;
        value: number;
        payload: Record<string, unknown>;
    }>;
    label?: string;
}) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    const item = payload[0];
    const data = item.payload;

    if (data.x !== undefined && data.y !== undefined) {
        return (
            <div className="rounded-lg border bg-white p-3 shadow-md">
                <p className="font-medium text-[#353535]">
                    X: {formatNumber(data.x as number)}
                </p>
                <p className="text-[#284B63]">
                    Y: {formatNumber(data.y as number)}
                </p>
            </div>
        );
    }

    return (
        <div className="rounded-lg border bg-white p-3 shadow-md">
            <p className="font-medium text-[#353535]">{label ?? item.name}</p>
            <p className="text-[#284B63]">{formatNumber(item.value)}</p>
        </div>
    );
}

function formatXAxisTick(value: string): string {
    return value.length > 14 ? `${value.slice(0, 13)}…` : value;
}

function aggregationLabel(agg: string): string {
    const labels: Record<string, string> = {
        sum: 'Sum',
        average: 'Average',
        count: 'Count',
        min: 'Minimum',
        max: 'Maximum',
    };

    return labels[agg] ?? 'Sum';
}

function metadataExplanation(metadata: DatasetChartMetadata): string {
    const parts: string[] = [];
    parts.push(`${metadata.total_rows_used.toLocaleString()} rows used`);

    if (metadata.missing_rows_skipped > 0) {
        parts.push(
            `${metadata.missing_rows_skipped.toLocaleString()} skipped due to missing values`,
        );
    }

    if (metadata.aggregation !== 'none') {
        parts.push(
            `aggregated as ${aggregationLabel(metadata.aggregation).toLowerCase()}`,
        );
    }

    if (metadata.truncated) {
        parts.push('top categories shown; others grouped');
    }

    if (metadata.correlation !== null && metadata.correlation !== undefined) {
        parts.push(`correlation r = ${metadata.correlation.toFixed(3)}`);
    }

    return parts.join(' · ');
}

export default function ChartPanel({ dataset }: Props) {
    const [chartType, setChartType] = useState(dataset.chart.type || 'bar');
    const [xColumn, setXColumn] = useState(
        dataset.chart.x_column ?? dataset.headers[0] ?? '',
    );
    const [yColumn, setYColumn] = useState(dataset.chart.y_column ?? '');
    const [aggregation, setAggregation] = useState('sum');
    const [binCount, setBinCount] = useState(8);
    const [dateGroup, setDateGroup] = useState('day');

    function generateChart() {
        const params: Record<string, string | number> = {
            chart_type: chartType,
            x_column: xColumn,
            y_column:
                needsYColumn(chartType) && yColumn !== '__none__'
                    ? yColumn
                    : '',
            aggregation,
            bin_count: binCount,
            date_group: dateGroup,
        };

        router.get(`/datasets/${dataset.id}`, params, {
            preserveScroll: true,
        });
    }

    function selectRecommendation(recommendation: {
        type: string;
        x_column: string;
        y_column: string | null;
    }) {
        setChartType(recommendation.type);
        setXColumn(recommendation.x_column);
        setYColumn(recommendation.y_column ?? '__none__');
    }

    const showYColumn = needsYColumn(chartType);
    const showAggregation =
        chartType === 'bar' && yColumn && yColumn !== '__none__';
    const showBinCount = chartType === 'histogram';
    const showDateGroup = chartType === 'line';

    return (
        <Card>
            <CardHeader>
                <CardTitle>Visualize</CardTitle>
                <CardDescription>
                    Use recommended charts based on detected column types, or
                    choose variables manually from the cleaned dataset.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                {dataset.chartRecommendations.length > 0 && (
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        {dataset.chartRecommendations.map((recommendation) => {
                            const isActive =
                                recommendation.type === chartType &&
                                recommendation.x_column === xColumn &&
                                (recommendation.y_column ?? '__none__') ===
                                    yColumn;

                            return (
                                <button
                                    key={`${recommendation.type}-${recommendation.x_column}-${recommendation.y_column ?? 'none'}`}
                                    type="button"
                                    className={[
                                        'rounded-xl border p-4 text-left transition-colors',
                                        isActive
                                            ? 'border-[#284B63] bg-[#E7F0F5]'
                                            : 'hover:bg-[#F7F9FA]',
                                    ].join(' ')}
                                    onClick={() =>
                                        selectRecommendation(recommendation)
                                    }
                                >
                                    <p className="font-semibold text-[#353535]">
                                        {recommendation.title}
                                    </p>
                                    <p className="mt-1 text-xs tracking-wide text-[#284B63] uppercase">
                                        {recommendation.type} chart
                                    </p>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {recommendation.reason}
                                    </p>
                                </button>
                            );
                        })}
                    </div>
                )}

                <div className="grid gap-3 md:grid-cols-4">
                    <Select value={chartType} onValueChange={setChartType}>
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Chart type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="bar">Bar chart</SelectItem>
                            <SelectItem value="line">Line chart</SelectItem>
                            <SelectItem value="pie">Pie chart</SelectItem>
                            <SelectItem value="histogram">Histogram</SelectItem>
                            <SelectItem value="scatter">
                                Scatter plot
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select value={xColumn} onValueChange={setXColumn}>
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="X-axis column" />
                        </SelectTrigger>
                        <SelectContent>
                            {dataset.headers.map((header) => (
                                <SelectItem key={header} value={header}>
                                    {header}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {showYColumn && (
                        <Select value={yColumn} onValueChange={setYColumn}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Value column" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">None</SelectItem>
                                {dataset.headers.map((header) => (
                                    <SelectItem key={header} value={header}>
                                        {header}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    <Button type="button" onClick={generateChart}>
                        Generate chart
                    </Button>
                </div>

                <div className="flex flex-wrap gap-3">
                    {showAggregation && (
                        <div className="flex items-center gap-2">
                            <label className="text-sm font-medium">
                                Aggregate:
                            </label>
                            <Select
                                value={aggregation}
                                onValueChange={setAggregation}
                            >
                                <SelectTrigger className="w-32">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="sum">Sum</SelectItem>
                                    <SelectItem value="average">
                                        Average
                                    </SelectItem>
                                    <SelectItem value="count">Count</SelectItem>
                                    <SelectItem value="min">Min</SelectItem>
                                    <SelectItem value="max">Max</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {showBinCount && (
                        <div className="flex items-center gap-2">
                            <label className="text-sm font-medium">Bins:</label>
                            <Select
                                value={String(binCount)}
                                onValueChange={(value) =>
                                    setBinCount(Number(value))
                                }
                            >
                                <SelectTrigger className="w-20">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {[4, 6, 8, 10, 12, 15, 20].map((count) => (
                                        <SelectItem
                                            key={count}
                                            value={String(count)}
                                        >
                                            {count}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {showDateGroup && (
                        <div className="flex items-center gap-2">
                            <label className="text-sm font-medium">
                                Group by:
                            </label>
                            <Select
                                value={dateGroup}
                                onValueChange={setDateGroup}
                            >
                                <SelectTrigger className="w-28">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="day">Day</SelectItem>
                                    <SelectItem value="month">Month</SelectItem>
                                    <SelectItem value="year">Year</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                </div>

                <div className="min-h-[340px] rounded-xl border bg-white p-4">
                    <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <h3 className="font-semibold text-[#353535]">
                            {dataset.chart.title}
                        </h3>
                        {dataset.chart.reason && (
                            <p className="text-xs text-muted-foreground">
                                {dataset.chart.reason}
                            </p>
                        )}
                    </div>

                    {dataset.chart.metadata && (
                        <div className="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-[#F7F9FA] p-3 text-xs text-muted-foreground">
                            <BarChart3 className="size-4 shrink-0 text-[#284B63]" />
                            <span>
                                {metadataExplanation(dataset.chart.metadata)}
                            </span>
                            {dataset.chart.metadata.correlation !== null &&
                                dataset.chart.metadata.correlation !==
                                    undefined && (
                                    <span className="flex items-center gap-1 font-medium text-[#3C6E71]">
                                        <TrendingUp className="size-3" />
                                        {toPercent(
                                            dataset.chart.metadata.correlation,
                                        )}
                                    </span>
                                )}
                        </div>
                    )}

                    {dataset.chart.message ? (
                        <div className="flex h-[280px] items-center justify-center">
                            <div className="max-w-md text-center">
                                <AlertTriangle className="mx-auto mb-3 size-8 text-[#F59E0B]" />
                                <p className="text-muted-foreground">
                                    {dataset.chart.message}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <ResponsiveContainer width="100%" height={280}>
                            {dataset.chart.type === 'pie' ? (
                                <PieChart>
                                    <Pie
                                        data={dataset.chart.data}
                                        dataKey="value"
                                        nameKey="name"
                                        cx="50%"
                                        cy="50%"
                                        outerRadius={90}
                                        label={({ name, percent }) =>
                                            `${formatXAxisTick(name ?? '')} ${((percent ?? 0) * 100).toFixed(0)}%`
                                        }
                                    >
                                        {dataset.chart.data.map(
                                            (entry, index) => (
                                                <Cell
                                                    key={entry.name}
                                                    fill={
                                                        chartColors[
                                                            index %
                                                                chartColors.length
                                                        ]
                                                    }
                                                />
                                            ),
                                        )}
                                    </Pie>
                                    <Tooltip content={<CustomTooltip />} />
                                    <Legend
                                        formatter={formatXAxisTick}
                                        wrapperStyle={{ fontSize: '12px' }}
                                    />
                                </PieChart>
                            ) : dataset.chart.type === 'line' ? (
                                <LineChart
                                    data={dataset.chart.data}
                                    margin={{ bottom: 30 }}
                                >
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis
                                        dataKey="name"
                                        tick={{ fontSize: 11 }}
                                        tickFormatter={formatXAxisTick}
                                        angle={-30}
                                        textAnchor="end"
                                        height={60}
                                        label={{
                                            value: dataset.chart.x_column ?? '',
                                            position: 'insideBottom',
                                            offset: -5,
                                            style: {
                                                fontSize: '12px',
                                                fill: '#6B7280',
                                            },
                                        }}
                                    />
                                    <YAxis
                                        tick={{ fontSize: 11 }}
                                        label={{
                                            value:
                                                dataset.chart.y_column ??
                                                'Count',
                                            angle: -90,
                                            position: 'insideLeft',
                                            style: {
                                                fontSize: '12px',
                                                fill: '#6B7280',
                                            },
                                        }}
                                    />
                                    <Tooltip content={<CustomTooltip />} />
                                    <Legend
                                        formatter={formatXAxisTick}
                                        wrapperStyle={{ fontSize: '12px' }}
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey="value"
                                        stroke="#284B63"
                                        strokeWidth={2}
                                        dot={{ r: 3 }}
                                        activeDot={{ r: 5 }}
                                    />
                                </LineChart>
                            ) : dataset.chart.type === 'scatter' ? (
                                <ScatterChart margin={{ bottom: 30 }}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis
                                        dataKey="x"
                                        name={dataset.chart.x_column ?? 'X'}
                                        type="number"
                                        tick={{ fontSize: 11 }}
                                        label={{
                                            value:
                                                dataset.chart.x_column ?? 'X',
                                            position: 'insideBottom',
                                            offset: -5,
                                            style: {
                                                fontSize: '12px',
                                                fill: '#6B7280',
                                            },
                                        }}
                                    />
                                    <YAxis
                                        dataKey="y"
                                        name={dataset.chart.y_column ?? 'Y'}
                                        type="number"
                                        tick={{ fontSize: 11 }}
                                        label={{
                                            value:
                                                dataset.chart.y_column ?? 'Y',
                                            angle: -90,
                                            position: 'insideLeft',
                                            style: {
                                                fontSize: '12px',
                                                fill: '#6B7280',
                                            },
                                        }}
                                    />
                                    <Tooltip content={<CustomTooltip />} />
                                    <Scatter
                                        name={dataset.chart.title}
                                        data={dataset.chart.data}
                                        fill="#284B63"
                                    />
                                </ScatterChart>
                            ) : (
                                <BarChart
                                    data={dataset.chart.data}
                                    margin={{ bottom: 30 }}
                                >
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis
                                        dataKey="name"
                                        tick={{ fontSize: 11 }}
                                        tickFormatter={formatXAxisTick}
                                        angle={-30}
                                        textAnchor="end"
                                        height={60}
                                        label={{
                                            value: dataset.chart.x_column ?? '',
                                            position: 'insideBottom',
                                            offset: -5,
                                            style: {
                                                fontSize: '12px',
                                                fill: '#6B7280',
                                            },
                                        }}
                                    />
                                    <YAxis
                                        tick={{ fontSize: 11 }}
                                        label={{
                                            value:
                                                dataset.chart.y_column ??
                                                'Count',
                                            angle: -90,
                                            position: 'insideLeft',
                                            style: {
                                                fontSize: '12px',
                                                fill: '#6B7280',
                                            },
                                        }}
                                    />
                                    <Tooltip content={<CustomTooltip />} />
                                    <Legend
                                        formatter={formatXAxisTick}
                                        wrapperStyle={{ fontSize: '12px' }}
                                    />
                                    <Bar
                                        dataKey="value"
                                        fill="#284B63"
                                        radius={[4, 4, 0, 0]}
                                    />
                                </BarChart>
                            )}
                        </ResponsiveContainer>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
