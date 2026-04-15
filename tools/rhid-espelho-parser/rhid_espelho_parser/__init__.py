"""Parser espelho/cartão RHID — paridade TALENTS6 (pdfplumber / cartao_pdf_parser)."""

from .cartao_pdf_parser import (
    extrair_dados_cartao_ponto,
    ler_cartao_ponto_pdf,
)
from .extract import build_days_from_t6_colaboradores, parse_espelho_pdf
from .marcacoes_espelho import (
    extrair_marcacoes_do_espelho,
    extrair_marcacoes_do_espelho_pdf,
)

__all__ = [
    "build_days_from_t6_colaboradores",
    "extrair_dados_cartao_ponto",
    "extrair_marcacoes_do_espelho",
    "extrair_marcacoes_do_espelho_pdf",
    "ler_cartao_ponto_pdf",
    "parse_espelho_pdf",
]
__version__ = "2.0.0"
