<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ValidatePaginationParameters
{
    private const MAX_PAGE = 10000;

    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->query() as $key => $value) {
            if (! is_string($key) || preg_match('/\A(?:page|[a-z0-9_]+_page)\z/i', $key) !== 1) {
                continue;
            }

            $page = is_string($value) || is_int($value) ? (string) $value : '';
            if (($page !== '0' && preg_match('/\A[1-9][0-9]*\z/', $page) !== 1)
                || (int) $page > self::MAX_PAGE) {
                throw ValidationException::withMessages([
                    $key => 'رقم الصفحة غير صالح أو يتجاوز الحد المسموح.',
                ]);
            }
        }

        return $next($request);
    }
}
