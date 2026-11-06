<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditRecorder
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public static function record(Request $request, string $event, Model $subject, array $changes): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'changes' => $changes,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route_name' => $request->route()?->getName(),
            'http_method' => $request->method(),
            'created_at' => now(),
        ]);
    }
}
