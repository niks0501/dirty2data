import { Head, Link } from '@inertiajs/react';
import { BarChart3, Database, FileSpreadsheet, Sparkles } from 'lucide-react';
import WorkflowSteps from '@/components/datasets/workflow-steps';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export default function Dashboard() {
    const cards = [
        {
            title: 'Upload datasets',
            description:
                'Import CSV or Excel files with validation for type, size, readability, and headers.',
            icon: FileSpreadsheet,
        },
        {
            title: 'Profile quality',
            description:
                'Review rows, columns, data types, missing values, duplicates, and statistics.',
            icon: Database,
        },
        {
            title: 'Visualize insights',
            description:
                'Generate bar, line, and pie charts from cleaned working data.',
            icon: BarChart3,
        },
    ];

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6">
                <div className="rounded-2xl bg-[#284B63] p-6 text-white shadow-sm">
                    <div className="max-w-3xl space-y-3">
                        <div className="flex items-center gap-2 text-sm text-white/80">
                            <Sparkles className="size-4" />
                            Data Cleaning and Analytics System
                        </div>
                        <h1 className="text-2xl font-bold md:text-3xl">
                            Turn messy tabular files into clean insights.
                        </h1>
                        <p className="text-white/80">
                            Upload datasets, inspect their structure, clean
                            common quality issues, and visualize patterns from
                            one guided dashboard workflow.
                        </p>
                        <Button
                            asChild
                            className="bg-white text-[#284B63] hover:bg-white/90"
                        >
                            <Link href="/datasets">Upload dataset</Link>
                        </Button>
                    </div>
                </div>

                <WorkflowSteps currentStep={0} />

                <div className="grid gap-4 md:grid-cols-3">
                    {cards.map((card) => (
                        <Card key={card.title}>
                            <CardHeader>
                                <card.icon className="mb-2 size-6 text-[#3C6E71]" />
                                <CardTitle>{card.title}</CardTitle>
                                <CardDescription>
                                    {card.description}
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Start with an upload</CardTitle>
                        <CardDescription>
                            No dataset is selected on the dashboard. Go to
                            Datasets to upload a CSV or Excel file, then
                            continue through Profile → Clean → Analyze →
                            Visualize.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button asChild>
                            <Link href="/datasets">Open datasets</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
    ],
};
