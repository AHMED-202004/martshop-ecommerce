<?php

namespace App\Support;

final class DetectBrand
{
    /**
     * يحاول استخراج الماركة من الاسم/السلَج
     */
    public static function from(string $name, string $slug = ''): ?string
    {
        $hay = mb_strtolower($name . ' ' . $slug, 'UTF-8');

        $map = [
            'adidas'        => ['adidas', 'أديداس'],
            'Skechers'      => ['skechers', 'سكيتشرز'],
            'Reebok'        => ['reebok'],
            'PUMA'          => ['puma'],
            'Under Armour'  => ['under armour', 'project rock', 'ua-'],
            'HI-TEC'        => ['hi-tec', 'hitec'],
            'Diadora'       => ['diadora'],
            'Rock'          => [' rock '], // انتبه للمسافة حتى لا تصطدم بكلمات أخرى
            'RUE BROCA'     => ['rue broca'],
            'Abdan'         => ['abdan', 'عِبدان'],
            'BLX'           => ['blx'],
            'Sami Boutique' => ['sami boutique'],
            'Generic'       => ['generic'],
        ];

        foreach ($map as $brand => $needles) {
            foreach ($needles as $n) {
                if (mb_strpos($hay, $n) !== false) {
                    return $brand;
                }
            }
        }

        return null; // لم تُعرف
    }
}
