import { router } from '@inertiajs/react';
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
import type { DatasetPageProps } from '@/types/datasets';

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

export default function ChartPanel({ dataset }: Props) {
    const [chartType, setChartType] = useState(dataset.chart.type || 'bar');
    const [xColumn, setXColumn] = useState(
        dataset.chart.x_column ?? dataset.headers[0] ?? '',
    );
    const [yColumn, setYColumn] = useState(
        dataset.chart.y_column ?? dataset.headers[0] ?? '',
    );

    function generateChart() {
        router.get(
            `/datasets/${dataset.id}`,
            {
                chart_type: chartType,
                x_column: xColumn,
                y_column: yColumn,
            },
            {
                preserveScroll: true,
            },
        );
    }

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
                        {dataset.chartRecommendations.map((recommendation) => (
                            <button
                                key={`${recommendation.type}-${recommendation.x_column}-${recommendation.y_column ?? 'none'}`}
                                type="button"
                                className="rounded-xl border p-4 text-left transition-colors hover:bg-[#F7F9FA]"
                                onClick={() => {
                                    setChartType(recommendation.type);
                                    setXColumn(recommendation.x_column);
                                    setYColumn(
                                        recommendation.y_column ??
                                            recommendation.x_column,
                                    );
                                }}
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
                        ))}
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
                            <SelectValue placeholder="Category/date column" />
                        </SelectTrigger>
                        <SelectContent>
                            {dataset.headers.map((header) => (
                                <SelectItem key={header} value={header}>
                                    {header}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={yColumn} onValueChange={setYColumn}>
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Value column" />
                        </SelectTrigger>
                        <SelectContent>
                            {dataset.headers.map((header) => (
                                <SelectItem key={header} value={header}>
                                    {header}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Button type="button" onClick={generateChart}>
                        Generate chart
                    </Button>
                </div>

                <div className="min-h-[340px] rounded-xl border bg-white p-4">
                    <h3 className="mb-4 font-semibold text-[#353535]">
                        {dataset.chart.title}
                    </h3>
                    {dataset.chart.reason && (
                        <p className="mb-4 text-sm text-muted-foreground">
                            {dataset.chart.reason}
                        </p>
                    )}

                    {dataset.chart.message ? (
                        <div className="flex h-[280px] items-center justify-center text-center text-muted-foreground">
                            {dataset.chart.message}
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
                                        label
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
                                    <Tooltip />
                                    <Legend />
                                </PieChart>
                            ) : dataset.chart.type === 'line' ? (
                                <LineChart data={dataset.chart.data}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="name" />
                                    <YAxis />
                                    <Tooltip />
                                    <Legend />
                                    <Line
                                        type="monotone"
                                        dataKey="value"
                                        stroke="#284B63"
                                        strokeWidth={2}
                                    />
                                </LineChart>
                            ) : dataset.chart.type === 'scatter' ? (
                                <ScatterChart>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis
                                        dataKey="x"
                                        name={dataset.chart.x_column ?? 'X'}
                                        type="number"
                                    />
                                    <YAxis
                                        dataKey="y"
                                        name={dataset.chart.y_column ?? 'Y'}
                                        type="number"
                                    />
                                    <Tooltip
                                        cursor={{ strokeDasharray: '3 3' }}
                                    />
                                    <Scatter
                                        name={dataset.chart.title}
                                        data={dataset.chart.data}
                                        fill="#284B63"
                                    />
                                </ScatterChart>
                            ) : (
                                <BarChart data={dataset.chart.data}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="name" />
                                    <YAxis />
                                    <Tooltip />
                                    <Legend />
                                    <Bar dataKey="value" fill="#284B63" />
                                </BarChart>
                            )}
                        </ResponsiveContainer>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
