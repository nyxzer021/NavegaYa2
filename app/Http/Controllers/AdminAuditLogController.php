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

return view('admin.audit.index', ['logs' => $query->paginate(40)->withQueryString(), 'event' => $event]);
    }
}
