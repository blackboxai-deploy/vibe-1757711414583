<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_name',
        'path',
        'thumbnail_path',
        'mime_type',
        'size',
        'dimensions',
        'room_id',
        'media_category_id',
        'metadata',
        'description',
        'tags',
        'is_featured'
    ];

    protected $casts = [
        'dimensions' => 'array',
        'metadata' => 'array',
        'tags' => 'array',
        'is_featured' => 'boolean',
        'size' => 'integer',
    ];

    /**
     * Get the room that owns this media.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the category that owns this media.
     */
    public function mediaCategory(): BelongsTo
    {
        return $this->belongsTo(MediaCategory::class);
    }

    /**
     * Get the galleries that contain this media.
     */
    public function galleries(): BelongsToMany
    {
        return $this->belongsToMany(Gallery::class, 'gallery_media')
                    ->withPivot('sort_order')
                    ->withTimestamps()
                    ->orderBy('gallery_media.sort_order');
    }

    /**
     * Get the full URL for the media file.
     */
    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }

    /**
     * Get the full URL for the thumbnail.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path ? Storage::url($this->thumbnail_path) : null;
    }

    /**
     * Check if the media is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the media is a video.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedSizeAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Get dimensions as string.
     */
    public function getDimensionsStringAttribute(): ?string
    {
        if (!$this->dimensions || !isset($this->dimensions['width'], $this->dimensions['height'])) {
            return null;
        }
        
        return $this->dimensions['width'] . ' × ' . $this->dimensions['height'];
    }

    /**
     * Scope to get featured media.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to get media by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('mime_type', 'like', $type . '%');
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($media) {
            $media->room->updateMediaCount();
        });

        static::deleted(function ($media) {
            // Delete physical files
            if (Storage::exists($media->path)) {
                Storage::delete($media->path);
            }
            
            if ($media->thumbnail_path && Storage::exists($media->thumbnail_path)) {
                Storage::delete($media->thumbnail_path);
            }
            
            $media->room->updateMediaCount();
        });
    }
}