<?php

namespace App\Support;

/**
 * Corpo HTML dos modelos padrão (sem tabelas fixas do Word).
 * Usa apenas placeholders dinâmicos — mesmos dados do PDF da proposta comercial.
 */
final class CanonicalContractTemplates
{
    /**
     * @return array<string, string> nome do modelo => body_html
     */
    public static function all(): array
    {
        return [
            'Consultoria - Padrão Talents' => self::consultoria(),
            'Contratação de Talentos - Padrão Talents' => self::contratacaoTalentos(),
            'Palestra - Padrão Talents' => self::palestra(),
        ];
    }

    private static function consultoria(): string
    {
        return <<<'HTML'
<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">1. Das partes</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;"><strong>CONTRATANTE:</strong> {{cliente_nome}}, pessoa jurídica de direito privado, inscrita no CNPJ sob o nº {{cliente_cnpj}}, com sede na forma de seus registros, neste ato representada na forma de seu documento social, doravante denominada simplesmente <strong>CONTRATANTE</strong>.</p>
<p style="font-size:11px;line-height:1.5;color:#0f172a;"><strong>CONTRATADA:</strong> {{empresa_nome}}, inscrita no CNPJ {{empresa_cnpj}}, com sede em {{empresa_endereco}}, e-mail {{empresa_email}}, telefone {{empresa_telefone}}, doravante denominada <strong>CONTRATADA</strong>.</p>

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">2. Do objeto</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;">O presente instrumento tem por objeto a prestação à CONTRATANTE dos serviços de consultoria e correlatos <strong>descritos na proposta comercial nº {{proposta_codigo}}</strong>, emitida em {{proposta_emitida_em}}, válida até {{validade_data}}, conforme especificação técnica e valores abaixo, os quais integram o presente contrato como se aqui estivessem transcritos.</p>

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">3. Dos serviços contratados e valores</h2>
<p style="font-size:11px;line-height:1.5;color:#64748b;">Somente constam deste instrumento os serviços efetivamente contratados na proposta e seus respectivos valores (alinhados ao PDF da proposta):</p>
{{servicos_detalhada_html}}
<p style="font-size:11px;line-height:1.5;color:#0f172a;margin-top:12px;"><strong>Honorário total do contrato:</strong> {{total_reais}} (<em>{{total_extenso}}</em>).</p>
<p style="font-size:11px;line-height:1.5;color:#0f172a;">Quantidade de colaboradores considerada na proposta (quando aplicável): <strong>{{numero_funcionarios}}</strong>.</p>

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">4. Da comissão comercial (quando houver)</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;">Quando aplicável à operação: percentual {{comissao_percent}}%, correspondente a {{comissao_reais}}, conforme proposta. Responsável comercial: {{vendedor_nome}} ({{vendedor_email}}).</p>

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">5. Do pagamento e prazo</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;"><strong>Forma de pagamento:</strong> {{forma_pagamento}}</p>
<p style="font-size:11px;line-height:1.5;color:#0f172a;"><strong>Prazo / condição complementar (dias):</strong> {{prazo_dias}}</p>
<p style="font-size:11px;line-height:1.5;color:#64748b;">Observações da proposta (quando houver): {{proposta_observacoes}}</p>

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">6. Das informações adicionais</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;"><strong>Indicação / origem do lead:</strong> {{proposta_indicacao}}</p>

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">7. Do foro e assinatura</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;">Fica eleito o foro da comarca de {{cidade_estado}}, com renúncia a qualquer outro, por mais privilegiado que seja.</p>
<p style="font-size:11px;line-height:1.45;color:#0f172a;margin-top:24px;">{{cidade_estado}}, {{data_hoje}}.</p>
HTML;
    }

