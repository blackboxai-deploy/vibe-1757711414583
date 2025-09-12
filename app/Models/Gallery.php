<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Gallery extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'room_id',
        'is_public',
        'cover_image',
        'media_count'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'media_count' => 'integer',
    ];

    /**
     * Get the room that owns this gallery.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the media that belong to this gallery.
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'gallery_media')
                    ->withPivot('sort_order')
                    ->withTimestamps()
                    ->orderBy('gallery_media.sort_order');
    }

    /**
     * Get the cover image URL.
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        if ($this->cover_image) {
            return Storage::url($this->cover_image);
        }
        
        // Use first media item as cover if no cover image set
        $firstMedia = $this->media()->first();
        return $firstMedia?->thumbnail_url ?? $firstMedia?->url;
    }

    /**
     * Update the media count for this gallery.
     */
    public function updateMediaCount(): void
    {
        $this->update([
            'media_count' => $this->media()->count()
        ]);
    }

    /**
     * Add media to this gallery.
     */
    public function addMedia(Media $media, int $sortOrder = null): void
    {
        $sortOrder = $sortOrder ?? ($this->media()->max('gallery_media.sort_order') + 1);
        
        $this->media()->attach($media->id, [
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->updateMediaCount();
    }

    /**
     * Remove media from this gallery.
     */
    public function removeMedia(Media $media): void
    {
        $this->media()->detach($media->id);
        $this->updateMediaCount();
    }

    /**
     * Scope to get public galleries.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Get gallery preview images (first 4 media items).
     */
    public function getPreviewImagesAttribute()
    {
        return $this->media()->limit(4)->get();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($gallery) {
            $gallery->updateMediaCount();
        });
    }
}