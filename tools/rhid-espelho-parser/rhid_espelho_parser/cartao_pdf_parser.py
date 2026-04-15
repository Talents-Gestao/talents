"""
Leitor completo do PDF e CSV do Cartão de Ponto (RHID/Control iD).
Extrai todas as páginas, tabelas e texto para trabalho estruturado.
Suporta PDF (pdfplumber) e CSV (API RHID formato CSV - mais confiável para importação).
"""
from __future__ import annotations

import csv
import io
import re
from dataclasses import dataclass, field
from typing import Any

try:
    import pdfplumber
    _HAS_PDFPLUMBER = True
except ImportError:
    _HAS_PDFPLUMBER = False


_RE_DATE = re.compile(r"\b(\d{2}/\d{2}/\d{4})\b")
_RE_DATE_FLEX = re.compile(r"(\d{1,2}/\d{1,2}/\d{2,4})")  # aceita 1/1/26 ou 01/01/2026
_RE_TIME = re.compile(r"\b(\d{2}:\d{2})\b")
_RE_CPF = re.compile(r"\b(\d{3}\.\d{3}\.\d{3}-\d{2})\b")


def _to_min(t: str) -> int:
    a, b = t.split(":")
    return int(a) * 60 + int(b)


def _eh_duracao(t: str, eh_primeira_entrada: bool) -> bool:
    """
    Verifica se o horário parece ser DURAÇÃO (ex: 08:00 = 8h trabalhadas).
    A coluna DURAÇÃO é excluída na extração; esta função é fallback para texto misturado.
    - 08:00 e 09:00 (horas redondas) podem ser DURAÇÃO
    - 08:35, 09:15 etc são horários reais (ENT.2, retorno almoço) - NÃO filtrar
    """
    minutos = _to_min(t)
    h, m = minutos // 60, minutos % 60
    # Só filtrar horas exatamente redondas que parecem DURAÇÃO (08:00, 09:00)
    if m == 0 and h in (8, 9):
        return True
    return False


def _normalizar_marcacoes_do_dia(marcacoes_str: str) -> str:
    """
    Remove duplicatas e DURAÇÃO. Mantém apenas ENT.1 SAÍ.1 ENT.2 SAÍ.2 (e opcionalmente ENT.3 SAÍ.3, ENT.4 SAÍ.4).
    DURAÇÃO nunca deve ser incluída (a coluna DURAÇÃO é excluída na extração).
    """
    if not marcacoes_str or not marcacoes_str.strip():
        return ""
    times = _RE_TIME.findall(marcacoes_str)
    if not times:
        return marcacoes_str.strip()

    # Remover duplicatas mantendo ordem
    vistos: set[str] = set()
    ordenados: list[str] = []
    for t in times:
        if t not in vistos:
            vistos.add(t)
            ordenados.append(t)

    # Filtrar DURAÇÃO: 08:xx e 09:xx sempre; 07:xx quando não é a primeira
    ordenados_sorted = sorted(ordenados, key=_to_min)
    primeira = ordenados_sorted[0] if ordenados_sorted else ""

    filtrados = [
        t for t in ordenados
        if not _eh_duracao(t, eh_primeira_entrada=(t == primeira))
    ]

    # Separar: expediente (<= 17:00) e hora extra (> 17:00)
    dentro_expediente: list[str] = []
    hora_extra: list[str] = []

    for t in filtrados:
        minutos = _to_min(t)
        if minutos <= 17 * 60:
            dentro_expediente.append(t)
        else:
            hora_extra.append(t)

    # Padrão: ENT.1 SAÍ.1 ENT.2 SAÍ.2 [ENT.3 SAÍ.3] [ENT.4 SAÍ.4]
    # Ordenar e manter pares entrada/saída
    dentro_expediente.sort(key=_to_min)

    if len(dentro_expediente) <= 4:
        resultado = dentro_expediente
    else:
        # Manter: 1ª (entrada), 2ª e 3ª (almoço), última (saída)
        primeira = dentro_expediente[0]
        ultima = dentro_expediente[-1]
        almoco = [t for t in dentro_expediente[1:-1] if 11 * 60 <= _to_min(t) <= 13 * 60 + 30]
        if len(almoco) >= 2:
            resultado = [primeira, almoco[0], almoco[1], ultima]
        else:
            resultado = dentro_expediente[:4]

    resultado.extend(hora_extra)
    return " ".join(resultado)


@dataclass
class CelulaTabela:
    """Célula de uma tabela extraída."""
    valor: str
    linha: int
    coluna: int


@dataclass
class TabelaExtraida:
    """Tabela extraída do PDF."""
    pagina: int
    linhas: list[list[str]]
    cabecalho: list[str] | None = None
    dados: list[dict[str, str]] = field(default_factory=list)

    def to_dict(self) -> dict[str, Any]:
        """Converte para dicionário serializável."""
        return {
            "pagina": self.pagina,
            "cabecalho": self.cabecalho,
            "linhas": self.linhas,
            "dados": self.dados,
        }


@dataclass
class PaginaPDF:
    """Conteúdo extraído de uma página do PDF."""
    numero: int
    texto_completo: str
    tabelas: list[TabelaExtraida] = field(default_factory=list)
    texto_por_linha: list[str] = field(default_factory=list)

    def to_dict(self) -> dict[str, Any]:
        return {
            "numero": self.numero,
            "texto_completo": self.texto_completo,
            "texto_por_linha": self.texto_por_linha,
            "tabelas": [t.to_dict() for t in self.tabelas],
        }


@dataclass
class CartaoPontoLido:
    """
    Documento completo do cartão de ponto lido do PDF.
    Contém todas as páginas, tabelas e texto extraídos.
    """
    paginas: list[PaginaPDF] = field(default_factory=list)
    texto_total: str = ""
    total_tabelas: int = 0
    total_linhas_tabela: int = 0

    def to_dict(self) -> dict[str, Any]:
        """Converte para dicionário (útil para JSON/API)."""
        return {
            "paginas": [p.to_dict() for p in self.paginas],
            "texto_total": self.texto_total,
            "total_paginas": len(self.paginas),
            "total_tabelas": self.total_tabelas,
            "total_linhas_tabela": self.total_linhas_tabela,
        }

    def iterar_todas_celulas(self):
        """Itera sobre todas as células de todas as tabelas."""
        for pagina in self.paginas:
            for tabela in pagina.tabelas:
                for i, linha in enumerate(tabela.linhas):
                    for j, celula in enumerate(linha):
                        yield pagina.numero, i, j, str(celula or "").strip()

    def obter_todas_tabelas_planas(self) -> list[list[list[str]]]:
        """Retorna lista de tabelas como matrizes de strings."""
        result = []
        for p in self.paginas:
            for t in p.tabelas:
                result.append(t.linhas)
        return result


