<?php

namespace Tests\Unit\PromptValidation;

use App\Services\AiJsonParser;
use PHPUnit\Framework\TestCase;

class AiJsonRepairTest extends TestCase
{
    private AiJsonParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AiJsonParser;
    }

    public function test_null_slipped_key_repaired(): void
    {
        $json = '[ { "a": 1, "total_conversions":   }, { "b": 2 } ]';
        $res = $this->parser->tryJsonDecode($json);
        $this->assertIsArray($res);
        $this->assertCount(2, $res);
        $this->assertArrayHasKey('a', $res[0]);
    }

    public function test_trailing_comma_repaired(): void
    {
        $json = '[ { "a": 1, }, { "b": 2, } ]';
        $res = $this->parser->tryJsonDecode($json);
        $this->assertIsArray($res);
        $this->assertCount(2, $res);
    }

    public function test_truncated_mid_string_recovered(): void
    {
        $json = '[ { "resource": "auth", "method": "GET", "path": "/users", "description": "list users yang trun';
        $res = $this->parser->tryJsonDecode($json);
        $this->assertIsArray($res);
        $this->assertCount(1, $res);
    }

    public function test_missing_closing_bracket_appended(): void
    {
        $json = '[ { "a": 1 }, { "b": 2 }';
        $res = $this->parser->tryJsonDecode($json);
        $this->assertIsArray($res);
        $this->assertCount(2, $res);
    }
}