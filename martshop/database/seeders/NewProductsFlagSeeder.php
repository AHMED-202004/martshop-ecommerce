<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class NewProductsFlagSeeder extends Seeder
{
    public function run(): void
    {
        // عدّل السلاج/المعرفات حسب منتجاتك
        Product::whereIn('slug', [
            
            'hush-puppies-casual-shoe',
            'skechers-dyna-lite',
            'dr-flexer-medic-leather-shoe',
        ])->update(['is_new' => true]);
        // شيل العلم عن الكل أولاً (اختياري)
        Product::query()->update(['is_new' => false]);

        // علّم آخر 24 منتج تمت إضافتهم كـ "وصلنا حديثاً"
        Product::orderBy('created_at', 'desc')
            ->limit(24)
            ->update(['is_new' => true]);
    }
}
