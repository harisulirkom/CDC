<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    public static function log(string $action, ?string $entity = null, ?int $entityId = null, array $meta = []): void
    {
        try {
            $request = request();
            $user = $request?->user();
            AuditLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'meta' => $meta ?: null,
                'ip' => $request?->ip(),
                'user_agent' => substr((string) $request?->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Audit log failed', ['error' => $e->getMessage()]);
        }
    }
}
