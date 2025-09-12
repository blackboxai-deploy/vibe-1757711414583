<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'media_count'
    ];

    protected $casts = [
        'media_count' => 'integer',
    ];

    /**
     * Get all media for this room.
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Get all galleries for this room.
     */
    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    /**
     * Get the latest media for this room.
     */
    public function latestMedia(int $limit = 5): HasMany
    {
        return $this->hasMany(Media::class)->latest()->limit($limit);
    }

    /**
     * Update the media count for this room.
     */
    public function updateMediaCount(): void
    {
        $this->update([
            'media_count' => $this->media()->count()
        ]);
    }

    /**
     * Get room type display name.
     */
    public function getTypeDisplayAttribute(): string
    {
        return match($this->type) {
            'living_room' => 'Living Room',
            'bedroom' => 'Bedroom',
            'kitchen' => 'Kitchen',
            'bathroom' => 'Bathroom',
            'dining_room' => 'Dining Room',
            'office' => 'Office',
            'basement' => 'Basement',
            'attic' => 'Attic',
            'garage' => 'Garage',
            'outdoor' => 'Outdoor Space',
            default => ucfirst(str_replace('_', ' ', $this->type ?? 'General'))
        };
    }
}