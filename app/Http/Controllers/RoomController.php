<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\MediaCategory;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoomController extends Controller
{
    /**
     * Display a listing of the rooms.
     */
    public function index(): View
    {
        $rooms = Room::withCount('media')
            ->orderBy('name')
            ->get();

        return view('rooms.index', compact('rooms'));
    }

    /**
     * Show the form for creating a new room.
     */
    public function create(): View
    {
        $roomTypes = $this->getRoomTypes();
        return view('rooms.create', compact('roomTypes'));
    }

    /**
     * Store a newly created room in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:rooms',
            'description' => 'nullable|string|max:1000',
            'type' => 'nullable|string|in:' . implode(',', array_keys($this->getRoomTypes())),
        ]);

        Room::create($request->only(['name', 'description', 'type']));

        return redirect()->route('rooms.index')
            ->with('success', 'Room created successfully!');
    }

    /**
     * Display the specified room.
     */
    public function show(Room $room, Request $request): View
    {
        $query = $room->media()->with(['mediaCategory']);

        // Filter by category
        if ($request->filled('category')) {
            $query->where('media_category_id', $request->category);
        }

        // Filter by type (image/video)
        if ($request->filled('type')) {
            $query->ofType($request->type);
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
        $categories = MediaCategory::active()->orderBy('name')->get();

        // Get media statistics for this room
        $stats = [
            'total_media' => $room->media()->count(),
            'images' => $room->media()->ofType('image')->count(),
            'videos' => $room->media()->ofType('video')->count(),
            'total_size' => $room->media()->sum('size'),
        ];

        return view('rooms.show', compact('room', 'media', 'categories', 'stats'));
    }

    /**
     * Show the form for editing the specified room.
     */
    public function edit(Room $room): View
    {
        $roomTypes = $this->getRoomTypes();
        return view('rooms.edit', compact('room', 'roomTypes'));
    }

    /**
     * Update the specified room in storage.
     */
    public function update(Request $request, Room $room): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:rooms,name,' . $room->id,
            'description' => 'nullable|string|max:1000',
            'type' => 'nullable|string|in:' . implode(',', array_keys($this->getRoomTypes())),
        ]);

        $room->update($request->only(['name', 'description', 'type']));

        return redirect()->route('rooms.show', $room)
            ->with('success', 'Room updated successfully!');
    }

    /**
     * Remove the specified room from storage.
     */
    public function destroy(Room $room): RedirectResponse
    {
        if ($room->media()->count() > 0) {
            return redirect()->route('rooms.index')
                ->with('error', 'Cannot delete room that contains media files. Please remove all media first.');
        }

        $room->delete();

        return redirect()->route('rooms.index')
            ->with('success', 'Room deleted successfully!');
    }

    /**
     * Get available room types.
     */
    private function getRoomTypes(): array
    {
        return [
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
            'hallway' => 'Hallway',
            'closet' => 'Closet',
            'laundry' => 'Laundry Room',
            'pantry' => 'Pantry',
            'other' => 'Other',
        ];
    }
}