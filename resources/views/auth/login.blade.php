<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no">
    <title>Carnicería Franco - Login</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.jpeg') }}" />
    <link href="{{ asset('fonts/quicksand/quicksand.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('bootstrap/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/plugins.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/structure.css') }}" rel="stylesheet" type="text/css" class="structure" />
    <link href="{{ asset('assets/css/authentication/form-1.css') }}" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/app/auth/login.css') }}">
</head>

<body class="form">
    <div class="form-container">
        <div class="form-form">
            <div class="form-form-wrap">
                <div class="form-container">
                    <div class="form-content">
                        <div class="text-center">
                            <img src="{{ asset('images/logo.jpeg') }}" alt="Logo Carnicería Franco" class="login-logo">
                            <h1 class="login-title">Carnicería Franco</h1>
                            <p class="login-subtitle">Sistema de Gestión Comercial</p>
                        </div>

                        <form class="text-left" action="{{ route('login') }}" method="POST">
                            @csrf

                            <div class="form">
                                <div id="username-field" class="field-wrapper input" style="margin-bottom: 18px;">
                                    <label class="field-label">Correo Electrónico</label>
                                    <input id="email" name="email" type="email"
                                        class="form-control login-input @error('email') is-invalid @enderror"
                                        placeholder="tucorreo@ejemplo.com" value="{{ old('email') }}" required
                                        autocomplete="email" autofocus>
                                    @error('email')
                                        <span class="invalid-feedback d-block" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div id="password-field" class="field-wrapper input" style="margin-bottom: 22px;">
                                    <label class="field-label">Contraseña</label>
                                    <input id="password" name="password" type="password"
                                        class="form-control login-input @error('password') is-invalid @enderror"
                                        placeholder="••••••••" required autocomplete="current-password">
                                    @error('password')
                                        <span class="invalid-feedback d-block" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-wrapper">
                                    <button type="submit" class="btn btn-block btn-login" value="">
                                        Iniciar Sesión
                                    </button>
                                </div>
                            </div>
                        </form>

                        <p class="footer-copy">
                            © 2025 Carnicería Franco. Todos los derechos reservados.<br>
                            Versión 1.0
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
