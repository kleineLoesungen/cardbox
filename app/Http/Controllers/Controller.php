<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * A query parameter as trimmed text. Anything that is not a string
     * (e.g. ?q[]=x) counts as empty instead of causing an
     * "array to string" error.
     */
    protected function queryText(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? trim($value) : '';
    }
}
