<?php

namespace MM\Meros\Foundations;

use Illuminate\Support\Traits\Macroable;

/**
 * Minimal stand-in for Laravel's Vite helper. It holds the CSP nonce and tag
 * attributes that packages such as Livewire set; it does not resolve a manifest.
 */
class Vite
{
    use Macroable;

    protected ?string $nonce = null;

    protected array $scriptAttributes = [];

    protected array $styleAttributes = [];

    public function cspNonce(): ?string
    {
        return $this->nonce;
    }

    public function useCspNonce(?string $nonce = null): string
    {
        return $this->nonce = $nonce ?? bin2hex(random_bytes(20));
    }

    public function useScriptTagAttributes(array|callable $attributes): static
    {
        $this->scriptAttributes = array_merge($this->scriptAttributes, is_callable($attributes) ? [] : $attributes);

        return $this;
    }

    public function useStyleTagAttributes(array|callable $attributes): static
    {
        $this->styleAttributes = array_merge($this->styleAttributes, is_callable($attributes) ? [] : $attributes);

        return $this;
    }
}
