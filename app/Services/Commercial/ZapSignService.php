<?php

namespace App\Services\Commercial;

use App\Models\CommercialSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZapSignService
{
    /**
     * Cria documento na ZapSign a partir do PDF em base64 (sem prefixo data:).
     *
     * @param  array<int, array<string, mixed>>  $signers
     * @return array{token: string, status: string, signers: array<int, array<string, mixed>>}
     */
    public function createDocumentFromPdfBase64(
        string $documentName,
        string $base64Pdf,
        array $signers,
        ?string $externalId = null,
    ): array {
        $settings = CommercialSetting::current();
        $token = trim((string) ($settings->zapsign_api_token ?? ''));
        if ($token === '') {
            throw new RuntimeException('Token da API ZapSign não configurado.');
        }

        $baseUrl = rtrim((string) ($settings->zapsign_api_base_url ?: 'https://api.zapsign.com.br/api/v1'), '/');
        $url = $baseUrl.'/docs/';
        $autoEmail = (bool) ($settings->zapsign_send_automatic_email ?? true);

        $signerPayloads = [];
        foreach ($signers as $signer) {
            $row = [
                'name' => (string) ($signer['name'] ?? ''),
                'email' => (string) ($signer['email'] ?? ''),
                'auth_mode' => (string) ($signer['auth_mode'] ?? 'assinaturaTela-tokenEmail'),
                'send_automatic_email' => (bool) ($signer['send_automatic_email'] ?? $autoEmail),
            ];
            if (! empty($signer['phone_country'])) {
                $row['phone_country'] = (string) $signer['phone_country'];
            }
            if (! empty($signer['phone_number'])) {
                $row['phone_number'] = (string) $signer['phone_number'];
            }
            if (isset($signer['order_group'])) {
                $row['order_group'] = (int) $signer['order_group'];
            }
            $signerPayloads[] = $row;
        }

        $body = [
            'name' => mb_substr($documentName, 0, 255),
            'base64_pdf' => $base64Pdf,
            'lang' => 'pt-br',
            'signature_order_active' => count($signerPayloads) > 1,
            'signers' => $signerPayloads,
        ];
        if ($externalId !== null && $externalId !== '') {
            $body['external_id'] = $externalId;
        }

        /** @var Response $response */
        $response = Http::timeout(120)
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->post($url, $body);

        if (! $response->successful()) {
            $msg = $response->json('message') ?? $response->json('error') ?? $response->body();
            throw new RuntimeException(
                is_string($msg) && $msg !== ''
                    ? 'ZapSign: '.$msg
                    : 'ZapSign retornou erro HTTP '.$response->status().'.'
            );
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['token'])) {
            throw new RuntimeException('Resposta inválida da ZapSign.');
        }

        /** @var array<int, array<string, mixed>> $signersOut */
        $signersOut = is_array($data['signers'] ?? null) ? $data['signers'] : [];

        return [
            'token' => (string) $data['token'],
            'status' => (string) ($data['status'] ?? 'pending'),
            'signers' => $signersOut,
        ];
    }
}
