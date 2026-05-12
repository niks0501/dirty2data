import { router } from '@inertiajs/react';
import { CheckCircle2, Eye, Wand2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CleaningPreview, DatasetPageProps } from '@/types/datasets';

type CleaningOperation =
    | 'remove_duplicates'
    | 'fill_missing'
    | 'convert_type'
    | 'standardize_text'
    | 'filter_invalid';

interface CleaningConfig {
    operation: CleaningOperation;
    column: string;
    method: string;
    value: string;
    target_type: string;
    text_format: string;
}

interface Props {
    dataset: DatasetPageProps;
}

const actions: Array<{
    operation: CleaningOperation;
    title: string;
    description: string;
}> = [
    {
        operation: 'remove_duplicates',
        title: 'Remove Duplicates',
        description: 'Keep the first copy of each repeated row.',
    },
    {
        operation: 'fill_missing',
        title: 'Fill Missing Values',
        description:
            'Replace blanks using mean, median, mode, or a custom value.',
    },
    {
        operation: 'convert_type',
        title: 'Convert Data Type',
        description:
            'Convert values to numeric, text, date, or boolean format.',
    },
    {
        operation: 'standardize_text',
        title: 'Standardize Text Format',
        description: 'Trim text and normalize case for cleaner categories.',
    },
    {
        operation: 'filter_invalid',
        title: 'Filter Invalid Rows',
        description: 'Remove rows that do not match the expected column type.',
    },
];

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

function operationLabel(operation: string): string {
    return (
        actions.find((action) => action.operation === operation)?.title ??
        'Cleaning Action'
    );
}

