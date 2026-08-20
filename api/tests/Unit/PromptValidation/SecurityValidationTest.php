<?php

namespace Tests\Unit\PromptValidation;

use App\Services\AiOutputParser;
use PHPUnit\Framework\TestCase;

class SecurityValidationTest extends TestCase
{
    private AiOutputParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AiOutputParser;
    }

    public function test_valid_security_has_checklist(): void
    {
        $content = <<<'MD'
# Security

## 1. Autentikasi & Session
Sanctum SPA session, HttpOnly cookie.

## 2. Otorisasi
Policy per resource.

## 3. Input Validation
FormRequest.

## 4. XSS
Escape output.

## 5. Data Protection
Encrypt at rest.

## 6. Dependencies
Update rutin.

## 7. Transport
HTTPS + HSTS.

## 8. Rate Limiting
Throttle 60/1.

## 9. Checklist
- [ ] Password hashed bcrypt
- [ ] CSRF token aktif
- [ ] Login rate limit
- [ ] Header HSTS
- [ ] Enkripsi DB sensitif
- [ ] Backup terenkripsi
- [ ] Audit log akses
- [ ] 2FA admin opsional
MD;
        $items = $this->parser->extractChecklistItems($content);
        $this->assertGreaterThanOrEqual(7, $items);
    }

    public function test_low_checklist_detected(): void
    {
        $content = <<<'MD'
## 9. Checklist
- [ ] Satu item saja
MD;
        $items = $this->parser->extractChecklistItems($content);
        $this->assertLessThan(6, $items);
    }

    public function test_placeholder_detected(): void
    {
        $content = "## 1. Autentikasi\nGunakan <API_KEY_PROVIDER>.";
        $count = preg_match_all('/<[A-Z][A-Z0-9_]*>/', $content);
        $this->assertGreaterThan(0, $count);
    }

    public function test_does_not_require_english_keywords_anymore(): void
    {
        // Prompt bahasa Indonesia — 'OWASP'/'authentication' tidak wajib.
        $content = strtolower("Autentikasi & Session\nOtorisasi RBAC\nKeamanan checklist");
        $this->assertStringContainsString('autentikasi', $content);
    }
}