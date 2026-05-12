# Design System

## 1. Design Philosophy

The interface should feel modern, elegant, clean, professional, and data-focused.

This system is a Data Cleaning and Analytics System. It should make the workflow of uploading, profiling, cleaning, analyzing, and visualizing datasets feel simple and guided.

Design keywords:
- Modern
- Minimal
- Professional
- Elegant
- Dashboard-focused
- Clean
- Readable
- Trustworthy
- Responsive

Avoid:
- Cluttered layouts
- Random colors
- Too many borders
- Heavy shadows
- Overuse of gradients
- Confusing controls
- Decorative fonts
- Inconsistent spacing

## 2. Product Workflow

The system follows this workflow:

Upload → Profile → Clean → Analyze → Visualize

Every screen must clearly show:
- Where the user is in the workflow
- What the user can do on the current screen
- What the next recommended action is

## 3. Visual Style

Use a modern enterprise dashboard style.

The UI should use:
- Light backgrounds
- White cards
- Soft shadows
- Rounded corners
- Clean typography
- Clear section hierarchy
- Comfortable spacing
- Minimal but meaningful colors

The design should look suitable for:
- A school project defense
- A real-world analytics dashboard
- A SaaS-style data management tool

## Color System

The system uses a calm, professional, and data-focused color palette.  
The colors should make the interface feel modern, elegant, readable, and trustworthy.

### Core Palette

| Token | Hex | Purpose |
|---|---:|---|
| Primary | `#284B63` | Main actions, active navigation, workflow progress |
| Secondary | `#3C6E71` | Secondary actions, highlights, chart accents |
| Neutral Dark | `#353535` | Main text, headings, dark UI elements |
| Surface | `#FFFFFF` | Cards, modals, tables, main content surfaces |
| Neutral Gray | `#D9D9D9` | Borders, dividers, disabled states |

### Extended Palette

| Token | Hex | Purpose |
|---|---:|---|
| Primary Hover | `#1F3A4D` | Hover state for primary buttons |
| Primary Soft | `#E7F0F5` | Light background for selected states |
| Secondary Hover | `#315A5D` | Hover state for secondary buttons |
| Secondary Soft | `#E6F0F1` | Light background for secondary highlights |
| Background | `#F7F9FA` | Main application background |
| Surface Muted | `#F1F3F4` | Subtle cards, table headers, empty states |
| Text Muted | `#6B7280` | Secondary text and descriptions |
| Text Light | `#9CA3AF` | Placeholder text and disabled labels |
| Divider | `#E5E7EB` | Light section dividers |

### Status Colors

| Token | Hex | Purpose |
|---|---:|---|
| Success | `#2E7D32` | Successful upload, completed cleaning, valid data |
| Success Soft | `#E8F5E9` | Success message backgrounds |
| Warning | `#F59E0B` | Missing values, columns needing review, warnings |
| Warning Soft | `#FEF3C7` | Warning message backgrounds |
| Error | `#C62828` | Invalid data, failed upload, destructive actions |
| Error Soft | `#FDECEC` | Error message backgrounds |
| Info | `#0284C7` | Informational notices and neutral analytics feedback |
| Info Soft | `#E0F2FE` | Info message backgrounds |

### Chart Colors

Use chart colors consistently and avoid random chart palettes.

| Token | Hex |
|---|---:|
| Chart Blue | `#284B63` |
| Chart Teal | `#3C6E71` |
| Chart Green | `#2E7D32` |
| Chart Amber | `#F59E0B` |
| Chart Purple | `#6D5BD0` |
| Chart Rose | `#C2416D` |

### Color Usage Rules

Use color to communicate meaning, not decoration.

Primary `#284B63` is used for:
- Primary buttons
- Active sidebar items
- Current workflow step
- Main dashboard highlights

Secondary `#3C6E71` is used for:
- Secondary buttons
- Supporting highlights
- Chart accents
- Informational UI elements

Neutral dark `#353535` is used for:
- Page titles
- Section headings
- Main body text

White `#FFFFFF` is used for:
- Cards
- Tables
- Modals
- Main content containers

Gray `#D9D9D9` is used for:
- Borders
- Dividers
- Input outlines
- Disabled states

Success green is used for:
- Completed cleaning actions
- Valid data
- Successful upload
- Improved data quality

Warning amber is used for:
- Missing values
- Incomplete fields
- Columns that need review
- Suggested cleaning actions

Error red is used for:
- Invalid data
- Upload failure
- Removed records
- Destructive actions

Do not use red, amber, or green randomly.  
These colors must always communicate system status or data quality feedback.

## 5. Typography

Use a clean sans-serif font such as Inter, Geist, Manrope, or system UI.

Typography rules:
- Page titles should be large and bold
- Section titles should be clear and medium-bold
- Body text should be readable
- Table text should be compact but clear
- Labels should be short and direct
- Avoid decorative fonts

## 6. Layout Rules

Use a dashboard-style layout.

Recommended structure:
1. Page header
2. Workflow step indicator
3. Summary cards
4. Main table or chart area
5. Action panel
6. Status or feedback message

Spacing rules:
- Use generous padding
- Group related items together
- Keep cards visually balanced
- Avoid overcrowding
- Use consistent gaps between sections

