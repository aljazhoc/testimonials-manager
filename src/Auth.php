<?php

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['user_id']);
    }

    public static function require(): void
    {
        if (!self::check()) {
            header('Location: ' . APP_BASE . '/?action=login');
            exit;
        }
    }

    public static function login(string $username, string $password): bool
    {
        $db   = Database::get();
        $stmt = $db->prepare('SELECT id, username, password_hash FROM tm_users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            return true;
        }
        return false;
    }

    public static function logout(): void
    {
        self::start();
        session_destroy();
    }

    public static function username(): string
    {
        return $_SESSION['username'] ?? '';
    }
}
