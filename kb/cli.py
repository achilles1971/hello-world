"""Command line: python -m kb.cli "How do I submit an expense?"  [--docs docs] [--search-only]"""
from __future__ import annotations

import argparse

from .answer import ask
from .ingest import load_folder
from .search import Index


def main() -> None:
    p = argparse.ArgumentParser(description="Ask the internal SOP knowledge base.")
    p.add_argument("question")
    p.add_argument("--docs", default="docs")
    p.add_argument("--search-only", action="store_true", help="show ranked sections, no AI answer")
    args = p.parse_args()

    index = Index(load_folder(args.docs))
    if args.search_only:
        for h in index.search(args.question):
            print(f"{h.score:6.2f}  {h.section.heading}  ({h.section.source_id})")
        return

    a = ask(args.question, index)
    if a.note:
        print(f"[{a.note}]\n")
    print(a.answer, "\n")
    for c in a.citations:
        flag = "  ⚠ needs re-verification" if c.stale else ""
        print(f"[{c.number}] {c.heading} — {c.doc_path}#{c.anchor}{flag}")
        print(f'    "{c.cited_text}"')
    if not a.found:
        print("(Logged as a documentation gap.)")


if __name__ == "__main__":
    main()
