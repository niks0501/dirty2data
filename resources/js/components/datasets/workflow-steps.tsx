import { CheckCircle2 } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const steps = [
    {
        label: 'Upload',
        tooltip:
            'Upload your CSV or Excel file to begin the data cleaning workflow.',
    },
    {
        label: 'Profile',
        tooltip:
            'Inspect your data: column types, missing values, basic statistics, and duplicate detection.',
    },
    {
        label: 'Clean',
        tooltip:
            'Fix issues using beginner-friendly operations: remove duplicates, fill blanks, convert types, standardize text.',
    },
    {
        label: 'Analyze',
        tooltip:
            'Review data quality scores, column insights, and recommendations before visualizing.',
    },
    {
        label: 'Visualize',
        tooltip:
            'Generate charts from your cleaned data to uncover patterns and share insights.',
    },
];

export interface WorkflowStepState {
    /** 0-based index of the currently active (highlighted) step */
    currentStep: number;
    /** Highest step index the user has reached / completed */
    maxUnlockedStep: number;
}

interface Props {
    currentStep: number;
    maxUnlockedStep?: number;
    onStepClick?: (stepIndex: number) => void;
}

export default function WorkflowSteps({
    currentStep,
    maxUnlockedStep,
    onStepClick,
}: Props) {
    const unlocked =
        maxUnlockedStep !== undefined ? maxUnlockedStep : currentStep;

    return (
        <div className="grid gap-2 rounded-xl border bg-white p-3 shadow-sm sm:grid-cols-5">
            {steps.map((step, index) => {
                const isComplete = index < currentStep;
                const isCurrent = index === currentStep;
                const isFuture = index > unlocked;
                const clickable =
                    onStepClick && index <= unlocked && index !== currentStep;

                return (
                    <Tooltip key={step.label}>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                className={[
                                    'flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors',
                                    isCurrent
                                        ? 'bg-[#E7F0F5] font-semibold text-[#284B63]'
                                        : '',
                                    isComplete && !isCurrent
                                        ? 'text-[#353535]'
                                        : '',
                                    isFuture ? 'text-muted-foreground' : '',
                                    clickable
                                        ? 'cursor-pointer hover:bg-[#F7F9FA]'
                                        : 'cursor-default',
                                ].join(' ')}
                                disabled={!clickable}
                                onClick={() => {
                                    if (clickable && onStepClick) {
                                        onStepClick(index);
                                    }
                                }}
                            >
                                <CheckCircle2
                                    className={[
                                        'size-4 shrink-0',
                                        isComplete || isCurrent
                                            ? 'text-[#2E7D32]'
                                            : 'text-muted-foreground',
                                    ].join(' ')}
                                />
                                <span>{step.label}</span>
                            </button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" className="max-w-[220px]">
                            <p className="text-xs">{step.tooltip}</p>
                        </TooltipContent>
                    </Tooltip>
                );
            })}
        </div>
    );
}
