<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'expires_at'])]
class ProjectApiToken extends Model
{
    protected $table = 'aiplanstudio_project.project_api_tokens';

    protected $hidden = ['token_hash', 'secret_hash', 'secret_salt'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public static function generate(Project $project, string $name, ?\DateTime $expiresAt = null): array
    {
        $rawToken = bin2hex(random_bytes(32));
        $rawSecret = bin2hex(random_bytes(32));
        $salt = bin2hex(random_bytes(16));

        $token = new self;
        $token->name = $name;
        $token->token_hash = hash('sha256', $rawToken);
        $token->secret_salt = $salt;
        $token->secret_hash = hash_hmac('sha256', $rawSecret, $salt);
        $token->expires_at = $expiresAt ?? now()->addDays(90);
        $token->project()->associate($project);
        $token->save();

        return [
            'token' => $rawToken,
            'secret' => $rawSecret,
            'model' => $token,
        ];
    }

    public function verifySignature(string $timestamp, string $body, string $providedSignature): bool
    {
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }
        $secret = $this->revealSecret();
        if ($secret === null) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return hash_equals($expected, $providedSignature);
    }

    private function revealSecret(): ?string
    {
        $secret = request()->attributes->get('project_token_secret');

        return is_string($secret) ? $secret : null;
    }
}
