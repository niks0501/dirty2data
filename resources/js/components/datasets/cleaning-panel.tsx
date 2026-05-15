import { router } from '@inertiajs/react';
import { CheckCircle2, Eye, HelpCircle, Lightbulb, Wand2 } from 'lucide-react';
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
    | 'filter_invalid'
    | 'replace_values'
    | 'remove_pattern'
    | 'extract_number'
    | 'split_column'
    | 'parse_list'
    | 'rename_column'
    | 'remove_column'
    | 'merge_columns'
    | 'numeric_range_filter'
    | 'date_format_convert'
    | 'remove_special_characters';

interface CleaningConfig {
    operation: CleaningOperation;
    column: string;
    method: string;
    value: string;
    target_type: string;
    text_format: string;
    search_value: string;
    replacement_value: string;
    pattern: string;
    delimiter: string;
    output_delimiter: string;
    new_columns: string;
    new_column: string;
    second_column: string;
    separator: string;
    min: string;
    max: string;
    date_format: string;
}

interface Props {
    dataset: DatasetPageProps;
    onDatasetUpdated: (dataset: DatasetPageProps) => void;
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
            "Numbers stored as text can't be used in calculations. Dates stored as text won't sort properly. Correct types enable charts, stats, and analysis to work.",
        whenToUse:
            "When a column shows numbers left-aligned (stored as text) or dates that don't sort chronologically — common when importing from Excel or CSV exports.",
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
    {
        operation: 'replace_values',
        title: 'Replace Values',
        description:
            'Replace exact tokens such as N/A, Unknown, or typo variants.',
        whyDoThis:
            'Consistent values make categories group correctly and reduce fake unique values during analysis.',
        whenToUse:
            'When one value should be standardized to another value across a column.',
        example: 'Replace "Unknown" with a blank or "Not specified".',
        studentTip:
            'Use this for clear, repeated tokens. Preview first to avoid replacing meaningful values.',
    },
    {
        operation: 'remove_pattern',
        title: 'Remove Pattern',
        description:
            'Remove regex patterns such as bracketed citation markers.',
        whyDoThis:
            'Imported web data often contains citations like [1] that block numeric conversion and clutter labels.',
        whenToUse:
            'When unwanted text follows a predictable pattern, such as [2], (est.), or ref markers.',
        example: 'Pattern \\[^[\\]]*\\] removes values like [2].',
        studentTip:
            'For bracketed references, use \\[[^\\]]*\\]. Always preview regex changes.',
    },
    {
        operation: 'extract_number',
        title: 'Extract Number',
        description: 'Keep the first numeric value from messy text.',
        whyDoThis:
            'Currency symbols, commas, and extra labels prevent calculations. Extracting numbers prepares columns for charts and statistics.',
        whenToUse: 'When values look like "$780,000" or "about 42".',
        example: '"$780,000,000" becomes "780000000".',
        studentTip:
            'After extracting numbers, convert the column to numeric for analysis.',
    },
    {
        operation: 'split_column',
        title: 'Split Column',
        description: 'Split one text column into new columns by a delimiter.',
        whyDoThis:
            'Combined fields hide useful attributes. Splitting makes each attribute easier to profile and analyze.',
        whenToUse: 'When cells contain values like "A - B" or "City, Country".',
        example: 'Split "City, Country" into "City" and "Country".',
        studentTip:
            'Choose new column names that describe the split parts clearly.',
    },
    {
        operation: 'parse_list',
        title: 'Parse List Values',
        description: 'Standardize multi-value cells such as A; B; C.',
        whyDoThis:
            'Consistent separators make list-like data easier to read, compare, and explain in a report.',
        whenToUse:
            'When cells contain several values separated by semicolons or pipes.',
        example: '"A; B; C" becomes "A, B, C".',
        studentTip:
            'This does not create separate rows. It standardizes the list format in the same cell.',
    },
    {
        operation: 'rename_column',
        title: 'Rename Column',
        description: 'Give a column a clearer analysis-ready name.',
        whyDoThis:
            'Clear labels make charts, tables, and defense explanations easier to understand.',
        whenToUse:
            'When uploaded headers are abbreviated, unclear, or inconsistent.',
        example: 'Rename "Amt" to "Amount".',
        studentTip: 'Use short, descriptive names without duplicate headers.',
    },
    {
        operation: 'remove_column',
        title: 'Remove Column',
        description: 'Remove a column from the cleaned working copy.',
        whyDoThis:
            'Irrelevant columns can distract from the analysis and clutter visualizations.',
        whenToUse:
            'Only when a column is not needed for profiling, analysis, or reporting.',
        example: 'Remove an empty notes column.',
        studentTip:
            'This is destructive for the working copy. Preview and make sure the original upload is preserved.',
    },
    {
        operation: 'merge_columns',
        title: 'Merge Columns',
        description: 'Combine two columns into one with a separator.',
        whyDoThis:
            "Splitting related information across columns makes analysis harder. Merging creates a cleaner, more readable field for labels, names, or combined identifiers.",
        whenToUse:
            "When two columns contain parts of the same information (e.g., 'First Name' and 'Last Name', or 'City' and 'Country').",
        example:
            "Merge 'First Name' and 'Last Name' with a space separator to create 'Full Name'.",
        studentTip:
            'Choose a separator that makes sense (space for names, comma for locations). The original columns stay in the dataset.',
    },
    {
        operation: 'numeric_range_filter',
        title: 'Filter Numeric Range',
        description:
            'Keep only rows where a numeric column falls within a range.',
        whyDoThis:
            'Extreme values (outliers) can distort averages and charts. Filtering to a sensible range keeps your analysis focused on relevant data.',
        whenToUse:
            'When a numeric column has unreasonable values (e.g., negative ages, prices far above normal, or values outside expected bounds).',
        example: "Filter 'Age' to keep only values between 0 and 120.",
        studentTip:
            'Set either a minimum, a maximum, or both. Check the column profile first to see the current min and max before deciding your range.',
    },
    {
        operation: 'date_format_convert',
        title: 'Convert Date Format',
        description:
            'Standardize dates to a consistent format like YYYY-MM-DD.',
        whyDoThis:
            "Inconsistent date formats ('01/15/2024' vs '2024-01-15' vs 'Jan 15, 2024') prevent proper sorting and time-based analysis.",
        whenToUse:
            'When dates in a column use different formats, making them sort incorrectly or appear as text.',
        example:
            "Convert all dates to 'Y-m-d' format for consistent sorting and chart-friendly values.",
        studentTip:
            "PHP's strtotime() handles most common date formats automatically. Choose a target format that works well with charts and sorting.",
    },
    {
        operation: 'remove_special_characters',
        title: 'Remove Special Characters',
        description:
            'Strip symbols and non-standard characters from text columns.',
        whyDoThis:
            'Special characters (®, ™, ★, emoji, currency symbols) clutter text data and can break text-based analysis, grouping, and export.',
        whenToUse:
            "When text columns contain unexpected symbols, emoji, or special punctuation that isn't meaningful for analysis.",
        example:
            "Strip '★' and '™' from product names to keep only letters, numbers, spaces, and basic punctuation.",
        studentTip:
            "This keeps letters, numbers, spaces, periods, commas, hyphens, and underscores. Everything else is removed. Preview first to make sure important symbols aren't lost.",
    },
];

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

