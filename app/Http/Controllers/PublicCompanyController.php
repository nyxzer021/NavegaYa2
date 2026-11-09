<?php

namespace App\Http\Controllers;

use App\Models\CompanyReview;
use App\Models\Organization;
use App\Models\OrganizationPhoto;
use App\Models\PhotoComment;
use App\Models\PhotoLike;
use App\Models\Reservation;
use App\Models\User;
use App\Models\UserSanction;
use App\Support\AdminScope;
use App\Support\BaggageAllowance;
use App\Support\TravelPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicCompanyController
{
    public function index(): View
    {
        $companies = Organization::withCount(['vessels', 'transportRoutes', 'aircraft', 'airRoutes'])
            ->withAvg(['reviews as average_rating' => fn ($query) => $query->where('status', 'published')], 'rating')
            ->where('type', 'transport_company')->where('status', 'active')->orderBy('commercial_name')->get();

        return view('companies.index', compact('companies'));
    }

    public function show(Organization $organization): View
    {
        abort_unless($organization->type === 'transport_company' && $organization->status === 'active', 404);
        $organization->load([
            'photos' => fn ($query) => $query->where('status', 'published')->orderBy('sort_order'),
            'banners',
            'pageSections',
            'sites',
            'vessels.basePort',
            'transportRoutes.originPort',
            'transportRoutes.destinationPort',
            'transportRoutes.departures' => fn ($query) => $query->where('departure_at', '>=', now()->startOfDay())->with(['vessel.organization', 'reservations.seats']),
            'aircraft',
            'airRoutes.departures' => fn ($query) => $query->where('departure_at', '>=', now()->startOfDay())->with(['aircraft.organization', 'reservations.seats']),
        ]);
        $reviews = CompanyReview::with('user')->where('organization_id', $organization->id)->where('status', 'published')->latest()->get();
        $average = round((float) $reviews->avg('rating'), 1);
        $completedReservations = collect();
        $hasUpcomingTrip = false;
        if (auth()->check()) {
            $completedReservations = $this->reservationsForCompany(auth()->user(), $organization->id)
                ->with(['companyReview', 'departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'airDeparture.airRoute'])
                ->where(function (Builder $query) {
                    $query->whereHas('departure', fn (Builder $departure) => $departure->where('status', 'completed'))
                        ->orWhereHas('airDeparture', fn (Builder $departure) => $departure->where('status', 'completed'));
                })
                ->latest('id')
                ->get();
            $hasUpcomingTrip = $this->reservationsForCompany(auth()->user(), $organization->id)
                ->where(function (Builder $query) {
                    $query->whereHas('departure', fn (Builder $departure) => $departure->whereIn('status', ['scheduled', 'boarding']))
                        ->orWhereHas('airDeparture', fn (Builder $departure) => $departure->whereIn('status', ['scheduled', 'boarding']));
                })->exists();
        }

        $baggage = BaggageAllowance::for($organization);
        $policyPoints = TravelPolicy::publicPoints($organization);
        $travelPolicy = $organization->travelPolicies()->where('kind', 'company_travel')->first()?->current();
        $photoIds = $organization->photos->pluck('id');
        $threads = PhotoComment::query()
            ->whereIn('organization_photo_id', $photoIds)
            ->where('status', 'published')
            ->whereNull('parent_id')
            ->with(['user:id,name,email', 'replies' => fn ($query) => $query->where('status', 'published')->with('user:id,name,email')->oldest()])
            ->oldest()
            ->get();
        $traveledIds = $this->traveledUserIds($organization->id, $threads->pluck('user_id')->merge($threads->flatMap(fn (PhotoComment $comment) => $comment->replies->pluck('user_id'))));
        $commentCount = $threads->count() + $threads->sum(fn (PhotoComment $comment) => $comment->replies->count());
        $reactionCount = $photoIds->isEmpty() ? 0 : PhotoLike::query()->whereIn('organization_photo_id', $photoIds)->count();
        $viewer = auth()->user();
        $canManageMedia = $viewer
            && $viewer->roles()->where('code', 'company_admin')->exists()
            && (int) AdminScope::organizationId($viewer) === (int) $organization->id;

        return view('companies.show', compact(
            'organization', 'reviews', 'average', 'completedReservations', 'hasUpcomingTrip',
            'baggage', 'policyPoints', 'travelPolicy', 'threads', 'traveledIds', 'commentCount', 'reactionCount', 'canManageMedia',
        ));
    }

    public function review(Request $request, Organization $organization): RedirectResponse
    {
        $this->guardParticipation($request->user());
        abort_unless($organization->type === 'transport_company' && $organization->status === 'active', 404);
        $data = $request->validate([
            'reservation_id' => ['required', 'integer', 'exists:reservations,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $reservation = $this->reservationsForCompany($request->user(), $organization->id)
            ->whereKey($data['reservation_id'])
            ->where(function (Builder $query) {
                $query->whereHas('departure', fn (Builder $departure) => $departure->where('status', 'completed'))
                    ->orWhereHas('airDeparture', fn (Builder $departure) => $departure->where('status', 'completed'));
            })->firstOrFail();

        if (CompanyReview::where('reservation_id', $reservation->id)->exists()) {
            return back()->with('success', 'Este viaje ya tiene una opinión registrada.');
        }

        CompanyReview::create($data + [
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return redirect()->to(route('companies.show', $organization).'#opiniones')
            ->with('success', 'Tu opinión fue enviada y está pendiente de revisión.');
    }

    public function comment(Request $request, Organization $organization): RedirectResponse
    {
        $this->guardParticipation($request->user());
        abort_unless($organization->type === 'transport_company' && $organization->status === 'active', 404);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $exists = CompanyReview::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('reservation_id')
            ->exists();
        if ($exists) {
            return back()->with('success', 'Ya publicaste un comentario en esta empresa.');
        }

        CompanyReview::create([
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'],
            'status' => 'published',
        ]);

        return redirect()->to(route('companies.show', $organization).'#opiniones')
            ->with('success', 'Tu comentario quedó registrado.');
    }

    public function photoComments(Organization $organization, OrganizationPhoto $photo): JsonResponse
    {
        abort_unless((int) $photo->organization_id === (int) $organization->id && $photo->status === 'published', 404);
        $comments = $photo->comments()->where('status', 'published')->whereNull('parent_id')->with(['user:id,name,email', 'replies' => fn ($query) => $query->where('status', 'published')->with('user:id,name,email')->oldest()])->oldest()->limit(40)->get();
        $traveled = $this->traveledUserIds($organization->id, $comments->pluck('user_id')->merge($comments->flatMap(fn (PhotoComment $comment) => $comment->replies->pluck('user_id'))));
        $payload = $comments->map(fn (PhotoComment $comment) => $this->commentPayload($comment, $traveled));

        return response()->json([
            'comments' => $payload,
            'reactions' => $photo->likes()->count(),
        ]);
    }

    public function storePhotoComment(Request $request, Organization $organization, OrganizationPhoto $photo): JsonResponse
    {
        $this->guardParticipation($request->user());
        abort_unless((int) $photo->organization_id === (int) $organization->id && $photo->status === 'published', 404);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:500'], 'parent_id' => ['nullable', 'integer']]);
        if (! empty($data['parent_id'])) {
            abort_unless($photo->comments()->whereKey($data['parent_id'])->exists(), 422);
        }
        $comment = $photo->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
            'status' => 'pending',
        ]);

        return response()->json(['id' => $comment->id, 'status' => 'pending'], 201);
    }

    public function togglePhotoLike(Request $request, Organization $organization, OrganizationPhoto $photo): JsonResponse
    {
        abort_unless((int) $photo->organization_id === (int) $organization->id && $photo->status === 'published', 404);
        $existing = PhotoLike::query()->where('organization_photo_id', $photo->id)->where('user_id', $request->user()->id)->first();
        if ($existing) {
            $existing->delete();
        } else {
            PhotoLike::create(['organization_photo_id' => $photo->id, 'user_id' => $request->user()->id]);
        }

        return response()->json(['likes' => $photo->likes()->count()]);
    }

    public function policy(Organization $organization): View
    {
        abort_unless($organization->type === 'transport_company' && $organization->status === 'active', 404);
        $document = $organization->travelPolicies()->where('kind', 'company_travel')->first();
        $version = $document?->current();
        abort_unless($version, 404);

        return view('companies.policy', compact('organization', 'version'));
    }

    private function guardParticipation(User $user): void
    {
        $block = UserSanction::activeBlock($user);
        abort_if($block, 403, $block?->loginMessage() ?? 'Tu cuenta no puede publicar.');
    }

    private function reservationsForCompany(User $user, int $organizationId): Builder
    {
        return Reservation::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)->orWhere('contact_email', $user->email);
            })
            ->where('status', 'confirmed')
            ->where(function ($query) use ($organizationId) {
                $query->whereHas('departure.transportRoute', fn (Builder $route) => $route->where('organization_id', $organizationId))
                    ->orWhereHas('airDeparture.airRoute', fn (Builder $route) => $route->where('organization_id', $organizationId));
            });
    }

    /**
     * @param  Collection<int, mixed>  $userIds
     * @return list<int>
     */
    private function traveledUserIds(int $organizationId, Collection $userIds): array
    {
        $ids = $userIds->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $emails = User::query()->whereIn('id', $ids)->pluck('email', 'id');
        $reservations = Reservation::query()
            ->where('status', 'confirmed')
            ->where(function (Builder $query) use ($ids, $emails) {
                $query->whereIn('user_id', $ids)->orWhereIn('contact_email', $emails->filter()->values());
            })
            ->where(function (Builder $query) use ($organizationId) {
                $query->whereHas('departure', fn (Builder $departure) => $departure->where('status', 'completed')->whereHas('transportRoute', fn (Builder $route) => $route->where('organization_id', $organizationId)))
                    ->orWhereHas('airDeparture', fn (Builder $departure) => $departure->where('status', 'completed')->whereHas('airRoute', fn (Builder $route) => $route->where('organization_id', $organizationId)));
            })
            ->get(['user_id', 'contact_email']);

        return $emails->filter(function (?string $email, int $id) use ($reservations) {
            return $reservations->contains(fn (Reservation $reservation) => (int) $reservation->user_id === $id || ($email && $reservation->contact_email === $email));
        })->keys()->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $traveled
     * @return array{id: int, author: string, body: string, at: string, traveled: bool, replies: list<array{id: int, author: string, body: string, at: string, traveled: bool}>}
     */
    private function commentPayload(PhotoComment $comment, array $traveled): array
    {
        return [
            'id' => $comment->id,
            'author' => $comment->user?->name ?: 'Viajero',
            'body' => $comment->body,
            'at' => $comment->created_at?->format('d/m/Y H:i') ?? '',
            'traveled' => in_array((int) $comment->user_id, $traveled, true),
            'replies' => $comment->replies->map(fn (PhotoComment $reply) => [
                'id' => $reply->id,
                'author' => $reply->user?->name ?: 'Viajero',
                'body' => $reply->body,
                'at' => $reply->created_at?->format('d/m/Y H:i') ?? '',
                'traveled' => in_array((int) $reply->user_id, $traveled, true),
            ])->values()->all(),
        ];
    }
}
