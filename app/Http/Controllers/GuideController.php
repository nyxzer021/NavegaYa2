<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\DestinationCity;
use App\Models\DestinationListing;
use App\Models\DestinationListingImage;
use App\Models\CompanyReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuideController extends Controller
{
    public function index(Request $request): View
    {
        $cityId = $request->integer('ciudad') ?: null;
        $type = in_array($request->string('tipo')->toString(), array_keys(MarketplaceBusinessController::TYPES), true)
            ? $request->string('tipo')->toString() : null;

        $listings = DestinationListing::with(['city', 'images'])
            ->where('is_active', true)
            ->where('status', 'published')
            ->when($cityId, fn ($query) => $query->where('destination_city_id', $cityId))
            ->when($type, fn ($query) => $query->where('type', $type))
            // Five directory entries + the permanent advertising card + up to three
            // sponsored cards keep the three-column grid at a maximum of three rows.
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('name')->paginate(5)->withQueryString();

        $cities = DestinationCity::where('is_active', true)->orderBy('name')->get();
        $ads = Advertisement::with('city')->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->when($cityId, fn ($query) => $query->where(fn ($q) => $q->whereNull('destination_city_id')->orWhere('destination_city_id', $cityId)))
            ->latest()->take(3)->get();
        $ads->each(fn ($ad) => $ad->increment('views'));

        return view('guide.index', compact('listings', 'cities', 'ads', 'cityId', 'type'));
    }

    public function show(DestinationListing $listing): View
    {
        abort_unless($listing->is_active && $listing->status === 'published', 404);
        $listing->increment('views');
        $reviews=$listing->organization->reviews()->with('user')->where('status','approved')->latest()->take(20)->get();
        return view('guide.show', ['listing' => $listing->load(['city','images.reviews.user','offers'=>fn($q)=>$q->where('is_active',true)]), 'types' => MarketplaceBusinessController::TYPES, 'reviews'=>$reviews, 'ratingAverage'=>round((float)$reviews->avg('rating'),1)]);
    }

    public function contact(DestinationListing $listing): RedirectResponse
    {
        abort_unless($listing->is_active && $listing->status==='published' && $listing->contact_phone,404); $listing->increment('contact_clicks');
        return redirect()->away('https://wa.me/'.preg_replace('/\D/','',$listing->contact_phone).'?text='.urlencode('Hola, vi su página en NavegaYA y deseo información.'));
    }

    public function review(Request $request, DestinationListing $listing): RedirectResponse
    {
        abort_unless($listing->is_active && $listing->status==='published',404);
        $data=$request->validate(['rating'=>['required','integer','between:1,5'],'comment'=>['required','string','min:10','max:1200']]);
        CompanyReview::updateOrCreate(['organization_id'=>$listing->organization_id,'user_id'=>$request->user()->id,'reservation_id'=>null],$data+['status'=>'pending']);
        return back()->with('success','Gracias. Tu opinión fue enviada a moderación.');
    }

    public function reviewImage(Request $request, DestinationListing $listing, DestinationListingImage $image): RedirectResponse
    {
        abort_unless($listing->is_active && $listing->status === 'published' && $image->destination_listing_id === $listing->id, 404);
        $data = $request->validate(['rating'=>['required','integer','between:1,5'],'comment'=>['required','string','min:3','max:700']]);
        $image->reviews()->updateOrCreate(['user_id'=>$request->user()->id], $data + ['status'=>'published']);
        return back()->with('success', 'Tu comentario y valoración se publicaron en la fotografía.');
    }
}
