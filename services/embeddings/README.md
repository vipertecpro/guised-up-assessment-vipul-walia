# Embedding Service

Create the virtual environment:

```bash
python3 -m venv .venv
```

Install dependencies:

```bash
.venv/bin/pip install -r requirements.txt
```

Start the service locally:

```bash
.venv/bin/uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Health endpoint: `http://127.0.0.1:8001/health`
