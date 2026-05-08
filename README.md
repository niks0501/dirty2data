
# dirty2data

### A Data Cleaning and Analytics System

**dirty2data** is a web-based data cleaning and analytics system that lets users upload CSV or Excel files, profile datasets, clean missing, duplicate, or invalid data, generate insights, and visualize results through dashboards.

The system follows the workflow:


Upload → Profile → Clean → Analyze → Visualize


---

## Project Description

dirty2data helps users transform raw and messy tabular datasets into cleaner, more reliable, and more meaningful information. It provides tools for data profiling, cleaning, analysis, and dashboard visualization to support data-driven decision-making.

---

## Features

### Dataset Upload

* Upload CSV or Excel files
* Validate file format
* Preview uploaded dataset in table format

### Data Profiling

* Show total rows and columns
* Detect column data types
* Identify missing values
* Detect duplicate records
* Generate basic statistics

### Data Cleaning

* Handle missing values
* Remove duplicate rows
* Convert data types
* Standardize formats
* Filter invalid data

### Data Comparison

* View original dataset
* View cleaned dataset
* Compare before-and-after changes

### Insights Generation

* Generate summary statistics
* Identify frequent values
* Detect simple trends and patterns
* Provide basic data interpretations

### Dashboard Visualization

* Display interactive dashboards
* Support bar, line, and pie charts
* Allow user-selectable variables

---

## Tech Stack

### Frontend

* React
* Tailwind CSS
* Axios
* Chart.js or Recharts

### Backend

* Laravel
* MySQL
* Laravel Sanctum

### Data Processing

* Python
* FastAPI
* Pandas
* NumPy
* OpenPyXL

---

## System Architecture

```text
React Frontend
      ↓
Laravel API Backend
      ↓
Python Data Processing Engine
      ↓
MySQL Database
```

---

## Technology Roles

| Technology | Purpose                                                      |
| ---------- | ------------------------------------------------------------ |
| React      | User interface, dataset preview, dashboard visualization     |
| Laravel    | API, authentication, file handling, database management      |
| Python     | Dataset profiling, cleaning, analytics, and insights         |
| MySQL      | Stores users, datasets, cleaning logs, and generated results |

---

## Core Workflow

### 1. Upload

Users upload a CSV or Excel file into the system.

### 2. Profile

The system scans the dataset and displays rows, columns, data types, missing values, duplicate records, and basic statistics.

### 3. Clean

Users apply cleaning actions such as removing duplicates, filling missing values, converting data types, and filtering invalid data.

### 4. Analyze

The system generates summaries, patterns, and simple interpretations from the cleaned dataset.

### 5. Visualize

Users view results through interactive charts and dashboards.

---

## Suggested Folder Structure

```text
dirty2data/
├── frontend/
│   └── React application
├── backend/
│   └── Laravel API
├── python-engine/
│   └── FastAPI and Pandas data processing service
└── README.md
```

---

## API Endpoints

| Method | Endpoint                            | Description                       |
| ------ | ----------------------------------- | --------------------------------- |
| POST   | `/api/datasets/upload`              | Upload dataset                    |
| GET    | `/api/datasets/{id}/preview`        | Preview dataset                   |
| GET    | `/api/datasets/{id}/profile`        | View dataset profile              |
| POST   | `/api/datasets/{id}/clean`          | Clean dataset                     |
| GET    | `/api/datasets/{id}/compare`        | Compare original and cleaned data |
| GET    | `/api/datasets/{id}/insights`       | Generate insights                 |
| GET    | `/api/datasets/{id}/visualizations` | Get dashboard data                |

---

## Sample Cleaning Methods

| Cleaning Method     | Description                                                     |
| ------------------- | --------------------------------------------------------------- |
| Remove Duplicates   | Removes repeated rows from the dataset                          |
| Fill Missing Values | Replaces empty values using mean, median, mode, or custom value |
| Drop Missing Rows   | Removes rows with incomplete required fields                    |
| Convert Data Types  | Converts values into proper number, text, or date formats       |
| Standardize Formats | Fixes inconsistent text, spacing, casing, and date formats      |
| Filter Invalid Data | Removes values that do not meet expected rules or ranges        |

---

## Project Objectives

* Develop a system for data preprocessing and analysis
* Apply practical data cleaning techniques
* Generate meaningful insights from datasets
* Create interactive dashboards
* Support data-driven decision-making

---

## Defense Explanation

dirty2data uses React for the interactive user interface, Laravel for API and database management, and Python for data profiling, cleaning, and analytics. This separation allows the system to follow a clear and organized workflow while using the strengths of each technology.

The system is designed to help users convert messy datasets into clean, analyzed, and visualized information through the workflow:

```text
Upload → Profile → Clean → Analyze → Visualize
```

---

## Project Status

```text
Status: In Development
Version: 1.0.0
```

---

## Contributors

```text
Group Members:
- Member 1
- Member 2
- Member 3
- Member 4
```

---

## License

This project is developed for academic purposes.

```
```
