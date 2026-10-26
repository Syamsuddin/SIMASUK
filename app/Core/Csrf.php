<?php

declare(strict_types=1);

namespace App\Core;

/** Token CSRF per sesi, dibandingkan dengan hash_equals (docs/21). */
final class Csrf
{
    public const FIELD = '_token';
    private const KEY = '_csrf';

    public static function token(Session $session): string
    {
        $token = $session->get(self::KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $session->set(self::KEY, $token);
        }
        return $token;
    }

    public static function rotate(Session $session): string
    {
        $session->forget(self::KEY);
        return self::token($session);
    }

    public static function valid(Session $session, mixed $given): bool
    {
        $token = $session->get(self::KEY);
        return is_string($token) && $token !== '' && is_string($given) && hash_equals($token, $given);
    }
}
