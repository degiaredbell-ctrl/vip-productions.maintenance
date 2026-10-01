<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $templates = ChecklistTemplate::with('items')->orderBy('machine_type')->get()->map(function ($t) {
            return [
                'id' => $t->id,
                'machine_type' => $t->machine_type,
                'name' => $t->name,
                'is_default' => $t->is_default,
                'items' => $t->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'category' => $item->category,
                        'name' => $item->name,
                        'spec' => $item->spec,
                        'sort_no' => $item->sort_no,
                    ];
                }),
            ];
        });

        return Inertia::render('Admin/Templates', [
            'templates' => $templates,
            'types' => collect(\App\Enums\MachineType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->toArray(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'machine_type' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.category' => ['required', 'string', 'max:50'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.spec' => ['nullable', 'string', 'max:255'],
        ]);

        $template = ChecklistTemplate::create([
            'machine_type' => $validated['machine_type'],
            'name' => $validated['name'],
            'is_default' => false,
        ]);

        foreach ($validated['items'] as $i => $item) {
            ChecklistTemplateItem::create([
                'template_id' => $template->id,
                'category' => $item['category'],
                'name' => $item['name'],
                'spec' => $item['spec'] ?? null,
                'sort_no' => $i + 1,
            ]);
        }

        return redirect()->back()->with('success', 'Template berhasil ditambahkan.');
    }

    public function destroy(Request $request, ChecklistTemplate $template): RedirectResponse
    {
        if ($template->is_default) {
            return redirect()->back()->with('error', 'Template default tidak dapat dihapus.');
        }

        $template->delete();
        return redirect()->back()->with('success', 'Template berhasil dihapus.');
    }
}
