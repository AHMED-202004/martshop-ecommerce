<?php

namespace App\Services;

class LegacyCatalogNormalizer
{
    public function inferBrand(string $name): ?string
    {
        $brands = [
            'adidas' => 'Adidas',
            'puma' => 'Puma',
            'skechers' => 'Skechers',
            'hush' => 'Hush Puppies',
            'diadora' => 'Diadora',
            'hi-tec' => 'HI-TEC',
            'reebok' => 'Reebok',
            'vans' => 'Vans',
            'nike' => 'Nike',
            'nautica' => 'Nautica',
            'rasasi' => 'Rasasi',
            'dareej' => 'Rasasi',
            'bogart' => 'Jacques Bogart',
            'silver scent' => 'Jacques Bogart',
            'armaf' => 'Armaf',
            'club de nuit' => 'Armaf',
            'ajmal' => 'Ajmal',
            'arabiyat' => 'Arabiyat',
            'lattafa' => 'Lattafa',
            'rue broca' => 'Rue Broca',
            'hooked' => 'Rue Broca',
            'awaan' => 'Awaan',
            'maharjan' => 'Maharjan',
            'mahrajan' => 'Maharjan',
            'golf' => 'GOLF & HORSE',
            'horse' => 'GOLF & HORSE',
        ];

        $lowerName = mb_strtolower($name);
        foreach ($brands as $needle => $brand) {
            if (str_contains($lowerName, $needle)) {
                return $brand;
            }
        }

        return null;
    }

    public function inferColor(string $name): ?string
    {
        $colors = [
            'أبيض' => 'White', 'white' => 'White',
            'أسود' => 'Black', 'black' => 'Black',
            'رمادي' => 'Grey', 'gray' => 'Grey', 'grey' => 'Grey',
            'بني' => 'Brown', 'brown' => 'Brown',
            'عسلي' => 'Beige', 'beige' => 'Beige', 'بيج' => 'Beige',
            'كحلي' => 'Navy', 'navy' => 'Navy',
            'أزرق' => 'Blue', 'blue' => 'Blue',
            'أحمر' => 'Red', 'red' => 'Red',
            'أخضر' => 'Green', 'green' => 'Green',
            'زيتي' => 'Olive', 'olive' => 'Olive',
        ];

        $lowerName = mb_strtolower($name);
        foreach ($colors as $needle => $color) {
            if (str_contains($lowerName, mb_strtolower($needle))) {
                return $color;
            }
        }

        return null;
    }
}
