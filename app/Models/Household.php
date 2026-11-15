<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function members(): HasMany
    {
        return $this->hasMany(PatientInformation::class, 'household_id');
    }

    protected function casts(): array
    {
        return ['average_income' => 'decimal:2', 'residency_years' => 'decimal:2'];
    }
}
