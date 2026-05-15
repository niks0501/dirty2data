import { Download, FileSpreadsheet } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { DatasetPageProps } from '@/types/datasets';

interface Props {
    dataset: DatasetPageProps;
}

type ExportFormat = 'csv' | 'xlsx';

export default function CleanedDatasetExportPanel({ dataset }: Props) {
    const [format, setFormat] = useState<ExportFormat>('csv');
    const [selectedColumns, setSelectedColumns] = useState<string[]>(
        dataset.headers,
    );

    const effectiveColumns = useMemo(() => {
        const available = selectedColumns.filter((column) =>
            dataset.headers.includes(column),
        );

        return available.length > 0 ? available : dataset.headers;
    }, [dataset.headers, selectedColumns]);

    function toggleColumn(column: string, checked: boolean) {
        if (checked) {
            setSelectedColumns(
                dataset.headers.filter(
                    (header) =>
                        header === column || effectiveColumns.includes(header),
                ),
            );
        } else {
            setSelectedColumns(
                effectiveColumns.filter((selected) => selected !== column),
            );
        }
    }

    function exportCleanedDataset() {
        const params = new URLSearchParams({ format });

        for (const column of effectiveColumns) {
            params.append('columns[]', column);
        }

        window.location.href = `/datasets/${dataset.id}/export?${params.toString()}`;
    }

    const allSelected = effectiveColumns.length === dataset.headers.length;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <FileSpreadsheet className="size-5 text-[#284B63]" />
                    Export Cleaned Dataset
                </CardTitle>
                <CardDescription>
                    Download only the cleaned working copy as CSV or Excel. Pick
                    the columns you want to include in the export.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                <div className="grid gap-3 md:grid-cols-[220px_minmax(0,1fr)]">
                    <div className="space-y-2">
                        <label className="text-sm font-medium">Format</label>
                        <Select
                            value={format}
                            onValueChange={(value) =>
                                setFormat(value as ExportFormat)
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Format" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="csv">CSV (.csv)</SelectItem>
                                <SelectItem value="xlsx">
                                    Excel (.xlsx)
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between gap-3">
                            <label className="text-sm font-medium">
                                Columns
                            </label>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    setSelectedColumns(
                                        allSelected ? [] : dataset.headers,
                                    )
                                }
                            >
                                {allSelected ? 'Clear all' : 'Select all'}
                            </Button>
                        </div>

                        <div className="grid max-h-48 gap-2 overflow-auto rounded-lg border bg-[#F7F9FA] p-3 sm:grid-cols-2 lg:grid-cols-3">
                            {dataset.headers.map((header) => {
                                const checked =
                                    effectiveColumns.includes(header);

                                return (
                                    <label
                                        key={header}
                                        className="flex items-center gap-2 rounded-md bg-white px-2 py-1.5 text-sm text-[#353535]"
                                    >
                                        <Checkbox
                                            checked={checked}
                                            onCheckedChange={(value) =>
                                                toggleColumn(
                                                    header,
                                                    value === true,
                                                )
                                            }
                                        />
                                        <span className="truncate">
                                            {header}
                                        </span>
                                    </label>
                                );
                            })}
                        </div>
                    </div>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-muted-foreground">
                        {effectiveColumns.length.toLocaleString()} of{' '}
                        {dataset.headers.length.toLocaleString()} columns
                        selected. Original uploaded data is not exported here.
                    </p>
                    <Button
                        type="button"
                        onClick={exportCleanedDataset}
                        disabled={effectiveColumns.length === 0}
                    >
                        <Download className="mr-2 size-4" />
                        Download cleaned {format.toUpperCase()}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
