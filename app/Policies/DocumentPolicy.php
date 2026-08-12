<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DocStatus;
use App\Models\Document;
use App\Models\User;

/**
 * Owners and admins both work with documents. The yard attendant never sees
 * them at all.
 */
final class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->seesSales();
    }

    public function view(User $user, Document $document): bool
    {
        return $user->role->seesSales();
    }

    public function create(User $user): bool
    {
        return $user->role->seesSales();
    }

    /**
     * An issued document is a record of what was sent, so editing one is
     * limited to the owner. Drafts are fair game for anyone in sales.
     */
    public function update(User $user, Document $document): bool
    {
        if (! $user->role->seesSales()) {
            return false;
        }

        return $document->status === DocStatus::Draft || $user->isOwner();
    }

    /** Only ever a draft, and only by the owner. Issued numbers are not reused. */
    public function delete(User $user, Document $document): bool
    {
        return $user->isOwner() && $document->status === DocStatus::Draft;
    }

    public function issue(User $user, Document $document): bool
    {
        return $user->role->seesSales() && $document->status === DocStatus::Draft;
    }

    public function convert(User $user, Document $document): bool
    {
        return $user->role->seesSales()
            && $document->doc_type->expires()
            && $document->status->isConvertible()
            && ! $document->hasLapsed();
    }

    public function reprice(User $user, Document $document): bool
    {
        return $user->role->seesSales() && $document->doc_type->expires();
    }
}
