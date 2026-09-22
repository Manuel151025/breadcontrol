# Política de seguridad

## Versiones con soporte

Este proyecto se despliega desde la rama `main`, y es la única que recibe
correcciones. Las versiones etiquetadas quedan como historia: si encuentras algo
en una anterior, compruébalo antes contra `main`.

| Versión | Soporte |
|---|---|
| `main` (última etiqueta publicada) | ✅ |
| Etiquetas anteriores | ❌ |

## Cómo reportar una vulnerabilidad

**No abras un issue público.** Un issue es visible para cualquiera desde el
momento en que se publica, incluido quien quiera aprovechar el fallo antes de que
esté corregido.

Usa **Security → Report a vulnerability** en este repositorio, que abre un aviso
privado visible solo para quien mantiene el proyecto.

Ayuda mucho incluir:

- Qué falla y qué permite hacer (leer datos de otra cuenta, saltarse un permiso,
  tomar una cuenta...).
- Los pasos exactos para reproducirlo, con la cuenta y el rol que usaste.
- La versión o el commit sobre el que lo probaste.

Recibirás respuesta en cuanto sea posible. Este es un proyecto académico
mantenido por una sola persona: no hay un equipo de guardia ni un programa de
recompensas, pero todo reporte se atiende y se te da crédito en el `CHANGELOG.md`
si quieres.

## Qué NO hacer

- **No pruebes contra el sitio publicado** (`breadcontrol.manuelcardenas.online`):
  tiene datos de una panadería real. Levanta tu propia instancia con
  `docker-compose.yml`, que trae todo lo necesario.
- Nada de pruebas de denegación de servicio, fuerza bruta masiva ni ingeniería
  social a las personas que usan el sistema.

## Qué protege el proyecto hoy

Para que el reporte apunte a algo que de verdad falte, esto ya está cubierto y
verificado con pruebas:

- Contraseñas con bcrypt y una política única de complejidad.
- Límite de intentos en el acceso **y** en la recuperación de acceso: 5 por cuenta
  cada 15 minutos y 20 por IP, guardados en base de datos.
- La recuperación no revela si una cuenta existe.
- Token CSRF en toda petición POST que modifica datos, verificado antes de decidir
  qué acción se pidió.
- Consultas preparadas en todos los accesos a la base, sin emulación.
- Salidas escapadas con `htmlspecialchars`.
- Autorización por objeto en el portal: cada pedido se comprueba contra la cuenta
  en sesión antes de mostrarlo, editarlo, cancelarlo o pagarlo.
- Cookie de sesión con `HttpOnly`, `SameSite=Lax` y `Secure` fuera del entorno
  local; el identificador se regenera al iniciar sesión.
- En cada cambio: `composer audit`, Gitleaks sobre todo el historial y Semgrep
  (`p/php` y `p/owasp-top-ten`).

Lo que se sabe que **falta** está inventariado, sin adornos, en
[`LIMITACIONES_Y_TRABAJO_FUTURO.md`](LIMITACIONES_Y_TRABAJO_FUTURO.md). Si tu
hallazgo ya está ahí, dilo igualmente: saber que alguien más lo encuentra ayuda a
priorizarlo.
