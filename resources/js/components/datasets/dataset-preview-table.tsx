import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DatasetPageProps } from '@/types/datasets';

interface Props {
    dataset: DatasetPageProps;
    activeTab: string;
}

export default function DatasetPreviewTable({ dataset, activeTab }: Props) {
    const hasRows = dataset.previewRows.length > 0;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Dataset Preview</CardTitle>
                <CardDescription>
                    Paginated cleaned working copy. Cleaning actions update this
                    table while the original upload is preserved for comparison.
                </CardDescription>
            </CardHeader>

            <CardContent className="p-0">
                {hasRows ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="sticky top-0 bg-[#F1F3F4]">
                                <tr>
                                    {dataset.headers.map((header) => (
                                        <th
                                            key={header}
                                            className="px-4 py-3 text-left font-semibold whitespace-nowrap text-[#353535]"
                                        >
                                            {header}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {dataset.previewRows.map((row, rowIndex) => (
                                    <tr
                                        key={rowIndex}
                                        className="border-t hover:bg-[#F7F9FA]"
                                    >
                                        {dataset.headers.map((header) => (
                                            <td
                                                key={header}
                                                className="px-4 py-3 whitespace-nowrap"
                                            >
                                                {String(row[header] ?? '')}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="p-8 text-center text-muted-foreground">
                        No preview rows are available for this dataset.
                    </div>
                )}

                <div className="flex flex-col items-center justify-between gap-3 border-t p-4 sm:flex-row">
                    <p className="text-sm text-muted-foreground">
                        Page {dataset.pagination.page} of{' '}
                        {dataset.pagination.lastPage} ·{' '}
                        {dataset.pagination.total.toLocaleString()} rows
                    </p>

                    <div className="flex gap-2">
                        <Button
                            asChild
                            variant="secondary"
                            size="sm"
                            disabled={dataset.pagination.page <= 1}
                        >
                            <Link
                                href={`/datasets/${dataset.id}?tab=${activeTab}&page=${dataset.pagination.page - 1}`}
                                preserveScroll
                            >
                                <ChevronLeft className="size-4" />
                                Previous
                            </Link>
                        </Button>
                        <Button
                            asChild
                            variant="secondary"
                            size="sm"
                            disabled={
                                dataset.pagination.page >=
                                dataset.pagination.lastPage
                            }
                        >
                            <Link
                                href={`/datasets/${dataset.id}?tab=${activeTab}&page=${dataset.pagination.page + 1}`}
                                preserveScroll
                            >
                                Next
                                <ChevronRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
