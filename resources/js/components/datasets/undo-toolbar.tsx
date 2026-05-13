import { router } from '@inertiajs/react';
import { Info, RotateCcw, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import type { CleaningLogEntry } from '@/types/datasets';

interface Props {
    datasetId: number;
    cleaningLog: CleaningLogEntry[];
    snapshotCount: number;
}

const operationLabels: Record<string, string> = {
    remove_duplicates: 'Removed duplicates',
    fill_missing: 'Filled missing values',
    convert_type: 'Converted data type',
    standardize_text: 'Standardized text',
    filter_invalid: 'Filtered invalid rows',
};

export default function UndoToolbar({
    datasetId,
    cleaningLog,
    snapshotCount,
}: Props) {
    const [pendingUndoLast, setPendingUndoLast] = useState(false);
    const [pendingReset, setPendingReset] = useState(false);

    const hasOperations = cleaningLog.length > 0;

    const handleUndoLast = () => {
        setPendingUndoLast(true);
        router.post(`/datasets/${datasetId}/undo`, {}, {
            onFinish: () => setPendingUndoLast(false),
        });
    };

    const handleUndoTo = (index: number) => {
        router.post(
            `/datasets/${datasetId}/undo/${index}`,
            {},
            { preserveScroll: true },
        );
    };

    const handleReset = () => {
        setPendingReset(true);
        router.post(`/datasets/${datasetId}/reset`, {}, {
            onFinish: () => setPendingReset(false),
        });
    };

    const latestOperationLabel = hasOperations
        ? operationLabels[cleaningLog[cleaningLog.length - 1].operation] ??
            cleaningLog[cleaningLog.length - 1].operation
        : '';

    const formatDate = (isoString: string): string =>
        new Date(isoString).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });

    return (
        <div className="flex flex-wrap items-center gap-2 rounded-lg bg-[#F7F9FA] px-4 py-3">
            <div className="flex items-center gap-1.5">
                <Undo2 className="size-4 text-[#469F7A]" />
                <span className="text-xs font-semibold text-[#353535]">
                    Undo
                </span>
            </div>

            <div className="flex flex-1 flex-wrap items-center gap-2">
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!hasOperations || pendingUndoLast}
                            onClick={() => {
                                if (
                                    window.confirm(
                                        `Undo the last operation: "${latestOperationLabel}"?`,
                                    )
                                ) {
                                    handleUndoLast();
                                }
                            }}
                        >
                            {pendingUndoLast
                                ? 'Undoing...'
                                : 'Undo Last'}
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        {hasOperations
                            ? `Undo the last action: ${latestOperationLabel}`
                            : 'No operations to undo yet'}
                    </TooltipContent>
                </Tooltip>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!hasOperations}
                        >
                            Undo To...
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-72">
                        <div className="px-2 py-1.5 text-xs font-semibold text-[#353535]">
                            Choose a point to revert to
                        </div>
                        <DropdownMenuSeparator />

                        {cleaningLog
                            .map((entry, logIndex) => {
                                const snapshotIndex =
                                    cleaningLog.length - 1 - logIndex;
                                const label =
                                    operationLabels[entry.operation] ??
                                    entry.operation;
                                const date = formatDate(entry.applied_at);

                                return (
                                    <DropdownMenuItem
                                        key={`${entry.operation}-${entry.applied_at}`}
                                        onClick={() => {
                                            if (
                                                window.confirm(
                                                    `Revert to state before "${label}"? Operations after this point will be undone.`,
                                                )
                                            ) {
                                                handleUndoTo(
                                                    cleaningLog.length - 1 -
                                                        logIndex,
                                                );
                                            }
                                        }}
                                    >
                                        <div className="flex flex-col gap-0.5">
                                            <span className="text-sm font-medium">
                                                Before: {label}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {date}
                                                {snapshotIndex === 0
                                                    ? ' (undo last action)'
                                                    : ` (undo ${snapshotIndex + 1} actions)`}
                                            </span>
                                        </div>
                                    </DropdownMenuItem>
                                );
                            })}

                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onClick={() => {
                                if (
                                    window.confirm(
                                        'Reset to original state? All cleaning operations will be undone.',
                                    )
                                ) {
                                    handleReset();
                                }
                            }}
                        >
                            <span className="text-sm font-medium text-[#D32F2F]">
                                Reset to Original
                            </span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!hasOperations || pendingReset}
                            onClick={() => {
                                if (
                                    window.confirm(
                                        'Reset to original? All cleaning operations will be permanently undone.',
                                    )
                                ) {
                                    handleReset();
                                }
                            }}
                        >
                            <RotateCcw className="size-3.5" />
                            {pendingReset ? 'Resetting...' : 'Reset All'}
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        {hasOperations
                            ? 'Restore the dataset to its original uploaded state'
                            : 'No operations to reset'}
                    </TooltipContent>
                </Tooltip>
            </div>

            {hasOperations && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <div className="flex items-center gap-1 rounded-full bg-[#E3F2FD] px-2.5 py-1 text-xs text-[#1565C0]">
                            <Info className="size-3" />
                            <span>{snapshotCount} snapshots saved</span>
                        </div>
                    </TooltipTrigger>
                    <TooltipContent>
                        Up to 10 previous states are kept. The oldest snapshots
                        are automatically dropped when the limit is reached.
                    </TooltipContent>
                </Tooltip>
            )}
        </div>
    );
}
