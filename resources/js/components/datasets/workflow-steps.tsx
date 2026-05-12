import { CheckCircle2 } from 'lucide-react';

const steps = ['Upload', 'Profile', 'Clean', 'Analyze', 'Visualize'];

interface Props {
    current: string;
}

export default function WorkflowSteps({ current }: Props) {
    const currentIndex = steps.indexOf(current);

    return (
        <div className="grid gap-2 rounded-xl border bg-white p-3 shadow-sm sm:grid-cols-5">
            {steps.map((step, index) => {
                const isComplete = index < currentIndex;
                const isCurrent = step === current;

                return (
                    <div
                        key={step}
                        className={[
                            'flex items-center gap-2 rounded-lg px-3 py-2 text-sm',
                            isCurrent
                                ? 'bg-[#E7F0F5] font-semibold text-[#284B63]'
                                : 'text-muted-foreground',
                        ].join(' ')}
                    >
                        <CheckCircle2
                            className={[
                                'size-4',
                                isComplete || isCurrent
                                    ? 'text-[#2E7D32]'
                                    : 'text-muted-foreground',
                            ].join(' ')}
                        />
                        <span>{step}</span>
                    </div>
                );
            })}
        </div>
    );
}
