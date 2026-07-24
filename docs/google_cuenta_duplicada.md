# Riesgo de cuenta duplicada al entrar con Google — análisis

**Fecha:** 2026-07-23 · **Rama:** `master` · **Estado:** **RESUELTO en la causa raíz** (2026-07-24).

> ## Solución implementada (2026-07-24)
> Se atacó la causa raíz (había 0 aprendices y 0 pedidos reales, nada que reconciliar):
> - **Email obligatorio y único en el registro tradicional** (Opción 1): `registro.php`
>   pide correo, validado con `filter_var` y con índice único `uq_cliente_email`
>   (los NULL no cuentan, así que las 47 cuentas históricas no se rompen).
>   Migración `sql/migraciones/2026-07-24_01_email_unico_cliente.sql`.
> - **Enlace automático en Google**: `googleCallback` ya enlazaba por email; ahora que el
>   registro guarda email, sí encuentra la cuenta existente en vez de duplicar. Caso borde
>   añadido: si el correo pertenece a una cuenta con **otro** `google_id`, se rechaza
>   (`?error=google_conflicto`), no se reasigna.
> - **Pantalla intermedia** `completar_email.php` (Opción 2 para lo existente): las cuentas
>   de portal (usuario+contraseña) sin email lo proporcionan la próxima vez que entran,
>   antes de continuar (bandera `falta_email` en `login()`/`requireCliente()`).
> - **NO se migraron ni fusionaron** cuentas existentes (decisión del dueño). Las 46
>   cuentas históricas sin usuario no se tocaron.
>
> Lo que sigue abajo es el análisis original que motivó la decisión.

## Pregunta (Tarea 4)

> ¿Cómo identifica `google_callback.php` a un usuario que vuelve? ¿Por `google_id`, por
> `email`, o por ambos? Si alguien se registró normal (sin email) y luego entra con Google,
> ¿se le crea una cuenta duplicada?

## Cómo identifica al usuario (evidencia)

`PortalClienteController::googleCallback()` ([controllers/PortalClienteController.php:176-196](../controllers/PortalClienteController.php#L176)) intenta, en orden:

1. **Por `google_id`** — `getClienteByGoogleId($google_id)`
   (`SELECT * FROM cliente WHERE google_id = ? AND activo = 1`).
2. **Por `email`** (solo si Google entrega un email verificado) — `getClienteByEmail($email)`
   (`SELECT * FROM cliente WHERE email = ? AND activo = 1`). Si encuentra, **enlaza** el
   `google_id` a esa cuenta (`vincularGoogleId`).
3. **Si no encuentra por ninguno de los dos → crea una cuenta nueva** con
   `registrarClienteGoogle($google_id, $email, $nombre, $foto_url)`.

Es decir: identifica **por `google_id` o por `email`**, en ese orden.

## Sí, se crea una cuenta duplicada — escenario confirmado

Un usuario que se registró de forma tradicional (por `registro.php`) tiene:
`usuario`, `contrasena_hash`, **`email = NULL`**, `google_id = NULL`.

Cuando ese mismo usuario entra luego con **"Continuar con Google"**:

1. `getClienteByGoogleId(sub)` → **NULL** (su `google_id` es NULL).
2. Google sí entrega su correo (p. ej. `juan@gmail.com`), pero `getClienteByEmail('juan@gmail.com')`
   busca `WHERE email = 'juan@gmail.com'` y **la cuenta tradicional tiene `email = NULL`** → **no
   coincide** → NULL. (El paso de enlace por email nunca dispara porque no hay email que comparar.)
3. → Cae en el paso 3 y **crea una cuenta nueva de Google**.

**Resultado:** la persona termina con **dos cuentas** para sí misma: una con `usuario`/contraseña
(email NULL) y otra con `google_id`/email. Iniciar sesión por un camino u otro la lleva a cuentas
distintas.

### Confirmación con los datos de producción
De 48 cuentas, **47 tienen `email = NULL`** y solo 1 tiene email (la única cuenta Google actual);
solo 1 tiene `usuario`. Es decir, **el registro tradicional nunca guarda email**, así que **no
existe ninguna clave de identidad compartida** entre el registro tradicional y el de Google. El
enlace por email (paso 2) es, en la práctica, código que hoy casi nunca puede dispararse.

## Causa raíz

No hay un identificador común entre los dos métodos de acceso:
- Registro tradicional → identidad = `usuario` (y `email` queda NULL porque el formulario no lo pide).
- Google → identidad = `google_id` / `email`.

Sin `email` (u otro dato) compartido, el sistema no puede reconocer que la cuenta tradicional y la
de Google son la misma persona, y crea una nueva.

## Consecuencias

- Pedidos, estado `es_aprendiz`, `id_instructor` y `cupo_semanal` quedan **repartidos entre las dos
  cuentas**. Si canjea el código de aprendiz en la cuenta de Google, la cuenta tradicional **no**
  queda vinculada (y viceversa).
- El instructor podría ver **aprendices duplicados**.
- **Interacción con el trabajo nuevo (tareas 1-3):** el campo de canje en `completar_perfil.php`
  funciona, pero se aplica a la **cuenta nueva de Google** (la duplicada). Si la persona ya tenía
  una cuenta tradicional, el vínculo de aprendiz queda en la de Google, no en la original. El campo
  es correcto; el problema es la duplicación de identidad, que es independiente.

## Opciones (para decidir aparte — NO implementadas)

1. **Pedir email en el registro tradicional y enlazar por email al entrar con Google.**
   El paso 2 de `googleCallback` ya enlaza por email; bastaría con que el registro guarde el email.
   *Limitación:* no arregla las 47 cuentas existentes sin email (no se puede adivinar su correo);
   solo previene duplicados futuros de cuentas nuevas que sí registren email.

2. **Flujo de "vincular cuenta existente" en el callback de Google.**
   Si no hay match por `google_id` ni por `email`, antes de crear una cuenta nueva, ofrecer
   "¿Ya tienes cuenta? Inicia sesión con tu usuario y contraseña para vincular Google". Más trabajo
   de UX pero cubre las cuentas existentes sin email.

3. **Herramienta de fusión de duplicados** (back-office): unir dos `cliente` en uno (reasignar
   `pedido_cliente`, `codigo_aprendiz`, etc.). Útil para limpiar los duplicados que ya existan.

4. **Aceptar la limitación** (no hacer nada) y documentarla para los usuarios.

**Recomendación:** es un problema de **identidad de cuentas**, distinto del canje de código. No se
implementó ningún cambio aquí; queda para decidir por separado (probablemente Opción 1 para
prevenir + Opción 2 o 3 para lo existente).
