<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        return Category::withCount('products')->orderBy('name')->get();
    }

    public function store(Request $request): Category
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'icon' => ['nullable', 'string', 'max:16'],   // emoji shown on the home tile
        ]);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(3));

        return Category::create($data);
    }

    public function update(Request $request, Category $category): Category
    {
        $category->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'icon' => ['nullable', 'string', 'max:16'],   // emoji shown on the home tile
        ]));

        return $category;
    }

    public function destroy(Category $category): array
    {
        $category->delete();

        return ['message' => 'Category deleted.'];
    }
}
