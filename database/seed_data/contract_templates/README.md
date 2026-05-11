# Modelos de contrato

## Fonte oficial do texto (HTML)

O conteúdo dos **três modelos padrão** é definido em código em `App\Support\CanonicalContractTemplates`: texto enxuto com **apenas placeholders** (`{{servicos_detalhada_html}}`, `{{total_reais}}`, etc.), **sem** tabelas de preço fixas do Word.

Os ficheiros `.docx` nesta pasta são **arquivo de referência** (versões antigas). O `ContractTemplateSeeder` **já não** converte DOCX para HTML no deploy.

## Atualizar manualmente no servidor

```bash
php artisan commercial:sync-canonical-contract-templates
```

Idempotente: sobrescreve o `body_html` dos três nomes padrão pelos textos canónicos.

## Volume Docker

Esta pasta fica **fora de `storage/`** para não ser substituída pelo volume `talents_storage` em produção.
