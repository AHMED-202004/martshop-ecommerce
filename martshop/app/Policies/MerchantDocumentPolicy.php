<?php

namespace App\Policies;

use App\Models\MerchantDocument;
use App\Models\User;

class MerchantDocumentPolicy
{
    public function view(User $user, MerchantDocument $document): bool
    {
        return $document->merchant->user_id === $user->id
            || $user->hasPermission('merchant-documents.view');
    }

    public function review(User $user, MerchantDocument $document): bool
    {
        return $document->merchant->user_id !== $user->id
            && $user->hasPermission('merchant-documents.review');
    }
}
