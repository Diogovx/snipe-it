<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\TermGenerationLog;
use App\Models\TermTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;

class TermGeneratorApiController extends Controller
{
    /**
     * Retorna a lista de templates disponíveis via API.
     */
    public function templates()
    {
        $templates = TermTemplate::where('active', true)
            ->get(['id', 'name','term_type', 'allowed_categories'])
            ->map(fn($t) => [
                'id'                 => $t->id,
                'name'               => $t->name,
                'term_type'          => $t->term_type, 
                'allowed_categories' => $t->allowed_categories ?? [],
            ]);

        return response()->json(['templates' => $templates]);
    }

    /**
     * Gera um termo via API e retorna o arquivo para download.
     *
     * Payload esperado:
     * {
     * "employee_num": "12345",
     * "template_id": 1,         // ID do template
     * "asset_tag": "ABC-001"    // opcional: se omitido, usa o primeiro ativo compatível
     * }
     */
    public function generate(Request $request)
    {
        $request->validate([
            'employee_num' => 'required|string',
            'template_id'  => 'required|integer|exists:term_templates,id',
            'asset_tag'    => 'nullable|string',
        ]);

        // 1. Busca o usuário pela matrícula
        $user = User::where('employee_num', $request->input('employee_num'))->first();

        if (!$user) {
            return response()->json([
                'error' => 'Usuário não encontrado para a matrícula: ' . $request->input('employee_num')
            ], 404);
        }

        // 2. Busca o template
        $template = TermTemplate::where('id', $request->input('template_id'))
            ->where('active', true)
            ->first();

        if (!$template) {
            return response()->json(['error' => 'Template não encontrado ou inativo.'], 404);
        }

        if (!file_exists($template->storagePath())) {
            return response()->json(['error' => 'Arquivo de template não encontrado no servidor.'], 500);
        }

        // 3. Busca o ativo
        $assetQuery = Asset::where('assigned_to', $user->id)
            ->where('assigned_type', 'App\\Models\\User')
            ->with(['model.category']);

        if ($request->filled('asset_tag')) {
            $assetQuery->where('asset_tag', $request->input('asset_tag'));
        }

        $asset = $assetQuery->first();

        if (!$asset) {
            return response()->json([
                'error' => 'Nenhum ativo encontrado para este usuário' .
                    ($request->filled('asset_tag') ? ' com a tag: ' . $request->input('asset_tag') : '') . '.'
            ], 404);
        }

        // 4. Verifica compatibilidade de categoria
        $categoryName = $asset->model?->category?->name;
        if (!$template->isCompatibleWith($categoryName)) {
            return response()->json([
                'error' => "Template '{$template->name}' não é compatível com a categoria '{$categoryName}'."
            ], 422);
        }

        // 5. Gera o documento
        try {
            $processor = new TemplateProcessor($template->storagePath());
            
            // Usa o novo buildContext blindado
            $context   = $this->buildContext($asset, $user);

            $arraysToProcess = ['accessories', 'components', 'user_assets'];

            // Processa blocos de repetição nativo do PhpWord
            foreach ($arraysToProcess as $blockName) {
                if (isset($context[$blockName]) && is_array($context[$blockName])) {
                    $rows = $context[$blockName];

                    try {
                        if (empty($rows)) {
                            // Se a lista estiver vazia, apaga o bloco
                            $processor->cloneBlock($blockName, 0, true, false);
                        } else {
                            // Clona o bloco e pede pro PhpWord numerar as variáveis
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
                        Log::info("API: Bloco '{$blockName}' ignorado ou não encontrado: " . $e->getMessage());
                    }
                    unset($context[$blockName]);
                }
            }

            // Variáveis simples
            foreach ($context as $key => $value) {
                if (!is_array($value)) {
                    try {
                        $processor->setValue($key, htmlspecialchars((string) $value));
                    } catch (\Throwable) {}
                }
            }

            // Salva o arquivo
            $fullName   = trim($user->first_name . ' ' . $user->last_name);
            $safeUser   = preg_replace('/[^a-zA-Z0-9 ._-]/', '', $fullName);
            $safeName   = preg_replace('/[^a-zA-Z0-9 ._-]/', '', $template->name);
            $fileName   = "{$asset->asset_tag} - {$safeName} - {$safeUser}.docx";
            $outputPath = storage_path("app/term-output/{$fileName}");

            if (!is_dir(dirname($outputPath))) {
                mkdir(dirname($outputPath), 0755, true);
            }

            $processor->saveAs($outputPath);

            // Registra o log
            TermGenerationLog::create([
                'term_template_id'    => $template->id,
                'asset_id'            => $asset->id,
                'assigned_user_id'    => $user->id,
                'generated_by_id'     => Auth::id() ?? $user->id,
                'generated_file_name' => $fileName,
            ]);

            // Retorna o arquivo para download
            return response()->download($outputPath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('API: Erro ao gerar termo', [
                'employee_num' => $request->input('employee_num'),
                'template_id'  => $template->id,
                'error'        => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Erro ao gerar o documento: ' . $e->getMessage()
            ], 500);
        }
    }

    private function buildContext(Asset $asset, User $user): array
    {
        $today = now();

        // Tradutor Inteligente
        $translateDbName = function ($name) {
            if (empty($name)) return null;
            $key = 'general.' . $name;
            $translated = trans($key);
            return $translated === $key ? $name : $translated;
        };

        // 1. BUSCAR ACESSÓRIOS DO ATIVO E DO USUÁRIO
        $assetAccessories = collect();
        try {
            $assetAccessories = $asset->assignedAccessories()->get();
        } catch (\Throwable $e) {}

        $userAccessories = collect();
        if ($user) {
            try {
                $userAccessories = $user->accessories()->get(); 
            } catch (\Throwable $e) {}
        }

        $allAccessories = $assetAccessories->merge($userAccessories);

        $accessoriesArray = $allAccessories->map(function($a) use ($translateDbName) {
            $obj = $a->name ? $a : ($a->accessory ?? $a);
            return [
                'acc_name'     => $obj->name ?: 'Sem nome', 
                'acc_serial'   => $obj->serial ?: '', 
                'acc_category' => $translateDbName($obj->category?->name) ?: '',
                'acc_qty'      => (string)($obj->pivot->qty ?? $a->qty ?? 1),
            ];
        })->toArray();

        // 2. BUSCAR COMPONENTES
        $componentsArray = [];
        try {
            $componentsArray = $asset->components()->get()->map(function($c) use ($translateDbName) {
                $obj = $c->name ? $c : ($c->component ?? $c);
                return [
                    'comp_name'     => $obj->name ?: 'Sem nome', 
                    'comp_serial'   => $obj->serial ?: '',
                    'comp_category' => $translateDbName($obj->category?->name) ?: '',
                    'comp_qty'      => (string)($obj->pivot->assigned_qty ?? $c->qty ?? 1),
                ];
            })->toArray();
        } catch (\Throwable $e) {}

        // 3. BUSCAR OUTROS ATIVOS DO USUÁRIO
        $userAssetsArray = [];
        if ($user) {
            try {
                $userAssetsArray = Asset::with(['model.category'])
                    ->where('assigned_to', $user->id)
                    ->where('assigned_type', 'App\\Models\\User')
                    ->where('id', '!=', $asset->id)
                    ->get()
                    ->map(fn($a) => [
                        'ua_tag'      => $a->asset_tag ?: 'Sem tag',
                        'ua_name'     => $a->name ?: '',
                        'ua_model'    => $a->model?->name ?: '',
                        'ua_serial'   => $a->serial ?: '',
                        'ua_category' => $translateDbName($a->model?->category?->name) ?: 'Sem categoria',
                    ])->toArray();
            } catch (\Throwable $e) {}
        }

        return [
            // Dados do usuário
            'user_name'          => $user ? trim($user->first_name . ' ' . $user->last_name) : 'N/A',
            'user_email'         => $user?->email ?: 'N/A',
            'user_employee_num'  => $user?->employee_num ?: 'N/A',
            'user_department'    => $user?->department?->name ?: 'N/A',
            'user_location'      => $user?->location?->name ?: 'N/A',

            // Dados do ativo principal
            'asset_tag'          => $asset->asset_tag ?: '',
            'asset_name'         => $asset->name ?: 'N/A',
            'asset_serial'       => $asset->serial ?: '',
            'asset_model'        => $asset->model?->name ?: '',
            'asset_category'     => $translateDbName($asset->model?->category?->name) ?: '',
            'asset_manufacturer' => $asset->model?->manufacturer?->name ?: '',

            // Datas
            'date_today'         => $today->format('d/m/Y'),
            'date_today_long'    => $today->translatedFormat('d \d\e F \d\e Y'),
            
            // Listas
            'accessories'        => $accessoriesArray,
            'components'         => $componentsArray,
            'user_assets'        => $userAssetsArray
        ];
    }
}