def _tabela_para_dicts(linhas: list[list[str]]) -> tuple[list[str] | None, list[dict[str, str]]]:
    """
    Converte linhas de tabela em lista de dicts (primeira linha = cabeçalho).
    Retorna (cabecalho, lista_de_dicts).
    """
    if not linhas or len(linhas) < 2:
        return None, []
    cabecalho = [str(c or "").strip() for c in linhas[0]]
    dados = []
    for row in linhas[1:]:
        d = {}
        for i, val in enumerate(row):
            key = cabecalho[i] if i < len(cabecalho) else f"col_{i}"
            d[key] = str(val or "").strip()
        dados.append(d)
    return cabecalho, dados


def ler_cartao_ponto_pdf(pdf_bytes: bytes) -> CartaoPontoLido:
    """
    Lê o PDF completo do cartão de ponto e extrai todo o conteúdo.

    Args:
        pdf_bytes: Conteúdo binário do PDF.

    Returns:
        CartaoPontoLido com todas as páginas, tabelas e texto.

    Raises:
        RuntimeError: Se pdfplumber não estiver instalado.
    """
    if not _HAS_PDFPLUMBER:
        raise RuntimeError(
            "pdfplumber não instalado. Execute: pip install pdfplumber"
        )

    resultado = CartaoPontoLido()
    textos_paginas = []

    with pdfplumber.open(io.BytesIO(pdf_bytes)) as pdf:
        for i, page in enumerate(pdf.pages):
            num_pagina = i + 1
            texto = page.extract_text() or ""
            linhas_texto = [ln.strip() for ln in texto.splitlines() if ln.strip()]
            textos_paginas.append(texto)

            tabelas_pagina: list[TabelaExtraida] = []
            tables = page.extract_tables()

            for table in tables or []:
                if not table:
                    continue
                linhas_limpas = [
                    [str(c or "").strip() for c in (row or [])]
                    for row in table
                ]
                cabecalho, dados = _tabela_para_dicts(linhas_limpas)
                tab = TabelaExtraida(
                    pagina=num_pagina,
                    linhas=linhas_limpas,
                    cabecalho=cabecalho,
                    dados=dados,
                )
                tabelas_pagina.append(tab)
                resultado.total_tabelas += 1
                resultado.total_linhas_tabela += len(linhas_limpas)

            pag = PaginaPDF(
                numero=num_pagina,
                texto_completo=texto,
                tabelas=tabelas_pagina,
                texto_por_linha=linhas_texto,
            )
            resultado.paginas.append(pag)

    resultado.texto_total = "\n\n".join(textos_paginas)
    return resultado


def extrair_colaboradores_do_cartao(cartao: CartaoPontoLido) -> list[dict[str, Any]]:
    """
    Tenta extrair lista de colaboradores do cartão de ponto (heurística).
    O PDF do RHID geralmente tem cabeçalho com nome, matrícula, PIS, etc.
    """
    colaboradores: list[dict[str, Any]] = []
    vistos: set[str] = set()

    for pag in cartao.paginas:
        for tab in pag.tabelas:
            if not tab.cabecalho:
                continue
            cab_lower = " ".join(tab.cabecalho).lower()
            # Colunas comuns: nome, matrícula, pis, cpf, funcionário
            for row in tab.dados:
                nome = (
                    row.get("nome")
                    or row.get("Nome")
                    or row.get("Funcionário")
                    or row.get("funcionário")
                    or row.get("Colaborador")
                    or ""
                )
                if nome and nome not in vistos:
                    vistos.add(nome)
                    colaboradores.append({
                        "nome": nome,
                        "matricula": row.get("matrícula") or row.get("Matrícula") or row.get("registro") or "",
                        "pis": row.get("pis") or row.get("PIS") or "",
                        "cpf": row.get("cpf") or row.get("CPF") or "",
                        "pagina": pag.numero,
                    })
    return colaboradores


def _preencher_campos_marcacao_pdf_linha(row: dict[str, Any]) -> tuple[str, str, str, str, str]:
    """
    Lê data, dia da semana, entradas, saídas e coluna agregada de horários.
    Cobre Cartão de Ponto e Espelho RHID (cabeçalhos variados no PDF).
    Espelho (API listColumns): DIA_DA_SEMANA, TODAS_MARCACOES; no PDF podem aparecer como
    \"Marcacoes (espelho)\", \"Marcações (Espelho)\", etc.
    """
    data = (
        row.get("data")
        or row.get("Data")
        or row.get("Data ")
        or ""
    )
    dia_sem = (
        row.get("dia")
        or row.get("Dia")
        or row.get("Dia da Semana")
        or row.get("Dia da semana")
        or row.get("DIA_DA_SEMANA")
        or row.get("dia_da_semana")
        or ""
    )
    entradas = row.get("entrada") or row.get("Entrada") or row.get("Entradas") or ""
    saidas = row.get("saída") or row.get("Saída") or row.get("Saídas") or row.get("Saida") or ""
    todas = (
        row.get("marcações")
        or row.get("Marcações")
        or row.get("marcacoes")
        or row.get("Marcacoes")
        or row.get("TODAS_MARCACOES")
        or row.get("todas_marcacoes")
        or row.get("Todas_marcacoes")
        or row.get("Marcacoes (espelho)")
        or row.get("Marcações (espelho)")
        or row.get("MARCACOES_ESPELHO")
        or ""
    )

    for key, raw in row.items():
        ks = str(key or "").strip()
        if not ks:
            continue
        kl = ks.lower()
        v = str(raw or "").strip()
        if not v:
            continue
        if "duração" in kl or "duracao" in kl:
            continue
        if "admissão" in kl or "admissao" in kl:
            continue

        if not todas:
            if "todas_marcacoes" in kl or "todas marcacoes" in kl or "todas marcações" in kl:
                todas = v
                continue
            if "espelho" in kl and ("marc" in kl or "marca" in kl):
                todas = v
                continue
            if kl in ("marcações", "marcacoes", "marcação", "marcacao"):
                todas = v
                continue

        if not data and "data" in kl and "admissão" not in kl and "admissao" not in kl:
            data = v
            continue
        if not dia_sem and (
            "dia_da_semana" in kl
            or kl in ("dia da semana", "dia semana")
            or (
                kl == "dia"
                and v.split()
                and v.split()[0].lower() in _DIAS_SEMANA
            )
        ):
            dia_sem = v
            continue
        if not entradas and "entrada" in kl and "saída" not in kl and "saida" not in kl:
            entradas = v
            continue
        if not saidas and ("saída" in kl or "saida" in kl):
            saidas = v
            continue
        if not data and kl == "dia" and _normalizar_data(v):
            data = v

    return (data, dia_sem, entradas, saidas, todas)


def extrair_marcacoes_do_cartao(cartao: CartaoPontoLido) -> list[dict[str, Any]]:
    """
    Tenta extrair marcações (data/hora) do cartão de ponto.
    Colunas típicas: Data, Dia, Entrada, Saída, Marcações, TODAS_MARCACOES (Espelho), etc.
    """
    marcacoes: list[dict[str, Any]] = []

    for pag in cartao.paginas:
        for tab in pag.tabelas:
            if not tab.cabecalho:
                continue
            for row in tab.dados:
                data, dia, entradas, saidas, todas = _preencher_campos_marcacao_pdf_linha(row)

                if data or todas or entradas or saidas:
                    marcacoes.append({
                        "data": data,
                        "dia_semana": dia,
                        "entradas": entradas,
                        "saidas": saidas,
                        "todas_marcacoes": todas,
                        "pagina": pag.numero,
                        "row": row,
                    })
    return marcacoes