export default function CleaningPanel({ dataset, onDatasetUpdated }: Props) {
    const [config, setConfig] = useState<CleaningConfig>({
        operation: 'remove_duplicates',
        column: dataset.headers[0] ?? '',
        method: 'mode',
        value: '',
        target_type: 'numeric',
        text_format: 'trim',
        search_value: '',
        replacement_value: '',
        pattern: '\\[[^\\]]*\\]',
        delimiter: ';',
        output_delimiter: ', ',
        new_columns: '',
        new_column: '',
        second_column: '',
        separator: ' ',
        min: '',
        max: '',
        date_format: 'Y-m-d',
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
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    onDatasetUpdated(page.props.dataset as DatasetPageProps);
                },
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
                    Choose a beginner-friendly cleaning action. Each operation
                    explains what it does, when to use it, and why it helps your
                    data.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-5">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    {actionDefinitions.map((action) => {
                        const isSelected =
                            action.operation === config.operation;
                        const isExpanded = action.operation === expandedAction;

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

                    {config.operation === 'replace_values' && (
                        <>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Find value
                                </label>
                                <Input
                                    value={config.search_value}
                                    onChange={(event) =>
                                        updateConfig(
                                            'search_value',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Unknown"
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Replace with
                                </label>
                                <Input
                                    value={config.replacement_value}
                                    onChange={(event) =>
                                        updateConfig(
                                            'replacement_value',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Not specified"
                                />
                            </div>
                        </>
                    )}

                    {config.operation === 'remove_pattern' && (
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Pattern to remove
                            </label>
                            <Input
                                value={config.pattern}
                                onChange={(event) =>
                                    updateConfig('pattern', event.target.value)
                                }
                                placeholder="\\[[^\\]]*\\]"
                            />
                        </div>
                    )}

                    {(config.operation === 'split_column' ||
                        config.operation === 'parse_list') && (
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Delimiter
                            </label>
                            <Input
                                value={config.delimiter}
                                onChange={(event) =>
                                    updateConfig(
                                        'delimiter',
                                        event.target.value,
                                    )
                                }
                                placeholder=";"
                            />
                        </div>
                    )}

                    {config.operation === 'parse_list' && (
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Output separator
                            </label>
                            <Input
                                value={config.output_delimiter}
                                onChange={(event) =>
                                    updateConfig(
                                        'output_delimiter',
                                        event.target.value,
                                    )
                                }
                                placeholder=", "
                            />
                        </div>
                    )}

                    {config.operation === 'split_column' && (
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                New columns
                            </label>
                            <Input
                                value={config.new_columns}
                                onChange={(event) =>
                                    updateConfig(
                                        'new_columns',
                                        event.target.value,
                                    )
                                }
                                placeholder="First part, Second part"
                            />
                        </div>
                    )}

                    {config.operation === 'rename_column' && (
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                New column name
                            </label>
                            <Input
                                value={config.new_column}
                                onChange={(event) =>
                                    updateConfig(
                                        'new_column',
                                        event.target.value,
                                    )
                                }
                                placeholder="Clear column name"
                            />
                        </div>
                    )}

                    {config.operation === 'merge_columns' && (
                        <>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Second column
                                </label>
                                <Select
                                    value={config.second_column}
                                    onValueChange={(value) =>
                                        updateConfig('second_column', value)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select second column" />
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
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Separator
                                </label>
                                <Input
                                    value={config.separator}
                                    onChange={(event) =>
                                        updateConfig(
                                            'separator',
                                            event.target.value,
                                        )
                                    }
                                    placeholder=" "
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    New column name
                                </label>
                                <Input
                                    value={config.new_column}
                                    onChange={(event) =>
                                        updateConfig(
                                            'new_column',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Merged column name"
                                />
                            </div>
                        </>
                    )}

                    {config.operation === 'numeric_range_filter' && (
                        <>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Min value
                                </label>
                                <Input
                                    type="number"
                                    value={config.min}
                                    onChange={(event) =>
                                        updateConfig(
                                            'min',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="0"
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Max value
                                </label>
                                <Input
                                    type="number"
                                    value={config.max}
                                    onChange={(event) =>
                                        updateConfig(
                                            'max',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="100"
                                />
                            </div>
                        </>
                    )}

                    {config.operation === 'date_format_convert' && (
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Target date format
                            </label>
                            <Select
                                value={config.date_format}
                                onValueChange={(value) =>
                                    updateConfig('date_format', value)
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Y-m-d" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Y-m-d">
                                        YYYY-MM-DD
                                    </SelectItem>
                                    <SelectItem value="m/d/Y">
                                        MM/DD/YYYY
                                    </SelectItem>
                                    <SelectItem value="d-m-Y">
                                        DD-MM-YYYY
                                    </SelectItem>
                                    <SelectItem value="F j, Y">
                                        Month Day, Year
                                    </SelectItem>
                                    <SelectItem value="M j, Y">
                                        Mon Day, Year
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {/* remove_special_characters: no additional params needed — uses the column selector above */}
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
                                    The data may already be in the desired
                                    state, or the operation may not apply to the
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
