import { BookOpen, GraduationCap, Lightbulb } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface Props {
    currentStep: number;
}

const stepGuides = [
    {
        step: 'Upload',
        icon: 'Upload',
        objective:
            'Get your data into the system so it can be analyzed and cleaned.',
        whatYouLearn: [
            'How to prepare a CSV or Excel file for import',
            'What makes a well-structured dataset',
            'How file formats affect data quality',
        ],
        academicNote:
            'In academic work, always document your data source and explain any pre-cleaning you did before uploading.',
    },
    {
        step: 'Profile',
        icon: 'Profile',
        objective:
            'Understand what your data looks like — types, missing values, and basic statistics.',
        whatYouLearn: [
            'How to identify data types in each column',
            'What missing values tell you about data collection',
            'How to spot duplicates and why they matter',
        ],
        academicNote:
            'The profiling step is your "data audit." Professors expect you to describe your dataset before cleaning it.',
    },
    {
        step: 'Clean',
        icon: 'Clean',
        objective:
            'Fix data issues to make your dataset reliable for analysis.',
        whatYouLearn: [
            'How to choose between mean, median, or mode for filling missing values',
            'Why removing duplicates is important for accurate counts',
            'How text standardization prevents fake duplicate categories',
        ],
        academicNote:
            'Always justify your cleaning choices in your methodology section. The audit log provides a complete record.',
    },
    {
        step: 'Analyze',
        icon: 'Analyze',
        objective:
            'Review data quality scores and understand your dataset\'s strengths and weaknesses.',
        whatYouLearn: [
            'How data quality is measured (completeness, uniqueness, validity, consistency, type accuracy)',
            'How to interpret recommendations and prioritize fixes',
            'How to compare your dataset\'s quality against standards',
        ],
        academicNote:
            'Quality scores help you quantify how "clean" your data is — useful for methodology sections and defense presentations.',
    },
    {
        step: 'Visualize',
        icon: 'Visualize',
        objective:
            'Create charts that reveal patterns, trends, and insights in your cleaned data.',
        whatYouLearn: [
            'Which chart type to choose for your data (bar, line, pie, histogram, scatter)',
            'How to configure chart axes and aggregations',
            'How to read correlation coefficients and distributions',
        ],
        academicNote:
            'Choose charts that answer your research question. A good visualization tells a story — don\'t just create charts for the sake of it.',
    },
];

const CONCEPTS = [
    {
        term: 'Missing Values',
        definition:
            'Empty cells in your dataset where data was expected but not provided.',
        example: 'A survey column where some respondents skipped a question.',
        color: 'text-[#F59E0B]',
    },
    {
        term: 'Duplicates',
        definition:
            'Rows where all values are identical, indicating the same record appears multiple times.',
        example: 'The same customer order appears twice in a sales report.',
        color: 'text-[#F97316]',
    },
    {
        term: 'Outliers',
        definition:
            'Values that are unusually high or low compared to the rest of the data.',
        example:
            'A person\'s age listed as 200 in a dataset where most ages are 20-60.',
        color: 'text-[#3C6E71]',
    },
    {
        term: 'Data Types',
        definition:
            'The kind of data stored in a column (numeric, text, date, boolean).',
        example:
            'A "Price" column should be numeric, not text — otherwise you can\'t calculate totals.',
        color: 'text-[#284B63]',
    },
    {
        term: 'Standardization',
        definition:
            'Making all values in a column follow the same format.',
        example:
            'Converting "Male," "male," "MALE" all to "Male" so they group together.',
        color: 'text-[#2E7D32]',
    },
];

export default function WorkflowGuide({ currentStep }: Props) {
    const guide = stepGuides[currentStep] ?? stepGuides[0];

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center gap-2">
                    <BookOpen className="size-5 text-[#3C6E71]" />
                    <CardTitle className="text-base">
                        Learning Guide: {guide.step} Phase
                    </CardTitle>
                </div>
                <CardDescription>
                    What you&apos;re doing, why it matters, and what
                    you&apos;re learning.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div>
                    <p className="text-sm font-semibold text-[#353535]">
                        <Lightbulb className="mb-0.5 inline size-3.5 text-[#F59E0B]" />{' '}
                        Objective
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {guide.objective}
                    </p>
                </div>

                <div>
                    <p className="text-sm font-semibold text-[#353535]">
                        <GraduationCap className="mb-0.5 inline size-3.5 text-[#3C6E71]" />{' '}
                        What You Learn
                    </p>
                    <ul className="mt-1 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        {guide.whatYouLearn.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                </div>

                <div className="rounded-lg bg-[#FEF3C7] p-3 text-sm text-[#92400E]">
                    <strong>Academic Note:</strong> {guide.academicNote}
                </div>
            </CardContent>
        </Card>
    );
}

export function GlossaryCard() {
    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center gap-2">
                    <BookOpen className="size-5 text-[#284B63]" />
                    <CardTitle className="text-base">
                        Data Cleaning Glossary
                    </CardTitle>
                </div>
                <CardDescription>
                    Key terms explained in plain language.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {CONCEPTS.map((concept) => (
                    <div key={concept.term}>
                        <p className="text-sm font-semibold text-[#353535]">
                            {concept.term}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {concept.definition}
                        </p>
                        <p className={`mt-0.5 text-xs ${concept.color}`}>
                            Example: {concept.example}
                        </p>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
