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
<h1 style="font-size:15px;color:#4a2070;margin:0 0 14px;text-align:center;text-transform:uppercase;letter-spacing:0.04em;">Contrato de prestação de serviços de consultoria Talents</h1>

<p style="font-size:11px;line-height:1.55;color:#0f172a;"><strong>CONTRATANTE:</strong> {{cliente_nome}}, pessoa jurídica de direito privado, inscrita no CNPJ sob o nº {{cliente_cnpj}}, com sede em {{cliente_endereco}}, neste ato representada por {{cliente_representante}}, doravante denominada <strong>CONTRATANTE</strong>.</p>

<p style="font-size:11px;line-height:1.55;color:#0f172a;"><strong>CONTRATADA:</strong> {{empresa_nome}}, CNPJ nº {{empresa_cnpj}}, com sede em {{empresa_endereco}}, e-mail {{empresa_email}}, telefone {{empresa_telefone}}, {{empresa_representacao}}, doravante denominada <strong>CONTRATADA</strong>.</p>

<h2 style="font-size:12px;color:#4a2070;margin:18px 0 8px;text-transform:uppercase;letter-spacing:0.06em;">Cláusula primeira – Objeto</h2>
<p style="font-size:11px;line-height:1.55;color:#0f172a;">O presente contrato tem como objeto a prestação de serviços de consultoria de gestão de pessoas, nos termos da <strong>Proposta Comercial nº {{proposta_codigo}}</strong> (emitida em {{proposta_emitida_em}}, com referência de validade conforme parametrização comercial), incluindo, conforme contratado, dentre outras entregas da natureza abaixo:</p>
<ul style="margin:8px 0 12px;padding-left:18px;font-size:11px;line-height:1.5;color:#0f172a;">
<li>Pesquisa de satisfação dos colaboradores;</li>
<li>Aplicação de mapeamento comportamental;</li>
<li>Entrega de relatório de mapeamento comportamental individual completo;</li>
<li>Análise do relatório de mapeamento comportamental individual (conforme plano contratado);</li>
<li>Devolutiva individual ou em grupo (conforme plano contratado);</li>
<li>Engenharia de cargos e relatório Matcher (conforme plano contratado).</li>
</ul>
<p style="font-size:11px;line-height:1.5;color:#64748b;">A discriminação contratual efetiva, valores e totais — somente o que constar na proposta:</p>
{{servicos_detalhada_html}}
<p style="font-size:11px;line-height:1.55;color:#0f172a;margin-top:10px;"><strong>Honorário total:</strong> {{total_reais}} (<em>{{total_extenso}}</em>). Colaboradores considerados na proposta (quando aplicável): <strong>{{numero_funcionarios}}</strong>.</p>
<p style="font-size:11px;line-height:1.45;color:#64748b;">Lista resumida dos serviços contratados: {{servicos_rotulos}}.</p>

<h2 style="font-size:12px;color:#4a2070;margin:18px 0 8px;text-transform:uppercase;letter-spacing:0.06em;">Cláusula segunda – Prazo</h2>
<p style="font-size:11px;line-height:1.55;color:#0f172a;">Este contrato tem início na data de assinatura e permanecerá vigente até a conclusão dos serviços contratados. Referência de prazo operacional (dias), quando aplicável à condição comercial: <strong>{{prazo_dias}}</strong>.</p>

<h2 style="font-size:12px;color:#4a2070;margin:18px 0 8px;text-transform:uppercase;letter-spacing:0.06em;">Cláusula terceira – Valor e forma de pagamento</h2>
<p style="font-size:11px;line-height:1.55;color:#0f172a;">A CONTRATANTE compromete-se a pagar à CONTRATADA os valores conforme plano e tabela da proposta acima.</p>
<p style="font-size:11px;line-height:1.55;color:#0f172a;"><strong>Forma de pagamento e condições:</strong></p>
<p style="font-size:11px;line-height:1.55;color:#0f172a;">{{forma_pagamento}}</p>
<p style="font-size:11px;line-height:1.55;color:#0f172a;"><strong>Dados para pagamento (referência):</strong> e-mail {{empresa_email}} — CNPJ {{empresa_cnpj}}.</p>
<p style="font-size:11px;line-height:1.55;color:#0f172a;">Os valores e condições apresentados neste instrumento têm validade de <strong>{{validade_proposta_dias}}</strong> dias a contar da data de envio à CONTRATANTE (parâmetro comercial alinhado ao PDF da proposta; validade calculada até {{validade_data}}). Após esse prazo, poderão ser revistos.</p>
<p style="font-size:11px;line-height:1.55;color:#0f172a;">§ 1º Em caso de inadimplência, os valores serão acrescidos de multa de 5% e juros de 1% ao mês sobre o saldo devedor.</p>

