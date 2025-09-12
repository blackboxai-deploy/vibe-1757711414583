<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Room;
use App\Models\MediaCategory;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Display a listing of the media.
     */
    public function index(Request $request): View
    {
        $query = Media::with(['room', 'mediaCategory']);

        // Filter by room
        if ($request->filled('room')) {
            $query->where('room_id', $request->room);
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('media_category_id', $request->category);
        }

        // Filter by type (image/video)
        if ($request->filled('type')) {
            $query->ofType($request->type);
        }

        // Search by filename or description
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereJsonContains('tags', $search);
            });
        }

        // Sort options
        $sort = $request->get('sort', 'latest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'name' => $query->orderBy('original_name'),
            'size' => $query->orderBy('size', 'desc'),
            default => $query->latest(),
        };

        $media = $query->paginate(24)->withQueryString();

        $rooms = Room::orderBy('name')->get();
        $categories = MediaCategory::active()->orderBy('name')->get();

        return view('media.index', compact('media', 'rooms', 'categories'));
    }

    /**
     * Show the form for creating a new media.
     */
    public function create(): View
    {
        $rooms = Room::orderBy('name')->get();
        $categories = MediaCategory::active()->orderBy('name')->get();

        return view('media.create', compact('rooms', 'categories'));
    }

    /**
     * Store a newly created media in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'files.*' => 'required|file|mimes:jpeg,png,jpg,gif,webp,mp4,webm,mov|max:50000',
            'room_id' => 'required|exists:rooms,id',
            'media_category_id' => 'required|exists:media_categories,id',
            'description' => 'nullable|string|max:1000',
            'tags' => 'nullable|string',
        ]);

        $uploadedCount = 0;
        $errors = [];

        foreach ($request->file('files', []) as $file) {
            try {
                $this->mediaService->store($file, [
                    'room_id' => $request->room_id,
                    'media_category_id' => $request->media_category_id,
                    'description' => $request->description,
                    'tags' => $request->tags ? explode(',', $request->tags) : null,
                    'is_featured' => $request->boolean('is_featured'),
                ]);
                $uploadedCount++;
            } catch (\Exception $e) {
                $errors[] = "Failed to upload {$file->getClientOriginalName()}: " . $e->getMessage();
            }
        }

        if ($uploadedCount > 0) {
            session()->flash('success', "{$uploadedCount} file(s) uploaded successfully!");
        }

        if (!empty($errors)) {
            session()->flash('errors', $errors);
        }

        return redirect()->route('media.index');
    }

    /**
     * Display the specified media.
     */
    public function show(Media $media): View
    {
        $media->load(['room', 'mediaCategory', 'galleries']);
        $relatedMedia = Media::where('room_id', $media->room_id)
            ->where('id', '!=', $media->id)
            ->limit(6)
            ->get();

        return view('media.show', compact('media', 'relatedMedia'));
    }

    /**
     * Show the form for editing the specified media.
     */
    public function edit(Media $media): View
    {
        $rooms = Room::orderBy('name')->get();
        $categories = MediaCategory::active()->orderBy('name')->get();

        return view('media.edit', compact('media', 'rooms', 'categories'));
    }

    /**
     * Update the specified media in storage.
     */
    public function update(Request $request, Media $media): RedirectResponse
    {
        $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'media_category_id' => 'required|exists:media_categories,id',
            'description' => 'nullable|string|max:1000',
            'tags' => 'nullable|string',
            'is_featured' => 'boolean',
        ]);

        $media->update([
            'room_id' => $request->room_id,
            'media_category_id' => $request->media_category_id,
            'description' => $request->description,
            'tags' => $request->tags ? explode(',', $request->tags) : null,
            'is_featured' => $request->boolean('is_featured'),
        ]);

        return redirect()->route('media.show', $media)
            ->with('success', 'Media updated successfully!');
    }

    /**
     * Remove the specified media from storage.
     */
    public function destroy(Media $media): RedirectResponse
    {
        $media->delete();

        return redirect()->route('media.index')
            ->with('success', 'Media deleted successfully!');
    }

    /**
     * Download the specified media file.
     */
    public function download(Media $media)
    {
        if (!Storage::exists($media->path)) {
            abort(404, 'File not found');
        }

        return Storage::download($media->path, $media->original_name);
    }
}