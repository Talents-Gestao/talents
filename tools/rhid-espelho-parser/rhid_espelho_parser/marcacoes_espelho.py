"""
Reexports do cartao_pdf_parser (paridade TALENTS6).
Mantido para compatibilidade com imports antigos.
"""
from __future__ import annotations

from typing import Any

from .cartao_pdf_parser import (
    extrair_marcacoes_do_cartao,
    ler_cartao_ponto_pdf,
)


def extrair_marcacoes_do_espelho_pdf(pdf_bytes: bytes) -> list[dict[str, Any]]:
    cartao = ler_cartao_ponto_pdf(pdf_bytes)
    return extrair_marcacoes_do_cartao(cartao)


def extrair_marcacoes_do_espelho(pdf_bytes: bytes) -> list[dict[str, Any]]:
    return extrair_marcacoes_do_espelho_pdf(pdf_bytes)
