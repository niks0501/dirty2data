import { router } from '@inertiajs/react';
import { Bookmark, Loader2, Play, Save, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { CleaningLogEntry, DatasetPageProps } from '@/types/datasets';

interface Props {
    dataset: DatasetPageProps;
    onDatasetUpdated: (dataset: DatasetPageProps) => void;
}

interface Recipe {
    id: number;
    name: string;
    steps: Array<Record<string, string | number | boolean | null>>;
    createdAt: string;
}

const operationLabels: Record<string, string> = {
    remove_duplicates: 'Remove duplicates',
    fill_missing: 'Fill missing values',
    convert_type: 'Convert data type',
    standardize_text: 'Standardize text',
    filter_invalid: 'Filter invalid rows',
};

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

function stepLabel(step: Record<string, unknown>): string {
    const op = String(step.operation ?? '');
    const label = operationLabels[op] ?? op;
    const col = step.column ? ` on "${String(step.column)}"` : '';

    return `${label}${col}`;
}

export default function RecipePanel({ dataset, onDatasetUpdated }: Props) {
    const [recipes, setRecipes] = useState<Recipe[]>([]);
    const [loadingRecipes, setLoadingRecipes] = useState(false);
    const hasFetched = useRef(false);
    const [recipeName, setRecipeName] = useState('');
    const [saving, setSaving] = useState(false);
    const [applying, setApplying] = useState<number | null>(null);
    const [message, setMessage] = useState<string | null>(null);

    const fetchRecipes = useCallback(async () => {
        setLoadingRecipes(true);

        try {
            const resp = await fetch('/recipes', {
                headers: { Accept: 'application/json' },
            });
            const data = await resp.json();

            setRecipes(data.recipes ?? []);
        } catch {
            // silently fail
        } finally {
            setLoadingRecipes(false);
        }
    }, []);

    useEffect(() => {
        if (!hasFetched.current) {
            hasFetched.current = true;
            void fetchRecipes();
        }
    });

    async function saveRecipe() {
        if (!recipeName.trim()) {
            return;
        }

        setSaving(true);
        setMessage(null);

        const steps = (dataset.cleaningLog ?? []).map(
            (entry: CleaningLogEntry) => {
                const logStr = JSON.stringify(entry.summary ?? {});

                const matches = logStr.match(
                    /operation: (\w+)|column:\s*"?([^",}]+)"?|method:\s*"?(\w+)"?|target_type:\s*"?(\w+)"?|text_format:\s*"?(\w+)"?/g,
                );

                const step: Record<string, string> = {
                    operation: entry.operation,
                };

                for (const match of matches ?? []) {
                    const [key, val] = match.split(':').map((s) => s.trim());

                    if (key && val) {
                        step[key] = val;
                    }
                }

                return step;
            },
        );

        const response = await fetch(`/datasets/${dataset.id}/recipes`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                name: recipeName.trim(),
                steps,
            }),
        });

        setSaving(false);

        if (response.ok) {
            setRecipeName('');
            setMessage('Recipe saved! You can apply it to future datasets.');
            void fetchRecipes();
        } else {
            setMessage('Failed to save recipe. Please try again.');
        }
    }

    async function applyRecipe(recipe: Recipe) {
        setApplying(recipe.id);
        setMessage(null);

        const response = await fetch(
            `/datasets/${dataset.id}/recipes/${recipe.id}/apply`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        );

        setApplying(null);

        if (response.ok) {
            setMessage('Recipe applied! Refreshing...');
            router.reload({
                only: ['dataset'],
                onSuccess: (page) => {
                    onDatasetUpdated(page.props.dataset as DatasetPageProps);
                    setMessage(null);
                },
            });
        } else {
            setMessage('Failed to apply recipe.');
        }
    }

    async function deleteRecipe(recipeId: number) {
        await fetch(`/recipes/${recipeId}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });

        void fetchRecipes();
    }

    const hasCleaningLog = (dataset.cleaningLog?.length ?? 0) > 0;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Data Recipes</CardTitle>
                <CardDescription>
                    Save your cleaning steps as a reusable recipe. Apply it to
                    other datasets with one click — no need to repeat the same
                    steps manually.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                {hasCleaningLog && (
                    <div className="rounded-lg bg-[#E7F0F5] p-4">
                        <p className="mb-2 text-sm font-semibold text-[#284B63]">
                            Current cleaning steps ({dataset.cleaningLog.length}
                            )
                        </p>
                        <ol className="list-decimal space-y-1 pl-4 text-sm text-[#353535]">
                            {(dataset.cleaningLog ?? []).map(
                                (entry: CleaningLogEntry, idx: number) => (
                                    <li key={idx}>
                                        {operationLabels[entry.operation] ??
                                            entry.operation}
                                    </li>
                                ),
                            )}
                        </ol>
                    </div>
                )}

                <div className="space-y-2">
                    <div className="flex gap-2">
                        <Input
                            placeholder="Recipe name (e.g., Monthly Sales Cleanup)"
                            value={recipeName}
                            onChange={(e) => setRecipeName(e.target.value)}
                            disabled={!hasCleaningLog}
                        />
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={saveRecipe}
                            disabled={
                                !recipeName.trim() || saving || !hasCleaningLog
                            }
                        >
                            {saving ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <Save className="size-4" />
                            )}
                            <span className="ml-2">Save</span>
                        </Button>
                    </div>
                    {!hasCleaningLog && (
                        <p className="text-xs text-muted-foreground">
                            Apply at least one cleaning action before saving a
                            recipe.
                        </p>
                    )}
                </div>

                {message && (
                    <div className="rounded-lg bg-[#E8F5E9] p-3 text-sm text-[#2E7D32]">
                        {message}
                    </div>
                )}

                <div className="space-y-2">
                    <p className="text-sm font-medium text-[#353535]">
                        <Bookmark className="mb-0.5 inline size-4 text-[#3C6E71]" />{' '}
                        Your saved recipes
                    </p>

                    {loadingRecipes ? (
                        <div className="flex items-center gap-2 py-4 text-sm text-muted-foreground">
                            <Loader2 className="size-4 animate-spin" />
                            Loading recipes...
                        </div>
                    ) : recipes.length === 0 ? (
                        <div className="rounded-lg bg-[#F7F9FA] p-4 text-center text-sm text-muted-foreground">
                            No saved recipes yet. Apply cleaning operations,
                            then save them as a recipe above.
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {recipes.map((recipe) => (
                                <div
                                    key={recipe.id}
                                    className="flex items-start justify-between gap-3 rounded-lg border p-3"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-[#353535]">
                                            {recipe.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {recipe.steps.length} step
                                            {recipe.steps.length !== 1
                                                ? 's'
                                                : ''}
                                            :{' '}
                                            {recipe.steps
                                                .map(stepLabel)
                                                .join(' → ')}
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {new Date(
                                                recipe.createdAt,
                                            ).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 gap-1">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            onClick={() => applyRecipe(recipe)}
                                            disabled={applying === recipe.id}
                                        >
                                            {applying === recipe.id ? (
                                                <Loader2 className="size-3.5 animate-spin" />
                                            ) : (
                                                <Play className="size-3.5" />
                                            )}
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            onClick={() =>
                                                deleteRecipe(recipe.id)
                                            }
                                        >
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <p className="text-xs text-muted-foreground">
                    Recipes remember the operations you applied, not the
                    specific data. Applying a recipe runs the same cleaning
                    operations on the current dataset.
                </p>
            </CardContent>
        </Card>
    );
}
