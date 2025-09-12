<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class MediaService
{
    protected ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Store uploaded media file.
     */
    public function store(UploadedFile $file, array $metadata = []): Media
    {
        // Generate unique filename
        $filename = $this->generateUniqueFilename($file);
        
        // Store the original file
        $path = $file->storeAs('media', $filename, 'public');
        
        // Get file information
        $fileInfo = $this->getFileInfo($file, $path);
        
        // Generate thumbnail for images
        $thumbnailPath = null;
        if ($this->isImage($file)) {
            $thumbnailPath = $this->generateThumbnail($path, $filename);
        }

        // Create media record
        return Media::create([
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'dimensions' => $fileInfo['dimensions'],
            'metadata' => $fileInfo['metadata'],
            'room_id' => $metadata['room_id'],
            'media_category_id' => $metadata['media_category_id'],
            'description' => $metadata['description'] ?? null,
            'tags' => $metadata['tags'] ?? null,
            'is_featured' => $metadata['is_featured'] ?? false,
        ]);
    }

    /**
     * Generate unique filename for uploaded file.
     */
    protected function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $baseName = Str::slug($baseName);
        
        return $baseName . '_' . time() . '_' . Str::random(8) . '.' . $extension;
    }

    /**
     * Get file information including dimensions and metadata.
     */
    protected function getFileInfo(UploadedFile $file, string $path): array
    {
        $dimensions = null;
        $metadata = [];

        if ($this->isImage($file)) {
            try {
                $image = $this->imageManager->read(Storage::disk('public')->path($path));
                $dimensions = [
                    'width' => $image->width(),
                    'height' => $image->height()
                ];

                // Extract EXIF data if available
                $exif = @exif_read_data(Storage::disk('public')->path($path));
                if ($exif) {
                    $metadata['exif'] = [
                        'make' => $exif['Make'] ?? null,
                        'model' => $exif['Model'] ?? null,
                        'datetime' => $exif['DateTime'] ?? null,
                        'exposure_time' => $exif['ExposureTime'] ?? null,
                        'f_number' => $exif['FNumber'] ?? null,
                        'iso' => $exif['ISOSpeedRatings'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                // If image processing fails, continue without dimensions
            }
        } elseif ($this->isVideo($file)) {
            // For videos, we could extract metadata using FFMpeg if needed
            // For now, we'll just store basic information
            $metadata['type'] = 'video';
        }

        return [
            'dimensions' => $dimensions,
            'metadata' => $metadata
        ];
    }

    /**
     * Generate thumbnail for image files.
     */
    protected function generateThumbnail(string $originalPath, string $filename): string
    {
        try {
            $image = $this->imageManager->read(Storage::disk('public')->path($originalPath));
            
            // Create thumbnail with max 300px on longest side
            $image->scale(width: 300, height: 300);
            
            // Generate thumbnail filename and path
            $thumbnailFilename = 'thumb_' . $filename;
            $thumbnailPath = 'media/thumbnails/' . $thumbnailFilename;
            
            // Ensure thumbnails directory exists
            Storage::disk('public')->makeDirectory('media/thumbnails');
            
            // Save thumbnail
            $image->save(Storage::disk('public')->path($thumbnailPath));
            
            return $thumbnailPath;
        } catch (\Exception $e) {
            // If thumbnail generation fails, return null
            return null;
        }
    }

    /**
     * Check if uploaded file is an image.
     */
    protected function isImage(UploadedFile $file): bool
    {
        return str_starts_with($file->getMimeType(), 'image/');
    }

    /**
     * Check if uploaded file is a video.
     */
    protected function isVideo(UploadedFile $file): bool
    {
        return str_starts_with($file->getMimeType(), 'video/');
    }

    /**
     * Delete media file and its thumbnail.
     */
    public function delete(Media $media): bool
    {
        $deleted = true;

        // Delete original file
        if (Storage::disk('public')->exists($media->path)) {
            $deleted = Storage::disk('public')->delete($media->path) && $deleted;
        }

        // Delete thumbnail if exists
        if ($media->thumbnail_path && Storage::disk('public')->exists($media->thumbnail_path)) {
            $deleted = Storage::disk('public')->delete($media->thumbnail_path) && $deleted;
        }

        return $deleted;
    }

    /**
     * Get formatted file size.
     */
    public function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get storage statistics.
     */
    public function getStorageStats(): array
    {
        $totalSize = Media::sum('size');
        $imageSize = Media::ofType('image')->sum('size');
        $videoSize = Media::ofType('video')->sum('size');

        return [
            'total_size' => $totalSize,
            'total_formatted' => $this->formatFileSize($totalSize),
            'images_size' => $imageSize,
            'images_formatted' => $this->formatFileSize($imageSize),
            'videos_size' => $videoSize,
            'videos_formatted' => $this->formatFileSize($videoSize),
            'images_percentage' => $totalSize > 0 ? round(($imageSize / $totalSize) * 100, 1) : 0,
            'videos_percentage' => $totalSize > 0 ? round(($videoSize / $totalSize) * 100, 1) : 0,
        ];
    }
}