<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\Room;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GalleryController extends Controller
{
    /**
     * Display a listing of the galleries.
     */
    public function index(): View
    {
        $galleries = Gallery::with(['room'])
            ->withCount('media')
            ->orderBy('name')
            ->paginate(12);

        return view('galleries.index', compact('galleries'));
    }

    /**
     * Show the form for creating a new gallery.
     */
    public function create(): View
    {
        $rooms = Room::orderBy('name')->get();
        return view('galleries.create', compact('rooms'));
    }

    /**
     * Store a newly created gallery in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'room_id' => 'nullable|exists:rooms,id',
            'is_public' => 'boolean',
        ]);

        $gallery = Gallery::create([
            'name' => $request->name,
            'description' => $request->description,
            'room_id' => $request->room_id,
            'is_public' => $request->boolean('is_public', true),
        ]);

        return redirect()->route('galleries.show', $gallery)
            ->with('success', 'Gallery created successfully!');
    }

    /**
     * Display the specified gallery.
     */
    public function show(Gallery $gallery, Request $request): View
    {
        $query = $gallery->media();

        // Filter by type (image/video)
        if ($request->filled('type')) {
            $query->ofType($request->type);
        }

        $media = $query->get();
        
        // Get available media that can be added to this gallery
        $availableMedia = null;
        if ($request->get('add_media')) {
            $availableMediaQuery = Media::with(['room', 'mediaCategory'])
                ->whereNotIn('id', $media->pluck('id'));
            
            if ($gallery->room_id) {
                $availableMediaQuery->where('room_id', $gallery->room_id);
            }
            
            $availableMedia = $availableMediaQuery->latest()->paginate(12, ['*'], 'available');
        }

        return view('galleries.show', compact('gallery', 'media', 'availableMedia'));
    }

    /**
     * Show the form for editing the specified gallery.
     */
    public function edit(Gallery $gallery): View
    {
        $rooms = Room::orderBy('name')->get();
        return view('galleries.edit', compact('gallery', 'rooms'));
    }

    /**
     * Update the specified gallery in storage.
     */
    public function update(Request $request, Gallery $gallery): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'room_id' => 'nullable|exists:rooms,id',
            'is_public' => 'boolean',
        ]);

        $gallery->update([
            'name' => $request->name,
            'description' => $request->description,
            'room_id' => $request->room_id,
            'is_public' => $request->boolean('is_public'),
        ]);

        return redirect()->route('galleries.show', $gallery)
            ->with('success', 'Gallery updated successfully!');
    }

    /**
     * Remove the specified gallery from storage.
     */
    public function destroy(Gallery $gallery): RedirectResponse
    {
        $gallery->delete();

        return redirect()->route('galleries.index')
            ->with('success', 'Gallery deleted successfully!');
    }

    /**
     * Add media to gallery.
     */
    public function addMedia(Request $request, Gallery $gallery): RedirectResponse
    {
        $request->validate([
            'media_ids' => 'required|array',
            'media_ids.*' => 'exists:media,id',
        ]);

        $addedCount = 0;
        foreach ($request->media_ids as $mediaId) {
            $media = Media::find($mediaId);
            
            // Check if media is already in gallery
            if (!$gallery->media()->where('media_id', $mediaId)->exists()) {
                $gallery->addMedia($media);
                $addedCount++;
            }
        }

        return redirect()->route('galleries.show', $gallery)
            ->with('success', "{$addedCount} media item(s) added to gallery!");
    }

    /**
     * Remove media from gallery.
     */
    public function removeMedia(Gallery $gallery, Media $media): RedirectResponse
    {
        $gallery->removeMedia($media);

        return redirect()->route('galleries.show', $gallery)
            ->with('success', 'Media removed from gallery!');
    }

    /**
     * Reorder media in gallery.
     */
    public function reorderMedia(Request $request, Gallery $gallery): RedirectResponse
    {
        $request->validate([
            'media_order' => 'required|array',
            'media_order.*' => 'exists:media,id',
        ]);

        foreach ($request->media_order as $index => $mediaId) {
            $gallery->media()->updateExistingPivot($mediaId, [
                'sort_order' => $index + 1
            ]);
        }

        return redirect()->route('galleries.show', $gallery)
            ->with('success', 'Media order updated!');
    }
}