<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Room;
use App\Models\Gallery;
use App\Models\MediaCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(): View
    {
        // Overall statistics
        $stats = [
            'total_media' => Media::count(),
            'total_rooms' => Room::count(),
            'total_galleries' => Gallery::count(),
            'total_size' => Media::sum('size'),
            'images_count' => Media::ofType('image')->count(),
            'videos_count' => Media::ofType('video')->count(),
        ];

        // Recent uploads (last 10)
        $recentMedia = Media::with(['room', 'mediaCategory'])
            ->latest()
            ->limit(10)
            ->get();

        // Featured media
        $featuredMedia = Media::with(['room', 'mediaCategory'])
            ->featured()
            ->latest()
            ->limit(8)
            ->get();

        // Room statistics
        $roomStats = Room::select('rooms.*')
            ->withCount('media')
            ->orderBy('media_count', 'desc')
            ->limit(5)
            ->get();

        // Category statistics
        $categoryStats = MediaCategory::select('media_categories.*')
            ->withCount('media')
            ->orderBy('media_count', 'desc')
            ->get();

        // Media upload trends (last 7 days)
        $uploadTrends = Media::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Fill missing dates with 0
        $trends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $trends[] = [
                'date' => $date,
                'count' => $uploadTrends[$date]['count'] ?? 0,
                'formatted_date' => now()->subDays($i)->format('M j')
            ];
        }

        // Storage usage by file type
        $storageByType = Media::select(
                DB::raw('CASE WHEN mime_type LIKE "image%" THEN "Images" ELSE "Videos" END as type'),
                DB::raw('SUM(size) as total_size'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('type')
            ->get();

        return view('dashboard', compact(
            'stats',
            'recentMedia',
            'featuredMedia',
            'roomStats',
            'categoryStats',
            'trends',
            'storageByType'
        ));
    }

    /**
     * Get dashboard statistics API endpoint.
     */
    public function getStats(): array
    {
        return [
            'total_media' => Media::count(),
            'total_rooms' => Room::count(),
            'total_galleries' => Gallery::count(),
            'total_size' => Media::sum('size'),
            'images_count' => Media::ofType('image')->count(),
            'videos_count' => Media::ofType('video')->count(),
        ];
    }

    /**
     * Get recent activity for dashboard.
     */
    public function getRecentActivity(): array
    {
        $recentMedia = Media::with(['room', 'mediaCategory'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($media) {
                return [
                    'id' => $media->id,
                    'type' => 'media_uploaded',
                    'title' => $media->original_name,
                    'description' => "Uploaded to {$media->room->name}",
                    'url' => route('media.show', $media),
                    'created_at' => $media->created_at,
                ];
            });

        $recentGalleries = Gallery::with('room')
            ->latest()
            ->limit(3)
            ->get()
            ->map(function ($gallery) {
                return [
                    'id' => $gallery->id,
                    'type' => 'gallery_created',
                    'title' => $gallery->name,
                    'description' => $gallery->room ? "Created for {$gallery->room->name}" : "General gallery",
                    'url' => route('galleries.show', $gallery),
                    'created_at' => $gallery->created_at,
                ];
            });

        return $recentMedia->concat($recentGalleries)
            ->sortByDesc('created_at')
            ->take(8)
            ->values()
            ->toArray();
    }
}