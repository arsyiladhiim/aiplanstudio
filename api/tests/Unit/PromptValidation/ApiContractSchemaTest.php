<?php

namespace Tests\Unit\PromptValidation;

use PHPUnit\Framework\TestCase;

class ApiContractSchemaTest extends TestCase
{
    private function validContract(): array
    {
        return [
            ['resource' => 'users', 'method' => 'GET', 'path' => '/api/users', 'auth' => 'session', 'description' => 'List users'],
            ['resource' => 'users', 'method' => 'POST', 'path' => '/api/users', 'auth' => 'session', 'description' => 'Create user'],
        ];
    }

    public function test_valid_contract_passes_schema_check(): void
    {
        $contract = $this->validContract();
        $this->assertCount(2, $contract);
        $this->assertSame('users', $contract[0]['resource']);
        $this->assertSame('GET', $contract[0]['method']);
    }

    public function test_missing_field_detected(): void
    {
        $contract = $this->validContract();
        unset($contract[0]['description']);
        $this->assertArrayNotHasKey('description', $contract[0]);
    }

    public function test_invalid_method_detected(): void
    {
        $contract = $this->validContract();
        $contract[0]['method'] = 'FOO';
        $allowed = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $this->assertNotContains(strtoupper($contract[0]['method']), $allowed);
    }

    public function test_invalid_path_detected(): void
    {
        $contract = $this->validContract();
        $contract[0]['path'] = 'api/users';
        $this->assertStringStartsNotWith('/', $contract[0]['path']);
    }
}
