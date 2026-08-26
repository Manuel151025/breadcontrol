#!/usr/bin/env bash
# ============================================================
#  Verificación de cabeceras de seguridad — BreadControl
#
#  Convierte las recomendaciones R-01 a R-05 del informe técnico del 2026-08-12
#  en una comprobación ejecutable. Hasta ahora esas correcciones vivían en
#  archivos de configuración (.htaccess, Dockerfile y el bloque `server` del
#  Nginx del VPS) y NADA impedía que un despliegue las revirtiera en silencio.
#  Ya pasó una vez: la cookie salía sin `Secure` porque Traefik descartaba
#  X-Forwarded-Proto, y solo se descubrió mirando a mano.
#
#  Uso:
#    scripts/verificar_cabeceras.sh URL [--perfil contenedor|produccion]
#
#  Perfiles:
#    contenedor  — lo que aporta la propia aplicación: .htaccess (R-03, R-04),
#                  Dockerfile (R-05, X-Powered-By) y el código de sesión
#                  (R-01, la cookie). Es todo lo comprobable sobre la imagen
#                  recién construida.
#    produccion  — todo lo anterior más la capa que pone el Nginx del VPS, que
#                  está DELANTE del contenedor y por tanto no existe en la
#                  imagen: HSTS (R-02) y ocultar la versión (R-05).
#                  Es el perfil por defecto.
#
#  Sale con código 0 si todo pasa, 1 si alguna comprobación falla.
# ============================================================

# Sin `set -e` a propósito: interesa ejecutar TODAS las comprobaciones y ver el
# panorama completo, no abortar en el primer fallo.
set -uo pipefail

URL="${1:-}"
PERFIL="produccion"
COOKIE_NOMBRE="${COOKIE_NOMBRE:-panaderia_session}"   # SESSION_NOMBRE en config/app.php

