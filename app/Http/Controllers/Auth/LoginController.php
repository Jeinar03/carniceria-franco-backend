<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /** Cada rol entra a su primera pantalla permitida. */
    protected function redirectTo()
    {
        return \App\Support\PanelRoles::homeUrl(auth()->user());
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Solo entran usuarios activos: un empleado bloqueado desde Usuarios ya no puede iniciar sesion.
     */
    protected function credentials(\Illuminate\Http\Request $request)
    {
        return array_merge($request->only($this->username(), 'password'), ['status' => 'ACTIVE']);
    }
}


