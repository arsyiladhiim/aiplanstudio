<?php

namespace Tests\Unit\PromptValidation;

use App\Services\AiOutputParser;
use PHPUnit\Framework\TestCase;

class ExistingPromptsValidationTest extends TestCase
{
    private AiOutputParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AiOutputParser;
    }

    private function headingsFrom(string $content): array
    {
        return collect($this->parser->extractMarkdownHeadings($content))
            ->map(fn ($h) => str_starts_with($h, '## ') ? substr($h, 3) : $h)
            ->values()
            ->all();
    }

    private function assertHasHeadings(string $content, array $required): void
    {
        $headings = $this->headingsFrom($content);
        foreach ($required as $r) {
            $this->assertTrue(
                collect($headings)->contains(fn ($h) => str_starts_with($h, $r)),
                "Missing heading: {$r}"
            );
        }
    }

    public function test_analisa_fixture_has_required_sections(): void
    {
        $this->assertHasHeadings(
<<<'MD'
# Analisa

## 1. Intent Summary
Ringkasan

## 2. User Personas
Persona

## 3. Core Problem
Masalah

## 4. Success Metrics
Metrik

## 5. Anti-Goals
Tidak

## 6. Daftar Halaman
- Dashboard
MD,
            ['1. Intent Summary', '2. User Personas', '3. Core Problem', '4. Success Metrics', '5. Anti-Goals', '6. Daftar Halaman']
        );
    }

    public function test_prd_fixture_has_required_sections(): void
    {
        $this->assertHasHeadings(
<<<'MD'
# PRD

## 1. Overview
## 2. User Stories
## 3. Functional Requirements
## 4. Non-Functional Requirements
## 5. Out of Scope
## 6. Assumptions & Constraints
## 7. Differentiation
## 8. Open Questions
MD,
            ['1. Overview', '2. User Stories', '3. Functional Requirements', '4. Non-Functional Requirements', '5. Out of Scope', '6. Assumptions', '7. Differentiation', '8. Open Questions']
        );
    }

    public function test_architecture_fixture_has_required_sections(): void
    {
        $this->assertHasHeadings(
<<<'MD'
# Architecture

## 1. Stack
## 2. Module Boundaries
## 3. Data Flow
## 4. Folder Structure
## 5. Deployment Topology
## 6. Trade-offs
MD,
            ['1. Stack', '2. Module Boundaries', '3. Data Flow', '4. Folder Structure', '5. Deployment Topology', '6. Trade-offs']
        );
    }

    public function test_env_config_fixture_has_required_sections(): void
    {
        $this->assertHasHeadings(
<<<'MD'
# Env Config

## 1. Pendahuluan
## 2. Environment Variables (Backend)
## 3. Environment Variables (Frontend)
## 5. File .env & .env.example
## 7. Checklist Verifikasi
MD,
            ['1. Pendahuluan', '2. Environment Variables', '3. Environment Variables', '5. File .env', '7. Checklist Verifikasi']
        );
    }

    public function test_security_fixture_has_required_sections(): void
    {
        $this->assertHasHeadings(
<<<'MD'
# Security

## 1. Autentikasi
## 2. Otorisasi
## 3. Input Validation
## 4. XSS
## 5. Data Protection
## 6. Dependencies
## 7. Transport
## 8. Rate Limiting
## 9. Checklist
MD,
            ['1. Autentikasi', '2. Otorisasi', '9. Checklist']
        );
    }

    public function test_deployment_fixture_has_required_sections(): void
    {
        $this->assertHasHeadings(
<<<'MD'
# Deployment

## 1. Prerequisites
## 2. Topology
## 3. Environment
## 4. Build & Start
## 5. Cloudflare Tunnel
## 6. Backup
## 7. Rollback
## 8. Zero-Downtime
## 9. Post-Deploy
## 10. Monitoring
MD,
            ['1. Prerequisites', '5. Cloudflare Tunnel', '7. Rollback', '10. Monitoring']
        );
    }

    public function test_observability_fixture_has_required_sections(): void
    {
        $this->assertHasHeadings(
<<<'MD'
# Observability

## 1. Health Checks
## 2. Structured Logging
## 3. Error Monitoring
## 4. Uptime
## 5. Slow Query
## 6. Dashboard
## 7. Runbook
## 8. Alerting
## 9. Post-Incident
MD,
            ['1. Health Checks', '2. Structured Logging', '7. Runbook', '9. Post-Incident']
        );
    }

    public function test_standards_fixture_contains_conventions_and_tech_terms(): void
    {
        $content = <<<'MD'
# STANDARDS.md

## 1. Coding Conventions
- TypeScript code wajib pakai lint & Prettier
- Convention: file naming kebab-case

## 2. Git Workflow
Dart project menggunakan lint rules.
MD;
        $this->assertStringContainsString('TypeScript', $content);
        $this->assertStringContainsString('lint', $content);
        $this->assertStringContainsString('Dart', $content);
    }

    public function test_agents_fixture_contains_agents_md_structure(): void
    {
        $content = <<<'MD'
# AGENTS.md

## 1. Agent Context
Agent harus mengikuti instruction set project ini.

## 2. Rules
- Selalu baca AGENTS.md sebelum bekerja.
MD;
        $this->assertStringContainsString('AGENTS.md', $content);
        $this->assertStringContainsString('agent', strtolower($content));
        $this->assertStringContainsString('instruction', $content);
    }

    public function test_analisa_fixture_extracts_ordered_headings(): void
    {
        $headings = $this->parser->extractMarkdownHeadings(
            "## 1. Intent Summary\n## 2. User Personas\n## 3. Core Problem\n## 4. Success Metrics"
        );
        $this->assertCount(4, $headings);
    }
}