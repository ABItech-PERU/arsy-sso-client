<?php

namespace Arsy\SSOClient\Tests\Fixtures;

/**
 * Usuario con el `createToken()` de Sanctum, que el paquete no instala.
 */
class TokenUser extends User
{
    // La tabla de Laravel exige `password`; un usuario SSO no lo trae.
    protected $attributes = ['password' => 'sin-uso'];

    public function createToken(string $name): object
    {
        return (object) ['plainTextToken' => 'token-local'];
    }
}
