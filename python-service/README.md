# dirty2data Python Service

FastAPI scaffold for the dirty2data data processing engine. This subtask defines the service boundary and the Data Quality Score JSON contract only.

## Scope

- `GET /health` returns a stable health payload for local checks and Laravel service monitoring.
- `POST /datasets/uploads` defines the future file-upload contract for CSV/XLS/XLSX datasets.
- `POST /quality-scores` defines the request/response schema Laravel can use once profiling and scoring are implemented.
- Pandas parsing, dataset profiling, and score formulas are intentionally not implemented in this scaffold.

## Safety contract

Public endpoints must not accept arbitrary user-controlled filesystem paths. File inputs should use the multipart upload endpoint. If an internal path-based workflow is added later, it must only accept server-generated identifiers or paths resolved inside a server-controlled storage root.

`UploadFile.filename` is untrusted display metadata only. Do not use it to build server file paths.

## Local setup

```bash
cd python-service
python -m venv .venv
.venv\Scripts\activate
python -m pip install --upgrade pip
python -m pip install -e .
fastapi dev app/main.py
```

On macOS/Linux, activate the environment with `source .venv/bin/activate`.

## Health check

```bash
curl http://127.0.0.1:8000/health
```

Expected response:

```json
{
  "status": "ok",
  "service": "dirty2data-python-service",
  "version": "0.1.0"
}
```

## Quality score response shape

The contract includes:

- optional `dataset_id`
- `score_type` (`before` or `after`)
- `final_score` from 0 to 100
- status label: `Excellent`, `Good`, `Fair`, `Poor`, or `Critical`
- weighted sub-scores for completeness, uniqueness, validity, consistency, and type accuracy
- `issues_summary`
- `recommendation_summary`
- profile metrics needed for scoring and dashboard display
