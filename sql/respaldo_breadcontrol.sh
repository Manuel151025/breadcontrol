#!/usr/bin/env bash
#
# Respaldo de la base de datos de BreadControl.
#
# Se ejecuta EN EL HOST del VPS (no dentro del contenedor): necesita acceso a
# Docker y debe escribir en un disco que sobreviva a los despliegues. El código
# de la aplicación vive dentro de la imagen, así que este archivo se copia al
# servidor una vez; el original versionado es este, en el repositorio.
#
#   Instalación y programación: docs/respaldos.md
#   Uso:  ./respaldo_breadcontrol.sh [carpeta_destino]
#
# Decisiones que conviene entender:
#
#  - `set -euo pipefail`: el script aborta ante cualquier error. Un respaldo que
#    falla a medias y devuelve "correcto" es peor que no tener respaldo, porque
#    da una confianza que no corresponde.
#  - `--single-transaction`: toma una foto coherente sin bloquear las tablas, de
#    modo que la panadería puede seguir vendiendo mientras corre.
#  - Se verifica el marcador final del volcado. Un archivo truncado también pesa
#    y también parece un respaldo; solo ese marcador prueba que terminó.
#  - Rotación: 7 diarias y 4 semanales. Sin ella, el disco se llena en silencio
#    y los respaldos dejan de escribirse justo cuando más falta harían.

set -euo pipefail

DESTINO="${1:-$HOME/respaldos/breadcontrol}"
RETENCION_DIARIAS=7
RETENCION_SEMANALES=4

# ── Localizar el contenedor de la base ──────────────────────────────────────
# Dokploy le pone un sufijo aleatorio que cambia en cada despliegue, así que se
# busca por prefijo en vez de escribir el nombre completo.
DB=$(docker ps --format '{{.Names}}' | grep '^breadcontrol-breadcontroldb' | head -1 || true)
if [ -z "$DB" ]; then
    echo "ERROR: no se encontró el contenedor de la base de datos." >&2
    exit 1
fi

mkdir -p "$DESTINO/diarias" "$DESTINO/semanales"

FECHA=$(date +%F_%H%M)
ARCHIVO="$DESTINO/diarias/breadcontrol_${FECHA}.sql.gz"

# ── Volcado ─────────────────────────────────────────────────────────────────
# Las credenciales se leen DENTRO del contenedor: nunca aparecen en la línea de
# comandos del host ni en el historial.
docker exec "$DB" sh -c '
    mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" \
        --single-transaction \
        --routines --triggers --events \
        --databases "$MYSQL_DATABASE"
' 2>/dev/null | gzip -9 > "$ARCHIVO"

# ── Verificación ────────────────────────────────────────────────────────────
if ! gunzip -c "$ARCHIVO" 2>/dev/null | tail -5 | grep -q "Dump completed"; then
    echo "ERROR: el respaldo quedó incompleto; se descarta $ARCHIVO" >&2
    rm -f "$ARCHIVO"
    exit 1
fi

# El esquema ronda las 30 tablas. Un volcado con menos de 20 significa que algo
# se interrumpió, aunque el marcador final estuviera presente.
TABLAS=$(gunzip -c "$ARCHIVO" | grep -c "^CREATE TABLE" || true)
if [ "$TABLAS" -lt 20 ]; then
    echo "ERROR: solo $TABLAS tablas en el volcado. Se descarta $ARCHIVO" >&2
    rm -f "$ARCHIVO"
    exit 1
fi

# El respaldo contiene hashes de contraseñas y datos personales de los clientes.
chmod 600 "$ARCHIVO"

# ── Copia semanal (domingos) ────────────────────────────────────────────────
# Las diarias cubren el error reciente; las semanales, el error que se descubre
# tarde — un dato corrupto hace diez días ya no estaría en ninguna diaria.
if [ "$(date +%u)" -eq 7 ]; then
    cp -p "$ARCHIVO" "$DESTINO/semanales/"
fi

# ── Rotación ────────────────────────────────────────────────────────────────
podar() {
    local carpeta="$1" conservar="$2"
    ls -1t "$carpeta"/breadcontrol_*.sql.gz 2>/dev/null \
        | tail -n +"$((conservar + 1))" \
        | xargs -r rm -f
}
podar "$DESTINO/diarias"   "$RETENCION_DIARIAS"
podar "$DESTINO/semanales" "$RETENCION_SEMANALES"

PESO=$(du -h "$ARCHIVO" | cut -f1)
echo "$(date '+%F %T')  OK  $ARCHIVO  ($PESO, $TABLAS tablas)"
