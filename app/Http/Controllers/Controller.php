<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    protected function me(): Member
    {
        $member = Auth::user();
        abort_unless($member instanceof Member, 403);

        return $member;
    }

    /**
     * A household-only record from another household answers 404, not 403, so its existence stays private.
     *
     * @param  object{household_id: int|null}&object  $record
     */
    protected function ensureVisible(object $record): void
    {
        abort_unless(method_exists($record, 'isVisibleTo') && $record->isVisibleTo($this->me()), 404);
    }

    protected function ensureAdmin(): void
    {
        abort_unless($this->me()->is_admin, 403);
    }
}