export default function CleaningPanel({ dataset }: Props) {
    const [config, setConfig] = useState<CleaningConfig>({
        operation: 'remove_duplicates',
        column: dataset.headers[0] ?? '',
        method: 'mode',
        value: '',
        target_type: 'numeric',
        text_format: 'trim',
    });
    const [preview, setPreview] = useState<CleaningPreview | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [previewing, setPreviewing] = useState(false);

    function updateConfig<Key extends keyof CleaningConfig>(
        key: Key,
        value: CleaningConfig[Key],
    ) {
        setConfig((current) => ({ ...current, [key]: value }));
        setPreview(null);
        setError(null);
    }

    async function previewCleaning() {
        setPreviewing(true);
        setError(null);

        const response = await fetch(`/datasets/${dataset.id}/clean/preview`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(config),
        });

        const payload = await response.json();
        setPreviewing(false);

        if (!response.ok) {
            const messages = Object.values(payload.errors ?? {}).flat();
            setError(String(messages[0] ?? 'Unable to preview this action.'));

            return;
        }

        setPreview(payload.preview);
    }

    function applyCleaning() {
        router.post(
            `/datasets/${dataset.id}/clean`,
            {
                ...config,
            },
            {
                preserveScroll: true,
            },
        );
    }

    const requiresColumn = config.operation !== 'remove_duplicates';
    const canApply = preview !== null && preview.operation === config.operation;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Clean</CardTitle>
                <CardDescription>
                    Choose a beginner-friendly cleaning action, preview the
                    expected changes, then apply it to the cleaned working copy.
                    The original upload remains unchanged.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-5">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    {actions.map((action) => {
                        const isSelected =
                            action.operation === config.operation;

                        return (
                            <button
                                key={action.operation}
                                type="button"
                                className={[
                                    'rounded-xl border p-4 text-left transition-colors',
                                    isSelected
                                        ? 'border-[#284B63] bg-[#E7F0F5]'
                                        : 'hover:bg-[#F7F9FA]',
                                ].join(' ')}
                                onClick={() =>
                                    updateConfig('operation', action.operation)
                                }
                            >
                                <div className="mb-2 flex items-center gap-2 font-semibold text-[#353535]">
                                    <Wand2 className="size-4 text-[#3C6E71]" />
                                    {action.title}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {action.description}
                                </p>
                            </button>
                        );
                    })}
                </div>

                <div className="grid gap-3 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-4">
                    {requiresColumn && (
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Column
                            </label>
                            <Select
                                value={config.column}
                                onValueChange={(value) =>
                                    updateConfig('column', value)
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Column" />
                                </SelectTrigger>
                                <SelectContent>
                                    {dataset.headers.map((header) => (
                                        <SelectItem key={header} value={header}>
                                            {header}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {config.operation === 'fill_missing' && (
                        <>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Fill method
                                </label>
                                <Select
                                    value={config.method}
                                    onValueChange={(value) =>
                                        updateConfig('method', value)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Method" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="mean">
                                            Mean
                                        </SelectItem>
                                        <SelectItem value="median">
                                            Median
                                        </SelectItem>
                                        <SelectItem value="mode">
                                            Mode
                                        </SelectItem>
                                        <SelectItem value="custom">
                                            Custom value
                                        </SelectItem>
                                        <SelectItem value="blank">
                                            Blank replacement
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            {config.method === 'custom' && (
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        Replacement value
                                    </label>
                                    <Input
                                        value={config.value}
                                        onChange={(event) =>
                                            updateConfig(
                                                'value',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            )}
                        </>
                    )}

                    {(config.operation === 'convert_type' ||
                        config.operation === 'filter_invalid') && (
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Expected type
                            </label>
                            <Select
                                value={config.target_type}
                                onValueChange={(value) =>
                                    updateConfig('target_type', value)
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="numeric">
                                        Numeric
                                    </SelectItem>
                                    <SelectItem value="text">Text</SelectItem>
                                    <SelectItem value="date">Date</SelectItem>
                                    <SelectItem value="boolean">
                                        Boolean
                                    </SelectItem>
                                    {config.operation === 'filter_invalid' && (
                                        <SelectItem value="non_blank">
                                            Not blank
                                        </SelectItem>
                                    )}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {config.operation === 'standardize_text' && (
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Text format
                            </label>
                            <Select
                                value={config.text_format}
                                onValueChange={(value) =>
                                    updateConfig('text_format', value)
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Format" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="trim">
                                        Trim spaces
                                    </SelectItem>
                                    <SelectItem value="lowercase">
                                        Lowercase
                                    </SelectItem>
                                    <SelectItem value="uppercase">
                                        Uppercase
                                    </SelectItem>
                                    <SelectItem value="title">
                                        Title Case
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                </div>

                {error && (
                    <div className="rounded-lg bg-[#FDECEC] p-3 text-sm text-[#C62828]">
                        {error}
                    </div>
                )}

                {preview && (
                    <div className="space-y-3 rounded-xl border bg-[#F7F9FA] p-4">
                        <div className="flex items-start gap-2">
                            <CheckCircle2 className="mt-0.5 size-4 text-[#2E7D32]" />
                            <div>
                                <h3 className="font-semibold text-[#353535]">
                                    Preview: {operationLabel(preview.operation)}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {preview.message}
                                </p>
                            </div>
                        </div>

                        {preview.changed_rows.length > 0 ? (
                            <div className="overflow-x-auto rounded-lg border bg-white">
                                <table className="w-full text-sm">
                                    <thead className="bg-[#F1F3F4]">
                                        <tr>
                                            <th className="px-3 py-2 text-left">
                                                Row
                                            </th>
                                            <th className="px-3 py-2 text-left">
                                                Status
                                            </th>
                                            <th className="px-3 py-2 text-left">
                                                Before
                                            </th>
                                            <th className="px-3 py-2 text-left">
                                                After
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {preview.changed_rows.map((row) => (
                                            <tr
                                                key={`${row.row_number}-${row.status}`}
                                                className="border-t"
                                            >
                                                <td className="px-3 py-2">
                                                    {row.row_number}
                                                </td>
                                                <td className="px-3 py-2 capitalize">
                                                    {row.status}
                                                </td>
                                                <td className="max-w-[280px] truncate px-3 py-2">
                                                    {JSON.stringify(row.before)}
                                                </td>
                                                <td className="max-w-[280px] truncate px-3 py-2">
                                                    {JSON.stringify(row.after)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No row-level changes were found for this action.
                            </p>
                        )}
                    </div>
                )}

                <div className="flex flex-col gap-3 sm:flex-row">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={previewCleaning}
                        disabled={previewing}
                    >
                        <Eye className="mr-2 size-4" />
                        {previewing ? 'Previewing...' : 'Preview changes'}
                    </Button>
                    <Button
                        type="button"
                        onClick={applyCleaning}
                        disabled={!canApply}
                    >
                        Apply to cleaned dataset
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
