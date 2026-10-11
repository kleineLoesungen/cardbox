<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /** Alle Benutzer, ohne Passwortdaten. Bewusst nur lesend. */
    public function index(UserService $users): AnonymousResourceCollection
    {
        return UserResource::collection($users->list());
    }
}
