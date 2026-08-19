<?php

namespace Tests\Unit\PromptValidation;

use App\Services\AiOutputParser;
use PHPUnit\Framework\TestCase;

class EnvFenceTest extends TestCase
{
    private AiOutputParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AiOutputParser;
    }

    public function test_env_example_fence_matches_via_prefix(): void
    {
        $content = "```env.example\nAPP_KEY=base64:x\nDB_PASSWORD=secret\nAPP_URL=https://x\nSESSION_DOMAIN=.x\nVAR_A=1\nVAR_B=2\nVAR_C=3\nVAR_D=4\nVAR_E=5\n```";
        $fence = $this->parser->extractCodeFence($content, 'env')
            ?? $this->parser->extractCodeFencePrefix($content, 'env');
        $this->assertNotNull($fence);
        $this->assertStringContainsString('APP_KEY', $fence);
    }

    public function test_plain_env_fence_still_matches(): void
    {
        $content = "```env\nAPP_KEY=x\nDB_PASSWORD=y\nAPP_URL=z\nSESSION_DOMAIN=d\nA=1\nB=2\nC=3\nD=4\nE=5\n```";
        $fence = $this->parser->extractCodeFence($content, 'env');
        $this->assertNotNull($fence);
    }

    public function test_env_vars_extracted(): void
    {
        $content = "```env.example\nAPP_KEY=a\nDB_PASSWORD=b\nAPP_URL=c\nSESSION_DOMAIN=d\nVAR_E=1\nVAR_F=2\nVAR_G=3\nVAR_H=4\nVAR_I=5\n```";
        $fence = $this->parser->extractCodeFencePrefix($content, 'env');
        $vars = $this->parser->extractEnvVars($fence);
        $this->assertGreaterThanOrEqual(8, count($vars));
    }
}