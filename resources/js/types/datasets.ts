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
    status: string;
}

export interface DatasetProcessingStatus {
    progress: number;
    rowsProcessed: number;
    startedAt: string | null;
    finishedAt: string | null;
    error: string | null;
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
    outliers_iqr?: {
        count: number;
        lower_bound: number;
        upper_bound: number;
        q1: number;
        q3: number;
    } | null;
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
    column?: string | null;
    source?: 'manual' | 'ai_recommendation' | string;
    recommendation_id?: number | null;
    explanation?: string | null;
    steps?: CleaningLogEntry[];
}

export interface CleaningRecommendationStep {
    operation: string;
    column: string;
    parameters: Record<string, DatasetValue | string[] | number[]>;
}

export interface DatasetCleaningRecommendation {
    id: number;
    rec_id: string | null;
    provider: string;
    model: string | null;
    status: 'suggested' | 'accepted' | 'rejected' | 'applied' | 'expired';
    column_name: string | null;
    issue: string;
    severity: 'low' | 'medium' | 'high';
    confidence: number;
    risk: 'low' | 'medium' | 'high';
    suggested_steps: CleaningRecommendationStep[];
    before_examples: string[];
    after_examples: string[];
    reason: string | null;
    created_at: string | null;
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
    risk?: string;
    reason?: string | null;
    recommendation_id?: number;
    steps?: CleaningRecommendationStep[];
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
    previewNote: string | null;
    profile: DatasetProfile | null;
    selectedColumn: string | null;
    selectedColumnProfile: DatasetColumnProfile | null;
    cleaningLog: CleaningLogEntry[];
    cleaningSnapshots: Array<Record<string, DatasetValue>>[];
    cleaningRecommendations: DatasetCleaningRecommendation[];
    pagination: DatasetPagination;
    chartRecommendations: DatasetChartRecommendation[];
    chart: DatasetChartPayload;
    createdAt: string;
    status: string;
    processing: DatasetProcessingStatus;
}

/** Shape of a single sub-score (0-100) in the breakdown. */
export interface QualityBreakdownScore {
    completeness: number;
    uniqueness: number;
    validity: number;
    consistency: number;
    type_accuracy: number;
}

/** Counts for detected dataset issues. */
export interface QualityIssuesSummary {
    missing_values: number;
    duplicate_rows: number;
    invalid_values: number;
    inconsistent_columns: number;
    type_issue_columns: number;
}

/** A single issue surfaced by the quality scoring engine. */
export interface QualityIssue {
    code: string;
    severity: 'error' | 'warning' | 'info';
    message: string;
    column: string | null;
    count: number | null;
}

/** A plain-language recommendation for improving data quality. */
export interface QualityRecommendation {
    code: string;
    message: string;
    recommended_action: string;
}

/** Deterministic data quality score returned after profiling. */
export interface DatasetQualityScore {
    quality_score: number;
    status: string;
    breakdown: QualityBreakdownScore;
    issues_summary: QualityIssuesSummary;
    issues: QualityIssue[];
    recommendations: QualityRecommendation[];
}
