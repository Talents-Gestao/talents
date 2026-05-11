# Modelos de contrato (seed)

Arquivos `.docx` de referência usados pelo `ContractTemplateSeeder`.

Esta pasta fica **fora de `storage/`** para que o volume Docker de produção (`talents_storage` montado em `storage/`) não apague os ficheiros presentes na imagem.

## Texto dinâmico igual à proposta

Depois do seed, **edite o modelo em HTML** (aba Contratos) e substitua listagens fixas de serviços por placeholders:

- **`{{servicos_detalhada_html}}`** — tabela só com serviços **contratados**, detalhe com quantidade de funcionários onde aplicável (igual ao PDF da proposta).
- **`{{servicos_lista_html}}`** — lista com marcadores, mesmo critério.
- **`{{svc_bloco_palestras_html}}`** — só aparece se **Palestras** estiver marcada na proposta; caso contrário fica vazio. Idem `svc_bloco_contratacao_html`, `svc_bloco_direcionamento_html`, etc.
- Metadados da proposta: `{{proposta_codigo}}`, `{{proposta_emitida_em}}`, `{{proposta_observacoes}}`, `{{comissao_reais}}`, `{{vendedor_email}}`.

Lista completa na UI ao editar modelo → painel **Placeholders**.