    private static function contratacaoTalentos(): string
    {
        return <<<'HTML'
<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">1. Das partes</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;"><strong>CONTRATANTE:</strong> {{cliente_nome}}, CNPJ {{cliente_cnpj}}, e-mail {{cliente_email}}, telefone {{cliente_telefone}}, doravante <strong>CONTRATANTE</strong>.</p>
<p style="font-size:11px;line-height:1.5;color:#0f172a;"><strong>CONTRATADA:</strong> {{empresa_nome}}, CNPJ {{empresa_cnpj}}, {{empresa_endereco}} — {{empresa_email}} / {{empresa_telefone}}, doravante <strong>CONTRATADA</strong>.</p>

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">2. Do objeto — contratação / recrutamento</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;">A CONTRATADA prestará à CONTRATANTE os serviços de recrutamento e seleção / contratação de talentos <strong>na forma contratada na proposta nº {{proposta_codigo}}</strong> (emitida em {{proposta_emitida_em}}, válida até {{validade_data}}). Os serviços efetivos, quantidades e valores são unicamente os da proposta:</p>
<p style="font-size:11px;line-height:1.5;color:#64748b;margin:8px 0;">Destaque do pacote de contratação (quando contratado na proposta):</p>
{{svc_bloco_contratacao_html}}
{{servicos_detalhada_html}}
<p style="font-size:11px;line-height:1.5;color:#0f172a;margin-top:12px;"><strong>Valor total:</strong> {{total_reais}} (<em>{{total_extenso}}</em>).</p>

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">3. Pagamento e prazo</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;">{{forma_pagamento}}</p>
<p style="font-size:11px;line-height:1.5;color:#0f172a;">Prazo em dias (referência): {{prazo_dias}}</p>

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">4. Comercial</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;">{{vendedor_nome}} — {{vendedor_email}}. Comissão: {{comissao_percent}}% ({{comissao_reais}}).</p>

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">5. Foro</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;">Foro de {{cidade_estado}}.</p>
<p style="font-size:11px;line-height:1.45;color:#0f172a;margin-top:24px;">{{cidade_estado}}, {{data_hoje}}.</p>
HTML;
    }

    private static function palestra(): string
    {
        return <<<'HTML'
<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">1. Das partes</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;"><strong>CONTRATANTE:</strong> {{cliente_nome}}, CNPJ {{cliente_cnpj}}, {{cliente_email}}, {{cliente_telefone}}.</p>
<p style="font-size:11px;line-height:1.5;color:#0f172a;"><strong>CONTRATADA:</strong> {{empresa_nome}}, CNPJ {{empresa_cnpj}}, {{empresa_endereco}}.</p>

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">2. Do objeto — palestras / treinamentos</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;">A CONTRATADA realizará junto à CONTRATANTE os serviços de palestras e/ou treinamentos <strong>conforme proposta comercial nº {{proposta_codigo}}</strong> ({{proposta_emitida_em}}, válida até {{validade_data}}).</p>
<p style="font-size:11px;line-height:1.5;color:#64748b;">Serviço de palestras contratado (quando houver na proposta):</p>
{{svc_bloco_palestras_html}}

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">3. Demais serviços na mesma proposta</h2>
<p style="font-size:11px;line-height:1.5;color:#64748b;">Se na mesma proposta houver outros serviços (consultoria, NR-1, etc.), constam abaixo — apenas os efetivamente contratados:</p>
{{servicos_detalhada_html}}

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">4. Valores totais</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;"><strong>Valor total do contrato:</strong> {{total_reais}} (<em>{{total_extenso}}</em>). Não há linhas de “desconto” ou “parcelas” automáticas — qualquer condição comercial detalhada deve constar em <strong>Forma de pagamento</strong> (abaixo) ou nas observações da proposta.</p>

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">5. Forma de pagamento</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;">{{forma_pagamento}}</p>
<p style="font-size:11px;line-height:1.5;color:#64748b;">Inclua aqui (nas Configurações → Empresa / texto padrão) condições como à vista, parcelamento ou descontos, pois o sistema não calcula parcelas automaticamente.</p>

<h2 style="font-size:14px;color:#4a2070;margin:16px 0 8px;text-transform:uppercase;">6. Encerramento</h2>
<p style="font-size:11px;line-height:1.5;color:#0f172a;">Responsável comercial: {{vendedor_nome}} ({{vendedor_email}}). Foro: {{cidade_estado}}.</p>
<p style="font-size:11px;line-height:1.45;color:#0f172a;margin-top:24px;">{{cidade_estado}}, {{data_hoje}}.</p>
HTML;
    }
}
