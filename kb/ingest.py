"""Load documents from a folder and split them into citable sections.

Each document becomes a list of Sections (one per heading). Each Section holds
its paragraphs / list items as separate blocks, because a block is the smallest
unit Claude can quote back in a citation.

Supported: .md, .txt, .pdf, .docx

Markdown files may start with front matter to track ownership and freshness:

    ---
    title: Listing Intake Checklist
    owner: Operations Manager
    verified: 2026-09-01
    ---
"""
from __future__ import annotations

import datetime as dt
import re
from dataclasses import dataclass, field
from pathlib import Path

SUPPORTED = {".md", ".markdown", ".txt", ".pdf", ".docx"}
STALE_AFTER_DAYS = 180


@dataclass
class Section:
    doc_path: str          # relative path of the source file
    doc_title: str
    heading: str           # "Doc title > H2 > H3" breadcrumb
    anchor: str            # slug for linking to the section
    blocks: list[str]      # paragraphs / list items, each one citable
    owner: str | None = None
    verified: dt.date | None = None
    page: int | None = None  # PDFs only

    @property
    def text(self) -> str:
        return "\n".join(self.blocks)

    @property
    def source_id(self) -> str:
        return f"{self.doc_path}#{self.anchor}"

    @property
    def is_stale(self) -> bool:
        if self.verified is None:
            return True
        return (dt.date.today() - self.verified).days > STALE_AFTER_DAYS


@dataclass
class Document:
    path: str
    title: str
    owner: str | None
    verified: dt.date | None
    sections: list[Section] = field(default_factory=list)


def slugify(text: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-") or "section"


def _parse_front_matter(raw: str) -> tuple[dict[str, str], str]:
    if not raw.startswith("---"):
        return {}, raw
    end = raw.find("\n---", 3)
    if end == -1:
        return {}, raw
    meta: dict[str, str] = {}
    for line in raw[3:end].strip().splitlines():
        if ":" in line:
            k, v = line.split(":", 1)
            meta[k.strip().lower()] = v.strip()
    return meta, raw[end + 4:].lstrip("\n")


def _parse_date(value: str | None) -> dt.date | None:
    if not value:
        return None
    try:
        return dt.date.fromisoformat(value.strip())
    except ValueError:
        return None


_LIST_ITEM = re.compile(r"^\s*(?:[-*+]|\d+[.)]|\[[ xX]\])\s+")
_HEADING = re.compile(r"^(#{1,6})\s+(.*?)\s*#*\s*$")


def _blocks_from_lines(lines: list[str]) -> list[str]:
    """Group lines into paragraphs; every list item is its own block."""
    blocks: list[str] = []
    buf: list[str] = []

    def flush() -> None:
        if buf:
            text = " ".join(s.strip() for s in buf).strip()
            if text:
                blocks.append(text)
            buf.clear()

    for line in lines:
        if not line.strip():
            flush()
        elif _LIST_ITEM.match(line):
            flush()
            buf.append(line.strip())
        elif buf and _LIST_ITEM.match(buf[0]) and line.startswith((" ", "\t")):
            buf.append(line)  # continuation of a list item
        elif buf and _LIST_ITEM.match(buf[0]):
            flush()
            buf.append(line)
        else:
            buf.append(line)
    flush()
    return blocks


def _sections_from_markdown(body: str, doc: Document) -> list[Section]:
    sections: list[Section] = []
    trail: list[tuple[int, str]] = []
    current_lines: list[str] = []
    in_code = False
    seen_anchors: dict[str, int] = {}

    def emit() -> None:
        blocks = _blocks_from_lines(current_lines)
        current_lines.clear()
        if not blocks:
            return
        names = [h for _, h in trail] or [doc.title]
        crumb = " > ".join([doc.title] + [n for n in names if n != doc.title])
        anchor = slugify(names[-1])
        n = seen_anchors.get(anchor, 0)
        seen_anchors[anchor] = n + 1
        if n:
            anchor = f"{anchor}-{n}"
        sections.append(Section(doc.path, doc.title, crumb, anchor, blocks,
                                doc.owner, doc.verified))

    for line in body.splitlines():
        if line.strip().startswith("```"):
            in_code = not in_code
            current_lines.append(line)
            continue
        m = None if in_code else _HEADING.match(line)
        if m:
            emit()
            level, name = len(m.group(1)), m.group(2).strip()
            if level == 1 and not trail and name:
                doc.title = doc.title if doc.title != Path(doc.path).stem else name
            trail = [(lv, h) for lv, h in trail if lv < level] + [(level, name)]
        else:
            current_lines.append(line)
    emit()
    return sections


def _load_pdf(path: Path, doc: Document) -> list[Section]:
    from pypdf import PdfReader

    sections = []
    for i, page in enumerate(PdfReader(str(path)).pages, start=1):
        blocks = _blocks_from_lines((page.extract_text() or "").splitlines())
        if blocks:
            sections.append(Section(doc.path, doc.title, f"{doc.title} > Page {i}",
                                    f"page-{i}", blocks, doc.owner, doc.verified, page=i))
    return sections


def _load_docx(path: Path) -> str:
    """Convert a .docx to markdown-ish text so headings become sections."""
    import docx

    out = []
    for p in docx.Document(str(path)).paragraphs:
        style = (p.style.name or "").lower() if p.style is not None else ""
        text = p.text.strip()
        if not text:
            out.append("")
        elif style.startswith("heading"):
            level = int(re.sub(r"\D", "", style) or 1)
            out += ["", "#" * min(level, 6) + " " + text, ""]
        elif "list" in style:
            out.append("- " + text)
        else:
            out += [text, ""]
    return "\n".join(out)


def load_document(path: Path, root: Path) -> Document:
    rel = path.relative_to(root).as_posix()
    suffix = path.suffix.lower()
    meta: dict[str, str] = {}
    body = ""
    if suffix in {".md", ".markdown", ".txt"}:
        meta, body = _parse_front_matter(path.read_text(encoding="utf-8", errors="replace"))
    elif suffix == ".docx":
        body = _load_docx(path)

    doc = Document(
        path=rel,
        title=meta.get("title") or path.stem.replace("_", " ").replace("-", " ").title(),
        owner=meta.get("owner"),
        verified=_parse_date(meta.get("verified")),  # None = never verified
    )
    doc.sections = _load_pdf(path, doc) if suffix == ".pdf" else _sections_from_markdown(body, doc)
    return doc


def load_folder(root: str | Path) -> list[Document]:
    root = Path(root)
    docs = []
    for path in sorted(root.rglob("*")):
        if path.is_file() and path.suffix.lower() in SUPPORTED and not path.name.startswith("."):
            docs.append(load_document(path, root))
    return docs
