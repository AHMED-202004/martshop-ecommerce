<?php

namespace App\Enums;

enum MerchantDocumentType: string
{
    case IdentityFront = 'identity_front';
    case IdentityBack = 'identity_back';
    case PersonalPhoto = 'personal_photo';
    case IdentitySelfie = 'identity_selfie';
    case StorePhoto = 'store_photo';
    case WarehousePhoto = 'warehouse_photo';
}
