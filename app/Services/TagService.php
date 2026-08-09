<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetTagAssignment;
use App\Models\ScanLog;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\TagBatch;
use App\Models\TagReplacementRequest;
use App\Models\User;
use App\Services\Tags\ScanResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * QR/Barcode tag pool (M05). Tags are pre-generated into a pool, printed, then
 * assigned to assets later — assets are never auto-tagged on creation.
 */
class TagService
{
    /**
     * Generate a batch of tags. Code type (qr|barcode) comes from the global
     * `tag_code_type` setting, snapshotted onto the batch — not a caller-supplied param.
     */
    public function generateBatch(int $quantity, User $actor): TagBatch
    {
        $codeType = Setting::get('tag_code_type', 'qr');

        return DB::transaction(function () use ($quantity, $codeType, $actor) {
            $batch = TagBatch::create([
                'quantity'   => $quantity,
                'code_type'  => $codeType,
                'created_by' => $actor->id,
            ]);

            for ($i = 0; $i < $quantity; $i++) {
                // Insert first so the row's own auto-increment id can be used as the
                // numbering source — global, monotonic, and (since tags are never hard
                // deleted) never reused. No separate counter table needed.
                $tag = Tag::create([
                    'batch_id'  => $batch->id,
                    'code_type' => $codeType,
                    'status'    => 'available',
                ]);

                $tagNumber = str_pad((string) $tag->id, 6, '0', STR_PAD_LEFT);

                $tag->update([
                    'tag_number'    => $tagNumber,
                    'qr_payload'    => $codeType === 'qr' ? url("/scan/{$tagNumber}") : null,
                    'barcode_value' => $codeType === 'barcode' ? $tagNumber : null,
                ]);
            }

            return $batch->load('tags');
        });
    }

    /**
     * Assign an available pool tag to an asset. Guards the asset has no existing active
     * assignment — replacement, not reassignment, is the path for an already-tagged asset.
     */
    public function assignToAsset(Tag $tag, Asset $asset, User $actor): AssetTagAssignment
    {
        return DB::transaction(function () use ($tag, $asset, $actor) {
            $tag->refresh();

            if (! $tag->isAvailable()) {
                throw ValidationException::withMessages([
                    'tag' => 'This tag is not available for assignment.',
                ]);
            }

            if ($asset->tagAssignments()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages([
                    'asset' => 'This asset already has an active tag. Submit a tag replacement request to change it.',
                ]);
            }

            $assignment = AssetTagAssignment::create([
                'asset_id'    => $asset->id,
                'tag_id'      => $tag->id,
                'status'      => 'active',
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
            ]);

            $tag->update(['status' => 'assigned']);
            $asset->update(['asset_tag' => $tag->tag_number]);

            return $assignment;
        });
    }

    /** Phase 2 — open a replacement request; the actual swap happens in applyReplacement(). */
    public function requestReplacement(Asset $asset, Tag $newTag, string $reason, User $actor): TagReplacementRequest
    {
        $currentTag = $asset->activeTag();

        if (! $currentTag) {
            throw ValidationException::withMessages([
                'asset' => 'This asset has no active tag to replace.',
            ]);
        }

        if (! $newTag->isAvailable()) {
            throw ValidationException::withMessages([
                'tag' => 'The selected replacement tag is not available.',
            ]);
        }

        return TagReplacementRequest::create([
            'asset_id'       => $asset->id,
            'current_tag_id' => $currentTag->id,
            'new_tag_id'     => $newTag->id,
            'reason'         => $reason,
            'status'         => 'pending',
            'requested_by'   => $actor->id,
        ]);
    }

    /**
     * Phase 2 — called by ApplyTagReplacement (the ApprovalRequestApproved listener) once
     * the replacement has cleared the full approval chain. Deactivates the old assignment
     * and tag, activates the new one, and re-syncs assets.asset_tag.
     */
    public function applyReplacement(TagReplacementRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $asset = $request->asset;

            $activeAssignment = $asset->tagAssignments()
                ->where('status', 'active')
                ->where('tag_id', $request->current_tag_id)
                ->first();

            $activeAssignment?->update([
                'status'         => 'inactive',
                'deactivated_at' => now(),
            ]);

            $request->currentTag->update(['status' => 'inactive']);

            AssetTagAssignment::create([
                'asset_id'    => $asset->id,
                'tag_id'      => $request->new_tag_id,
                'status'      => 'active',
                'assigned_by' => $request->requested_by,
                'assigned_at' => now(),
            ]);

            $request->newTag->update(['status' => 'assigned']);
            $asset->update(['asset_tag' => $request->newTag->tag_number]);

            $request->update(['status' => 'approved']);
        });
    }

    /**
     * Resolve a scanned tag_number to its routing outcome: assigned -> asset detail,
     * available -> assign prompt, inactive -> retired message (+ link to the asset's
     * current tag, found via the tag's most recent assignment, if any exists).
     */
    public function resolveScan(string $tagNumber): ScanResult
    {
        $tag = Tag::where('tag_number', $tagNumber)->firstOrFail();

        return match ($tag->status) {
            'assigned' => new ScanResult('assigned', $tag, $tag->activeAssignment?->asset),
            'available' => new ScanResult('available', $tag),
            default => $this->resolveInactiveScan($tag),
        };
    }

    public function logScan(Tag $tag, ?Asset $asset, User $user, string $method): ScanLog
    {
        return ScanLog::create([
            'tag_id'     => $tag->id,
            'asset_id'   => $asset?->id,
            'user_id'    => $user->id,
            'method'     => $method,
            'scanned_at' => now(),
        ]);
    }

    private function resolveInactiveScan(Tag $tag): ScanResult
    {
        $lastAssignment = $tag->assignments()->latest('assigned_at')->first();
        $asset = $lastAssignment?->asset;

        return new ScanResult('inactive', $tag, $asset, $asset?->activeTag());
    }
}