def extrair_marcacoes_do_espelho(cartao: CartaoPontoLido) -> list[dict[str, Any]]:
    """
    Espelho de ponto RHID: mesma leitura de tabelas do PDF que o Cartão; cabeçalhos costumam
    trazer DIA_DA_SEMANA e TODAS_MARCACOES / \"Marcacoes (espelho)\" conforme o relatório.
    """
    return extrair_marcacoes_do_cartao(cartao)


# ---------------------------------------------------------------------------
# Extração estruturada: NOME, CPF, DEPARTAMENTO, CARGO, DIA, MARCAÇÕES
# ---------------------------------------------------------------------------

# Padrão: dia da semana + horários (ex: "QUI 07:30 11:30 12:30 17:00") - comum no Cartão de Ponto
_RE_DIA_HORARIOS = re.compile(
    r"\s*(?:SEG|TER|QUA|QUI|SEX|SÁB|SAB|DOM)\s+\d{2}:\d{2}(?:\s+\d{2}:\d{2})*\s*",
    re.I,
)

_FIELD_LABELS = {
    "nome": re.compile(r"(?:Nome|Funcionário|Colaborador)\s*:\s*(.+)", re.I),
    "cpf": re.compile(r"CPF\s*:\s*(.+)", re.I),
    "departamento": re.compile(r"(?:Departamento|Depto|Setor)\s*:\s*(.+)", re.I),
    "cargo": re.compile(r"(?:Cargo|Função)\s*:\s*(.+)", re.I),
}

# Labels do Cartão de Ponto (layout diferente do Espelho)
_FIELD_LABELS_CARTAO = {
    "cpf": re.compile(r"CPF\s+DO\s+FUNCIONÁRIO\s*:\s*(\d[\d\s.\-]*)", re.I),
    "departamento": re.compile(r"NOME\s+DO\s+DEPARTAMENTO\s*:\s*(.+)", re.I),
    "admissao": re.compile(r"DATA\s+DE\s+ADMISSÃO\s+DO\s+FUNCIONÁRIO\s*:\s*(\d[\d/]*)", re.I),
}


def _remover_dia_e_horarios(valor: str) -> str:
    """Remove padrão 'SEG 07:30 11:30 12:30 17:00' de qualquer campo."""
    if not valor:
        return ""
    return _RE_DIA_HORARIOS.sub(" ", valor).strip()


def _limpar_nome(valor: str) -> str:
    """Remove PIS/PASEP, ADMISSÃO, CPF DO FUNCIONÁRIO e dia+horários do nome."""
    if not valor:
        return ""
    v = valor
    v = re.sub(r"\s+PIS/?\s*PASEP\s*:.*$", "", v, flags=re.I).strip()
    v = re.sub(r"\s+ADMISSÃO\s*:.*$", "", v, flags=re.I).strip()
    v = re.sub(r"\s+CPF\s+DO\s+FUNCIONÁRIO\s*:[\d\s.\-]*", "", v, flags=re.I).strip()
    v = re.sub(r"\s+DATA\s+DE\s+ADMISSÃO\s+DO\s+FUNCIONÁRIO\s*:[\d/\s]*", "", v, flags=re.I).strip()
    v = _remover_dia_e_horarios(v)
    return v.strip()


def _limpar_departamento(valor: str) -> str:
    """Remove sufixo 'CARGO: ...', 'NOME DO DEPARTAMENTO:' e dia+horários."""
    if not valor:
        return ""
    v = re.sub(r"\s+CARGO\s*:.*$", "", valor, flags=re.I).strip()
    v = re.sub(r"^NOME\s+DO\s+DEPARTAMENTO\s*:\s*", "", v, flags=re.I).strip()
    v = _remover_dia_e_horarios(v)
    return v.strip()


def _limpar_cargo(valor: str) -> str:
    """Remove 'NOME DO DEPARTAMENTO: X' e dia+horários do cargo."""
    if not valor:
        return ""
    v = re.sub(r"\s+NOME\s+DO\s+DEPARTAMENTO\s*:.*$", "", valor, flags=re.I).strip()
    v = _remover_dia_e_horarios(v)
    return v.strip()


def _eh_apenas_digitos(valor: str) -> bool:
    """Retorna True se o valor é apenas dígitos (PIS/CPF) - não é nome válido."""
    if not valor or not valor.strip():
        return False
    return bool(re.match(r"^[\d\s.\-]+$", valor.strip()))


def _eh_nome_valido(valor: str) -> bool:
    """Retorna False se o valor NÃO é um nome de pessoa válido (cargo, data+cargo, PIS, CTPS, etc)."""
    if not valor or not valor.strip():
        return False
    v = valor.strip()
    if _eh_apenas_digitos(v):
        return False
    # Rejeitar "DATA NOME DO CARGO: CARGO" ou "NOME DO CARGO: CARGO"
    if "NOME DO CARGO:" in v.upper():
        return False
    # Rejeitar "PIS CTPS DO FUNCIONÁRIO: X" (número + CTPS)
    if "CTPS DO FUNCIONÁRIO:" in v.upper():
        return False
    # Rejeitar se começa com data (dd/mm/yyyy)
    if re.match(r"^\d{1,2}/\d{1,2}/\d{2,4}\s", v):
        return False
    # Rejeitar se começa com 11 dígitos seguidos de espaço (PIS/CPF na coluna nome)
    if re.match(r"^\d{11}\s", v):
        return False
    return True


def _cpf_normalizado(valor: str) -> str:
    """Retorna os 11 dígitos do CPF para comparação."""
    if not valor:
        return ""
    return re.sub(r"\D", "", valor)


def _limpar_cpf_parser(valor: str) -> str:
    """Extrai apenas 11 dígitos do CPF (evita PIS ou texto misturado)."""
    if not valor:
        return ""
    digitos = re.sub(r"\D", "", valor)
    if len(digitos) == 11:
        return f"{digitos[:3]}.{digitos[3:6]}.{digitos[6:9]}-{digitos[9:]}"
    return ""  # PIS tem 11 dígitos também; se vier texto extra, descarta

_DIAS_SEMANA = {
    "seg", "ter", "qua", "qui", "sex", "sáb", "sab", "dom",
    "segunda", "terça", "terca", "quarta", "quinta", "sexta", "sábado", "sabado", "domingo",
    "mon", "tue", "wed", "thu", "fri", "sat", "sun",
}


def _limpar(valor: str) -> str:
    return valor.strip().rstrip(":").strip()


def _normalizar_data(s: str) -> str | None:
    """Converte 1/1/26 em 01/01/2026, retorna None se inválido."""
    if not s or not re.search(r"\d", s):
        return None
    m = _RE_DATE_FLEX.search(s)
    if not m:
        return None
    parts = m.group(1).split("/")
    if len(parts) != 3:
        return None
    d, M, y = parts[0].zfill(2), parts[1].zfill(2), parts[2]
    if len(y) == 2:
        y = "20" + y if int(y) < 50 else "19" + y
    return f"{d}/{M}/{y}"


