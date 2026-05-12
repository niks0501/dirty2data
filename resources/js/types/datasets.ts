export type DatasetValue = string | number | boolean | null;

export interface DatasetPreview {
    headers: string[];
    sample_rows: Array<Record<string, DatasetValue>>;
    row_count: number;
    column_count: number;
}

export interface DatasetSummary {
    id: number;
    originalName: string;
    rowCount: number;
    columnCount: number;
    createdAt: string;
}

export interface DatasetColumnProfile {
    name: string;
    type: 'numeric' | 'text' | 'date' | 'boolean' | 'empty';
    missing_count: number;
    missing_percentage: number;
    unique_count: number;
    distinct_values: Array<{
        value: string;
        count: number;
    }>;
    sample_values: string[];
    most_frequent: {
        value: string;
        count: number;
    } | null;
    mode: string | null;
    minimum?: string | number | null;
    maximum?: string | number | null;
    average?: number | null;
    median?: number | null;
}

export interface DatasetProfile {
    row_count: number;
    column_count: number;
    duplicate_count: number;
    columns: DatasetColumnProfile[];
}

export interface CleaningLogEntry {
    operation: string;
    applied_at: string;
    summary: Record<string, DatasetValue>;
}

export interface CleaningPreviewRowChange {
    row_number: number;
    status: 'added' | 'removed' | 'changed' | string;
    before: Record<string, DatasetValue> | null;
    after: Record<string, DatasetValue> | null;
}

export interface CleaningPreview {
    operation: string;
    summary: Record<string, DatasetValue>;
    message: string;
    affected_count: number;
    changed_rows: CleaningPreviewRowChange[];
    will_change_dataset: boolean;
}

export interface DatasetPagination {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
}

export interface DatasetChartMetadata {
    total_rows_used: number;
    missing_rows_skipped: number;
    aggregation: string;
    truncated: boolean;
    total_categories?: number;
    bin_count?: number;
    date_group?: string;
    correlation?: number | null;
}

export interface DatasetChartPayload {
    type: 'bar' | 'line' | 'pie' | 'histogram' | 'scatter' | string;
    title: string;
    data: Array<{
        name: string;
        value: number;
        x?: number;
        y?: number;
        bin_min?: number;
        bin_max?: number;
    }>;
    message: string | null;
    x_column: string | null;
    y_column: string | null;
    reason?: string | null;
    metadata: DatasetChartMetadata | null;
}

export interface DatasetChartRecommendation {
    type: 'bar' | 'line' | 'pie' | 'histogram' | 'scatter' | string;
    x_column: string;
    y_column: string | null;
    title: string;
    reason: string;
}

export interface DatasetPageProps {
    id: number;
    originalName: string;
    mimeType: string | null;
    extension: string | null;
    sizeBytes: number;
    rowCount: number;
    originalRowCount: number;
    columnCount: number;
    headers: string[];
    previewRows: Array<Record<string, DatasetValue>>;
    profile: DatasetProfile | null;
    selectedColumn: string | null;
    selectedColumnProfile: DatasetColumnProfile | null;
    cleaningLog: CleaningLogEntry[];
    pagination: DatasetPagination;
    chartRecommendations: DatasetChartRecommendation[];
    chart: DatasetChartPayload;
    createdAt: string;
}