shift $(( $# > 0 ? 1 : 0 ))
while [ $# -gt 0 ]; do
    case "$1" in
        --perfil) PERFIL="${2:-}"; shift 2 ;;
        *) echo "Opción desconocida: $1" >&2; exit 2 ;;
    esac
done

if [ -z "$URL" ]; then
    echo "Uso: $0 URL [--perfil contenedor|produccion]" >&2
    exit 2
fi
if [ "$PERFIL" != "contenedor" ] && [ "$PERFIL" != "produccion" ]; then
    echo "Perfil no válido: «$PERFIL» (usa contenedor o produccion)" >&2
    exit 2
fi

RESPUESTA="$(mktemp)"
trap 'rm -f "$RESPUESTA"' EXIT

fallos=0
avisos=0

# Colores solo si la salida es una terminal; en el registro de CI estorban.
if [ -t 1 ]; then
    VERDE=$'\033[32m'; ROJO=$'\033[31m'; AMARILLO=$'\033[33m'; FIN=$'\033[0m'
else
    VERDE=''; ROJO=''; AMARILLO=''; FIN=''
fi

ok()    { printf '  %s✓%s %s\n' "$VERDE" "$FIN" "$1"; }
fallo() { printf '  %s✗%s %s\n' "$ROJO"  "$FIN" "$1"; fallos=$(( fallos + 1 )); }
aviso() { printf '  %s!%s %s\n' "$AMARILLO" "$FIN" "$1"; avisos=$(( avisos + 1 )); }

# Valor de una cabecera, insensible a mayúsculas. Vacío si no viene.
valor_de() {
    grep -i "^$1:" "$RESPUESTA" | head -1 | cut -d: -f2- | tr -d '\r' | sed 's/^ *//'
}

# exigir "descripción" "Cabecera" ["patrón extendido opcional"]
exigir() {
    local desc="$1" cab="$2" patron="${3:-}" v
    v="$(valor_de "$cab")"
    if [ -z "$v" ]; then
        fallo "$desc — la cabecera $cab no viene en la respuesta"
    elif [ -n "$patron" ] && ! printf '%s' "$v" | grep -qiE "$patron"; then
        fallo "$desc — $cab vale «$v», se esperaba que casara con «$patron»"
    else
        ok "$desc"
    fi
}

exigir_ausente() {
    local desc="$1" cab="$2" v
    v="$(valor_de "$cab")"
    if [ -n "$v" ]; then
        fallo "$desc — $cab sigue presente y vale «$v»"
    else
        ok "$desc"
    fi
}

# ── Descargar la respuesta ───────────────────────────────────
echo
echo "Verificando cabeceras de $URL (perfil: $PERFIL)"
echo

if ! curl -sS -D "$RESPUESTA" -o /dev/null --max-time 20 "$URL"; then
    echo "${ROJO}No se pudo obtener una respuesta de $URL${FIN}" >&2
    exit 1
fi

codigo="$(head -1 "$RESPUESTA" | awk '{print $2}')"
if [ "${codigo:-000}" -ge 400 ] 2>/dev/null; then
    # No se aborta: `Header always set` viaja también en respuestas de error, así
    # que las comprobaciones siguen siendo válidas. Pero conviene saberlo.
    aviso "La respuesta fue HTTP $codigo; las cabeceras se comprueban igual"
fi

# ── R-03 · Política de seguridad de contenido ────────────────
csp="$(valor_de Content-Security-Policy)"
if [ -z "$csp" ]; then
    fallo "R-03 · CSP activa — la cabecera Content-Security-Policy no viene"
else
    # Se comprueban las directivas que de verdad cierran algo y que un retoque
    # descuidado podría dejarse por el camino, no solo que la cabecera exista.
    faltan=""
    for directiva in "default-src 'self'" "frame-ancestors" "base-uri" "object-src 'none'" "form-action"; do
        printf '%s' "$csp" | grep -qF "$directiva" || faltan="$faltan; $directiva"
    done
    if [ -n "$faltan" ]; then
        fallo "R-03 · CSP completa — faltan directivas:${faltan#;}"
    else
        ok "R-03 · CSP activa y con las directivas clave"
    fi

    # Aviso, no fallo: es el punto 22 de LIMITACIONES, una deuda conocida y
    # documentada. Que se vea en cada ejecución evita que se olvide, pero
    # romper el CI por algo que ya está decidido y anotado solo genera ruido.
    if printf '%s' "$csp" | grep -qF "'unsafe-inline'"; then
        aviso "R-03 · la CSP conserva 'unsafe-inline' (punto 22 de LIMITACIONES)"
    fi
fi

# ── R-04 · Cabeceras defensivas ──────────────────────────────
exigir "R-04 · X-Content-Type-Options"  "X-Content-Type-Options" "^nosniff$"
exigir "R-04 · Referrer-Policy"         "Referrer-Policy"        "strict-origin-when-cross-origin"
exigir "R-04 · Permissions-Policy"      "Permissions-Policy"     "camera=\(\)"
exigir "R-04 · X-Frame-Options"         "X-Frame-Options"        "^(SAMEORIGIN|DENY)$"

# ── R-05 · No anunciar la versión de PHP ─────────────────────
exigir_ausente "R-05 · X-Powered-By retirada" "X-Powered-By"

# ── R-01 · La cookie de sesión ───────────────────────────────
#
# Es la comprobación que más importa de todas, y por eso se hace en los dos
# perfiles. El atributo Secure lo decide el código a partir de APP_ENV
# ([includes/sesion.php]) precisamente para NO depender de que la cadena
# Nginx→Traefik reenvíe X-Forwarded-Proto — que es lo que falló en su día.
# Comprobarlo también sobre el contenedor, servido por HTTP plano, es lo que
# convierte esa decisión en algo que un cambio futuro no puede deshacer en
# silencio: si alguien vuelve a deducir Secure de la petición, aquí falla.
linea="$(grep -i '^set-cookie:' "$RESPUESTA" | grep -i "$COOKIE_NOMBRE" | head -1 | tr -d '\r')"
if [ -z "$linea" ]; then
    fallo "R-01 · Cookie de sesión — la respuesta no trae $COOKIE_NOMBRE"
else
    faltan=""
    printf '%s' "$linea" | grep -qi 'secure'    || faltan="$faltan Secure"
    printf '%s' "$linea" | grep -qi 'httponly'  || faltan="$faltan HttpOnly"
    printf '%s' "$linea" | grep -qi 'samesite=' || faltan="$faltan SameSite"
    if [ -n "$faltan" ]; then
        fallo "R-01 · Cookie de sesión endurecida — faltan atributos:$faltan"
    else
        ok "R-01 · Cookie de sesión con Secure, HttpOnly y SameSite"
    fi
fi

# ── Capa del servidor web: solo existe delante del contenedor ──
if [ "$PERFIL" = "produccion" ]; then

    # R-02 · HSTS. Se exige un año porque ese es el valor ya desplegado; el
    # despliegue escalonado (max-age=300 primero) fue una medida temporal
    # mientras se comprobaba la renovación del certificado.
    hsts="$(valor_de Strict-Transport-Security)"
    if [ -z "$hsts" ]; then
        fallo "R-02 · HSTS activo — la cabecera Strict-Transport-Security no viene"
    else
        edad="$(printf '%s' "$hsts" | grep -oiE 'max-age=[0-9]+' | head -1 | cut -d= -f2)"
        if [ -z "$edad" ] || [ "$edad" -lt 31536000 ]; then
            fallo "R-02 · HSTS de al menos un año — max-age=${edad:-ausente}"
        else
            ok "R-02 · HSTS activo (max-age=$edad)"
        fi
    fi

    # R-05 · El servidor no debe publicar su número de versión.
    servidor="$(valor_de Server)"
    if printf '%s' "$servidor" | grep -qE '[0-9]+\.[0-9]+'; then
        fallo "R-05 · Servidor sin versión — Server: $servidor"
    else
        ok "R-05 · Servidor sin versión (Server: ${servidor:-—})"
    fi
fi

# ── Resumen ──────────────────────────────────────────────────
echo
if [ "$fallos" -eq 0 ]; then
    if [ "$avisos" -gt 0 ]; then
        echo "${VERDE}Todas las comprobaciones pasaron${FIN} (con $avisos aviso(s) pendiente(s))"
    else
        echo "${VERDE}Todas las comprobaciones pasaron${FIN}"
    fi
    exit 0
fi
echo "${ROJO}$fallos comprobación(es) fallida(s)${FIN}"
exit 1
