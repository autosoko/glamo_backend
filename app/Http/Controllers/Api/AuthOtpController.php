<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\AuthController as V1AuthController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthOtpController extends Controller
{
    public function requestOtp(Request $request, V1AuthController $authController)
    {
        return $authController->requestOtp($request);
    }

    public function verifyOtp(Request $request, V1AuthController $authController)
    {
        return $authController->verifyOtp($request);
    }
}
