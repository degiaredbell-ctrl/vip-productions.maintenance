<?php

namespace App\Actions\Pm;

use App\Enums\PmStatus;
use App\Models\PmRecord;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;

class ApprovePmRecord
{
    public function handle(PmRecord $record, bool $approved, ?string $note = null): PmRecord
    {
        $record->update([
            'status' => $approved ? PmStatus::Approved : PmStatus::Rejected,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        AuditLogService::record(
            $approved ? 'pm.approve' : 'pm.reject',
            PmRecord::class,
            $record->id,
            ['note' => $note]
        );

        return $record;
    }
}
