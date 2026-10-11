<?php

namespace App\Http\Controllers;

use App\Services\LearningSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Lernplan": presets or a custom plan. Rules in LearningSettingsService.
 */
class LearningSettingsController extends Controller
{
    public function __construct(private LearningSettingsService $settings) {}

    public function show(Request $request): View
    {
        return view('learn.settings', ['current' => $this->settings->current($request->user())]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->settings->save($request->user(), $request->only('plan', 'box_count', 'day_gaps'));

        return redirect('/learn/settings')->with('toast', ['kind' => 'success', 'message' => 'Lernplan gespeichert.']);
    }
}
