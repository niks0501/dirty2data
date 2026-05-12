export interface DatasetPreview {
    headers: string[];
    sample_rows: Array<Record<string, string | number | boolean | null>>;
    row_count: number;
    column_count: number;
}

export interface Dataset {
    id: number;
    team_id: number;
    uploaded_by_id: number;
    original_name: string;
    disk_path: string;
    mime_type: string | null;
    size_bytes: number;
    preview: DatasetPreview | null;
    created_at: string;
    updated_at: string;
}

// Shape sent to the frontend via Inertia page props (controller converts snake_case → camelCase)
export interface DatasetPageProps {
    id: number;
    originalName: string;
    mimeType: string | null;
    sizeBytes: number;
    rowCount: number;
    columnCount: number;
    headers: string[];
    previewRows: Array<Record<string, string | number | boolean | null>>;
    createdAt: string;
}
