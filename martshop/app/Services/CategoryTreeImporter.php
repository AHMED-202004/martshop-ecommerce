<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryTreeImporter
{
    public function import(array $tree): int
    {
        return DB::transaction(function () use ($tree) {
            $imported = 0;
            $this->importLevel($tree, null, null, 0, $imported);

            return $imported;
        });
    }

    private function importLevel(
        array $nodes,
        ?Category $parent,
        ?string $parentPath,
        int $depth,
        int &$imported
    ): void {
        $sortOrder = 0;

        foreach ($nodes as $slug => $node) {
            $path = $parentPath ? $parentPath.'/'.$slug : $slug;
            $name = is_array($node)
                ? ($node['title'] ?? $this->pretty($slug))
                : (string) $node;

            $category = Category::query()->updateOrCreate(
                ['path' => $path],
                [
                    'parent_id' => $parent?->id,
                    'name' => $name,
                    'slug' => $slug,
                    'depth' => $depth,
                    'status' => 'active',
                    'sort_order' => $sortOrder,
                ]
            );
            $imported++;
            $sortOrder++;

            if (is_array($node) && isset($node['children']) && is_array($node['children'])) {
                $this->importLevel($node['children'], $category, $path, $depth + 1, $imported);
            }
        }
    }

    private function pretty(string $slug): string
    {
        return Str::of($slug)->replace(['-', '_'], ' ')->title()->toString();
    }
}
