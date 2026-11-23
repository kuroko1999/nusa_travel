<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id && $booking->status === 'selesai', 403);
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5', 'quote' => 'required|string|min:10|max:500']);
        Review::updateOrCreate(['booking_id' => $booking->id], $data + ['user_id' => $request->user()->id, 'approved' => false]);

        return back()->with('status', 'Ulasan dikirim untuk ditinjau.');
    }

    public function index()
    {
        return view('admin.reviews', ['reviews' => Review::with('user', 'booking')->latest()->paginate(20)]);
    }

    public function approve(Review $review)
    {
        $review->update(['approved' => true]);

        return back()->with('status', 'Ulasan ditampilkan.');
    }

    public function hide(Review $review)
    {
        $review->update(['approved' => false]);

        return back()->with('status', 'Ulasan disembunyikan.');
    }
}
