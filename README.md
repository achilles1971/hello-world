# SOP Knowledge Base

Team members ask plain-English questions about policies, checklists, and onboarding, and get a short answer that quotes the exact lines from your internal docs it relies on. Each quote links to its source and shows who owns that doc and when it was last verified.

## How it works

```
docs/ (md, txt, pdf, docx)
   │  kb/ingest.py   split each doc by heading → sections → paragraphs/list items
   ▼
BM25 index           kb/search.py   keyword ranking + small synonym table (pto→vacation…)
   │  top 6 sections
   ▼
Claude API           kb/answer.py   sections sent as `search_result` blocks with citations on
   │                                → API returns verbatim `cited_text` for every claim
   ▼
Answer + [1][2] quotes + owner / verified date / "may be out of date" badge
```

- **Quotes come from the API, not from the model.** Citations use the Claude API's search-result citations. The quoted text is copied out of the source by the API, so a quote can't be invented.
- **"Not in the docs" is a real result.** If an answer has no citation, it's shown as not covered. The question is logged to `data/unanswered.jsonl` and listed under the **Unanswered questions** tab, which gives you a list of SOPs still to write.
- **Freshness.** Put front matter at the top of each Markdown doc:
  ```
  ---
  title: Commission and Expense Policy
  owner: Office Manager
  verified: 2026-09-01
  ---
  ```
  Docs that haven't been verified in 180 days, or never, are flagged everywhere they're cited. The **Library** tab lists every doc with its status.
- **Offline fallback.** If there's no API key, or the API fails, it returns the top-matching passages word for word without a summary.

## Run it

```bash
pip install -r requirements.txt
export ANTHROPIC_API_KEY=sk-ant-...      # optional; without it you get offline mode
uvicorn app:app --reload                # http://localhost:8000
```

CLI: `python -m kb.cli "When is commission paid?"` (add `--search-only` to see ranking scores).
Tests: `python -m pytest -q` (no API key needed).

Settings (env vars): `KB_DOCS` (docs folder, default `docs/`), `KB_MODEL` (default `claude-opus-5-5`), `KB_EFFORT` (default `medium`), `KB_OFFLINE=1`.
After adding or editing docs, `POST /api/reindex` or restart.

The files in `docs/` are made-up sample SOPs for a brokerage. Replace them with your own.

## Limits (read before rolling out)

- **No login or permissions.** Anyone who can reach the server can read every doc. Run it on a private network or behind your SSO proxy, and don't put HR or comp docs in it that some staff shouldn't see.
- **Keyword search, not semantic.** It works well up to a few hundred docs. If people phrase questions very differently from the docs, extend `SYNONYMS` in `kb/search.py` or switch to embeddings.
- **Scanned PDFs** (images with no text layer) need OCR first.
- **No connectors.** Docs must be exported into the folder; there's no live Google Drive, Notion, or Slack sync.
