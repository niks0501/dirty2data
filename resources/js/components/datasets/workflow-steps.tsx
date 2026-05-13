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

interface Props {
    currentStep: number;
}

export default function WorkflowSteps({ currentStep }: Props) {
    return (
        <div className="grid gap-2 rounded-xl border bg-white p-3 shadow-sm sm:grid-cols-5">
            {steps.map((step, index) => {
                const isComplete = index < currentStep;
                const isCurrent = index === currentStep;

                return (
                    <Tooltip key={step.label}>
                        <TooltipTrigger asChild>
                            <div
                                className={[
                                    'flex cursor-default items-center gap-2 rounded-lg px-3 py-2 text-sm',
                                    isCurrent
                                        ? 'bg-[#E7F0F5] font-semibold text-[#284B63]'
                                        : 'text-muted-foreground',
                                ].join(' ')}
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
                            </div>
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
