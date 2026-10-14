<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::with('user')->latest('created_at');
        if ($event = $request->query('event')) {
            $query->where('event', $event);
        }

        if ($model = trim((string) $request->query('model'))) {
            $query->where('subject_type', 'App\\Models\\'.$model);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->whereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return view('admin.audit.index', [
            'logs' => $query->paginate(40)->withQueryString(), 'event' => $event, 'model' => $model ?? '', 'search' => $search ?? '',
            'models' => AuditLog::query()->distinct()->orderBy('subject_type')->pluck('subject_type')->map(fn ($class) => class_basename($class)),
        ]);
    }
}
