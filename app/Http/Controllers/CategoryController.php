<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\MembershipCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories', ['categories' => MembershipCategory::query()->withCount('members')->orderBy('name')->get()]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        MembershipCategory::query()->create($request->validated());

        return redirect()->route('categories.index')->with('success', 'Category created.');
    }

    public function update(StoreCategoryRequest $request, MembershipCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('success', 'Category updated. Existing transaction prices are unchanged.');
    }

    public function destroy(MembershipCategory $category): RedirectResponse
    {
        DB::transaction(function () use ($category): void {
            $category = MembershipCategory::query()->lockForUpdate()->findOrFail($category->id);
            if ($category->members()->exists() || $category->transactions()->exists()) {
                throw ValidationException::withMessages(['category' => 'This category is in use. Deactivate it to preserve member history.']);
            }
            $category->delete();
        }, 3);

        return redirect()->route('categories.index')->with('success', 'Category deleted.');
    }
}
