<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Valida URLs externas antes de usá-las como destino de requisições HTTP
 * feitas pelo servidor (SSRF — Server-Side Request Forgery).
 *
 * Regras:
 * - Apenas https:// e http:// são aceitos.
 * - O host não pode resolver para endereços IP privados/reservados (RFC 1918,
 *   loopback, link-local, CGNAT, IPv6 loopback/link-local).
 * - Hostnames comuns de metadata de cloud (169.254.169.254, etc.) são bloqueados
 *   mesmo que não resolvam no ambiente local.
 */
class SafeExternalUrl
{
    /** Blocos CIDR privados / reservados em formato compacto para comparação rápida. */
    private const BLOCKED_PREFIXES = [
        '10.',
        '172.16.', '172.17.', '172.18.', '172.19.',
        '172.20.', '172.21.', '172.22.', '172.23.',
        '172.24.', '172.25.', '172.26.', '172.27.',
        '172.28.', '172.29.', '172.30.', '172.31.',
        '192.168.',
        '127.',
        '0.',
        '169.254.',  // link-local / metadata AWS
        '100.64.',   // CGNAT
        '::1',       // IPv6 loopback
        'fe80:',     // IPv6 link-local
        'fc00:', 'fd', // IPv6 ULA
    ];

    /** Hostnames conhecidos de serviço de metadados de cloud. */
    private const BLOCKED_HOSTS = [
        'metadata.google.internal',
        'metadata.internal',
    ];

    /**
     * Valida e normaliza a URL. Retorna a URL sem barra final se válida.
     *
     * @throws \InvalidArgumentException se a URL for insegura ou malformada.
     */
    public static function validate(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new \InvalidArgumentException('URL não pode ser vazia.');
        }

        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host'])) {
            throw new \InvalidArgumentException("URL inválida ou sem host: {$url}");
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException("Esquema não permitido '{$scheme}' em URL externa. Use https://.");
        }

        $host = strtolower($parsed['host']);

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new \InvalidArgumentException("Host bloqueado por segurança: {$host}");
        }

        // Valida IP literal ou resolve hostname.
        $ip = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? $host
            : (gethostbyname($host) ?: null);

        if ($ip !== null) {
            self::assertNotPrivateIp($ip, $host);
        }

        return rtrim($url, '/');
    }

    /**
     * Mesma validação, mas retorna null em vez de lançar exceção.
     */
    public static function tryValidate(string $url): ?string
    {
        try {
            return self::validate($url);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private static function assertNotPrivateIp(string $ip, string $originalHost): void
    {
        // ip2long cobre IPv4; para IPv6 usamos prefixos string.
        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                throw new \InvalidArgumentException(
                    "Host '{$originalHost}' resolve para endereço privado/reservado ({$ip}) — não permitido."
                );
            }
        }

        // Verificação adicional via FILTER_FLAG_NO_PRIV_RANGE + FILTER_FLAG_NO_RES_RANGE para IPv4.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \InvalidArgumentException(
                    "Host '{$originalHost}' resolve para endereço privado/reservado ({$ip}) — não permitido."
                );
            }
        }
    }
}
