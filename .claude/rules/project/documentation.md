---
paths:
  - "docs/**"
  - "README.md"
  - "CHANGELOG.md"
---

# Documentación (arsy/sso-client)

DIRECTIVA: adapta `tech/documentation.md` a un `docs/` plano que lee
quien instala el paquete en un satélite.

## Reglas

1. **Dueños.**
   - El contrato con account (rutas, payloads, firmas, variables) es
     `docs/satelites.md` de account; aquí se enlaza, no se copia.
   - `docs/guia-de-uso.md` guía la instalación en un satélite, con
     variables (§2), auto-login (§5), webhooks (§6), eventos (§7) y
     llamadas a account (§8).
   - `docs/construccion.md` explica las decisiones de diseño.
2. **Cambio visible.** Una variable, ruta, evento o comportamiento nuevo
   actualiza la sección de la guía que le toca y `CHANGELOG.md` en el
   mismo cambio.
3. **CHANGELOG.** Keep a Changelog en español (`Añadido`, `Cambiado`,
   `Corregido`, `Eliminado`, `Seguridad`) bajo `## [Sin publicar]`.
   Publicar sigue `tech/laravel-package.md` regla 7, con el commit
   `docs(changelog): publicar la versión X.Y.Z`.
4. **Documento nuevo.** Plano en `docs/`, en español, en `kebab-case` y
   con el tipo como prefijo (`guia-`, `referencia-`, `explicacion-`),
   enlazado desde la sección «Documentación» del README.
5. **Excepciones de `tech/documentation.md`.** No hay `docs/README.md`,
   porque la sección «Documentación» del README es el índice.
   `docs/construccion.md` conserva su nombre sin prefijo porque el README
   lo enlaza; renombrarlo corrige ese enlace en el mismo cambio.
