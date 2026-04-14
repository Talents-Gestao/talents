from __future__ import annotations

import re
from datetime import date, datetime
from pathlib import Path
from typing import Any

import fitz

RE_DATE_BR = re.compile(r"\b(\d{2}/\d{2}/\d{4})\b")
RE_DATE_ISO = re.compile(r"\b(\d{4}-\d{2}-\d{2})\b")


def _parse_date_token(token: str) -> date | None:
    token = token.strip()
    if RE_DATE_ISO.match(token):
        try:
            return datetime.strptime(token, "%Y-%m-%d").date()
        except ValueError:
            return None
    m = RE_DATE_BR.match(token)
    if m:
        try:
            return datetime.strptime(m.group(1), "%d/%m/%Y").date()
        except ValueError:
            return None
    return None


def _first_date_in_line(line: str) -> date | None:
    for m in RE_DATE_ISO.finditer(line):
        d = _parse_date_token(m.group(1))
        if d:
            return d
    for m in RE_DATE_BR.finditer(line):
        d = _parse_date_token(m.group(1))
        if d:
            return d
    return None


def extract_pdf_text(path: Path) -> str:
    doc = fitz.open(path)
    parts: list[str] = []
    try:
        for page in doc:
            parts.append(page.get_text("text"))
    finally:
        doc.close()
    return "\n".join(parts)


def parse_espelho_pdf(
    path: Path,
    *,
    id_person: int | None = None,
    period_ini: date | None = None,
    period_fim: date | None = None,
) -> dict[str, Any]:
    """
    Extrai blocos por dia usando linhas que contenham datas (dd/mm/aaaa ou ISO).
    Layout exato do RHID pode variar; ajuste regex aqui com PDFs reais.
    """
    raw = extract_pdf_text(path)
    lines = [ln.strip() for ln in raw.splitlines()]
    lines = [ln for ln in lines if ln]

    days: list[dict[str, Any]] = []
    header_lines: list[str] = []
    current: date | None = None
    bucket: list[str] = []

    for ln in lines:
        d = _first_date_in_line(ln)
        if d:
            if current is not None and bucket:
                days.append(
                    {
                        "date": current.isoformat(),
                        "raw_lines": bucket[:],
                        "text": "\n".join(bucket),
                    }
                )
            current = d
            bucket = [ln]
        elif current is None:
            header_lines.append(ln)
        else:
            bucket.append(ln)

    if current is not None and bucket:
        days.append(
            {
                "date": current.isoformat(),
                "raw_lines": bucket[:],
                "text": "\n".join(bucket),
            }
        )

    person_name: str | None = None
    for h in header_lines[:40]:
        if "nome" in h.lower() and ":" in h:
            person_name = h.split(":", 1)[-1].strip() or None
            break

    return {
        "schema_version": 1,
        "id_person": id_person,
        "person_name": person_name,
        "period_ini": period_ini.isoformat() if period_ini else None,
        "period_fim": period_fim.isoformat() if period_fim else None,
        "header_text": "\n".join(header_lines[:200]),
        "days": days,
        "summary": {"line_count": len(lines), "day_count": len(days)},
    }
