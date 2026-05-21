<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Config;

use Opora\Core\Config\SecurityHeadersConfig;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersConfigTest extends TestCase
{
    public function test_constructor_stores_all_values(): void
    {
        $securityHeadersConfig = new SecurityHeadersConfig(
            defaultSrc: ["'self'", 'https://fonts.googleapis.com'],
            scriptSrc: ["'self'", "'unsafe-inline'"],
            styleSrc: ["'self'", "'unsafe-inline'"],
            imgSrc: ["'self'", 'data:', 'https:'],
            connectSrc: ["'self'"],
            fontSrc: ["'self'", 'https://fonts.gstatic.com'],
            frameSrc: ["'none'"],
            frameOptions: 'DENY',
            contentTypeOptions: 'nosniff',
            referrerPolicy: 'strict-origin-when-cross-origin',
            permissionsPolicy: [
                'geolocation=()',
                'microphone=()',
                'camera=()',
                'payment=()',
            ],
            upgradeInsecureRequests: true,
        );

        $headers = $securityHeadersConfig->toHeaderArray();

        self::assertSame('DENY', $securityHeadersConfig->frameOptions);
        self::assertSame('nosniff', $securityHeadersConfig->contentTypeOptions);
        self::assertSame('strict-origin-when-cross-origin', $securityHeadersConfig->referrerPolicy);
        self::assertTrue($securityHeadersConfig->upgradeInsecureRequests);
        self::assertArrayHasKey('Content-Security-Policy', $headers);
        self::assertArrayHasKey('X-Frame-Options', $headers);
        self::assertArrayHasKey('X-Content-Type-Options', $headers);
        self::assertArrayHasKey('Referrer-Policy', $headers);
        self::assertArrayHasKey('Permissions-Policy', $headers);
    }

    public function test_defaults_returns_secure_config(): void
    {
        $securityHeadersConfig = SecurityHeadersConfig::defaults();

        $headers = $securityHeadersConfig->toHeaderArray();

        self::assertSame('DENY', $headers['X-Frame-Options']);
        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
        self::assertStringContainsString("default-src 'self'", $headers['Content-Security-Policy']);
        self::assertStringContainsString("script-src 'self'", $headers['Content-Security-Policy']);
        self::assertStringContainsString("style-src 'self'", $headers['Content-Security-Policy']);
        self::assertStringContainsString("img-src 'self' data: https:", $headers['Content-Security-Policy']);
        self::assertStringContainsString("connect-src 'self'", $headers['Content-Security-Policy']);
        self::assertStringContainsString("font-src 'self'", $headers['Content-Security-Policy']);
        self::assertStringContainsString("frame-src 'none'", $headers['Content-Security-Policy']);
        self::assertStringContainsString('upgrade-insecure-requests', $headers['Content-Security-Policy']);
    }

    public function test_defaults_in_development_disables_upgrade_insecure_requests(): void
    {
        $securityHeadersConfig = SecurityHeadersConfig::defaults(isDevelopment: true);

        $csp = $securityHeadersConfig->toHeaderArray()['Content-Security-Policy'];

        self::assertStringNotContainsString('upgrade-insecure-requests', $csp);
    }

    public function test_toHeaderArray_returns_all_required_headers(): void
    {
        $securityHeadersConfig = SecurityHeadersConfig::defaults();
        $headers = $securityHeadersConfig->toHeaderArray();

        self::assertCount(5, $headers);
        self::assertSame([
            'Content-Security-Policy',
            'X-Frame-Options',
            'X-Content-Type-Options',
            'Referrer-Policy',
            'Permissions-Policy',
        ], \array_keys($headers));
    }

    public function test_custom_permissions_policy_overrides_defaults(): void
    {
        $securityHeadersConfig = new SecurityHeadersConfig(
            permissionsPolicy: ['accelerometer=()', 'gyroscope=()'],
        );

        $headers = $securityHeadersConfig->toHeaderArray();

        self::assertStringContainsString('accelerometer=()', $headers['Permissions-Policy']);
        self::assertStringContainsString('gyroscope=()', $headers['Permissions-Policy']);
        self::assertStringNotContainsString('geolocation=()', $headers['Permissions-Policy']);
    }

    public function test_custom_csp_directive_overrides_default_src(): void
    {
        $securityHeadersConfig = new SecurityHeadersConfig(
            defaultSrc: ["'self'", 'https://example.com'],
        );

        $csp = $securityHeadersConfig->toHeaderArray()['Content-Security-Policy'];

        self::assertStringContainsString("default-src 'self' https://example.com", $csp);
    }

    public function test_empty_csp_directive_excludes_it(): void
    {
        $securityHeadersConfig = new SecurityHeadersConfig(
            defaultSrc: [],
        );

        $csp = $securityHeadersConfig->toHeaderArray()['Content-Security-Policy'];

        self::assertStringNotContainsString('default-src', $csp);
    }
}
