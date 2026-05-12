import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock, Columns3, Database, HardDrive, Rows3 } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { DatasetPageProps } from '@/types/datasets';

interface Props {
    dataset: DatasetPageProps;
}

function formatBytes(bytes: number): string {
    if (bytes === 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const size = parseFloat(
        (bytes / Math.pow(1024, i)).toFixed(1),
    );

    return `${size} ${units[i]}`;
}

function formatDate(isoString: string): string {
    const date = new Date(isoString);

    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getMimeBadge(mimeType: string | null): string {
    if (!mimeType) {
        return 'Unknown';
    }

    if (mimeType.includes('csv')) {
        return 'CSV';
    }

    if (mimeType.includes('xls')) {
        return mimeType.includes('xlsx') ? 'XLSX' : 'XLS';
    }

    return 'File';
}

function guessColumnType(
    values: (string | number | boolean | null)[],
): string {
    const nonNull = values.filter((v) => v !== null && v !== '');

    if (nonNull.length === 0) {
        return 'Empty';
    }

    const allNumeric = nonNull.every(
        (v) =>
            typeof v === 'number' ||
            (typeof v === 'string' &&
                v.trim() !== '' &&
                !isNaN(Number(v))),
    );

    if (allNumeric) {
        return 'Numeric';
    }

    const allBoolean = nonNull.every(
        (v) =>
            typeof v === 'boolean' ||
            (typeof v === 'string' &&
                ['true', 'false', '0', '1'].includes(v.toLowerCase())),
    );

    if (allBoolean) {
        return 'Boolean';
    }

    const datePattern = /^\d{4}-\d{2}-\d{2}/;
    const allDate = nonNull.every(
        (v) => typeof v === 'string' && datePattern.test(v),
    );

    if (allDate) {
        return 'Date';
    }

    return 'Text';
}

export default function Show({ dataset }: Props) {

    const hasPreviewRows = dataset.previewRows.length > 0;

    const columnValues = (header: string) =>
        dataset.previewRows.map((row) => row[header]);

    return (
        <>
            <Head title={dataset.originalName} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={dataset.originalName}
                    description="Dataset preview"
                />

                <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    <Card>
                        <CardContent className="flex items-center gap-3 p-4">
                            <Rows3 className="h-5 w-5 shrink-0 text-muted-foreground" />
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">
                                    Rows
                                </p>
                                <p className="truncate text-sm font-medium">
                                    {dataset.rowCount.toLocaleString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="flex items-center gap-3 p-4">
                            <Columns3 className="h-5 w-5 shrink-0 text-muted-foreground" />
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">
                                    Columns
                                </p>
                                <p className="truncate text-sm font-medium">
                                    {dataset.columnCount}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="flex items-center gap-3 p-4">
                            <HardDrive className="h-5 w-5 shrink-0 text-muted-foreground" />
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">
                                    Size
                                </p>
                                <p className="truncate text-sm font-medium">
                                    {formatBytes(dataset.sizeBytes)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="flex items-center gap-3 p-4">
                            <Database className="h-5 w-5 shrink-0 text-muted-foreground" />
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">
                                    Type
                                </p>
                                <Badge
                                    variant="secondary"
                                    className="mt-0.5 text-xs"
                                >
                                    {getMimeBadge(dataset.mimeType)}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="flex items-center gap-3 p-4">
                            <Clock className="h-5 w-5 shrink-0 text-muted-foreground" />
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">
                                    Uploaded
                                </p>
                                <p className="truncate text-sm font-medium">
                                    {formatDate(dataset.createdAt)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">
                                File
                            </p>
                            <p className="truncate text-sm font-medium">
                                {dataset.originalName}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardContent className="p-0">
                        {hasPreviewRows ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="sticky top-0 bg-muted">
                                        <tr>
                                            {dataset.headers.map(
                                                (header) => (
                                                    <th
                                                        key={header}
                                                        className="whitespace-nowrap px-4 py-2 text-left font-medium"
                                                    >
                                                        {header}
                                                        <Badge
                                                            variant="secondary"
                                                            className="ml-2 text-xs"
                                                        >
                                                            {guessColumnType(
                                                                columnValues(
                                                                    header,
                                                                ),
                                                            )}
                                                        </Badge>
                                                    </th>
                                                ),
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {dataset.previewRows.map(
                                            (row, i) => (
                                                <tr
                                                    key={i}
                                                    className="border-t hover:bg-muted/50"
                                                >
                                                    {dataset.headers.map(
                                                        (header) => (
                                                            <td
                                                                key={header}
                                                                className="whitespace-nowrap px-4 py-2"
                                                            >
                                                                {String(
                                                                    row[
                                                                        header
                                                                    ] ?? '',
                                                                )}
                                                            </td>
                                                        ),
                                                    )}
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="p-6 text-center text-muted-foreground">
                                No preview data available.
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div>
                    <Link
                        href="/datasets"
                    >
                        <Button variant="secondary">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Upload
                        </Button>
                    </Link>
                </div>
            </div>
        </>
    );
}

Show.layout = (props: {
    dataset: DatasetPageProps;
}) => ({
    breadcrumbs: [
        {
            title: 'Datasets',
            href: '/datasets',
        },
        {
            title: props.dataset.originalName,
            href: '#',
        },
    ],
});
