"""Run: python -m pytest -q   (no API key needed; the Claude call is faked)"""
from __future__ import annotations

from pathlib import Path
from types import SimpleNamespace as NS

import pytest

from kb import answer as answer_mod
from kb.answer import ask
from kb.ingest import load_folder
from kb.search import Index

DOCS = Path(__file__).resolve().parent.parent / "docs"


@pytest.fixture(scope="module")
def index() -> Index:
    return Index(load_folder(DOCS))


@pytest.fixture(autouse=True)
def gap_log(tmp_path, monkeypatch):
    path = tmp_path / "gaps.jsonl"
    monkeypatch.setattr(answer_mod, "GAP_LOG", path)
    monkeypatch.delenv("KB_OFFLINE", raising=False)
    return path


def test_sections_and_front_matter(index):
    titles = {d.title for d in index.docs}
    assert "Commission and Expense Policy" in titles
    commission = next(d for d in index.docs if d.title == "Commission and Expense Policy")
    assert commission.owner == "Office Manager"
    assert all(s.is_stale for s in commission.sections)  # verified 2025-01-15
    steps = next(s for s in commission.sections if s.anchor == "expense-reimbursement").blocks
    assert steps[0].startswith("1. Submit the receipt")  # list items are separate citable blocks


@pytest.mark.parametrize("question, expected_anchor", [
    ("How do I get reimbursed for an expense?", "expense-reimbursement"),
    ("when does commission get paid", "commission-disbursement"),
    ("what should I do my first week", "first-week"),
    ("deadline to upload signed contracts", "submitting-contracts"),
    ("can I take vacation", "time-off-and-coverage"),
])
def test_search_finds_right_section(index, question, expected_anchor):
    # Claude reads the top 6; require the right section in the top 3.
    anchors = [h.section.anchor for h in index.search(question)[:3]]
    assert expected_anchor in anchors


def _fake_client(content):
    create = lambda **kw: NS(stop_reason="end_turn", content=content, _req=kw)  # noqa: E731
    return NS(beta=NS(messages=NS(create=create)))


def test_answer_numbers_citations_from_api(index):
    hits = index.search("How do I get reimbursed for an expense?")
    first = hits[0].section
    cite = NS(type="search_result_location", search_result_index=0, start_block_index=0,
              end_block_index=1, cited_text=first.blocks[0], source=first.source_id)
    content = [NS(type="text", text="Submit the receipt within 30 days", citations=[cite]),
               NS(type="text", text=".", citations=None)]
    a = ask("How do I get reimbursed for an expense?", index, client=_fake_client(content))
    assert a.found and a.answer == "Submit the receipt within 30 days[1]."
    assert a.citations[0].cited_text in first.text
    assert a.citations[0].stale is True


def test_uncited_answer_is_logged_as_gap(index, gap_log):
    content = [NS(type="text", text="The docs don't cover that.", citations=None)]
    a = ask("what is the dress code for open houses", index, client=_fake_client(content))
    assert not a.found
    assert "dress code" in gap_log.read_text()


def test_offline_mode_returns_verbatim_passages(index, monkeypatch):
    monkeypatch.setenv("KB_OFFLINE", "1")
    a = ask("earnest money deadline", index)
    assert a.mode == "offline" and a.citations
    assert "3 business days" in a.citations[0].cited_text
