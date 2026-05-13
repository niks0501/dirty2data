import { router } from '@inertiajs/react';
import {
    CheckCircle2,
    Eye,
    HelpCircle,
    Lightbulb,
    Wand2,
} from 'lucide-react';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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

interface ActionDefinition {
    operation: CleaningOperation;
    title: string;
    description: string;
    whyDoThis: string;
    whenToUse: string;
    example: string;
    studentTip: string;
}

const actionDefinitions: ActionDefinition[] = [
    {
        operation: 'remove_duplicates',
        title: 'Remove Duplicates',
        description: 'Keep the first copy of each repeated row.',
        whyDoThis:
            'Duplicate rows inflate your row count and can make averages, totals, and charts inaccurate. Removing them ensures each record represents a unique observation.',
        whenToUse:
            'When the same data row appears more than once — common after merging files from multiple sources or exporting reports that repeat data.',
        example:
            'If row 5 and row 47 have the same name, date, and value, this keeps row 5 and removes row 47.',
        studentTip:
            'Before removing, check if duplicates are legitimate (e.g., two students sharing a name in different sections) or truly repeated data.',
    },
    {
        operation: 'fill_missing',
        title: 'Fill Missing Values',
        description:
            'Replace blanks using mean, median, mode, or a custom value.',
        whyDoThis:
            'Blank cells break calculations, charts, and analysis. Filling them with sensible values (instead of deleting the row) preserves your data for reliable statistics.',
        whenToUse:
            'When you have missing cells in a column — especially numeric columns where you need to calculate averages, or categorical columns where blanks confuse grouping.',
        example:
            'A survey column where 10% of respondents skipped a question: fill with the most common answer (mode) to keep your dataset complete.',
        studentTip:
            'For school projects: use mean for symmetric data, median for data with outliers. Explain your choice — professors value justification over any single method.',
    },
    {
        operation: 'convert_type',
        title: 'Convert Data Type',
        description:
            'Convert values to numeric, text, date, or boolean format.',
        whyDoThis:
            'Numbers stored as text can\'t be used in calculations. Dates stored as text won\'t sort properly. Correct types enable charts, stats, and analysis to work.',
        whenToUse:
            'When a column shows numbers left-aligned (stored as text) or dates that don\'t sort chronologically — common when importing from Excel or CSV exports.',
        example:
            'A "Price" column showing "$10.00" as text: convert to numeric (10.00) so you can calculate totals and averages.',
        studentTip:
            'If your chart won\'t render or stats show "N/A," check that the column type is correct. This is the #1 cause of "why isn\'t my chart working?"',
    },
    {
        operation: 'standardize_text',
        title: 'Standardize Text Format',
        description: 'Trim text and normalize case for cleaner categories.',
        whyDoThis:
            'Inconsistent text ("Male" vs "male" vs "MALE" vs " male ") creates fake duplicate categories, splitting what should be a single group into many.',
        whenToUse:
            'When categorical columns (gender, department, country) show too many unique values or when sorting looks wrong because of mixed case.',
        example:
            'A "Department" column with "Engineering," "engineering," and " ENGINEERING" treated as three separate categories. Standardizing merges them.',
        studentTip:
            'Always trim whitespace first — invisible trailing spaces are the silent killer of data analysis. Then standardize case.',
    },
    {
        operation: 'filter_invalid',
        title: 'Filter Invalid Rows',
        description: 'Remove rows that do not match the expected column type.',
        whyDoThis:
            'Invalid data (letters in a number column, dates in a text field that should be names) can corrupt your analysis and produce wrong conclusions.',
        whenToUse:
            "When a column should contain only numeric/date values but some rows have text or empty values that can't be fixed by conversion.",
        example:
            'An "Age" column where someone typed "twenty-five" instead of 25. Filter removes the row so your age statistics stay accurate.',
        studentTip:
            'Review filtered rows before removing them. Sometimes invalid data is actually correct data in an unexpected format (e.g., "N/A" might mean "Not Applicable," not missing).',
    },
];

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
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
    const [expandedAction, setExpandedAction] =
        useState<CleaningOperation>('remove_duplicates');

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
            { ...config },
            { preserveScroll: true },
        );
    }

    const requiresColumn = config.operation !== 'remove_duplicates';
    const canApply = preview !== null && preview.operation === config.operation;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Clean</CardTitle>
                <CardDescription>
                    Choose a beginner-friendly cleaning action. Each operation
                    explains what it does, when to use it, and why it helps your
                    data.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-5">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    {actionDefinitions.map((action) => {
                        const isSelected =
                            action.operation === config.operation;
                        const isExpanded =
                            action.operation === expandedAction;

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
                                onClick={() => {
                                    updateConfig('operation', action.operation);
                                    setExpandedAction(action.operation);
                                }}
                            >
                                <div className="mb-2 flex items-center gap-2 font-semibold text-[#353535]">
                                    <Wand2 className="size-4 text-[#3C6E71]" />
                                    {action.title}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {action.description}
                                </p>

                                {isExpanded && (
                                    <div className="mt-3 space-y-2 border-t pt-3">
                                        <div className="flex items-start gap-1.5">
                                            <HelpCircle className="mt-0.5 size-3.5 shrink-0 text-[#3C6E71]" />
                                            <p className="text-xs text-[#353535]">
                                                <span className="font-semibold">
                                                    Why?
                                                </span>{' '}
                                                {action.whyDoThis}
                                            </p>
                                        </div>
                                        <div className="flex items-start gap-1.5">
                                            <Lightbulb className="mt-0.5 size-3.5 shrink-0 text-[#F59E0B]" />
                                            <p className="text-xs text-[#353535]">
                                                <span className="font-semibold">
                                                    Student Tip:
                                                </span>{' '}
                                                {action.studentTip}
                                            </p>
                                        </div>
                                    </div>
                                )}
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
                            <Tooltip>
                                <TooltipTrigger asChild>
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
                                                <SelectItem
                                                    key={header}
                                                    value={header}
                                                >
                                                    {header}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </TooltipTrigger>
                                <TooltipContent side="top">
                                    <p className="text-xs">
                                        Choose which column to apply this
                                        cleaning action to.
                                    </p>
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    )}

                    {config.operation === 'fill_missing' && (
                        <>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Fill method
                                </label>
                                <Tooltip>
                                    <TooltipTrigger asChild>
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
                                                    Mean (average)
                                                </SelectItem>
                                                <SelectItem value="median">
                                                    Median (middle value)
                                                </SelectItem>
                                                <SelectItem value="mode">
                                                    Mode (most common)
                                                </SelectItem>
                                                <SelectItem value="custom">
                                                    Custom value
                                                </SelectItem>
                                                <SelectItem value="blank">
                                                    Blank replacement
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </TooltipTrigger>
                                    <TooltipContent
                                        side="top"
                                        className="max-w-[260px]"
                                    >
                                        <div className="text-xs">
                                            <p className="font-semibold">
                                                Which method to choose:
                                            </p>
                                            <ul className="mt-1 list-disc space-y-0.5 pl-3">
                                                <li>
                                                    <strong>Mean:</strong> Best
                                                    for symmetric data without
                                                    outliers.
                                                </li>
                                                <li>
                                                    <strong>Median:</strong>{' '}
                                                    Best when your data has
                                                    extreme values.
                                                </li>
                                                <li>
                                                    <strong>Mode:</strong> Use
                                                    for categories (e.g.,
                                                    gender, department).
                                                </li>
                                                <li>
                                                    <strong>Custom:</strong>{' '}
                                                    Type any value you prefer.
                                                </li>
                                            </ul>
                                        </div>
                                    </TooltipContent>
                                </Tooltip>
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
                            <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-[#2E7D32]" />
                            <div className="space-y-1">
                                <h3 className="font-semibold text-[#353535]">
                                    Preview:{' '}
                                    {
                                        actionDefinitions.find(
                                            (a) =>
                                                a.operation ===
                                                preview.operation,
                                        )?.title
                                    }
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {preview.message}
                                </p>
                                {preview.will_change_dataset && (
                                    <p className="flex items-center gap-1.5 text-sm font-medium text-[#0284C7]">
                                        <Lightbulb className="size-3.5" />
                                        This action will modify{' '}
                                        {preview.affected_count.toLocaleString()}{' '}
                                        row
                                        {preview.affected_count !== 1
                                            ? 's'
                                            : ''}
                                        . Click &quot;Apply&quot; to confirm.
                                    </p>
                                )}
                            </div>
                        </div>

                        {preview.changed_rows.length > 0 ? (
                            <div className="overflow-x-auto rounded-lg border bg-white">
                                <table className="w-full text-sm">
                                    <thead className="bg-[#F1F3F4]">
                                        <tr>
                                            <th className="w-12 px-3 py-2 text-left">
                                                #
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
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    {row.row_number}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <span
                                                        className={[
                                                            'inline-block rounded-full px-2 py-0.5 text-xs font-medium',
                                                            row.status ===
                                                            'removed'
                                                                ? 'bg-[#FDECEC] text-[#C62828]'
                                                                : row.status ===
                                                                    'added'
                                                                  ? 'bg-[#E8F5E9] text-[#2E7D32]'
                                                                  : 'bg-[#E0F2FE] text-[#0284C7]',
                                                        ].join(' ')}
                                                    >
                                                        {row.status}
                                                    </span>
                                                </td>
                                                <td className="max-w-[280px] truncate px-3 py-2">
                                                    {row.before
                                                        ? formatRowForPreview(
                                                              row.before,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td className="max-w-[280px] truncate px-3 py-2">
                                                    {row.after
                                                        ? formatRowForPreview(
                                                              row.after,
                                                          )
                                                        : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="rounded-lg bg-[#F1F3F4] p-4 text-center text-sm text-muted-foreground">
                                <p>
                                    No row-level changes were found for this
                                    action.
                                </p>
                                <p className="mt-1 text-xs">
                                    The data may already be in the desired state,
                                    or the operation may not apply to the
                                    selected column.
                                </p>
                            </div>
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

                <div className="rounded-lg bg-[#E0F2FE] p-3 text-sm text-[#0284C7]">
                    <Lightbulb className="mb-1 inline size-3.5 align-middle" />{' '}
                    <strong>Remember:</strong> Cleaning changes only the working
                    copy. Your original uploaded file is preserved and never
                    modified. You can always re-upload to start fresh.
                </div>
            </CardContent>
        </Card>
    );
}

function formatRowForPreview(
    row: Record<string, string | number | boolean | null>,
): string {
    const entries = Object.entries(row);

    if (entries.length <= 2) {
        return entries.map(([k, v]) => `${k}: ${v ?? ''}`).join(', ');
    }

    return JSON.stringify(row);
}
