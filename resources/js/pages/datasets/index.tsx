import { Head, useForm } from '@inertiajs/react';
import { Upload, FileSpreadsheet } from 'lucide-react';
import AlertError from '@/components/alert-error';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';

function formatFileSize(bytes: number): string {
    if (bytes === 0) {
return '0 Bytes';
}

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

export default function Index() {
    const { data, setData, post, processing, errors } = useForm({
        dataset_file: null as File | null,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        post(window.location.pathname);
    }

    const errorMessages = Object.values(errors);

    return (
        <>
            <Head title="Upload Dataset" />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Upload Dataset"
                    description="Upload a CSV or Excel file to begin profiling"
                />

                {errorMessages.length > 0 && (
                    <AlertError errors={errorMessages} />
                )}

                <Card className="mx-auto w-full max-w-xl">
                    <CardHeader>
                        <CardTitle>Upload your dataset</CardTitle>
                        <CardDescription>
                            Accepted formats: CSV, XLSX, XLS (max 10MB)
                        </CardDescription>
                    </CardHeader>

                    <form onSubmit={handleSubmit}>
                        <CardContent>
                            <div className="flex flex-col items-center gap-4">
                                <div
                                    className="flex w-full cursor-pointer flex-col items-center gap-3 rounded-lg border-2 border-dashed border-border p-8 transition-colors hover:border-primary/50"
                                    onClick={() =>
                                        document
                                            .getElementById(
                                                'file-upload',
                                            )
                                            ?.click()
                                    }
                                >
                                    {data.dataset_file ? (
                                        <>
                                            <FileSpreadsheet className="size-10 text-muted-foreground" />
                                            <div className="text-center">
                                                <p className="font-medium text-foreground">
                                                    {
                                                        data
                                                            .dataset_file
                                                            .name
                                                    }
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {formatFileSize(
                                                        data
                                                            .dataset_file
                                                            .size,
                                                    )}
                                                </p>
                                            </div>
                                        </>
                                    ) : (
                                        <>
                                            <Upload className="size-10 text-muted-foreground" />
                                            <div className="text-center">
                                                <p className="font-medium text-foreground">
                                                    Click to browse or
                                                    drag and drop
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    CSV, XLSX, or XLS
                                                    files
                                                </p>
                                            </div>
                                        </>
                                    )}
                                </div>

                                <input
                                    id="file-upload"
                                    type="file"
                                    accept=".csv,.xlsx,.xls"
                                    className="hidden"
                                    onChange={(e) => {
                                        const file =
                                            e.target.files?.[0];

                                        if (file) {
                                            setData(
                                                'dataset_file',
                                                file,
                                            );
                                        }
                                    }}
                                />
                            </div>
                        </CardContent>

                        <CardFooter>
                            <Button
                                type="submit"
                                disabled={
                                    !data.dataset_file || processing
                                }
                                className="w-full"
                            >
                                {processing
                                    ? 'Uploading...'
                                    : 'Upload Dataset'}
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
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
