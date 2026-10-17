<?php

namespace Arsy\SSOClient\Tests\Fixtures;

/**
 * Usuario con el `createToken()` de Sanctum, que el paquete no instala.
 */
class TokenUser extends User
{
    public function createToken(string $name): object
    {
        return (object) ['plainTextToken' => 'token-local'];
    }
}
