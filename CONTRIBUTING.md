# Guía de contribución — BreadControl

¡Gracias por tu interés en contribuir! Esta guía explica cómo montar el entorno,
correr las pruebas y enviar cambios que el CI acepte.

## Requisitos del entorno

- PHP **8.2** o superior (con extensiones `pdo` y `pdo_mysql`)
- [Composer](https://getcomposer.org/) 2.x
- MySQL 8.0 / MariaDB 10.4+ (solo para las pruebas de integración)

## Montar el entorno de desarrollo

```bash
git clone https://github.com/Manuel151025/breadcontrol.git
cd breadcontrol
composer install
cp .env.example .env   # y completa tus credenciales locales
```

Para crear una base de datos local desde cero:

```sql
CREATE DATABASE panaderia_bd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
mysql -u root panaderia_bd < sql/init/01_esquema_base.sql
mysql -u root panaderia_bd < sql/init/90_semilla_ci.sql   # datos mínimos de prueba
```

## Ejecutar las pruebas

```bash
composer test              # suite completa (unitarias + integración)
composer test:unit         # solo unitarias (no requieren base de datos)
composer test:integracion  # solo integración (requieren MySQL)
vendor/bin/phpstan analyse # análisis estático (nivel 10, debe pasar limpio)
```

Notas:
- Las pruebas de integración corren cada caso dentro de una **transacción con
  rollback**: nunca dejan datos en tu base local.
- Si MySQL no está disponible, las pruebas de integración **se omiten** (no fallan).
- Puedes apuntar las pruebas a otra base con variables de entorno, que tienen
  prioridad sobre `.env`: `DB_NAME=otra_bd composer test`.

## Estándares del proyecto

- **Idioma:** código, comentarios, mensajes de commit y documentación en **español**.
- **Commits:** formato `tipo: descripción` en minúsculas. Tipos usados:
  `feat`, `fix`, `refactor`, `test`, `ci`, `docs`, `chore`.
  - Ejemplo: `fix: htmlspecialchars nunca recibe NULL en las vistas`
- **Lógica de negocio:** las reglas puras van en `helpers/` (ver
  `helpers/ReglasPortal.php`) con sus pruebas unitarias; el acceso a datos va en
  `models/`; las vistas no calculan reglas de negocio.
- **Vistas:** el CSS y el JS de cada vista viven en `assets/css/` y `assets/js/`
  (no inline), siguiendo el patrón `<link>` en la primera línea de la vista.
- **Seguridad:** toda salida escapada con `htmlspecialchars`, todo POST de
  mutación con token CSRF, y consultas siempre preparadas (PDO).

## Proceso para enviar cambios

1. Crea una rama desde `master`: `git checkout -b fix/descripcion-corta`
2. Haz tus cambios **con pruebas** (nuevas o actualizadas según aplique).
3. Verifica en local: `composer test` y `vendor/bin/phpstan analyse`.
4. Abre un Pull Request describiendo **qué** cambia y **por qué**.
5. El CI (GitHub Actions) debe pasar en verde sus 4 verificaciones:
   sintaxis PHP, PHPStan, pruebas unitarias y pruebas de integración.

## Reportar errores

Abre un [issue](https://github.com/Manuel151025/breadcontrol/issues) con:
- Pasos exactos para reproducir el problema
- Comportamiento esperado vs. observado
- Entorno (PHP, MySQL, navegador si aplica) y capturas si ayudan
