<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;

/**
 * Shared by the movement controllers: persist the optional supporting documents uploaded when
 * a request is submitted, linked to its ApprovalRequest so the approver sees them on the
 * request detail page. Storage mirrors AssetAttachment (public disk).
 */
trait StoresApprovalAttachments
{
    protected function storeApprovalAttachments(Request $request, ?int $approvalRequestId): void
    {
        if (! $approvalRequestId || ! $request->hasFile('documents')) {
            return;
        }

        $approvalRequest = ApprovalRequest::find($approvalRequestId);
        if (! $approvalRequest) {
            return;
        }

        foreach ($request->file('documents') as $file) {
            $path = $file->store("approvals/{$approvalRequestId}", 'public');

            $approvalRequest->attachments()->create([
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => $request->user()->id,
            ]);
        }
    }
}