def _extrair_marcacoes_tabela_wide(table: list, current: dict | None) -> list[dict]:
    """
    Tabela no formato: 1ª linha = datas (01/01, 02/01, ...), demais = horários por coluna.
    Retorna lista de {dia, dia_semana, marcacoes} ou [] se não for esse formato.
    """
    if not table or len(table) < 2 or current is None:
        return []
    header = [str(c or "").strip() for c in table[0]]
    colunas_datas: list[tuple[int, str]] = []
    for i, h in enumerate(header):
        if h and ("duração" in h.lower() or "duracao" in h.lower()):
            continue  # Coluna DURAÇÃO: ignorar completamente
        d = _normalizar_data(h)
        if d:
            colunas_datas.append((i, d))
    if not colunas_datas:
        return []
    resultado: list[dict] = []
    for row in table[1:]:
        cells = [str(c or "").strip() for c in (row or [])]
        for col_idx, dia in colunas_datas:
            if col_idx >= len(cells):
                continue
            val = cells[col_idx]
            if not val or val == "-":
                continue
            marc_norm = _normalizar_marcacoes_do_dia(val)
            if marc_norm:
                resultado.append({"dia": dia, "dia_semana": "", "marcacoes": marc_norm})
    return resultado


def _eh_linha_marcacao(cells: list[str]) -> tuple[str, str, str] | None:
    """
    Verifica se uma linha de tabela representa uma marcação diária.
    Busca data em qualquer célula (não só a primeira).
    Retorna (dia, dia_semana, marcacoes) ou None.
    """
    if not cells:
        return None
    cells_str = [str(c or "").strip() for c in cells]
    # Pular cabeçalhos como "Data", "Dia", "Marcações"
    if cells_str and cells_str[0].lower() in (
        "data",
        "dia",
        "marcações",
        "marcacoes",
        "horário",
        "horario",
        "todas_marcacoes",
        "dia_da_semana",
        "dia da semana",
    ):
        return None

    dia = None
    dia_semana = ""
    horarios_parts: list[str] = []

    for i, val in enumerate(cells_str):
        if not val or val == "-":
            continue
        # Tentar data na primeira célula ou em qualquer célula
        if dia is None:
            d = _normalizar_data(val)
            if d:
                dia = d
                continue
        if val.lower() in _DIAS_SEMANA:
            dia_semana = val
            continue
        # Ignorar célula da coluna DURAÇÃO (valor como "04:35" = 4h35min trabalhadas)
        if "duração" in val.lower() or "duracao" in val.lower():
            continue
        times = _RE_TIME.findall(val)
        if times:
            horarios_parts.extend(times)
        elif val and val not in ("", "-") and not re.match(r"^\d+$", val):
            # Pode ser horário sem : (improvável) ou texto
            if _RE_TIME.search(val):
                horarios_parts.extend(_RE_TIME.findall(val))

    if dia:
        return dia, dia_semana, " ".join(horarios_parts)
    return None


def _extrair_campos_de_celula_mista(celula: str) -> dict[str, str]:
    """
    Extrai nome, cpf, departamento, cargo de uma célula com conteúdo misturado (formato Cartão).
    Ex: "ADRIANA... CPF DO FUNCIONÁRIO: 38091643802 SEG 07:30..." -> nome, cpf
    Ex: "ESPECIALISTA CONTAS A PAGAR NOME DO DEPARTAMENTO: FINANCEIRO QUI 07:30..." -> cargo, departamento
    """
    out: dict[str, str] = {}
    # Nome: tudo antes de "CPF DO FUNCIONÁRIO" ou "PIS" ou "DATA DE ADMISSÃO"
    # Ignorar se for só dígitos (PIS/CPF) - não é nome
    m_nome = re.search(r"^(.+?)\s+(?:CPF\s+DO\s+FUNCIONÁRIO|PIS|DATA\s+DE\s+ADMISSÃO)", celula, re.I)
    if m_nome:
        candidato = _limpar_nome(m_nome.group(1))
        if candidato and not re.match(r"^[\d\s\.\-]+$", candidato):
            out["nome"] = candidato
    # CPF DO FUNCIONÁRIO
    m_cpf = _FIELD_LABELS_CARTAO["cpf"].search(celula)
    if m_cpf:
        out["cpf"] = _limpar_cpf_parser(m_cpf.group(1))
    # NOME DO DEPARTAMENTO - pode estar sozinho ou após cargo ("CARGO NOME DO DEPARTAMENTO: DEPT")
    m_depto = _FIELD_LABELS_CARTAO["departamento"].search(celula)
    if m_depto:
        out["departamento"] = _limpar_departamento(m_depto.group(1))
        # Cargo: texto antes de "NOME DO DEPARTAMENTO"
        antes = celula[: m_depto.start()].strip()
        if antes and len(antes) > 2:
            out["cargo"] = _limpar_cargo(antes)
    return out


def _extrair_campo_texto(linhas: list[str], start: int) -> dict[str, str]:
    """
    Dada uma lista de linhas de texto e um index de início,
    tenta extrair campos do cabeçalho do colaborador (Nome, CPF, etc).
    """
    campos: dict[str, str] = {}
    for i in range(start, min(start + 15, len(linhas))):
        ln = linhas[i]
        # Tentar labels do Cartão primeiro
        for campo, regex in _FIELD_LABELS_CARTAO.items():
            if campo == "admissao":
                continue
            if campo in campos:
                continue
            m = regex.search(ln)
            if m:
                if campo == "cpf":
                    campos[campo] = _limpar_cpf_parser(_limpar(m.group(1)))
                elif campo == "departamento":
                    campos[campo] = _limpar_departamento(_limpar(m.group(1)))
                else:
                    campos[campo] = _limpar(m.group(1))
        # Labels padrão (Espelho)
        for campo, regex in _FIELD_LABELS.items():
            if campo in campos and campos[campo]:
                continue
            m = regex.search(ln)
            if m:
                raw = _limpar(m.group(1))
                if campo == "nome":
                    campos[campo] = _limpar_nome(raw)
                elif campo == "cpf":
                    campos[campo] = _limpar_cpf_parser(campos.get("cpf", "") or raw)
                elif campo == "departamento":
                    campos[campo] = _limpar_departamento(campos.get("departamento", "") or raw)
                elif campo == "cargo":
                    campos[campo] = _limpar_cargo(raw)
                else:
                    campos[campo] = raw
        if len(campos) >= 4:
            break
    return campos


