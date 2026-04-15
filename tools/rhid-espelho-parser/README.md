# Parser de espelho RHID (PDF)

Paridade com o projeto **TALENTS6** (`cartao_pdf_parser.py`): **pdfplumber**, `extrair_dados_cartao_ponto` (texto + tabelas, normalização de horários), montagem de `days` agregados por data para o Laravel.

## Uso

```bash
pip install -r requirements.txt
python3 -m rhid_espelho_parser /caminho/espelho.pdf --id-person 123 --period-ini 2026-04-01 --period-fim 2026-04-30
```

No servidor (Docker/Linux), o Laravel usa por padrão `/usr/bin/python3`. Ajuste `RHID_ESPELHO_PYTHON` no `.env` se necessário.

Saída: JSON em stdout com **`schema_version`: 2**, campos `colaboradores`, `days` (uma entrada por data com `text` e `colaboradores[]`), `summary` / `meta`.

## Testes

```bash
python -m pytest tests
```
