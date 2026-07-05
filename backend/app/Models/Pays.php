<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pays extends Model
{
    use HasFactory;

    protected $fillable = [
        'code_iso',
        'nom',
        'capitale',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function ambassades()
    {
        return $this->hasMany(Ambassade::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
