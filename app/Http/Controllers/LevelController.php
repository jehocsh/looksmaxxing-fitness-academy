<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLevelRequest;
use App\Models\MembershipLevel;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LevelController extends Controller
{
    public function index(): View
    {
        return view('admin.levels', ['levels' => MembershipLevel::query()->withCount('members')->orderBy('fee_cents')->get()]);
    }

    public function store(StoreLevelRequest $request): RedirectResponse
    {
        MembershipLevel::query()->create($this->data($request));

        return redirect()->route('levels.index')->with('success', 'Membership level created.');
    }

    public function update(StoreLevelRequest $request, MembershipLevel $level): RedirectResponse
    {
        $level->update($this->data($request));

        return redirect()->route('levels.index')->with('success', 'Level updated. Existing transaction prices are unchanged.');
    }

    private function data(StoreLevelRequest $request): array
    {
        $data = $request->safe()->except(['fee', 'benefits_text']);
        $data['fee_cents'] = MembershipService::centavos((string) $request->validated('fee'));
        $data['benefits'] = array_values(array_filter(array_map('trim', preg_split('/\\R/', $request->validated('benefits_text')))));

        return $data;
    }

    public function destroy(MembershipLevel $level): RedirectResponse
    {
        DB::transaction(function () use ($level): void {
            $level = MembershipLevel::query()->lockForUpdate()->findOrFail($level->id);
            if ($level->members()->exists() || $level->transactions()->exists()) {
                throw ValidationException::withMessages(['level' => 'This level is in use. Deactivate it to preserve member history.']);
            }
            $level->delete();
        }, 3);

        return redirect()->route('levels.index')->with('success', 'Membership level deleted.');
    }
}
