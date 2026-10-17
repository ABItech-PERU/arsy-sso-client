<?php

namespace Arsy\SSOClient\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Vincula a mano una cuenta local previa (sin `sso_id`) con su usuario de
 * account. El login nunca enlaza por correo; este comando es la única vía.
 */
class LinkUserCommand extends Command
{
    protected $signature = 'arsy-sso:link-user
        {email : Correo de la cuenta local sin vincular}
        {sso_id : Id (UUID) del usuario en account}';

    protected $description = 'Vincula una cuenta local previa con su usuario de account';

    public function handle(): int
    {
        $userModelClass = config('arsy-sso.user_model');
        $email = (string) $this->argument('email');
        $ssoId = (string) $this->argument('sso_id');

        if (! Str::isUuid($ssoId)) {
            $this->error('El sso_id debe ser el UUID del usuario en account.');

            return self::FAILURE;
        }

        $user = $userModelClass::where('email', $email)->first();

        if (! $user) {
            $this->error("No hay una cuenta local con el correo {$email}.");

            return self::FAILURE;
        }

        if ($user->sso_id !== null) {
            $this->error('La cuenta local ya está vinculada; su sso_id no se reescribe.');

            return self::FAILURE;
        }

        if ($userModelClass::where('sso_id', $ssoId)->exists()) {
            $this->error('Ese sso_id ya pertenece a otra cuenta local.');

            return self::FAILURE;
        }

        $user->forceFill(['sso_id' => $ssoId])->save();

        Log::notice('[SSO] Cuenta local vinculada por comando.', [
            'user_id' => $user->getKey(),
            'sso_id' => $ssoId,
        ]);

        $this->info("Cuenta local {$user->getKey()} vinculada a {$ssoId}.");

        return self::SUCCESS;
    }
}
