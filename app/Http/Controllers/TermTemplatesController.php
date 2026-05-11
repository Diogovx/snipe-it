<?php

namespace App\Http\Controllers;

use App\Models\TermTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TermTemplatesController extends Controller
{
    public function index()
    {
        $this->authorize('superuser'); // só admins

        $templates = TermTemplate::orderBy('name')->get();
        return view('term-templates.index', compact('templates'));
    }

    public function create()
    {
        $this->authorize('superuser');
        $categories = \App\Models\Category::orderBy('name')->get();
        return view('term-templates.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $this->authorize('superuser');

        $request->validate([
            'name'     => 'required|string|max:255',
            'template' => 'required|file|mimes:docx|max:10240',
            // categorias: linha por linha no textarea
            'allowed_categories' => 'nullable|array',
            'allowed_categories.*' => 'string',
            'term_type'            => 'required|string|in:checkout,checkin',
            'depends_on' => 'nullable|string|max:100',
            // campos: JSON digitado pelo admin ou gerado pelo form
            'field_map' => 'nullable|string',
        ]);

        $file     = $request->file('template');
        $fileName = uniqid('tpl_') . '_' . $file->getClientOriginalName();

        $destDir = storage_path('app/term-templates');
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $file->move($destDir, $fileName);

        $categories = $request->input('allowed_categories');
        $fieldMap   = $this->parseFieldMap($request->input('field_map'));

        TermTemplate::create([
            'name'               => $request->input('name'),
            'file_name'          => $fileName,
            'term_type'          => $request->input('term_type'),
            'depends_on' => $request->input('depends_on') ?: null, 
            'allowed_categories' => $categories,
            'field_map'          => $fieldMap,
        ]);

        return redirect()->route('term-templates.index')
            ->with('success', 'Template criado com sucesso!');
    }

    public function edit(TermTemplate $termTemplate)
    {
        $this->authorize('superuser');
        $categories = \App\Models\Category::orderBy('name')->get();
        return view('term-templates.edit', compact('termTemplate', 'categories'));
    }

    public function update(Request $request, TermTemplate $termTemplate)
    {
        $this->authorize('superuser');

        $request->validate([
            'name'               => 'required|string|max:255',
            'template'           => 'nullable|file|mimes:docx|max:10240',
            'allowed_categories' => 'nullable|array',
            'allowed_categories.*' => 'string',
            'term_type'            => 'required|string|in:checkout,checkin',
            'depends_on' => 'nullable|string|max:100',
            'field_map'          => 'nullable|string',
            'active'             => 'boolean',
        ]);

        if ($request->hasFile('template')) {
            $oldPath = storage_path("app/term-templates/{$termTemplate->file_name}");
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }

            $file     = $request->file('template');
            $fileName = uniqid('tpl_') . '_' . $file->getClientOriginalName();
            $destDir  = storage_path('app/term-templates');
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $file->move($destDir, $fileName);
            $termTemplate->file_name = $fileName;
        }

        $termTemplate->update([
            'name'               => $request->input('name'),
            'file_name'          => $termTemplate->file_name,
            'term_type'          => $request->input('term_type'), 
            'allowed_categories' => $this->parseCategories($request->input('allowed_categories')),
            'depends_on' => $request->input('depends_on') ?: null,
            'field_map'          => $this->parseFieldMap($request->input('field_map')),
            'active'             => $request->boolean('active', true),
        ]);

        return redirect()->route('term-templates.index')
            ->with('success', 'Template atualizado!');
    }

    public function destroy(TermTemplate $termTemplate)
    {
        $this->authorize('superuser');

        Storage::disk('local')->delete("term-templates/{$termTemplate->file_name}");
        $termTemplate->delete();

        return redirect()->route('term-templates.index')
            ->with('success', 'Template removido.');
    }

    // ---

    private function parseCategories(mixed $raw): ?array
{
    if (empty($raw)) return null;

    // Vindo do formulário como array (checkboxes)
    if (is_array($raw)) {
        return array_values(array_filter(array_map('trim', $raw)));
    }

    // Fallback para string com newlines (caso de uso futuro via API)
    return collect(explode("\n", $raw))
        ->map(fn($l) => trim($l))
        ->filter()
        ->values()
        ->all();
}
    // TermTemplatesController.php

    public function logs(TermTemplate $termTemplate)
    {
        $this->authorize('superuser');

        $logs = $termTemplate->logs()
            ->with(['asset', 'assignedUser', 'generatedBy'])
            ->latest()
            ->paginate(25);

        return view('term-templates.logs', compact('termTemplate', 'logs'));
    }

    private function parseFieldMap(?string $raw): ?array
    {
        if (!$raw) return null;

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}