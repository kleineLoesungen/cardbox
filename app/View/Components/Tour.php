<?php

namespace App\View\Components;

use App\Services\TourService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

/**
 * <x-tour :pages="['heute']" />: the not yet seen steps of these pages for
 * the person logged in (docs/specs/gefuehrte-tour.md). Renders nothing for
 * guests or when everything was seen — then Driver.js is not loaded either.
 */
class Tour extends Component
{
    /** @var list<array{id: string, target: string|null, title: string, text: string}> */
    public array $steps;

    /** @param  list<string>  $pages */
    public function __construct(TourService $tours, array $pages)
    {
        $user = Auth::user();
        $this->steps = $user === null ? [] : $tours->pending($user, $pages);
    }

    public function shouldRender(): bool
    {
        return $this->steps !== [];
    }

    public function render(): View
    {
        return view('components.tour');
    }
}
