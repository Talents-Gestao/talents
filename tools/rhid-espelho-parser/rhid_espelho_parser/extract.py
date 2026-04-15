"""
Parser do espelho/cartão RHID — paridade com TALENTS6 (cartao_pdf_parser).
Usa pdfplumber e extrair_dados_cartao_ponto; monta days agregados por data para o Laravel.
"""
from __future__ import annotations

import re
from datetime import date, datetime
from pathlib import Path
from typing import Any

from .cartao_pdf_parser import (
    extrair_dados_cartao_ponto,
    ler_cartao_ponto_pdf,
)

_RE_HHMM = re.compile(r"\b(\d{2}:\d{2})\b")


def marcacoes_string_to_ent_sai_slots(marcacoes_str: str) -> dict[str, str]:
    """
    Mapeia até 8 horários HH:MM na ordem para ENT.1/SAÍ.1 … ENT.4/SAÍ.4.
    Índice par = entrada, ímpar = saída (par ordenado no espelho normalizado).
    """
    out: dict[str, str] = {}
    for i in range(1, 5):
        out[f"ent_{i}"] = ""
        out[f"sai_{i}"] = ""
    if not marcacoes_str or not str(marcacoes_str).strip():
        return out
    times = _RE_HHMM.findall(str(marcacoes_str).strip())
    for idx, t in enumerate(times[:8]):
        pair = idx // 2 + 1
        if idx % 2 == 0:
            out[f"ent_{pair}"] = t
        else:
            out[f"sai_{pair}"] = t
    return out


def _dia_br_para_iso(dia: str) -> str | None:
    """Converte dia dd/mm/aaaa (ou retorno do parser T6) para YYYY-MM-DD."""
    if not dia or not str(dia).strip():
        return None
    s = str(dia).strip()
    m = re.match(
        r"^(\d{1,2})/(\d{1,2})/(\d{2,4})$",
        s,
    )
    if not m:
        return None
    d, M, y = m.groups()
    d, M = int(d), int(M)
    yi = int(y)
    if len(y) == 2:
        yi = 2000 + yi if yi < 50 else 1900 + yi
    try:
        return date(yi, M, d).isoformat()
    except ValueError:
        return None


def build_days_from_t6_colaboradores(colaboradores: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """
    Uma entrada por ref_date (unique no Laravel), com colaboradores[] e text para a UI.
    """
    by_date: dict[str, list[dict[str, Any]]] = {}
    for colab in colaboradores:
        nome = (colab.get("nome") or "").strip()
        for m in colab.get("marcacoes") or []:
            if not isinstance(m, dict):
                continue
            dia_raw = m.get("dia") or ""
            iso = _dia_br_para_iso(str(dia_raw))
            if not iso:
                continue
            marc_raw = m.get("marcacoes") or ""
            frag: dict[str, Any] = {
                "nome": nome,
                "cpf": colab.get("cpf") or "",
                "departamento": colab.get("departamento") or "",
                "cargo": colab.get("cargo") or "",
                "dia_semana": m.get("dia_semana") or "",
                "marcacoes": marc_raw,
                "justificativas": m.get("justificativas") or "",
            }
            frag.update(marcacoes_string_to_ent_sai_slots(str(marc_raw)))
            by_date.setdefault(iso, []).append(frag)

    days_out: list[dict[str, Any]] = []
    for iso in sorted(by_date.keys()):
        frags = by_date[iso]
        lines: list[str] = []
        for f in frags:
            bits = [f.get("nome") or "", f.get("dia_semana") or "", f.get("marcacoes") or ""]
            j = (f.get("justificativas") or "").strip()
            if j:
                bits.append(j)
            line = " ".join(b for b in bits if b).strip()
            if line:
                lines.append(line)
        text = " | ".join(lines) if len(lines) > 1 else (lines[0] if lines else "")
        days_out.append(
            {
                "date": iso,
                "ref_date": iso,
                "schema": "t6",
                "text": text,
                "colaboradores": frags,
            }
        )
    return days_out


def parse_espelho_pdf(
    path: Path,
    *,
    id_person: int | None = None,
    period_ini: date | None = None,
    period_fim: date | None = None,
) -> dict[str, Any]:
    """
    Extrai dados no mesmo pipeline do TALENTS6 (extrair_dados_cartao_ponto).
    schema_version 2: colaboradores + days derivados para EspelhoPdfIngestService.
    """
    pdf_bytes = path.read_bytes()
    colaboradores = extrair_dados_cartao_ponto(pdf_bytes)
    cartao = ler_cartao_ponto_pdf(pdf_bytes)
    days = build_days_from_t6_colaboradores(colaboradores)

    person_name: str | None = None
    if colaboradores:
        person_name = (colaboradores[0].get("nome") or "").strip() or None

    return {
        "schema_version": 2,
        "id_person": id_person,
        "person_name": person_name,
        "period_ini": period_ini.isoformat() if period_ini else None,
        "period_fim": period_fim.isoformat() if period_fim else None,
        "colaboradores": colaboradores,
        "days": days,
        "summary": {
            "line_count": sum(len(p.texto_por_linha) for p in cartao.paginas),
            "day_count": len(days),
            "colaborador_count": len(colaboradores),
            "total_paginas": len(cartao.paginas),
            "total_tabelas": cartao.total_tabelas,
            "total_linhas_tabela": cartao.total_linhas_tabela,
        },
        "meta": {
            "parser": "cartao_pdf_parser.TALENTS6",
            "texto_total_len": len(cartao.texto_total or ""),
        },
    }
