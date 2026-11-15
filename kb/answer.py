"""Turn a question + retrieved sections into an answer with verbatim citations.

The retrieved sections are sent to Claude as `search_result` content blocks with
citations enabled. The API then returns citations whose `cited_text` is copied
from the source blocks by the API itself, not retyped by the model, so every
quote shown to the user really exists in the internal doc it points to.

An answer with zero citations is treated as "not in the docs" and logged as a
content gap so someone can write the missing SOP.
"""
from __future__ import annotations

import datetime as dt
import json
import os
from dataclasses import asdict, dataclass, field
from pathlib import Path

from .search import Hit, Index, tokenize

MODEL = os.environ.get("KB_MODEL", "claude-opus-5-5")
EFFORT = os.environ.get("KB_EFFORT", "medium")
GAP_LOG = Path(os.environ.get("KB_GAP_LOG", "data/unanswered.jsonl"))

SYSTEM_PROMPT = """You answer employees' questions about company policies, checklists, \
and onboarding using ONLY the internal documents provided as search results.

Rules:
- Every factual statement must be supported by a citation to the search results. \
Prefer citing the exact step, number, deadline, or rule rather than paraphrasing it away.
- If the documents do not answer the question, say plainly that the internal docs \
don't cover it and suggest who to ask if a document names an owner or contact. \
Do not fill gaps with general knowledge or what companies "usually" do.
- If two documents conflict, point out the conflict and cite both.
- For procedures, answer as a short numbered list in the documented order.
- Be brief. No preamble, no closing offer to help further."""


@dataclass
class Citation:
    number: int
    cited_text: str
    doc_path: str
    doc_title: str
    heading: str
    anchor: str
    owner: str | None
    verified: str | None
    stale: bool


@dataclass
class Answer:
    question: str
    answer: str
    citations: list[Citation] = field(default_factory=list)
    found: bool = True
    mode: str = "claude"     # "claude" | "offline"
    note: str | None = None

    def to_dict(self) -> dict:
        return asdict(self)


def _search_result_block(hit: Hit) -> dict:
    s = hit.section
    return {
        "type": "search_result",
        "source": s.source_id,
        "title": s.heading,
        "content": [{"type": "text", "text": b} for b in s.blocks],
        "citations": {"enabled": True},
    }


def _log_gap(question: str, reason: str) -> None:
    try:
        GAP_LOG.parent.mkdir(parents=True, exist_ok=True)
        with GAP_LOG.open("a", encoding="utf-8") as f:
            f.write(json.dumps({"at": dt.datetime.now().isoformat(timespec="seconds"),
                                "question": question, "reason": reason}) + "\n")
    except OSError:
        pass


def _cite(hit: Hit, number: int, text: str) -> Citation:
    s = hit.section
    return Citation(number, text, s.doc_path, s.doc_title, s.heading, s.anchor,
                    s.owner, s.verified.isoformat() if s.verified else None, s.is_stale)


def offline_answer(question: str, hits: list[Hit], note: str | None = None) -> Answer:
    """No model available: return the best-matching passages verbatim."""
    if not hits:
        _log_gap(question, "no matching documents")
        return Answer(question, "No internal document matches this question.",
                      found=False, mode="offline", note=note)
    q = set(tokenize(question))
    cites, lines = [], []
    for n, hit in enumerate(hits[:3], start=1):
        best = max(hit.section.blocks, key=lambda b: len(q & set(tokenize(b))))
        cites.append(_cite(hit, n, best))
        lines.append(f"- {hit.section.heading} [{n}]")
    text = "Most relevant passages (no AI summary, offline mode):\n" + "\n".join(lines)
    return Answer(question, text, cites, found=True, mode="offline", note=note)


def ask(question: str, index: Index, k: int = 6, client=None) -> Answer:
    question = question.strip()
    hits = index.search(question, k=k)
    if not hits:
        _log_gap(question, "no matching documents")
        return Answer(question, "I couldn't find anything in the internal docs about that. "
                      "It may not be documented yet; it has been logged as a gap.", found=False)

    if os.environ.get("KB_OFFLINE") == "1":
        return offline_answer(question, hits, note="KB_OFFLINE=1")

    import anthropic

    client = client or anthropic.Anthropic()
    try:
        response = client.beta.messages.create(
            model=MODEL,
            max_tokens=4000,
            system=SYSTEM_PROMPT,
            output_config={"effort": EFFORT},
            betas=["server-side-fallback-2026-07-01"],
            fallbacks="default",
            messages=[{
                "role": "user",
                "content": [_search_result_block(h) for h in hits]
                + [{"type": "text", "text": f"Question: {question}"}],
            }],
        )
    except TypeError as e:  # the SDK raises this when no credentials are configured at all
        if "authentication method" not in str(e):
            raise
        return offline_answer(question, hits, note="No Anthropic API key configured; showing raw passages.")
    except anthropic.AuthenticationError:
        return offline_answer(question, hits, note="Invalid Anthropic API key; showing raw passages.")
    except anthropic.RateLimitError:
        return offline_answer(question, hits, note="Claude API rate limit hit; showing raw passages.")
    except anthropic.APIStatusError as e:
        return offline_answer(question, hits, note=f"Claude API error {e.status_code}; showing raw passages.")
    except anthropic.APIConnectionError:
        return offline_answer(question, hits, note="Could not reach the Claude API; showing raw passages.")

    if response.stop_reason == "refusal":
        return offline_answer(question, hits, note="The model declined this question; showing raw passages.")

    # Assemble the answer, adding [n] markers after each cited span.
    numbers: dict[tuple[int, int, int], int] = {}
    citations: list[Citation] = []
    parts: list[str] = []
    for block in response.content:
        if block.type != "text":
            continue
        parts.append(block.text)
        marks = []
        for c in block.citations or []:
            if c.type != "search_result_location":
                continue
            key = (c.search_result_index, c.start_block_index, c.end_block_index)
            if key not in numbers:
                numbers[key] = len(numbers) + 1
                hit = hits[c.search_result_index]
                citations.append(_cite(hit, numbers[key], c.cited_text))
            if numbers[key] not in marks:
                marks.append(numbers[key])
        parts.extend(f"[{n}]" for n in marks)

    text = "".join(parts).strip()
    if not citations:
        _log_gap(question, "answer had no supporting citation")
        return Answer(question, text or "The internal docs don't cover this.", found=False)
    return Answer(question, text, citations)
