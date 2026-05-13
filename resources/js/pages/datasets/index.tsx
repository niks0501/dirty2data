import { Head, Link, useForm } from '@inertiajs/react';
import { FileSpreadsheet, Loader2, Upload } from 'lucide-react';
import AlertError from '@/components/alert-error';
import WorkflowSteps from '@/components/datasets/workflow-steps';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DatasetSummary } from '@/types/datasets';

interface Props {
    datasets: DatasetSummary[];
}

function formatFileSize(bytes: number): string {
    if (bytes === 0) {
        return '0 Bytes';
    }

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
}

function formatDate(isoString: string): string {
    return new Date(isoString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function Index({ datasets }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        dataset_file: null as File | null,
    });

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        post('/datasets');
    }

    const errorMessages = Object.values(errors);

    return (
        <>
            <Head title="Upload Dataset" />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Upload Dataset"
                    description="Upload CSV or Excel files to begin the Upload → Profile → Clean → Analyze → Visualize workflow."
                />

                <WorkflowSteps current="Upload" />

                {errorMessages.length > 0 && (
                    <AlertError errors={errorMessages} />
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Upload your dataset</CardTitle>
                            <CardDescription>
                                Accepted formats: CSV, XLSX, XLS. Maximum size:
                                50MB. Files over 200,000 rows are processed in
                                the background and will show progress on the
                                dataset page.
                            </CardDescription>
                        </CardHeader>

                        <form onSubmit={handleSubmit}>
                            <CardContent>
                                <button
                                    type="button"
                                    className="flex w-full cursor-pointer flex-col items-center gap-3 rounded-xl border-2 border-dashed border-[#D9D9D9] bg-[#F7F9FA] p-10 transition-colors hover:border-[#284B63]"
                                    onClick={() =>
                                        document
                                            .getElementById('file-upload')
                                            ?.click()
                                    }
                                >
                                    {data.dataset_file ? (
                                        <>
                                            <FileSpreadsheet className="size-12 text-[#284B63]" />
                                            <div className="text-center">
                                                <p className="font-medium text-[#353535]">
                                                    {data.dataset_file.name}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {formatFileSize(
                                                        data.dataset_file.size,
                                                    )}
                                                </p>
                                            </div>
                                        </>
                                    ) : (
                                        <>
                                            <Upload className="size-12 text-[#284B63]" />
                                            <div className="text-center">
                                                <p className="font-medium text-[#353535]">
                                                    Click to browse or drag and
                                                    drop
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    Files must include a header
                                                    row.
                                                </p>
                                            </div>
                                        </>
                                    )}
                                </button>

                                <input
                                    id="file-upload"
                                    type="file"
                                    accept=".csv,.xlsx,.xls"
                                    className="hidden"
                                    onChange={(event) => {
                                        const file = event.target.files?.[0];

                                        if (file) {
                                            setData('dataset_file', file);
                                        }
                                    }}
                                />
                            </CardContent>

                            <CardFooter>
                                <Button
                                    type="submit"
                                    disabled={!data.dataset_file || processing}
                                    className="w-full"
                                >
                                    {processing
                                        ? 'Uploading...'
                                        : 'Upload Dataset'}
                                </Button>
                            </CardFooter>
                        </form>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Recent datasets</CardTitle>
                            <CardDescription>
                                Open an uploaded dataset to profile, clean, and
                                visualize it.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {datasets.length > 0 ? (
                                datasets.map((dataset) => (
                                    <Link
                                        key={dataset.id}
                                        href={`/datasets/${dataset.id}`}
                                        className="block rounded-lg border p-3 transition-colors hover:bg-[#F7F9FA]"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="font-medium text-[#353535]">
                                                {dataset.originalName}
                                            </p>
                                            {dataset.status ===
                                                'processing' && (
                                                <span className="flex items-center gap-1 rounded-full bg-[#E7F0F5] px-2 py-0.5 text-xs font-medium text-[#284B63]">
                                                    <Loader2 className="size-3 animate-spin" />
                                                    Processing
                                                </span>
                                            )}
                                            {dataset.status === 'failed' && (
                                                <span className="rounded-full bg-[#FDECEC] px-2 py-0.5 text-xs font-medium text-[#C62828]">
                                                    Failed
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {dataset.rowCount.toLocaleString()}{' '}
                                            rows · {dataset.columnCount} columns
                                            · {formatDate(dataset.createdAt)}
                                        </p>
                                    </Link>
                                ))
                            ) : (
                                <div className="rounded-lg bg-[#F7F9FA] p-4 text-sm text-muted-foreground">
                                    No dataset uploaded yet. Upload your first
                                    CSV or Excel file to begin.
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        {
            title: 'Datasets',
            href: '/datasets',
        },
        {
            title: 'Upload',
            href: '#',
        },
    ],
};