## 7. Component Rules

### Buttons

Primary buttons are for main actions:
- Upload Dataset
- Start Profiling
- Clean Data
- Generate Insights
- Export Report

Secondary buttons are for optional actions:
- Preview
- Reset
- Back
- Cancel

Danger buttons are for destructive actions:
- Remove Dataset
- Clear Rules

Button style:
- Rounded corners
- Medium padding
- Clear label
- Optional icon
- No excessive animation

### Cards

Use cards for:
- Dataset summaries
- Data quality reports
- Cleaning actions
- Insight summaries
- Chart containers

Card style:
- White background
- Subtle border
- Soft shadow
- Rounded corners
- Clear heading

### Tables

Use clean and readable tables.

Table rules:
- Clear column names
- Sticky header if possible
- Hover state for rows
- Badges for data types and statuses
- Highlight changed cells in comparison views
- Horizontal scrolling for wide datasets

### Badges

Use badges for:
- Numeric
- Text
- Date
- Boolean
- Missing
- Duplicate
- Invalid
- Clean
- Applied
- Pending

## 8. Screen Rules

### Upload Screen

Purpose:
Allow users to upload CSV or Excel files.

Design rules:
- Use a large drag-and-drop upload card
- Show accepted file types
- Display uploaded file name and size
- Show basic file information after upload
- Provide a clear next action button

### Profiling Screen

Purpose:
Show the current condition of the dataset.

Must display:
- Number of rows
- Number of columns
- Data types
- Missing values
- Duplicate records
- Basic statistics

Design rules:
- Use summary cards at the top
- Use a profiling table for columns
- Use badges for data types
- Use warning indicators for columns with issues

### Cleaning Screen

Purpose:
Allow users to apply cleaning methods.

Must support:
- Handle missing values
- Remove duplicates
- Convert data types
- Standardize formats
- Filter invalid data

Design rules:
- Group cleaning options into cards
- Explain each cleaning method briefly
- Show preview before applying changes
- Provide clear apply/reset buttons
- Show success or warning feedback

### Comparison Screen

Purpose:
Compare original and cleaned datasets.

Design rules:
- Show original vs cleaned data
- Highlight changed cells
- Show number of modified rows
- Show number of removed duplicates
- Show number of filled missing values

### Analytics Screen

Purpose:
Generate meaningful insights from the dataset.

Design rules:
- Use insight cards
- Use plain-language explanations
- Show summary statistics
- Show most frequent values
- Show trends or patterns when available

### Dashboard Screen

Purpose:
Visualize the cleaned dataset.

Must support:
- Bar chart
- Line chart
- Pie chart
- User-selectable variables

Design rules:
- Place chart controls above the chart
- Use clean chart containers
- Display chart title and explanation
- Show empty-state messages when chart cannot be generated

## 9. UX Writing Rules

Use simple and helpful text.

Good examples:
- Upload your dataset to begin profiling.
- This column has missing values.
- Duplicates were removed successfully.
- Select variables to generate a chart.
- No numeric columns found for this chart.

Avoid:
- Long paragraphs
- Vague labels
- Unexplained technical terms
- Confusing error messages

## 10. Empty States and Error States

Every screen must handle empty and error states.

Examples:
- No dataset uploaded yet.
- Upload a dataset first to view profiling results.
- No cleaning actions selected.
- Select variables to generate a visualization.
- Invalid file format. Please upload a CSV or Excel file.
- Unable to read this column as a date.
- Chart cannot be generated because the selected column is not numeric.

## 11. Accessibility Rules

The interface must be readable and accessible.

Rules:
- Use strong color contrast
- Do not rely on color alone
- Use labels for inputs
- Keep font sizes readable
- Make buttons easy to click
- Provide helpful error messages
- Make large tables scrollable

## 12. Responsive Design Rules

The system must work on desktop, laptop, tablet, and small screens.

Desktop:
- Use a wide dashboard layout
- Show cards in rows
- Use side-by-side layouts when helpful

Tablet:
- Use two-column card layouts
- Keep charts readable

Mobile:
- Stack content vertically
- Use full-width cards
- Allow horizontal scrolling for tables

## 13. AI Generation Rules

When generating UI for this system:

1. Follow the Upload → Profile → Clean → Analyze → Visualize workflow.
2. Use modern dashboard design.
3. Use clean cards, readable tables, and clear chart containers.
4. Prioritize usability over decoration.
5. Use consistent spacing, colors, and typography.
6. Explain data cleaning actions in simple language.
7. Show data quality issues clearly.
8. Use badges, alerts, and summary cards.
9. Always include empty states and error states.
10. Keep the design professional and suitable for academic defense.
11. Avoid overly complex animations.
12. Avoid random colors.
13. Avoid cluttered screens.
14. Make every screen responsive.

## 14. Defense-Oriented Design Goals

The design should help demonstrate that the system is:
- Well-structured
- Easy to use
- Responsive
- Logical in workflow
- Capable of profiling data
- Capable of cleaning data
- Capable of generating insights
- Capable of visualizing results clearly

Each feature should visually support the explanation of:
- What the feature does
- Why it is needed
- How it helps users make data-driven decisions