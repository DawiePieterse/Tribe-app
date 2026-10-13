<?php

namespace App\Http\Middleware;

use App\Models\Member;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pages for the family, not for helpers: lists, recipes, polls, calendars, Stuur na Tribe, Familie. */
class FamilyOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = $request->user();
        abort_if($member instanceof Member && $member->isHelper(), 403, __('Hierdie deel van Tribe is vir die familie.'));

        return $next($request);
    }
}