def extrair_dados_cartao_ponto(pdf_bytes: bytes) -> list[dict[str, Any]]:
    """
    Extrai dados estruturados do PDF do Cartão de Ponto (RHID).

    Retorna lista de colaboradores, cada um com:
      - nome, cpf, departamento, cargo
      - marcacoes: lista de {dia, dia_semana, marcacoes}
    """
    if not _HAS_PDFPLUMBER:
        raise RuntimeError("pdfplumber não instalado. Execute: pip install pdfplumber")

    colaboradores: list[dict[str, Any]] = []
    current: dict[str, Any] | None = None

    with pdfplumber.open(io.BytesIO(pdf_bytes)) as pdf:
        for page in pdf.pages:
            texto = page.extract_text() or ""
            linhas = [ln.strip() for ln in texto.splitlines()]
            tables = page.extract_tables() or []

            # --- Estratégia 1: Extrair cabeçalhos de colaboradores do texto ---
            for idx, ln in enumerate(linhas):
                for campo, regex in _FIELD_LABELS.items():
                    if campo != "nome":
                        continue
                    m = regex.search(ln)
                    if m:
                        nome_val = _limpar_nome(_limpar(m.group(1)))
                        if not nome_val or len(nome_val) < 2 or not _eh_nome_valido(nome_val):
                            continue
                        if current is not None:
                            colaboradores.append(current)
                        campos = _extrair_campo_texto(linhas, idx)
                        current = {
                            "nome": _limpar_nome(campos.get("nome", nome_val)),
                            "cpf": _limpar_cpf_parser(campos.get("cpf", "")),
                            "departamento": _limpar_departamento(campos.get("departamento", "")),
                            "cargo": _limpar_cargo(campos.get("cargo", "")),
                            "marcacoes": [],
                        }

            # --- Estratégia 2: Extrair marcações das tabelas ---
            for table in tables:
                if not table:
                    continue
                # Tabela com colunas = dias (01/01, 02/01, ...): primeira linha são datas
                marcacoes_por_coluna = _extrair_marcacoes_tabela_wide(table, current)
                if marcacoes_por_coluna and current is not None:
                    for entry in marcacoes_por_coluna:
                        current["marcacoes"].append(entry)
                    continue
                # Tabela com linhas = dias (Data | Dia | Marcações | DURAÇÃO)
                header_row = [str(c or "").strip() for c in (table[0] or [])]
                col_duracao = next(
                    (i for i, h in enumerate(header_row) if h and ("duração" in h.lower() or "duracao" in h.lower())),
                    None,
                )
                for row in table:
                    cells = [str(c or "").strip() for c in (row or [])]
                    if col_duracao is not None:
                        cells = [c for i, c in enumerate(cells) if i != col_duracao]
                    parsed = _eh_linha_marcacao(cells)
                    if parsed:
                        dia, dia_semana, marcacoes_str = parsed
                        entry = {
                            "dia": dia,
                            "dia_semana": dia_semana,
                            "marcacoes": _normalizar_marcacoes_do_dia(marcacoes_str),
                        }
                        if current is not None:
                            current["marcacoes"].append(entry)
                    else:
                        # Pode ser cabeçalho de colaborador dentro da tabela (Espelho ou Cartão)
                        joined = " ".join(cells).strip()
                        nome_val = None
                        # Tentar extrair nome: padrão Nome: X ou célula mista (Cartão)
                        m_nome = _FIELD_LABELS["nome"].search(joined)
                        if m_nome:
                            nome_val = _limpar_nome(_limpar(m_nome.group(1)))
                        else:
                            extraidos = _extrair_campos_de_celula_mista(joined)
                            if extraidos.get("nome"):
                                nome_val = extraidos["nome"]
                        if nome_val and len(nome_val) >= 2 and _eh_nome_valido(nome_val):
                            if current is not None:
                                nome_current = _limpar_nome(current.get("nome", ""))
                                if nome_current.upper() == nome_val.upper():
                                    for c2 in cells:
                                        ext = _extrair_campos_de_celula_mista(c2)
                                        for f, v in ext.items():
                                            if v and not current.get(f):
                                                current[f] = v
                                        for f, rx in _FIELD_LABELS.items():
                                            if f == "nome" or current.get(f):
                                                continue
                                            m2 = rx.search(c2)
                                            if m2:
                                                if f == "cpf":
                                                    current[f] = _limpar_cpf_parser(_limpar(m2.group(1)))
                                                elif f == "departamento":
                                                    current[f] = _limpar_departamento(_limpar(m2.group(1)))
                                                elif f == "cargo":
                                                    current[f] = _limpar_cargo(_limpar(m2.group(1)))
                                                else:
                                                    current[f] = _limpar(m2.group(1))
                                    break
                                colaboradores.append(current)
                            if current is None or _limpar_nome(current.get("nome", "")).upper() != nome_val.upper():
                                current = {
                                    "nome": nome_val,
                                    "cpf": "",
                                    "departamento": "",
                                    "cargo": "",
                                    "marcacoes": [],
                                }
                                for c2 in cells:
                                    ext = _extrair_campos_de_celula_mista(c2)
                                    for f, v in ext.items():
                                        if v:
                                            current[f] = v
                                    for f, rx in _FIELD_LABELS.items():
                                        if f == "nome" or current.get(f):
                                            continue
                                        m2 = rx.search(c2)
                                        if m2:
                                            if f == "cpf":
                                                current[f] = _limpar_cpf_parser(_limpar(m2.group(1)))
                                            elif f == "departamento":
                                                current[f] = _limpar_departamento(_limpar(m2.group(1)))
                                            elif f == "cargo":
                                                current[f] = _limpar_cargo(_limpar(m2.group(1)))
                                            else:
                                                current[f] = _limpar(m2.group(1))
                            break

    if current is not None:
        colaboradores.append(current)

    # --- Estratégia 3: Fallback via tabelas com cabeçalho de colunas ---
    if not colaboradores:
        colaboradores = _extrair_via_tabelas_cabecalho(pdf_bytes)
    else:
        # Se temos colaboradores mas sem marcações, tentar extrair do fallback e mesclar
        total_marc = sum(len(c.get("marcacoes", [])) for c in colaboradores)
        if total_marc == 0:
            fallback = _extrair_via_tabelas_cabecalho(pdf_bytes)
            if fallback:
                _mesclar_marcacoes(colaboradores, fallback)

    # --- Deduplicar por nome (limpo) e limpar campos ---
    return _deduplicar_e_limpar(colaboradores)


def _mesclar_marcacoes(colaboradores: list[dict], fallback: list[dict]) -> None:
    """Mescla marcações do fallback nos colaboradores por nome."""
    por_nome: dict[str, list] = {}
    for c in fallback:
        n = _limpar_nome(c.get("nome", "")).upper()
        if n and c.get("marcacoes"):
            por_nome[n] = c["marcacoes"]
    for c in colaboradores:
        n = _limpar_nome(c.get("nome", "")).upper()
        if n in por_nome and not c.get("marcacoes"):
            c["marcacoes"] = list(por_nome[n])


