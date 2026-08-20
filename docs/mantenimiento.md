# Calendario de actualización y mantenimiento

**Punto C13 de la lista de verificación previa a producción** (informe técnico del 2026-08-12): *«Las dependencias y componentes del servidor tienen un calendario de actualización»*.

Ocultar la versión de un componente (R-05) reduce lo que un escaneo automatizado aprende del sistema, pero **no protege de nada por sí solo**: una versión con una vulnerabilidad conocida sigue siendo vulnerable aunque no se anuncie. Lo que protege es actualizar.

---

## 1. Inventario, a 2026-08-15

| Componente | Versión | Dónde se cambia | Fin de soporte |
|---|---|---|---|
| **PHP** | 8.2 | `Dockerfile` (repositorio) | **31 dic 2026** ⚠️ |
| **MySQL** | 8.0 | Dokploy (imagen `mysql:8`) | abr 2026 ⚠️ *(verificar)* |
| Apache | el de `php:8.2-apache` | `Dockerfile` | con la imagen base |
| Nginx | 1.24.0 | VPS (`apt`) | rama estable |
| Traefik | 3.6.7 | Dokploy | activo |
| Dokploy | 0.29.12 | su propio actualizador | activo |
| PHPStan | 2.2.8 | `composer.json` | activo |
| PHPUnit | 11.5.56 | `composer.json` | activo |
| Bootstrap Icons | 1.11.3 | autohospedado en `assets/` | activo |

### Las dos urgencias reales

**PHP 8.2 deja de recibir parches de seguridad el 31 de diciembre de 2026.** Faltan unos cuatro meses. A partir de esa fecha, cualquier vulnerabilidad que se descubra en PHP 8.2 **no se corrige**, y el sistema queda expuesto sin que nada lo avise. La migración a PHP 8.3 u 8.4 debe planificarse **antes de noviembre**, no en diciembre.

El cambio en sí es una línea del `Dockerfile` (`FROM php:8.4-apache`), pero requiere verificación: las 154 pruebas y PHPStan en nivel 10 son precisamente la red que permite hacerlo con confianza. Conviene probarlo primero en local con la nueva imagen antes de tocar producción.

**MySQL 8.0 alcanzó su fin de vida en abril de 2026** según el calendario publicado por Oracle. Conviene verificarlo antes de actuar, porque esa fecha está en el límite de lo que puedo confirmar. La sucesora natural es **MySQL 8.4 LTS**, con soporte hasta 2032. Es una actualización mayor del motor: exige respaldo previo (ver `docs/respaldos.md`), probar el esquema y verificar que las consultas del sistema siguen siendo válidas — recordar que la diferencia entre MariaDB y MySQL 8 en `only_full_group_by` ya rompió una pantalla en producción.

---

## 2. Cadencia

### Cada mes — 15 minutos

```bash
composer outdated --direct     # dependencias de PHP
composer audit                 # vulnerabilidades conocidas en ellas
```

Las actualizaciones de parche y menores se aplican directamente: `composer update`, correr las pruebas, y publicar si pasan. Así se hizo con PHPStan 2.2.7 → 2.2.8 el 2026-08-15: las 154 pruebas y el análisis en nivel 10 siguieron limpios.

### Antes y después de cada despliegue — 1 minuto

```bash
php scripts/migraciones.php
```

Responde si la base tiene aplicadas todas las migraciones de `sql/migraciones/`.
Conviene mirarlo **antes** de publicar —para saber si el código nuevo necesita un
esquema que todavía no está— y **después**, para confirmar que quedó al día.

Hasta el 2026-08-20 esa pregunta no se podía responder sin exportar la estructura
de los dos lados y compararla a mano. Ese día costó cuatro comandos, dos idas y
vueltas y una falsa alarma: dos tablas parecían distintas y solo cambiaba el
orden de las columnas, porque MySQL 8 y MariaDB ordenan el guion bajo al revés.

El script **no aplica** nada, a propósito. En MySQL el DDL hace commit implícito,
así que una migración que falle a mitad deja hechas las sentencias anteriores y
no hay vuelta atrás automática; conviene aplicarlas de una en una y sabiendo
dónde se quedó si algo sale mal. Además la imagen de la aplicación no lleva
cliente `mysql`, de modo que en el servidor no podría ejecutarlas aunque quisiera.

El flujo para una migración nueva es:

```bash
# 1) aplicarla contra la base
docker exec -i "$DB" sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" breadcontrol' < sql/migraciones/ARCHIVO.sql

# 2) registrarla
php scripts/migraciones.php --marcar=ARCHIVO.sql
```

También avisa de dos situaciones que suelen pasar desapercibidas: una migración
**alterada** (el archivo cambió después de aplicarse, así que lo que hay en la
base se generó con otro contenido) y una **huérfana** (registrada, pero el
archivo ya no existe porque se borró o se renombró).

### Cada trimestre — 1 hora

- **Imágenes base**: reconstruir con la última versión de parche de `php:8.2-apache` y `mysql:8`. Basta con volver a desplegar; Docker traerá la imagen actualizada.
- **Sistema del VPS**: `sudo apt update && sudo apt list --upgradable`. Ojo: el VPS es compartido, así que actualizar Nginx afecta a los 24 sitios. Coordinar y usar `nginx -t` antes de recargar.
- **Repetir la prueba de restauración** de `docs/respaldos.md`. Un procedimiento que funcionó una vez puede dejar de hacerlo cuando cambia el esquema o la versión del motor.

### Cada año — planificado

- Revisar el fin de soporte de PHP y MySQL y planificar el salto de versión mayor.
- Revisar si Snyk reporta hallazgos nuevos que antes no aparecían.

### Continuo, sin intervención

El CI ejecuta **Snyk en cada `push`** (`.github/workflows/ci.yml`): analiza las dependencias y el código, y **falla la construcción** ante un hallazgo de severidad alta. Es la única parte de este calendario que no depende de que alguien se acuerde.

---

## 3. Cómo actualizar cada cosa

| Qué | Cómo | Riesgo |
|---|---|---|
| Dependencias PHP | `composer update`, correr pruebas, `git push` | Bajo — el CI lo verifica |
| Versión de PHP | cambiar `FROM` en `Dockerfile`, probar en local, `git push` | **Medio** — probar antes las 154 pruebas y PHPStan |
| Bootstrap Icons | volver a ejecutar la descarga de `assets/fonts/` y `assets/css/bootstrap-icons.css` | Bajo |
| MySQL | cambiar la imagen en Dokploy | **Alto** — respaldo previo obligatorio |
| Nginx / sistema | `apt upgrade` en el VPS | **Alto** — afecta a los 24 sitios |
| Traefik / Dokploy | desde el propio Dokploy | **Alto** — enruta todos los sitios |

La regla que se ha seguido en este proyecto y conviene mantener: **lo que está en el repositorio se actualiza con confianza, porque el CI lo verifica; lo que está en el servidor compartido se actualiza con copia de seguridad, validación previa y en horario tranquilo.**

---

## 4. Próximas acciones con fecha

| Cuándo | Qué |
|---|---|
| ~~Este mes~~ | ~~PHPStan 2.2.7 → 2.2.8~~ — hecho el 2026-08-15 |
| Antes de noviembre 2026 | **Migrar a PHP 8.3 u 8.4** antes del fin de soporte de 8.2 |
| Cuando se pueda planificar | Verificar el estado de soporte de MySQL 8.0 y evaluar el salto a 8.4 LTS |
| Cada trimestre | Repetir la prueba de restauración |
