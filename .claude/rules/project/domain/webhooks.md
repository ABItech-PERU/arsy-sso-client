---
paths:
  - "src/Http/Controllers/SsoWebhookController.php"
  - "src/Services/{SsoWebhookHandlerService,SsoSessionIndex}.php"
  - "src/Listeners/**"
  - "src/Events/{SsoUserLoggedOutViaWebhook,SsoWebhook*}.php"
---

# Webhooks de account y cierre de sesión único

DIRECTIVA: account avisa y el satélite obedece; un aviso con firma
válida cierra sesiones o sincroniza, y el resto lo decide el satélite
escuchando el evento.

## Tabla anti-vanilla

| Prohibido | Usar |
| --- | --- |
| Firmar `json_encode($request->all())` | `hash_hmac('sha256', $request->getContent(), $secreto)` y `hash_equals()` |
| `Session::flush()`, `Auth::logout()` o borrar filas de `sessions` | `$this->sessionIndex->revoke($userId, $idpSessionId)` |
| Escribir en el usuario más que `email` desde un webhook | evento con `data` crudo para el satélite |

## Reglas

1. **Contrato.** Forma, eventos y firma los fija account en
   `docs/satelites.md` §4. `X-Arsy-Signature` es el HMAC SHA-256 del
   cuerpo crudo con `SSO_WEBHOOK_SECRET`; sin secreto o sin firma, 401.
   La firma v1 con `t=…,v1=…` y 300 s es de `arsy/platform-client` y no
   se agrega aquí.
2. **Eventos.**

   | `event_type` | Acción | Evento del paquete |
   | --- | --- | --- |
   | `app.revoked`, `session.revoked`, `session.ended`, `user.logout` | cierra la sesión de `data.session_id`, o todas sin él | `SsoUserLoggedOutViaWebhook` |
   | `user.updated` | sincroniza `email` | `SsoWebhookUserUpdated` con `data` |
   | `user.suspended` | cierra todas sus sesiones | `SsoWebhookUserSuspended` |
   | `user.deleted` | cierra todas y llama `delete()` | `SsoWebhookUserDeleted` |
   | otro | nada; responde 200 | — |

   Un tipo nuevo entra en el `switch` de `SsoWebhookHandlerService`, en
   esta tabla y en `docs/guia-de-uso.md` §6 en el mismo cambio.
3. **Cierre de sesión único.** Exige `SESSION_DRIVER=redis`.
   - `SessionIndexSubscriber` escribe, en cada `Authenticated`, el hash
     `sso:user_sessions:{user_id}` (sesión local → sesión de account) en
     la conexión de sesiones y lo renueva con `session.lifetime`; en
     `Logout` quita la sesión.
   - `revoke()` de `SsoSessionIndex` destruye cada sesión con
     `Session::getHandler()`, sin recorrer todas.
   - Con otro driver o con `auto_revoke_sessions` en `false`, solo se
     dispara `SsoUserLoggedOutViaWebhook` y el satélite cierra.
4. **El evento va primero.** `SsoUserLoggedOutViaWebhook` se dispara
   antes de destruir, para que el satélite actúe aunque el índice no
   aplique.
5. **Redis en pruebas.** Un comando Redis nuevo en `SsoSessionIndex` se
   implementa también en `src/Testing/InMemoryRedisConnection.php`
   (`project/testing.md`).
