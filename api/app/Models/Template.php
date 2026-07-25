<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['name', 'target', 'description', 'seed'])]
class Template extends Model
{
    use HasFactory;
    protected function casts(): array
    {
        return [
            'seed' => 'array',
        ];
    }
}
