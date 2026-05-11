<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TermTemplate extends Model
{
    const TERM_TYPES = [
        'checkin',
        'checkout', 
        'test',
        // adicione os tipos do seu negócio aqui
    ];
    protected $fillable = [
        'name',
        'term_type',
        'depends_on',
        'file_name',
        'allowed_categories',
        'field_map',
        'active',
    ];

    protected $casts = [
        'allowed_categories' => 'array',
        'field_map'          => 'array',
        'active'             => 'boolean',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(TermGenerationLog::class);
    }

    /**
     * Verifica se este template é compatível com uma categoria de ativo.
     */
    public function isCompatibleWith(?string $categoryName): bool
    {
        if (empty($this->allowed_categories)) {
            return true;
        }

        return in_array($categoryName, $this->allowed_categories, true);
    }

    public function storagePath(): string
    {
        return storage_path("app/term-templates/{$this->file_name}");
    }
}