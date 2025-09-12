<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'color',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all media for this category.
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Scope to get only active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get media count for this category.
     */
    public function getMediaCountAttribute(): int
    {
        return $this->media()->count();
    }

    /**
     * Get default categories for seeding.
     */
    public static function getDefaultCategories(): array
    {
        return [
            [
                'name' => 'Photography',
                'description' => 'Interior photography and professional shots',
                'icon' => 'camera',
                'color' => '#3B82F6',
            ],
            [
                'name' => 'Videos',
                'description' => 'Video tours and recordings',
                'icon' => 'video',
                'color' => '#EF4444',
            ],
            [
                'name' => '360° Views',
                'description' => 'Panoramic and 360-degree captures',
                'icon' => 'globe',
                'color' => '#10B981',
            ],
            [
                'name' => 'Before/After',
                'description' => 'Renovation and transformation documentation',
                'icon' => 'refresh',
                'color' => '#F59E0B',
            ],
            [
                'name' => 'Details',
                'description' => 'Close-up shots of specific features',
                'icon' => 'zoom-in',
                'color' => '#8B5CF6',
            ],
            [
                'name' => 'Lighting',
                'description' => 'Lighting setups and ambiance captures',
                'icon' => 'lightbulb',
                'color' => '#F97316',
            ]
        ];
    }
}