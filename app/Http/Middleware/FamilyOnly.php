<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** Pages for the family, not for helpers (the `family` gate): everything but the calendar and settings. */
class FamilyOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Gate::denies('family'), 403, __('Hierdie deel van Tribe is vir die familie.'));

        return $next($request);
    }
}
