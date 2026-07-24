# Cuentas creadas por Google — campos vacíos y pantallas afectadas

**Fecha:** 2026-07-24 · **Rama:** `master`

## Qué campos quedan vacíos en una cuenta de Google

`PortalClienteModel::registrarClienteGoogle()` sólo inserta `nombre`, `tipo='mostrador'`,
`email`, `google_id`, `foto_url`, `activo`. Por tanto, una cuenta creada por Google tiene
en **NULL / valor por defecto**:

| Campo | Estado en cuenta Google |
|-------|--------------------------|
| `usuario` | **NULL** |
| `contrasena_hash` | **NULL** |
| `telefono` | **NULL** |
| `pin_recuperacion` | **NULL** |
| `codigo_recuperacion`, `codigo_expira` | NULL |
| `id_instructor`, `fecha_aprendiz` | NULL |
| `es_aprendiz` | 0 (default) |
| `cupo_semanal` | 20000 (default) |
| `notas`, `es_beneficiaria` | NULL / 0 |

## Pantallas / funciones que asumían esos campos

| Lugar | Qué asumía | Efecto | Estado |
|-------|------------|--------|--------|
| `views/portal/perfil.php` (campo Usuario) | `usuario` no nulo, pasado a `htmlspecialchars` | Deprecated de PHP impreso en el `value` del input | **CORREGIDO** (Punto 1 `?? ''` + Punto 3 muestra "Accedes con Google") |
| `views/portal/perfil.php` (campo Teléfono) | `telefono` no nulo | Mismo Deprecated | **CORREGIDO** (`?? ''`) |
| `perfil.php` tarjeta "Seguridad" (cambiar contraseña) | `contrasena_hash` no nulo (`password_verify(..., $hash)`) | Con hash NULL: Deprecated + siempre "contraseña incorrecta" (inservible) | **CORREGIDO** (se oculta la tarjeta para Google + guard en el controlador) |
| `perfil.php` tarjeta "PIN de recuperación" | Requiere la contraseña actual | Inservible para Google (no tiene contraseña) | **CORREGIDO** (se oculta + guard) |
| `login()` tradicional (`getClienteByUsuario`) | Busca por `usuario` | Una cuenta Google (usuario NULL) no aparece; no puede iniciar sesión tradicional | Correcto por diseño: inician con Google. No falla. |
| `recuperarPass()` (recuperación de contraseña) | Busca por `usuario` | Cuenta Google → "Usuario no encontrado" | No falla, pero no orienta a usar Google. Ver "pendiente" abajo. |
| Resto de vistas que muestran `usuario`/`telefono` | valores no nulos en `htmlspecialchars` | Deprecated inline | **CORREGIDO** en bloque (Punto 1, todas las vistas con `?? ''`) |

Además, con la lógica de entorno (Punto 2), en producción **ningún** Deprecated/Warning
llega a la pantalla aunque se cuele alguno: van sólo al log.

## Decisión tomada en perfil.php (Punto 3) — la opción más simple

Se eligió **indicar claramente que la cuenta accede con Google**, en vez de mostrar un
campo `usuario` vacío editable o construir un flujo para "crear usuario/contraseña"
(que mezclaría dos métodos de autenticación y añade más superficie):

- El campo **"Usuario"** se reemplaza por un indicador de solo lectura
  **"Accedes con Google · &lt;email&gt;"** cuando `usuario` está vacío.
- Las tarjetas de **Contraseña** y **PIN** se **ocultan** para cuentas Google (no aplican
  sin contraseña) y se muestra una nota: *"Tu cuenta usa Google… no necesitas contraseña
  ni PIN en BreadControl"*.
- Defensa en profundidad: aunque estén ocultas, `cambiar_pass` y `guardar_pin` en el
  controlador rechazan con mensaje claro si `contrasena_hash` está vacío.
- El **teléfono** sí queda editable (es opcional y el usuario puede completarlo).

## Pendiente (menor, a decidir aparte)

- `recuperarPass()` no distingue "usuario no existe" de "esa persona entra con Google".
  Podría, si el correo/usuario corresponde a una cuenta Google, sugerir "Inicia sesión con
  Google". No se implementó aquí (no bloquea nada; la persona simplemente usa el botón de
  Google).
