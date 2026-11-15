"""Web server: search box UI + JSON API.

Run:  uvicorn app:app --reload        (then open http://localhost:8000)
Docs folder defaults to ./docs; override with KB_DOCS=/path/to/sops
"""
from __future__ import annotations

import json
import os
import threading
from pathlib import Path

from fastapi import FastAPI, HTTPException
from fastapi.responses import FileResponse, PlainTextResponse
from pydantic import BaseModel

from kb.answer import GAP_LOG, ask
from kb.ingest import load_folder
from kb.search import Index

DOCS_DIR = Path(os.environ.get("KB_DOCS", "docs")).resolve()
app = FastAPI(title="SOP Knowledge Base")
_lock = threading.Lock()
_index: Index | None = None


def get_index() -> Index:
    global _index
    with _lock:
        if _index is None:
            _index = Index(load_folder(DOCS_DIR))
        return _index


class Question(BaseModel):
    question: str


@app.get("/")
def home() -> FileResponse:
    return FileResponse(Path(__file__).parent / "static" / "index.html")


@app.post("/api/ask")
def api_ask(q: Question) -> dict:
    if not q.question.strip():
        raise HTTPException(400, "Empty question")
    return ask(q.question, get_index()).to_dict()


@app.post("/api/reindex")
def api_reindex() -> dict:
    global _index
    with _lock:
        _index = Index(load_folder(DOCS_DIR))
        return {"documents": len(_index.docs), "sections": len(_index.sections)}


@app.get("/api/library")
def api_library() -> list[dict]:
    """Every document with owner and verification status (stale-content view)."""
    out = []
    for d in get_index().docs:
        stale = any(s.is_stale for s in d.sections) if d.sections else True
        out.append({"path": d.path, "title": d.title, "owner": d.owner,
                    "verified": d.verified.isoformat() if d.verified else None,
                    "stale": stale, "sections": len(d.sections)})
    return out


@app.get("/api/gaps")
def api_gaps() -> list[dict]:
    """Questions the docs could not answer: your SOP-writing backlog."""
    if not GAP_LOG.exists():
        return []
    return [json.loads(line) for line in GAP_LOG.read_text().splitlines() if line.strip()][-200:]


@app.get("/doc/{path:path}")
def view_doc(path: str):
    target = (DOCS_DIR / path).resolve()
    if DOCS_DIR not in target.parents or not target.is_file():
        raise HTTPException(404)
    if target.suffix.lower() in {".md", ".markdown", ".txt"}:
        return PlainTextResponse(target.read_text(encoding="utf-8", errors="replace"))
    return FileResponse(target)
