<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\TermGenerationLog;
use App\Models\TermTemplate;
use App\Models\User;
use App\Services\TermEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;

class TermGeneratorController extends Controller
{
    public function showForm(int $assetId)
    {
        $asset = Asset::with(['model.category'])->findOrFail($assetId);
        $categoryName = $asset->model?->category?->name;

        $templates = TermTemplate::where('active', true)
            ->get()
            ->filter(fn ($t) => $t->isCompatibleWith($categoryName))
            ->values();

        $assignedUser = null;
        if ($asset->assigned_to && str_contains((string) $asset->assigned_type, 'User')) {
            $assignedUser = User::find($asset->assigned_to);
        }

        return view('hardware.term-form', compact('asset', 'templates', 'assignedUser'));
    }

    public function generate(
        Request $request,
        int $assetId,
        TermEligibilityService $eligibility
    ) {
        $request->validate(['term_template_id' => 'required|exists:term_templates,id']);

        $asset = Asset::with(['model.category'])->findOrFail($assetId);
        $template = TermTemplate::findOrFail($request->input('term_template_id'));

        $assignedUser = null;
        if ($asset->assigned_to && strpos((string) $asset->assigned_type, 'User') !== false) {
            $assignedUser = User::find($asset->assigned_to);
        }

        if (! $eligibility->canGenerate($template, $asset, $assignedUser?->id)) {
            abort(403);
        }

        try {
            $processor = new TemplateProcessor($template->storagePath());
            $context = $this->buildContext($asset, $assignedUser);

            $arraysToProcess = ['accessories', 'components', 'user_assets'];

            foreach ($arraysToProcess as $blockName) {
                if (isset($context[$blockName]) && is_array($context[$blockName])) {
                    $rows = $context[$blockName];

                    try {
                        if (empty($rows)) {
                            $processor->cloneBlock($blockName, 0, true, false);
                        } else {
                            $processor->cloneBlock($blockName, count($rows), true, true);

                            $i = 1;
                            foreach ($rows as $rowData) {
                                foreach ($rowData as $field => $value) {
                                    $tagName = "{$field}#{$i}";
                                    $processor->setValue($tagName, htmlspecialchars((string) $value));
                                }
                                $i++;
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::info("Bloco '{$blockName}' ignorado: ".$e->getMessage());
                    }

                    unset($context[$blockName]);
                }
            }

            foreach ($context as $key => $value) {
                if (! is_array($value)) {
                    try {
                        $processor->setValue($key, htmlspecialchars((string) $value));
                    } catch (\Throwable $e) {
                    }
                }
            }

            $fullName = $assignedUser ? trim($assignedUser->first_name.' '.$assignedUser->last_name) : 'sem_usuario';
            $safeUser = preg_replace('/[^a-zA-Z0-9 ._-]/', '', $fullName);
            $safeName = preg_replace('/[^a-zA-Z0-9 ._-]/', '', $template->name);
            $fileName = "{$asset->asset_tag} - {$safeName} - {$safeUser}.docx";
            $outputPath = storage_path("app/term-output/{$fileName}");

            if (! is_dir(dirname($outputPath))) {
                mkdir(dirname($outputPath), 0755, true);
            }

            $processor->saveAs($outputPath);

            TermGenerationLog::create([
                'term_template_id' => $template->id,
                'asset_id' => $asset->id,
                'assigned_user_id' => $assignedUser?->id,
                'generated_by_id' => Auth::id(),
                'generated_file_name' => $fileName,
                'term_type' => $template->term_type,
            ]);

            return response()->download($outputPath, $fileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Erro ao gerar termo', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Erro ao gerar o documento: '.$e->getMessage());
        }
    }

    public function history(int $assetId)
    {
        $logs = TermGenerationLog::with(['template', 'generatedBy', 'assignedUser'])
            ->where('asset_id', $assetId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($logs);
    }

    // Adicionar em TermGeneratorController.php

    public function status(int $assetId, TermEligibilityService $eligibility)
    {
        $asset = Asset::with(['model.category'])->findOrFail($assetId);

        $assignedUser = null;
        if ($asset->assigned_to && strpos((string) $asset->assigned_type, 'User') !== false) {
            $assignedUser = User::find($asset->assigned_to);
        }

        return response()->json([
            'generate_url' => route('terms.generate', $asset->id),
            'templates' => $eligibility->statusForAsset($asset, $assignedUser?->id),
        ]);
    }

    private function buildContext(Asset $asset, $user): array
    {
        $today = now();

        $translateDbName = function ($name) {
            if (empty($name)) {
                return null;
            }
            $key = 'general.'.$name;
            $translated = trans($key);

            return $translated === $key ? $name : $translated;
        };

        $assetAccessories = collect();
        try {
            $assetAccessories = $asset->assignedAccessories()->get();
        } catch (\Throwable $e) {
        }

        $userAccessories = collect();
        if ($user) {
            try {
                $userAccessories = $user->accessories()->get();
            } catch (\Throwable $e) {
            }
        }

        $allAccessories = $assetAccessories->merge($userAccessories);

        $accessoriesArray = $allAccessories->map(function ($a) use ($translateDbName) {
            $obj = $a->name ? $a : ($a->accessory ?? $a);

            return [
                'acc_name' => $obj->name ?: 'Sem nome',
                'acc_serial' => $obj->serial ?: '',
                'acc_category' => $translateDbName($obj->category?->name) ?: '',
                'acc_qty' => (string) ($obj->pivot->qty ?? $a->qty ?? 1),
            ];
        })->toArray();

        $componentsArray = [];
        try {
            $componentsArray = $asset->components()->get()->map(function ($c) use ($translateDbName) {
                $obj = $c->name ? $c : ($c->component ?? $c);

                return [
                    'comp_name' => $obj->name ?: 'Sem nome',
                    'comp_serial' => $obj->serial ?: '',
                    'comp_category' => $translateDbName($obj->category?->name) ?: '',
                    'comp_qty' => (string) ($obj->pivot->assigned_qty ?? $c->qty ?? 1),
                ];
            })->toArray();
        } catch (\Throwable $e) {
        }

        $userAssetsArray = [];
        if ($user) {
            try {
                $userAssetsArray = Asset::with(['model.category'])
                    ->where('assigned_to', $user->id)
                    ->where('assigned_type', 'App\\Models\\User')
                    ->where('id', '!=', $asset->id)
                    ->get()
                    ->map(fn ($a) => [
                        'ua_tag' => $a->asset_tag ?: 'Sem tag',
                        'ua_name' => $a->name ?: '',
                        'ua_model' => $a->model?->name ?: '',
                        'ua_serial' => $a->serial ?: '',
                        'ua_category' => $translateDbName($a->model?->category?->name) ?: 'Sem categoria',
                    ])->toArray();
            } catch (\Throwable $e) {
            }
        }

        return [
            'user_name' => $user ? trim($user->first_name.' '.$user->last_name) : 'N/A',
            'user_email' => $user?->email ?: 'N/A',
            'user_employee_num' => $user?->employee_num ?: 'N/A',
            'user_department' => $user?->department?->name ?: 'N/A',
            'user_location' => $user?->location?->name ?: 'N/A',

            'asset_tag' => $asset->asset_tag ?: '',
            'asset_name' => $asset->name ?: 'N',
            'asset_serial' => $asset->serial ?: '',
            'asset_model' => $asset->model?->name ?: '',
            'asset_category' => $translateDbName($asset->model?->category?->name) ?: '',
            'asset_manufacturer' => $asset->model?->manufacturer?->name ?: '',

            'date_today' => $today->format('d/m/Y'),
            'date_today_long' => $today->translatedFormat('d \d\e F \d\e Y'),

            'accessories' => $accessoriesArray,
            'components' => $componentsArray,
            'user_assets' => $userAssetsArray,
        ];
    }
}