<h2 style="font-size:12px;color:#4a2070;margin:18px 0 8px;text-transform:uppercase;letter-spacing:0.06em;">Cláusula quarta – Obrigações da contratada</h2>
<p style="font-size:11px;line-height:1.55;color:#0f172a;">A CONTRATADA compromete-se a: (1) aplicar os mapeamentos comportamentais conforme os serviços contratados e as metodologias e ferramentas utilizadas; (2) entregar os relatórios de mapeamentos comportamentais individuais completos; (3) analisar os mapeamentos comportamentais individuais conforme os serviços contratados; (4) realizar as devolutivas individuais ou em grupo conforme os serviços contratados; (5) garantir confidencialidade sobre as informações coletadas; (6) reaplicar o mapeamento caso necessário, conforme frequência contratada (por exemplo, 6 meses ou 1 ano), para fins de acompanhar a evolução comportamental de cada colaborador.</p>

<h2 style="font-size:12px;color:#4a2070;margin:18px 0 8px;text-transform:uppercase;letter-spacing:0.06em;">Cláusula quinta – Obrigações da contratante</h2>
<p style="font-size:11px;line-height:1.55;color:#0f172a;">A CONTRATANTE compromete-se a: (1) fornecer dados corretos e completos dos participantes avaliados; (2) efetuar o pagamento conforme as condições acordadas; (3) não compartilhar ou distribuir os relatórios com terceiros sem autorização da CONTRATADA.</p>

<h2 style="font-size:12px;color:#4a2070;margin:18px 0 8px;text-transform:uppercase;letter-spacing:0.06em;">Cláusula sexta – Confidencialidade</h2>
<p style="font-size:11px;line-height:1.55;color:#0f172a;">Ambas as partes obrigam-se a manter em sigilo todas as informações a que tiverem acesso em razão deste contrato, inclusive relatórios e perfis comportamentais dos avaliados.</p>

<h2 style="font-size:12px;color:#4a2070;margin:18px 0 8px;text-transform:uppercase;letter-spacing:0.06em;">Cláusula sétima – Propriedade intelectual</h2>
<p style="font-size:11px;line-height:1.55;color:#0f172a;">Todos os materiais, metodologias, relatórios e documentos utilizados ou entregues pela CONTRATADA são de sua exclusiva propriedade intelectual, sendo vedada a cópia, reprodução ou utilização para outros fins sem autorização expressa.</p>

<h2 style="font-size:12px;color:#4a2070;margin:18px 0 8px;text-transform:uppercase;letter-spacing:0.06em;">Cláusula oitava – Rescisão</h2>
<p style="font-size:11px;line-height:1.55;color:#0f172a;">O contrato poderá ser rescindido por qualquer das partes mediante aviso prévio de 15 dias, desde que não haja pendência de serviços ou pagamentos. Em caso de rescisão, serão devidos os valores proporcionais aos serviços efetivamente prestados.</p>

<h2 style="font-size:12px;color:#4a2070;margin:18px 0 8px;text-transform:uppercase;letter-spacing:0.06em;">Cláusula nona – Foro</h2>
<p style="font-size:11px;line-height:1.55;color:#0f172a;">Para dirimir quaisquer dúvidas oriundas deste contrato, as partes elegem o foro da comarca de {{foro_comarca}}, com exclusão de qualquer outro, por mais privilegiado que seja.</p>

<p style="font-size:11px;line-height:1.55;color:#64748b;margin-top:14px;">Indicação / origem do lead (quando houver): {{proposta_indicacao}}. Observações da proposta: {{proposta_observacoes}}. Comissão comercial (quando houver): {{comissao_percent}}% ({{comissao_reais}}). Responsável comercial: {{vendedor_nome}} ({{vendedor_email}}).</p>

<p style="font-size:11px;line-height:1.55;color:#0f172a;margin-top:28px;text-align:center;">________________________________________<br /><strong>CONTRATANTE</strong></p>
<p style="font-size:11px;line-height:1.55;color:#0f172a;margin-top:28px;text-align:center;">________________________________________<br /><strong>CONTRATADA — {{empresa_nome}}</strong></p>
<p style="font-size:11px;line-height:1.45;color:#0f172a;margin-top:16px;">{{cidade_estado}}, {{data_hoje}}.</p>
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
