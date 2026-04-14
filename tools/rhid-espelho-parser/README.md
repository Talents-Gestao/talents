# Parser de espelho RHID (PDF)

Extrai texto do PDF e agrupa linhas por data (`dd/mm/aaaa` ou ISO). Ajuste `extract.py` conforme o layout real do seu tenant.

## Uso

```bash
pip install -r requirements.txt
python -m rhid_espelho_parser /caminho/espelho.pdf --id-person 123 --period-ini 2026-04-01 --period-fim 2026-04-30
```

Saida: JSON em stdout (`schema_version: 1`).

## Testes

```bash
python -m pytest tests
```
