<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect(Auth::homePath());
        }

        $this->render('auth/login', ['title' => 'Sign in'], 'guest');
    }

    public function login(): void
    {
        $this->verifyCsrf();

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        with_old(['email' => $email]);

        if ($email === '' || $password === '') {
            validation_errors(['login' => ['Please enter your email and password.']]);
            $this->redirect('/login');
        }

        if (!Auth::attempt($email, $password)) {
            validation_errors(['login' => ['The provided credentials are incorrect.']]);
            $this->redirect('/login');
        }

        clear_old();
        $this->redirect(Auth::homePath(), 'Welcome back.');
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        Auth::logout();
        $this->redirect('/login', 'You have been signed out.');
    }
}
