from __future__ import annotations

import argparse
import json
import sys
from datetime import date
from pathlib import Path

from rhid_espelho_parser.extract import parse_espelho_pdf


def main() -> None:
    p = argparse.ArgumentParser(description="Extrai dados do espelho RHID (PDF) para JSON.")
    p.add_argument("pdf_path", type=Path)
    p.add_argument("--id-person", type=int, default=None)
    p.add_argument("--period-ini", type=str, default=None, help="YYYY-MM-DD")
    p.add_argument("--period-fim", type=str, default=None, help="YYYY-MM-DD")
    args = p.parse_args()

    if not args.pdf_path.is_file():
        print(f"Arquivo nao encontrado: {args.pdf_path}", file=sys.stderr)
        sys.exit(2)

    def _parse_d(s: str | None) -> date | None:
        if not s:
            return None
        return date.fromisoformat(s)

    data = parse_espelho_pdf(
        args.pdf_path,
        id_person=args.id_person,
        period_ini=_parse_d(args.period_ini),
        period_fim=_parse_d(args.period_fim),
    )
    json.dump(data, sys.stdout, ensure_ascii=False)


if __name__ == "__main__":
    main()
