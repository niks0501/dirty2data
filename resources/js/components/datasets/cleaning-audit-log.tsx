import { CheckCircle2, Clock, ScrollText } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { CleaningLogEntry } from '@/types/datasets';

interface Props {
    log: CleaningLogEntry[];
}

const operationLabels: Record<string, string> = {
    remove_duplicates: 'Removed duplicates',
    fill_missing: 'Filled missing values',
    convert_type: 'Converted data type',
    standardize_text: 'Standardized text',
    filter_invalid: 'Filtered invalid rows',
    replace_values: 'Replaced values',
    remove_pattern: 'Removed pattern',
    extract_number: 'Extracted numbers',
    split_column: 'Split column',
    parse_list: 'Parsed list values',
    rename_column: 'Renamed column',
    remove_column: 'Removed column',
    merge_columns: 'Merged columns',
    numeric_range_filter: 'Filtered numeric range',
    date_format_convert: 'Converted date format',
    remove_special_characters: 'Removed special characters',
    ai_recommendation_pipeline: 'Applied AI recommendation',
};

export default function CleaningAuditLog({ log }: Props) {
    if (log.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Cleaning Audit Log</CardTitle>
                    <CardDescription>
                        Every cleaning action is recorded here for
                        reproducibility and transparency.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="rounded-lg bg-[#F7F9FA] p-6 text-center">
                        <ScrollText className="mx-auto mb-2 size-8 text-[#9CA3AF]" />
                        <p className="text-sm text-muted-foreground">
                            No cleaning actions have been applied yet.
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            When you clean your data, each operation will appear
                            here with a timestamp and summary of what changed.
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    const formatDate = (isoString: string): string =>
        new Date(isoString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });

    const summaryLabels: Record<string, string> = {
        removed_rows: 'Rows removed',
        affected_rows: 'Rows affected',
        affected_cells: 'Cells affected',
        removed_columns: 'Columns removed',
        renamed_columns: 'Columns renamed',
        replaced_cells: 'Cells replaced',
        pattern_removed_cells: 'Pattern removals',
        extracted_cells: 'Numbers extracted',
        split_rows: 'Rows split',
        parsed_cells: 'List cells parsed',
        merged_rows: 'Rows merged',
        cleaned_cells: 'Cells cleaned',
        converted_cells: 'Converted cells',
        standardized_cells: 'Standardized cells',
        filled_cells: 'Filled cells',
        steps: 'Steps applied',
        step_summaries: 'Step summaries',
        rows_before: 'Rows before',
        rows_after: 'Rows after',
        fill_method: 'Method',
        column: 'Column',
        target_type: 'Target type',
        text_format: 'Text format',
        format: 'Format applied',
        method: 'Method',
        replacement: 'Replacement',
        search_value: 'Find value',
        replacement_value: 'Replacement value',
        pattern: 'Pattern',
        delimiter: 'Delimiter',
        output_delimiter: 'Output separator',
        new_columns: 'New columns',
        new_column: 'New column',
        columns: 'Columns',
        min: 'Minimum',
        max: 'Maximum',
        date_format: 'Date format',
    };

    const formatSummaryValue = (value: unknown): string => {
        if (Array.isArray(value)) {
            return `${value.length} item${value.length === 1 ? '' : 's'}`;
        }

        if (value && typeof value === 'object') {
            return JSON.stringify(value);
        }

        return String(value);
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Cleaning Audit Log</CardTitle>
                <CardDescription>
                    Complete history of every cleaning operation applied. This
                    log supports reproducibility and academic integrity.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-0 p-0">
                <div className="divide-y">
                    {log.map((entry, index) => (
                        <div
                            key={`${entry.operation}-${entry.applied_at}`}
                            className="flex items-start gap-4 px-6 py-4"
                        >
                            <div className="flex flex-col items-center">
                                <div className="flex size-8 items-center justify-center rounded-full bg-[#E8F5E9]">
                                    <CheckCircle2 className="size-4 text-[#2E7D32]" />
                                </div>
                                {index < log.length - 1 && (
                                    <div className="mt-1 h-full min-h-[16px] w-0.5 bg-[#D9D9D9]" />
                                )}
                            </div>

                            <div className="min-w-0 flex-1 space-y-1.5">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h4 className="text-sm font-semibold text-[#353535]">
                                        {operationLabels[entry.operation] ??
                                            entry.operation}
                                    </h4>
                                    <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                        <Clock className="size-3" />
                                        {formatDate(entry.applied_at)}
                                    </span>
                                </div>

                                {entry.summary &&
                                    typeof entry.summary === 'object' &&
                                    Object.keys(entry.summary).length > 0 && (
                                        <div className="flex flex-wrap gap-x-4 gap-y-1">
                                            {Object.entries(entry.summary).map(
                                                ([key, value]) => (
                                                    <div
                                                        key={key}
                                                        className="text-xs"
                                                    >
                                                        <span className="text-muted-foreground">
                                                            {summaryLabels[
                                                                key
                                                            ] ?? key}
                                                            :
                                                        </span>{' '}
                                                        <span className="font-medium text-[#353535]">
                                                            {formatSummaryValue(
                                                                value,
                                                            )}
                                                        </span>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    )}
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