def _extrair_via_tabelas_cabecalho(pdf_bytes: bytes) -> list[dict[str, Any]]:
    """
    Fallback: quando o PDF usa tabela com colunas nomeadas
    (Nome, CPF, Departamento, Cargo, Data, Marcações) em vez de blocos por colaborador.
    """
    resultado: list[dict[str, Any]] = []
    agrupado: dict[str, dict[str, Any]] = {}

    with pdfplumber.open(io.BytesIO(pdf_bytes)) as pdf:
        for page in pdf.pages:
            tables = page.extract_tables() or []
            for table in tables:
                if not table or len(table) < 2:
                    continue
                header = [str(c or "").strip().lower() for c in table[0]]

                col_nome = _find_col(header, ["nome", "funcionário", "funcionario", "colaborador"])
                col_cpf = _find_col(header, ["cpf"])
                col_depto = _find_col(header, ["departamento", "depto", "setor"])
                col_cargo = _find_col(header, ["cargo", "função", "funcao"])
                col_dia = _find_col(header, ["data", "dia"])
                col_dia_sem = _find_col(
                    header,
                    ["dia da semana", "dia semana", "dia_semana", "dia_da_semana", "dia da semana "],
                )
                col_marc = _find_col(
                    header,
                    [
                        "marcações",
                        "marcacoes",
                        "marcação",
                        "todas_marcacoes",
                        "todas marcações",
                        "todas marcacoes",
                        "marcacoes (espelho)",
                        "marcações (espelho)",
                        "marcacoes(espelho)",
                    ],
                )
                col_duracao = _find_col(header, ["duração", "duracao"])

                if col_nome is None:
                    continue

                for row in table[1:]:
                    cells = [str(c or "").strip() for c in (row or [])]
                    nome_raw = cells[col_nome] if col_nome < len(cells) else ""
                    nome = _limpar_nome(nome_raw)
                    if not nome:
                        continue
                    # Ignorar linhas onde "nome" não é válido (PIS/CPF, data+cargo, NOME DO CARGO, etc)
                    if not _eh_nome_valido(nome):
                        # Só tentar mesclar por CPF quando for PIS/CPF (dígitos)
                        if _eh_apenas_digitos(nome):
                            cpf_row = cells[col_cpf] if col_cpf is not None and col_cpf < len(cells) else ""
                            cpf_norm = _cpf_normalizado(cpf_row) or _cpf_normalizado(nome)
                            if cpf_norm and len(cpf_norm) == 11:
                                for k, v in agrupado.items():
                                    if _cpf_normalizado(v.get("cpf", "")) == cpf_norm:
                                        dia = cells[col_dia] if col_dia is not None and col_dia < len(cells) else ""
                                        dia_sem = cells[col_dia_sem] if col_dia_sem is not None and col_dia_sem < len(cells) else ""
                                        marc = cells[col_marc] if col_marc is not None and col_marc < len(cells) else ""
                                        if col_duracao is not None and col_marc == col_duracao:
                                            marc = ""
                                        if dia and marc and _RE_TIME.search(marc):
                                            v["marcacoes"].append({
                                                "dia": dia,
                                                "dia_semana": dia_sem,
                                                "marcacoes": _normalizar_marcacoes_do_dia(marc),
                                            })
                                        if not v.get("departamento") and col_depto is not None and col_depto < len(cells):
                                            v["departamento"] = _limpar_departamento(cells[col_depto])
                                        if not v.get("cargo") and col_cargo is not None and col_cargo < len(cells):
                                            v["cargo"] = _limpar_cargo(cells[col_cargo])
                                        break
                        continue

                    cpf_raw = cells[col_cpf] if col_cpf is not None and col_cpf < len(cells) else ""
                    depto_raw = cells[col_depto] if col_depto is not None and col_depto < len(cells) else ""
                    cargo = cells[col_cargo] if col_cargo is not None and col_cargo < len(cells) else ""
                    dia = cells[col_dia] if col_dia is not None and col_dia < len(cells) else ""
                    dia_sem = cells[col_dia_sem] if col_dia_sem is not None and col_dia_sem < len(cells) else ""
                    marc = cells[col_marc] if col_marc is not None and col_marc < len(cells) else ""
                    if col_duracao is not None and col_marc == col_duracao:
                        marc = ""  # Não usar coluna DURAÇÃO como marcações
                    if not marc or not _RE_TIME.search(marc):
                        marc_jornada = _extrair_marc_de_jornada(header, cells)
                        if marc_jornada:
                            marc = marc_jornada

                    cpf = _limpar_cpf_parser(cpf_raw) or cpf_raw
                    depto = _limpar_departamento(depto_raw)
                    cargo_limpo = _limpar_cargo(cargo)

                    key = nome.upper()
                    if key not in agrupado:
                        agrupado[key] = {
                            "nome": nome,
                            "cpf": cpf,
                            "departamento": depto,
                            "cargo": cargo_limpo,
                            "marcacoes": [],
                        }
                    if not agrupado[key]["cpf"] and cpf:
                        agrupado[key]["cpf"] = cpf
                    if not agrupado[key]["departamento"] and depto:
                        agrupado[key]["departamento"] = depto
                    if not agrupado[key]["cargo"] and cargo_limpo:
                        agrupado[key]["cargo"] = cargo_limpo

                    if dia:
                        agrupado[key]["marcacoes"].append({
                            "dia": dia,
                            "dia_semana": dia_sem,
                            "marcacoes": _normalizar_marcacoes_do_dia(marc),
                        })

    resultado = list(agrupado.values())
    return resultado


def _find_col(header: list[str], candidates: list[str]) -> int | None:
    for i, h in enumerate(header):
        for cand in candidates:
            if cand in h:
                return i
    return None


def _extrair_marc_de_jornada(header: list[str], cells: list[str]) -> str:
    """
    Quando a coluna MARCAÇÕES está vazia, tenta extrair das colunas JORNADA REALIZADA
    (ENT. 1, SAÍ. 1, ENT. 2, SAÍ. 2, ENT. 3, SAÍ. 3).
    """
    partes: list[str] = []
    for n in range(1, 5):
        for lbl in (f"ent. {n}", f"ent {n}", f"ent.{n}", f"saí. {n}", f"sai. {n}", f"saí {n}", f"sai {n}"):
            col = _find_col(header, [lbl])
            if col is not None and col < len(cells):
                val = (cells[col] or "").strip()
                m = _RE_TIME.search(val)
                if m:
                    partes.append(m.group(1))
    return " ".join(partes)


