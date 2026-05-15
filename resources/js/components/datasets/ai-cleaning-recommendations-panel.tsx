import { router } from '@inertiajs/react';
import {
    AlertTriangle,
    BrainCircuit,
    CheckCircle2,
    Eye,
    Loader2,
    Sparkles,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    CleaningPreview,
    DatasetCleaningRecommendation,
    DatasetPageProps,
} from '@/types/datasets';

interface Props {
    dataset: DatasetPageProps;
    onDatasetUpdated: (dataset: DatasetPageProps) => void;
}

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

export default function AiCleaningRecommendationsPanel({
    dataset,
    onDatasetUpdated,
}: Props) {
    const [recommendations, setRecommendations] = useState<
        DatasetCleaningRecommendation[]
    >(dataset.cleaningRecommendations ?? []);
    const [provider, setProvider] = useState<string | null>(null);
    const [model, setModel] = useState<string | null>(null);
    const [fallbackReason, setFallbackReason] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [previewingId, setPreviewingId] = useState<number | null>(null);
    const [applyingId, setApplyingId] = useState<number | null>(null);
    const [preview, setPreview] = useState<CleaningPreview | null>(null);

    async function loadRecommendations() {
        const response = await fetch(
            `/datasets/${dataset.id}/cleaning/recommendations`,
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            return;
        }

        const payload = await response.json();
        setRecommendations(payload.recommendations ?? []);
    }

    async function generateRecommendations() {
        setLoading(true);
        setError(null);
        setPreview(null);

        const response = await fetch(
            `/datasets/${dataset.id}/cleaning/recommendations/generate`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        );

        const payload = await response.json();
        setLoading(false);

        if (!response.ok) {
            const messages = Object.values(payload.errors ?? {}).flat();
            setError(
                String(
                    messages[0] ??
                        'Unable to generate AI cleaning recommendations.',
                ),
            );

            return;
        }

        setProvider(payload.provider ?? null);
        setModel(payload.model ?? null);
        setFallbackReason(payload.fallback_reason ?? null);
        setRecommendations(payload.recommendations ?? []);
    }

    async function previewRecommendation(recommendationId: number) {
        setPreviewingId(recommendationId);
        setError(null);

        const response = await fetch(
            `/datasets/${dataset.id}/cleaning/recommendations/${recommendationId}/preview`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        );

        const payload = await response.json();
        setPreviewingId(null);

        if (!response.ok) {
            const messages = Object.values(payload.errors ?? {}).flat();
            setError(
                String(messages[0] ?? 'Unable to preview recommendation.'),
            );

            return;
        }

        setPreview(payload.preview);
    }

    async function applyRecommendation(recommendationId: number) {
        setApplyingId(recommendationId);
        setError(null);

        try {
            const response = await fetch(
                `/datasets/${dataset.id}/cleaning/recommendations/${recommendationId}/apply`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ confirmed: true }),
                },
            );

            const payload = await response.json();

            if (!response.ok) {
                const messages = Object.values(payload.errors ?? {}).flat();
                setError(
                    String(messages[0] ?? 'Unable to apply recommendation.'),
                );

                return;
            }

            router.reload({
                only: ['dataset', 'qualityScore'],
                onSuccess: (page) => {
                    onDatasetUpdated(page.props.dataset as DatasetPageProps);
                },
            });
            await loadRecommendations();
            setPreview(null);
        } catch {
            setError('Failed to apply recommendation. Please try again.');
        } finally {
            setApplyingId(null);
        }
    }

    async function dismissRecommendation(recommendationId: number) {
        await fetch(
            `/datasets/${dataset.id}/cleaning/recommendations/${recommendationId}/reject`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        );

        setRecommendations((current) =>
            current.map((recommendation) =>
                recommendation.id === recommendationId
                    ? { ...recommendation, status: 'rejected' }
                    : recommendation,
            ),
        );
    }

    const visibleRecommendations = recommendations.filter(
        (recommendation) =>
            recommendation.status === 'suggested' ||
            recommendation.status === 'accepted',
    );

    return (
        <Card className="border-[#D9E6EC] bg-gradient-to-br from-white to-[#F7FAFC]">
            <CardHeader className="space-y-3">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <div>
                        <div className="mb-2 flex items-center gap-2">
                            <div className="flex size-9 items-center justify-center rounded-full bg-[#E7F0F5]">
                                <BrainCircuit className="size-4 text-[#284B63]" />
                            </div>
                            <CardTitle>AI Cleaning Recommendations</CardTitle>
                        </div>
                        <CardDescription>
                            Gemini analyzes the profile and sample values, then
                            suggests safe cleaning steps. AI never applies
                            changes directly; you preview and approve each
                            recommendation first.
                        </CardDescription>
                    </div>
                    <Button
                        type="button"
                        onClick={generateRecommendations}
                        disabled={loading}
                    >
                        {loading ? (
                            <Loader2 className="mr-2 size-4 animate-spin" />
                        ) : (
                            <Sparkles className="mr-2 size-4" />
                        )}
                        {loading ? 'Analyzing...' : 'Generate recommendations'}
                    </Button>
                </div>

                {(provider || model) && (
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        {provider && (
                            <Badge variant="secondary">{provider}</Badge>
                        )}
                        {model && <Badge variant="secondary">{model}</Badge>}
                        {fallbackReason && (
                            <span className="rounded-full bg-[#FEF3C7] px-2 py-1 text-[#92400E]">
                                Fallback used: {fallbackReason}
                            </span>
                        )}
                    </div>
                )}
            </CardHeader>
            <CardContent className="space-y-4">
                {error && (
                    <div className="flex items-start gap-2 rounded-lg bg-[#FDECEC] p-3 text-sm text-[#C62828]">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        {error}
                    </div>
                )}

                {visibleRecommendations.length === 0 && !loading ? (
                    <div className="rounded-xl border border-dashed bg-white p-6 text-center">
                        <Sparkles className="mx-auto mb-2 size-8 text-[#9CA3AF]" />
                        <p className="text-sm font-medium text-[#353535]">
                            No active AI suggestions yet.
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Generate recommendations after profiling, then
                            review every suggested rule before applying it.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {visibleRecommendations.map((recommendation) => (
                            <div
                                key={recommendation.id}
                                className="space-y-4 rounded-xl border bg-white p-4 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            {recommendation.column_name && (
                                                <Badge variant="outline">
                                                    {recommendation.column_name}
                                                </Badge>
                                            )}
                                            <Badge
                                                className={riskClass(
                                                    recommendation.risk,
                                                )}
                                            >
                                                {recommendation.risk} risk
                                            </Badge>
                                            <Badge variant="secondary">
                                                {Math.round(
                                                    recommendation.confidence *
                                                        100,
                                                )}
                                                % confidence
                                            </Badge>
                                        </div>
                                        <h3 className="font-semibold text-[#353535]">
                                            {recommendation.issue}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {recommendation.reason}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            void dismissRecommendation(
                                                recommendation.id,
                                            )
                                        }
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </div>

                                <div className="rounded-lg bg-[#F7F9FA] p-3 text-sm">
                                    <p className="mb-2 font-medium text-[#353535]">
                                        Suggested deterministic steps
                                    </p>
                                    <div className="space-y-1 text-muted-foreground">
                                        {recommendation.suggested_steps.map(
                                            (step, index) => (
                                                <p
                                                    key={`${step.operation}-${index}`}
                                                >
                                                    {index + 1}.{' '}
                                                    {operationLabel(
                                                        step.operation,
                                                    )}
                                                    {step.column
                                                        ? ` on ${step.column}`
                                                        : ''}
                                                </p>
                                            ),
                                        )}
                                    </div>
                                </div>

                                {(recommendation.before_examples.length > 0 ||
                                    recommendation.after_examples.length >
                                        0) && (
                                    <div className="grid gap-3 text-xs md:grid-cols-2">
                                        <ExampleBox
                                            title="Before"
                                            values={
                                                recommendation.before_examples
                                            }
                                        />
                                        <ExampleBox
                                            title="After"
                                            values={
                                                recommendation.after_examples
                                            }
                                        />
                                    </div>
                                )}

                                {preview?.recommendation_id ===
                                    recommendation.id && (
                                    <PreviewBox preview={preview} />
                                )}

                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() =>
                                            void previewRecommendation(
                                                recommendation.id,
                                            )
                                        }
                                        disabled={
                                            previewingId === recommendation.id
                                        }
                                    >
                                        <Eye className="mr-2 size-4" />
                                        {previewingId === recommendation.id
                                            ? 'Previewing...'
                                            : 'Preview'}
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={() =>
                                            void applyRecommendation(
                                                recommendation.id,
                                            )
                                        }
                                        disabled={
                                            preview?.recommendation_id !==
                                                recommendation.id ||
                                            applyingId === recommendation.id
                                        }
                                    >
                                        <CheckCircle2 className="mr-2 size-4" />
                                        {applyingId === recommendation.id
                                            ? 'Applying...'
                                            : 'Apply approved steps'}
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ExampleBox({ title, values }: { title: string; values: string[] }) {
    return (
        <div className="rounded-lg border bg-white p-3">
            <p className="mb-1 font-medium text-[#353535]">{title}</p>
            {values.length > 0 ? (
                <div className="space-y-1 text-muted-foreground">
                    {values.map((value) => (
                        <p key={value} className="truncate">
                            {value}
                        </p>
                    ))}
                </div>
            ) : (
                <p className="text-muted-foreground">No examples provided.</p>
            )}
        </div>
    );
}

function PreviewBox({ preview }: { preview: CleaningPreview }) {
    return (
        <div className="rounded-lg border border-[#BBD7E6] bg-[#E0F2FE] p-3 text-sm text-[#075985]">
            <p className="font-medium">Preview result</p>
            <p>{preview.message}</p>
            <p className="mt-1">
                {preview.will_change_dataset
                    ? `${preview.affected_count.toLocaleString()} rows or cells may change.`
                    : 'No changes were detected for this recommendation.'}
            </p>
        </div>
    );
}

function riskClass(risk: string): string {
    if (risk === 'high') {
        return 'bg-[#FDECEC] text-[#C62828]';
    }

    if (risk === 'medium') {
        return 'bg-[#FEF3C7] text-[#92400E]';
    }

    return 'bg-[#E8F5E9] text-[#2E7D32]';
}

function operationLabel(operation: string): string {
    const labels: Record<string, string> = {
        remove_duplicates: 'Remove duplicate rows',
        fill_missing: 'Fill missing values',
        convert_type: 'Convert data type',
        standardize_text: 'Standardize text',
        filter_invalid: 'Filter invalid rows',
        replace_values: 'Replace values',
        remove_pattern: 'Remove pattern',
        extract_number: 'Extract number',
        split_column: 'Split column',
        parse_list: 'Parse list values',
        rename_column: 'Rename column',
        remove_column: 'Remove column',
        merge_columns: 'Merge columns',
        numeric_range_filter: 'Filter numeric range',
        date_format_convert: 'Convert date format',
        remove_special_characters: 'Remove special characters',
    };

    return labels[operation] ?? operation;
}
