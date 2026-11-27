# Contribuir

Gracias por querer mejorar el paquete. Esta guía resume lo que revisa la
integración continua, para que tu PR pase a la primera.

## Preparar el entorno

```bash
git clone https://github.com/abitech-dev/abitech-sso-laravel.git
cd abitech-sso-laravel
composer install
composer test
```

Las pruebas usan Orchestra Testbench y un Redis simulado
(`Abitech\Sso\Testing`). No necesitan una central en marcha.

## Antes de abrir el PR

- Cada cambio de comportamiento lleva su prueba en `tests/`.
- `composer validate --strict` pasa sin avisos.
- Una variable de entorno nueva entra en `config/sso.php` y en la
  [guía de uso](docs/guia-de-uso.md).
- Los ejemplos usan datos de prueba, nunca credenciales reales.

## Mensajes de commit

Se usan [Conventional Commits](https://www.conventionalcommits.org/es/v1.0.0/),
como `feat(webhook): cerrar la sesión de un usuario suspendido` o
`fix(callback): validar el estado de OAuth`.

Cada cambio que nota quien usa el paquete suma su línea en la sección
`[Sin publicar]` del `CHANGELOG.md`, en el mismo PR.

## Publicación

Las versiones siguen [SemVer](https://semver.org/lang/es/) sobre la API
pública, que son la configuración, las rutas, los eventos, las migraciones
y los servicios. Un `fix` sube el parche, un `feat` la menor y un cambio
incompatible la mayor.

1. Un PR cambia `## [Sin publicar]` por la versión con su fecha, por ejemplo
   `## [2.1.0] - 2026-12-01`, y deja arriba una sección `[Sin publicar]`
   vacía.
2. Al unirlo, la Action crea la etiqueta `v2.1.0` y el release de GitHub con
   esas notas.
3. Packagist publica la etiqueta sola.
