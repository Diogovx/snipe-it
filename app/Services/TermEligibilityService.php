<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\TermGenerationLog;
use App\Models\TermTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TermEligibilityService
{
    /**
     * Checks whether a given template can be generated for the asset.
     * If the template declares a dependency, verifies that a term of
     * the required type has already been generated or signed.
     */
    public function canGenerate(TermTemplate $template, Asset $asset, ?int $userId): bool
    {
        if (!$template->depends_on) {
            return true; // sem dependência, sempre liberado
        }

        // 1ª verificação: arquivo digitalizado no Snipe-IT
        if ($this->hasSignedDocumentInSnipe($asset, $template->depends_on)) {
            return true;
        }

        // 2ª verificação: log de geração do tipo requerido
        return $this->hasLogOfType($asset->id, $template->depends_on, $userId);
    }

    /**
     * Returns the most recent generation log for the given term type and asset.
     */
    public function lastLogOfType(int $assetId, string $termType, ?int $userId): ?TermGenerationLog
    {
        return TermGenerationLog::where('asset_id', $assetId)
            ->where('term_type', $termType)
            ->when($userId, fn($q) => $q->where('assigned_user_id', $userId))
            ->with('generatedBy')
            ->latest()
            ->first();
    }

    /**
     * Builds the eligibility status for every available template for this asset.
     * Returns an array ready for the frontend to consume.
     */
    public function statusForAsset(Asset $asset, ?int $userId): array
    {
        $categoryName = $asset->model?->category?->name;

        $templates = TermTemplate::where('active', true)
            ->get()
            ->filter(fn($t) => $t->isCompatibleWith($categoryName))
            ->values();

        return $templates->map(function (TermTemplate $t) use ($asset, $userId) {
            $lastLog  = $this->lastLogOfType($asset->id, $t->term_type, $userId);
            $canGen   = $this->canGenerate($t, $asset, $userId);

            $blockedBy = null;
            if (!$canGen && $t->depends_on) {
                $depTemplate = TermTemplate::where('term_type', $t->depends_on)
                    ->where('active', true)
                    ->first();
                $blockedBy = $depTemplate?->name ?? $t->depends_on;
            }

            return [
                'id'          => $t->id,
                'name'        => $t->name,
                'term_type'   => $t->term_type,
                'depends_on'  => $t->depends_on,
                'can_generate'=> $canGen,
                'blocked_by'  => $blockedBy,
                'generated'   => (bool) $lastLog,
                'last_log'    => $lastLog ? [
                    'generated_at' => $lastLog->created_at->format('d/m/Y H:i'),
                    'generated_by' => trim(
                        ($lastLog->generatedBy?->first_name ?? '') . ' ' .
                        ($lastLog->generatedBy?->last_name ?? '')
                    ),
                ] : null,
            ];
        })->values()->toArray();
    }

    private function hasLogOfType(int $assetId, string $termType, ?int $userId): bool
    {
        return TermGenerationLog::where('asset_id', $assetId)
            ->where('term_type', $termType)
            ->when($userId, fn($q) => $q->where('assigned_user_id', $userId))
            ->exists();
    }

    private function hasSignedDocumentInSnipe(Asset $asset, string $requiredType): bool
    {
        try {
            $response = Http::withToken(config('services.snipeit.api_key'))
                ->timeout(5)
                ->get(config('services.snipeit.url') . "/api/v1/hardware/{$asset->id}/files");

            if (!$response->successful()) {
                return false;
            }

            foreach ($response->json('rows', []) as $file) {
                $name = strtolower($file['name'] ?? '');
                if (strpos($name, strtolower($requiredType)) !== false) {
                    return true;
                }
            }

            return false;

        } catch (\Throwable $e) {
            Log::error("Snipe-IT unavailable when checking attachments: " . $e->getMessage());
            return false;
        }
    }
}