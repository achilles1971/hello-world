"""BM25 keyword search over document sections.

No embeddings, no vector DB: for a few hundred SOP pages, BM25 with light
stemming and a small synonym table is fast, free, and easy to debug. Claude
then reads the top sections and decides what actually answers the question.
"""
from __future__ import annotations

import math
import re
from collections import Counter
from dataclasses import dataclass

from .ingest import Document, Section

STOPWORDS = set("""
a an and are as at be but by can do does for from how i if in into is it its me
my of on or our should so than that the their them then there these they this to
us was we what when where which who why will with you your about after before
need needs get got have has had any all also just
""".split())

# Plain-English questions rarely use the exact wording in the SOP.
SYNONYMS = {
    "pto": ["vacation", "time", "off", "leave"],
    "vacation": ["pto", "leave"],
    "sick": ["leave", "pto"],
    "onboard": ["onboarding", "orientation", "new", "hire"],
    "onboarding": ["orientation", "new", "hire", "first", "week"],
    "pay": ["commission", "compensation", "payroll", "split"],
    "paid": ["commission", "payroll", "disbursement"],
    "commission": ["split", "compensation", "disbursement"],
    "expense": ["reimbursement", "receipt"],
    "reimburse": ["reimbursement", "expense"],
    "laptop": ["computer", "equipment", "device"],
    "password": ["login", "credentials", "account", "access"],
    "listing": ["listings", "seller", "mls"],
    "offer": ["offers", "purchase", "agreement", "contract"],
    "deadline": ["due", "within", "days"],
    "boss": ["manager", "broker"],
    "quit": ["resignation", "termination", "offboarding"],
}


def _stem(word: str) -> str:
    for suf in ("ing", "edly", "ed", "ies", "es", "s"):
        if word.endswith(suf) and len(word) - len(suf) >= 3:
            return word[: -len(suf)] + ("y" if suf == "ies" else "")
    return word


def tokenize(text: str) -> list[str]:
    words = re.findall(r"[a-z0-9]+", text.lower())
    return [_stem(w) for w in words if w not in STOPWORDS]


def expand_query(text: str) -> list[str]:
    raw = [w for w in re.findall(r"[a-z0-9]+", text.lower()) if w not in STOPWORDS]
    terms = [_stem(w) for w in raw]
    for w in raw:
        terms += [_stem(s) for s in SYNONYMS.get(w, [])]
    return terms


@dataclass
class Hit:
    section: Section
    score: float


class Index:
    def __init__(self, docs: list[Document], k1: float = 1.4, b: float = 0.75):
        self.docs = docs
        self.sections = [s for d in docs for s in d.sections]
        self.k1, self.b = k1, b
        # Heading words count 3x: "Expense Reimbursement" in a heading is a strong signal.
        self.tfs = [Counter(tokenize(s.text) + tokenize(s.heading) * 3) for s in self.sections]
        self.lens = [sum(tf.values()) for tf in self.tfs]
        self.avg_len = (sum(self.lens) / len(self.lens)) if self.lens else 1.0
        df: Counter[str] = Counter()
        for tf in self.tfs:
            df.update(tf.keys())
        n = len(self.sections)
        self.idf = {t: math.log(1 + (n - c + 0.5) / (c + 0.5)) for t, c in df.items()}

    def search(self, query: str, k: int = 6) -> list[Hit]:
        terms = expand_query(query)
        if not terms or not self.sections:
            return []
        original = set(tokenize(query))
        scores = []
        for i, tf in enumerate(self.tfs):
            score = 0.0
            for t in set(terms):
                f = tf.get(t)
                if not f:
                    continue
                weight = 1.0 if t in original else 0.4  # synonyms count less
                norm = f * (self.k1 + 1) / (f + self.k1 * (1 - self.b + self.b * self.lens[i] / self.avg_len))
                score += weight * self.idf.get(t, 0.0) * norm
            if score > 0:
                scores.append(Hit(self.sections[i], score))
        scores.sort(key=lambda h: h.score, reverse=True)
        return scores[:k]
