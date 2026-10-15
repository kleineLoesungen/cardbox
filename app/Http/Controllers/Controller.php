<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    /**
     * htmx answer to cards changed from the dictionary (own or moderated):
     * a toast; the sheet closes and the results reload.
     */
    protected function dictionaryChanged(int $count, string $done): Response
    {
        return response()
            ->view('partials.toast-oob', ['toast' => [
                'kind' => 'success',
                'message' => $count === 1 ? "Karte {$done}." : "{$count} Karten {$done}.",
            ]])
            ->header('HX-Trigger', 'sheet-close, dictionary-refresh');
    }
}
