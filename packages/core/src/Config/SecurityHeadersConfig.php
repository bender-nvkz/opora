<?php

declare(strict_types=1);

namespace Opora\Core\Config;

/**
 * Типизированный конфиг security-заголовков ответа.
 *
 * Собирает заголовки через toHeaderArray().
 * defaults() даёт A+ на securityheaders.com.
 * upgradeInsecureRequests отключается в development-окружении.
 *
 * @api
 */
final readonly class SecurityHeadersConfig
{
    /**
     * @param list<string>     $defaultSrc              CSP: default-src директивы
     * @param list<string>     $scriptSrc               CSP: script-src директивы
     * @param list<string>     $styleSrc                CSP: style-src директивы
     * @param list<string>     $imgSrc                  CSP: img-src директивы
     * @param list<string>     $connectSrc              CSP: connect-src директивы
     * @param list<string>     $fontSrc                 CSP: font-src директивы
     * @param list<string>     $frameSrc                CSP: frame-src директивы
     * @param non-empty-string $frameOptions            X-Frame-Options (DENY | SAMEORIGIN)
     * @param non-empty-string $contentTypeOptions      X-Content-Type-Options (nosniff)
     * @param non-empty-string $referrerPolicy          Referrer-Policy
     * @param list<string>     $permissionsPolicy       Permissions-Policy директивы
     * @param bool             $upgradeInsecureRequests CSP: upgrade-insecure-requests (false в dev)
     */
    public function __construct(
        public array $defaultSrc = ["'self'"],
        public array $scriptSrc = ["'self'"],
        public array $styleSrc = ["'self'"],
        public array $imgSrc = ["'self'", 'data:', 'https:'],
        public array $connectSrc = ["'self'"],
        public array $fontSrc = ["'self'"],
        public array $frameSrc = ["'none'"],
        public string $frameOptions = 'DENY',
        public string $contentTypeOptions = 'nosniff',
        public string $referrerPolicy = 'strict-origin-when-cross-origin',
        public array $permissionsPolicy = [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
        ],
        public bool $upgradeInsecureRequests = true,
    ) {
    }

    /**
     * Конфиг с дефолтами для A+ на securityheaders.com.
     *
     * @param bool $isDevelopment если true — upgrade-insecure-requests отключается
     */
    public static function defaults(bool $isDevelopment = false): self
    {
        return new self(
            upgradeInsecureRequests: !$isDevelopment,
        );
    }

    /**
     * Собирает заголовки в формате header-name → value.
     *
     * @return non-empty-array<string, non-empty-string>
     */
    public function toHeaderArray(): array
    {
        $csp = $this->buildCsp();

        $permissions = $this->permissionsPolicy !== []
            ? \implode(', ', $this->permissionsPolicy)
            : 'geolocation=(),microphone=(),camera=()';

        return [
            'Content-Security-Policy' => $csp,
            'X-Frame-Options' => $this->frameOptions,
            'X-Content-Type-Options' => $this->contentTypeOptions,
            'Referrer-Policy' => $this->referrerPolicy,
            'Permissions-Policy' => $permissions,
        ];
    }

    /**
     * Строит Content-Security-Policy строку.
     *
     * @return non-empty-string
     */
    private function buildCsp(): string
    {
        $directives = [];

        if ($this->defaultSrc !== []) {
            $directives[] = 'default-src ' . \implode(' ', $this->defaultSrc);
        }
        if ($this->scriptSrc !== []) {
            $directives[] = 'script-src ' . \implode(' ', $this->scriptSrc);
        }
        if ($this->styleSrc !== []) {
            $directives[] = 'style-src ' . \implode(' ', $this->styleSrc);
        }
        if ($this->imgSrc !== []) {
            $directives[] = 'img-src ' . \implode(' ', $this->imgSrc);
        }
        if ($this->connectSrc !== []) {
            $directives[] = 'connect-src ' . \implode(' ', $this->connectSrc);
        }
        if ($this->fontSrc !== []) {
            $directives[] = 'font-src ' . \implode(' ', $this->fontSrc);
        }
        if ($this->frameSrc !== []) {
            $directives[] = 'frame-src ' . \implode(' ', $this->frameSrc);
        }
        if ($this->upgradeInsecureRequests) {
            $directives[] = 'upgrade-insecure-requests';
        }

        /** @var non-empty-string */
        return \implode('; ', $directives);
    }
}
