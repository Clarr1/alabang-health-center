<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientProfileAudit extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['details'];

    protected function casts(): array
    {
        return ['details' => 'encrypted:array'];
    }
}
