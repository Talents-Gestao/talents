<?php

namespace App\Concerns;

use Illuminate\Contracts\Encryption\DecryptException;

trait SafelyDecryptsAttributes
{
    /**
     * Lê o atributo com cast encrypted sem lançar se APP_KEY/ciphertext forem incompatíveis.
     *
     * @param  mixed  $fallback  Valor devolvido se vazio no banco ou se a descriptografia falhar.
     */
    public function safeDecrypt(string $attribute, mixed $fallback = null): mixed
    {
        $raw = $this->getRawOriginal($attribute);
        $hasOriginal = $raw !== null && $raw !== '';
        // Modelo novo ou atributo alterado em memória: o ciphertext fica em $attributes,
        // mas getRawOriginal() ainda está vazio até syncOriginal()/save().
        $hasInAttributes = array_key_exists($attribute, $this->attributes);

        if (! $hasOriginal && ! $hasInAttributes) {
            return $fallback;
        }

        try {
            return $this->getAttribute($attribute);
        } catch (DecryptException) {
            return $fallback;
        }
    }

    public function hasStoredEncrypted(string $attribute): bool
    {
        return filled($this->getRawOriginal($attribute));
    }

    /**
     * true se não há ciphertext ou se a descriptografia é possível com a APP_KEY atual.
     */
    public function canDecrypt(string $attribute): bool
    {
        if (! $this->hasStoredEncrypted($attribute)) {
            return true;
        }

        $sentinel = new \stdClass;

        return $this->safeDecrypt($attribute, $sentinel) !== $sentinel;
    }
}
