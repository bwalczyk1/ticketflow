<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\LoginUser;
use App\Services\Auth\LogoutUser;
use App\Services\Auth\RegisterUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser->handle(
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password'),
        );

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request, LoginUser $loginUser): UserResource
    {
        $user = $loginUser->handle($request->validated('email'), $request->validated('password'));

        return new UserResource($user);
    }

    public function logout(Request $request, LogoutUser $logoutUser): Response
    {
        $logoutUser->handle($request->user());

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