def _deduplicar_e_limpar(colaboradores: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Remove duplicatas por nome (limpo) e aplica limpeza final nos campos.
    Ignora entradas onde nome é só PIS/CPF (dados misturados); tenta mesclar por CPF."""
    agrupado: dict[str, dict[str, Any]] = {}
    digit_only_pendentes: list[dict[str, Any]] = []

    for c in colaboradores:
        nome = _limpar_nome(c.get("nome", ""))
        if not nome:
            continue
        # Nome inválido (PIS/CPF, data+cargo, NOME DO CARGO, etc)
        if not _eh_nome_valido(nome):
            if _eh_apenas_digitos(nome):
                digit_only_pendentes.append(c)
            continue
        key = nome.upper().strip()
        if key not in agrupado:
            agrupado[key] = {
                "nome": nome,
                "cpf": _limpar_cpf_parser(c.get("cpf", "")) or c.get("cpf", ""),
                "departamento": _limpar_departamento(c.get("departamento", "")),
                "cargo": _limpar_cargo(c.get("cargo", "")),
                "marcacoes": list(c.get("marcacoes", [])),
            }
        else:
            # Mesclar: manter dados mais completos
            exist = agrupado[key]
            if not exist["cpf"] and c.get("cpf"):
                exist["cpf"] = _limpar_cpf_parser(c.get("cpf", "")) or c.get("cpf", "")
            if not exist["departamento"] and c.get("departamento"):
                exist["departamento"] = _limpar_departamento(c.get("departamento", ""))
            if not exist["cargo"] and c.get("cargo"):
                exist["cargo"] = _limpar_cargo(c.get("cargo", ""))
            exist["marcacoes"].extend(c.get("marcacoes", []))

    # Mesclar entradas digit-only por CPF (evitar linhas duplicadas com PIS no lugar do nome)
    for c in digit_only_pendentes:
        cpf_c = _cpf_normalizado(c.get("cpf", "")) or _cpf_normalizado(c.get("nome", ""))
        if len(cpf_c) != 11:
            continue
        for exist in agrupado.values():
            if _cpf_normalizado(exist.get("cpf", "")) == cpf_c:
                if not exist["departamento"] and c.get("departamento"):
                    exist["departamento"] = _limpar_departamento(c.get("departamento", ""))
                if not exist["cargo"] and c.get("cargo"):
                    exist["cargo"] = _limpar_cargo(c.get("cargo", ""))
                exist["marcacoes"].extend(c.get("marcacoes", []))
                break
    # Remover marcações duplicadas (mesmo dia) e normalizar horários
    for c in agrupado.values():
        vistos_dias: set[str] = set()
        unicas: list[dict] = []
        for m in c["marcacoes"]:
            dia = m.get("dia", "")
            if dia and dia not in vistos_dias:
                vistos_dias.add(dia)
                marc = m.get("marcacoes", "")
                unicas.append({
                    "dia": dia,
                    "dia_semana": m.get("dia_semana") or "",
                    "marcacoes": _normalizar_marcacoes_do_dia(marc or ""),
                    "justificativas": m.get("justificativas") or "",
                })
        c["marcacoes"] = unicas
    return list(agrupado.values())


# ---------------------------------------------------------------------------
# Parser CSV (API RHID formato CSV - dados mais limpos que PDF)
# ---------------------------------------------------------------------------

# Colunas de marcação do CSV RHID (Entrada 1, Saída 1, etc) - apenas estas
_COLUNAS_MARCACAO_RHID = [
    "entrada 1", "saída 1", "saida 1",
    "entrada 2", "saída 2", "saida 2",
    "entrada 3", "saída 3", "saida 3",
]
# Colunas a excluir (durações, previsto, etc)
_COLUNAS_EXCLUIR_MARCACAO = [
    "previsto", "total normais", "total noturno", "banco total", "banco saldo",
    "dia falta", "falta e atraso", "abono", "extra diurna", "extra noturna",
]


def _parsear_dia_com_semana(dia_raw: str) -> tuple[str, str]:
    """
    Parseia "01/03/2026 DOM" em (dia="01/03/2026", dia_semana="DOM").
    Retorna (dia_normalizado, dia_semana).
    """
    if not dia_raw or not dia_raw.strip():
        return ("", "")
    v = dia_raw.strip()
    dia = _normalizar_data(v)
    # Extrair dia da semana (SEG, TER, DOM, etc) após a data
    m = re.search(r"\d{1,2}/\d{1,2}/\d{2,4}\s+([A-Za-zÀ-ÿ]{2,})", v)
    dia_sem = m.group(1).strip().upper() if m else ""
    return (dia or "", dia_sem)


def _find_csv_col(row: dict[str, str], candidates: list[str]) -> str | None:
    """Encontra valor da coluna por nome (case insensitive, partial match)."""
    keys_lower = {(k or "").strip().lower(): k for k in row.keys() if k}
    for cand in candidates:
        c = cand.lower()
        for h, orig in keys_lower.items():
            if c in h or h in c:
                val = row.get(orig) or ""
                return (val.strip() if isinstance(val, str) else str(val or "").strip()) or None
    return None


def _coletar_marcacoes_rhid(row: dict[str, str]) -> str:
    """
    Coleta marcações (horários HH:MM) das colunas Entrada 1, Saída 1, Entrada 2, Saída 2, etc.
    Inclui coluna agregada do Espelho (TODAS_MARCACOES / Marcacoes (espelho)) quando existir.
    Exclui Previsto, Total Normais, Banco Saldo e ignora "Folga".
    Valores como "Justificado 04 - Férias (Dia)" não são horários - são capturados em _coletar_justificativas_rhid.
    """
    partes: list[str] = []
    for k, v in row.items():
        v = v if v is not None else ""
        if not v or not str(v).strip():
            continue
        kl = (k or "").strip().lower()
        # Excluir colunas de duração/previsto
        if any(exc in kl for exc in _COLUNAS_EXCLUIR_MARCACAO):
            continue
        eh_ent_sai = any(m in kl for m in _COLUNAS_MARCACAO_RHID)
        eh_todas_espelho = (
            "todas_marcacoes" in kl
            or "todas marcacoes" in kl
            or "todas marcações" in kl
            or ("espelho" in kl and ("marc" in kl or "marcação" in kl or "marcacao" in kl))
        )
        if not eh_ent_sai and not eh_todas_espelho:
            continue
        if str(v).strip().upper() == "FOLGA":
            continue
        m = _RE_TIME.search(str(v))
        if m:
            partes.append(m.group(1))
    return " ".join(partes)


def _coletar_justificativas_rhid(row: dict[str, str]) -> str:
    """
    Coleta TODAS as justificativas da linha:
    - Coluna "Justificativas" (ex: "01 - Declaração Horas", "03 - Atestado Médico (HS)")
    - Colunas Entrada 1, Saída 1, Entrada 2, Saída 2, Entrada 3, Saída 3 quando o valor contém
      texto de justificação (ex: "Justificado 04 - Férias (Dia)", "Justificado Ajuste Banco Horas",
      "Justificado Enterro no período da tarde", "Justificado 01 - Declaração Horas").
    Ignora valores que são apenas horário (HH:MM) ou "Folga".
    """
    justifs: list[str] = []
    vistos: set[str] = set()

    for k, v in row.items():
        v = v if v is not None else ""
        if not v or not str(v).strip():
            continue
        val = str(v).strip()
        kl = (k or "").strip().lower()

        # 1. Coluna Justificativas
        if "justificativa" in kl:
            if val and val not in vistos:
                vistos.add(val)
                justifs.append(val)
            continue

        # 2. Colunas Entrada/Saída (excluir duração/previsto)
        if any(exc in kl for exc in _COLUNAS_EXCLUIR_MARCACAO):
            continue
        if not any(m in kl for m in _COLUNAS_MARCACAO_RHID):
            continue
        if val.upper() == "FOLGA":
            continue
        # Se for apenas horário (ex: "07:34 (M)", "14:20 (I)"), não é justificativa
        if re.match(r"^\s*\d{2}:\d{2}\s*(\([A-Za-z]\))?\s*$", val):
            continue
        # Qualquer outro texto nas colunas de marcação é justificativa (Justificado X, Atestado, Férias, etc.)
        if val and val not in vistos:
            vistos.add(val)
            justifs.append(val)

    return " | ".join(justifs) if justifs else ""


def extrair_dados_cartao_csv(csv_bytes: bytes) -> list[dict[str, Any]]:
    """
    Extrai dados do Cartão de Ponto a partir do CSV retornado pela API RHID.
    O CSV tem colunas bem definidas (Nome, CPF, Departamento, Cargo, Data, Marcações)
    e é mais confiável que o PDF para importação.

    Suporta:
    - Formato longo: uma linha por dia (Nome, CPF, Data, Marcações)
    - Formato wide: uma linha por colaborador, colunas = datas (01/01, 02/01, ...)

    Retorna lista de colaboradores no mesmo formato de extrair_dados_cartao_ponto.
    """
    for encoding in ("utf-8", "utf-8-sig", "latin-1", "cp1252"):
        try:
            text = csv_bytes.decode(encoding)
            break
        except UnicodeDecodeError:
            continue
    else:
        raise ValueError("Não foi possível decodificar o CSV (tentou utf-8, latin-1, cp1252)")

    # Detectar delimitador: ; ou , (nunca tab - CSV brasileiro usa ;, mesmo arquivo)
    first_line = text.split("\n")[0] if text else ""
    if "\t" in first_line and ";" not in first_line and "," not in first_line:
        text = text.replace("\t", ";")
        first_line = text.split("\n")[0]
    delimiter = ";" if ";" in first_line and first_line.count(";") >= first_line.count(",") else ","

    reader = csv.DictReader(io.StringIO(text), delimiter=delimiter)
    rows = list(reader)
    if not rows:
        return []

    headers = list(rows[0].keys()) if rows else []
    # Formato wide: colunas com datas (01/01, 02/01, 01/01/2025, etc)
    colunas_datas: list[tuple[str, str]] = []
    for h in headers:
        if not h or ("duração" in h.lower() or "duracao" in h.lower()):
            continue
        d = _normalizar_data(str(h).strip())
        if d:
            colunas_datas.append((h, d))

    agrupado: dict[str, dict[str, Any]] = {}
    current_key: str | None = None  # Para linhas de continuação (sem nome, mas com data/marcações)

    for row in rows:
        # Priorizar "Nome do funcionário" (evitar "Nome da empresa")
        nome_raw = _find_csv_col(row, ["nome do funcionário", "nome do funcionario", "funcionário", "funcionario", "colaborador", "person", "name", "nome"])
        nome = _limpar_nome(nome_raw or "")
        tem_nome_valido = nome and _eh_nome_valido(nome)

        # Formato longo: linhas de continuação (sem nome, mas com data/marcações) -> adicionar ao último colaborador
        if not tem_nome_valido and not colunas_datas:
            dia_raw = _find_csv_col(row, ["dia", "data", "date"])
            dia, dia_sem = _parsear_dia_com_semana(dia_raw or "")
            marc = _coletar_marcacoes_rhid(row)
            if not marc:
                marc_raw = _find_csv_col(
                    row,
                    [
                        "marcações",
                        "marcacoes",
                        "marcação",
                        "horários",
                        "horarios",
                        "todas_marcacoes",
                        "todas marcações",
                        "marcacoes (espelho)",
                        "marcações (espelho)",
                    ],
                )
                marc = marc_raw or ""
                for k, v in row.items():
                    if v and _RE_TIME.search(str(v)):
                        if not any(exc in k.lower() for exc in _COLUNAS_EXCLUIR_MARCACAO):
                            marc = f"{marc} {v}".strip()
            justif = _coletar_justificativas_rhid(row)
            if current_key and dia and agrupado.get(current_key) and (marc or justif):
                marc_norm = _normalizar_marcacoes_do_dia(marc)
                agrupado[current_key]["marcacoes"].append({
                    "dia": dia,
                    "dia_semana": dia_sem,
                    "marcacoes": marc_norm,
                    "justificativas": (justif or "").strip(),
                })
            continue

        if not tem_nome_valido:
            continue

        cpf_raw = _find_csv_col(row, ["cpf"])
        cpf = _limpar_cpf_parser(cpf_raw or "") or cpf_raw or ""
        depto_raw = _find_csv_col(row, ["departamento", "depto", "setor", "department"])
        depto = _limpar_departamento(depto_raw or "")
        cargo_raw = _find_csv_col(row, ["cargo", "função", "funcao", "role"])
        cargo = _limpar_cargo(cargo_raw or "")

        key = nome.upper().strip()
        current_key = key
        if key not in agrupado:
            agrupado[key] = {
                "nome": nome,
                "cpf": cpf,
                "departamento": depto,
                "cargo": cargo,
                "marcacoes": [],
            }
        else:
            exist = agrupado[key]
            if not exist["cpf"] and cpf:
                exist["cpf"] = cpf
            if not exist["departamento"] and depto:
                exist["departamento"] = depto
            if not exist["cargo"] and cargo:
                exist["cargo"] = cargo

        # Formato wide: colunas são datas
        if colunas_datas:
            for col_name, dia in colunas_datas:
                val = (row.get(col_name) or "").strip()
                if not val or val == "-":
                    continue
                marc_norm = _normalizar_marcacoes_do_dia(val)
                # Se não há horários mas há texto (ex: "Justificado 04 - Férias (Dia)"), usar como justificativa
                justif = "" if val.upper() == "FOLGA" else (val if not marc_norm else "")
                if marc_norm or justif:
                    agrupado[key]["marcacoes"].append({
                        "dia": dia,
                        "dia_semana": "",
                        "marcacoes": marc_norm,
                        "justificativas": justif.strip() if justif else "",
                    })
        else:
            # Formato longo: coluna Dia (prioridade sobre Data de Admissão) + Marcações + Justificativas
            dia_raw = _find_csv_col(row, ["dia", "data", "date"])
            dia, dia_sem = _parsear_dia_com_semana(dia_raw or "")
            marc = _coletar_marcacoes_rhid(row)
            if not marc:
                marc_raw = _find_csv_col(
                    row,
                    [
                        "marcações",
                        "marcacoes",
                        "marcação",
                        "horários",
                        "horarios",
                        "todas_marcacoes",
                        "todas marcações",
                        "marcacoes (espelho)",
                        "marcações (espelho)",
                    ],
                )
                marc = marc_raw or ""
                for k, v in row.items():
                    if v and _RE_TIME.search(str(v)):
                        if not any(exc in k.lower() for exc in _COLUNAS_EXCLUIR_MARCACAO):
                            marc = f"{marc} {v}".strip()
            justif = _coletar_justificativas_rhid(row)
            if dia and (marc or justif):
                marc_norm = _normalizar_marcacoes_do_dia(marc)
                agrupado[key]["marcacoes"].append({
                    "dia": dia,
                    "dia_semana": dia_sem,
                    "marcacoes": marc_norm,
                    "justificativas": (justif or "").strip(),
                })

    return _deduplicar_e_limpar(list(agrupado.values()))
