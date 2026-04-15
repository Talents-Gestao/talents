from __future__ import annotations

from datetime import date
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[1]


def test_build_days_from_t6_colaboradores() -> None:
    from rhid_espelho_parser.extract import build_days_from_t6_colaboradores

    colabs = [
        {
            "nome": "Fulano",
            "cpf": "",
            "departamento": "X",
            "cargo": "Y",
            "marcacoes": [
                {
                    "dia": "14/04/2026",
                    "dia_semana": "TER",
                    "marcacoes": "08:00 12:00",
                    "justificativas": "",
                },
            ],
        },
        {
            "nome": "Ciclano",
            "cpf": "",
            "departamento": "",
            "cargo": "",
            "marcacoes": [
                {
                    "dia": "14/04/2026",
                    "dia_semana": "TER",
                    "marcacoes": "09:00 18:00",
                    "justificativas": "",
                },
            ],
        },
    ]
    days = build_days_from_t6_colaboradores(colabs)
    assert len(days) == 1
    d0 = days[0]
    assert d0["date"] == "2026-04-14"
    assert d0["schema"] == "t6"
    assert "text" in d0
    assert len(d0["colaboradores"]) == 2
    fulano = next(c for c in d0["colaboradores"] if c["nome"] == "Fulano")
    assert fulano["ent_1"] == "08:00"
    assert fulano["sai_1"] == "12:00"
    assert fulano["ent_2"] == ""
    ciclano = next(c for c in d0["colaboradores"] if c["nome"] == "Ciclano")
    assert ciclano["ent_1"] == "09:00"
    assert ciclano["sai_1"] == "18:00"


def test_marcacoes_string_to_ent_sai_slots() -> None:
    from rhid_espelho_parser.extract import marcacoes_string_to_ent_sai_slots

    s = marcacoes_string_to_ent_sai_slots("07:30 11:30 12:30 17:00 18:00 22:00")
    assert s["ent_1"] == "07:30"
    assert s["sai_1"] == "11:30"
    assert s["ent_2"] == "12:30"
    assert s["sai_2"] == "17:00"
    assert s["ent_3"] == "18:00"
    assert s["sai_3"] == "22:00"
    assert s["ent_4"] == ""
    assert s["sai_4"] == ""

    empty = marcacoes_string_to_ent_sai_slots("")
    assert all(empty[k] == "" for k in empty)


def test_parse_espelho_pdf_schema2_mocked(tmp_path: Path, monkeypatch: pytest.MonkeyPatch) -> None:
    pdf = tmp_path / "x.pdf"
    pdf.write_bytes(b"%PDF-1.4\n")

    from rhid_espelho_parser.cartao_pdf_parser import CartaoPontoLido
    import rhid_espelho_parser.extract as extract_mod

    def fake_extrair(_b: bytes) -> list:
        return [
            {
                "nome": "Fulano",
                "cpf": "",
                "departamento": "",
                "cargo": "",
                "marcacoes": [
                    {
                        "dia": "01/03/2026",
                        "dia_semana": "DOM",
                        "marcacoes": "07:00",
                        "justificativas": "",
                    },
                ],
            },
        ]

    def fake_ler(_b: bytes):
        c = CartaoPontoLido()
        c.paginas = []
        c.texto_total = ""
        c.total_tabelas = 0
        c.total_linhas_tabela = 0
        return c

    monkeypatch.setattr(extract_mod, "extrair_dados_cartao_ponto", fake_extrair)
    monkeypatch.setattr(extract_mod, "ler_cartao_ponto_pdf", fake_ler)

    from rhid_espelho_parser.extract import parse_espelho_pdf

    out = parse_espelho_pdf(
        pdf,
        id_person=99,
        period_ini=date(2026, 3, 1),
        period_fim=date(2026, 3, 31),
    )
    assert out["schema_version"] == 2
    assert out["id_person"] == 99
    assert len(out["colaboradores"]) == 1
    assert len(out["days"]) == 1
    assert out["days"][0]["date"] == "2026-03-01"
    assert out["summary"]["day_count"] == 1


def test_preencher_campos_marcacao_cartao_pdf_parser() -> None:
    from rhid_espelho_parser.cartao_pdf_parser import _preencher_campos_marcacao_pdf_linha

    row = {
        "TODAS_MARCACOES": "07:00 12:00",
        "DIA_DA_SEMANA": "SEG",
        "Data": "01/03/2026",
    }
    data, dia, ent, sai, todas = _preencher_campos_marcacao_pdf_linha(row)
    assert data == "01/03/2026"
    assert dia == "SEG"
    assert todas == "07:00 12:00"
    row2 = {"Marcacoes (espelho)": "08:15 17:30", "Data": "14/04/2026"}
    d2, _, _, _, t2 = _preencher_campos_marcacao_pdf_linha(row2)
    assert d2 == "14/04/2026"
    assert t2 == "08:15 17:30"
