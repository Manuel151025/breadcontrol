# Respaldo y restauración de la base de datos

**Punto C11 de la lista de verificación previa a producción** (informe técnico del 2026-08-12).

Hasta el 2026-08-15 el proyecto no tenía ningún procedimiento de respaldo: existían dos volcados sueltos hechos a mano en algún momento (`breadcontrol_respaldo.sql` y `backups/panaderia_bd_pre_fix.sql`), sin automatización, sin rotación y sin que nadie hubiera probado nunca restaurarlos. En la práctica, una corrupción de la base habría costado **todos los datos desde la última vez que alguien se acordó de exportar**.

> **Un respaldo que nadie ha restaurado no es un respaldo: es un archivo.** Por eso este documento incluye la prueba de restauración, no solo el comando para generarlo.

---

## 1. Qué hace el script

`sql/respaldo_breadcontrol.sh` genera un volcado comprimido, lo verifica y rota los antiguos.

| Decisión | Por qué |
|---|---|
| Se ejecuta en el **host**, no en el contenedor | El contenedor se reemplaza en cada despliegue y se llevaría los respaldos consigo |
| `--single-transaction` | Toma una foto coherente **sin bloquear** las tablas: la panadería puede seguir vendiendo mientras corre |
| Verifica el marcador `Dump completed` | Un archivo truncado también pesa y también parece un respaldo |
| Verifica que haya al menos 20 tablas | Detecta interrupciones que dejan el marcador pero no los datos |
| `chmod 600` | El volcado contiene hashes de contraseñas y datos personales de clientes |
| 7 diarias + 4 semanales | Las diarias cubren el error reciente; las semanales, el que se descubre tarde |
| Las credenciales se leen **dentro** del contenedor | Nunca aparecen en la línea de comandos del host ni en el historial |

---

## 2. Instalación en el VPS

El código de la aplicación vive dentro de la imagen de Docker, así que el host no tiene una copia del repositorio: el script se instala una vez.

```bash
mkdir -p ~/bin ~/respaldos/breadcontrol
nano ~/bin/respaldo_breadcontrol.sh      # pegar el contenido de sql/respaldo_breadcontrol.sh
chmod +x ~/bin/respaldo_breadcontrol.sh
```

Primera ejecución manual, para comprobar que funciona antes de programarlo:

```bash
~/bin/respaldo_breadcontrol.sh
ls -lh ~/respaldos/breadcontrol/diarias/
```

Debe imprimir una línea `OK` con el peso y el número de tablas.

---

## 3. Programación diaria

```bash
crontab -e
```

Añadir:

```cron
# Respaldo de BreadControl, todos los días a las 3:00 (America/Bogota)
0 3 * * * /home/manuel/bin/respaldo_breadcontrol.sh >> /home/manuel/respaldos/breadcontrol/registro.log 2>&1
```

Se elige la madrugada porque el volcado, aunque no bloquea, consume disco y CPU en un servidor compartido con otros proyectos.

**Comprobar a los dos días** que el archivo del día existe y que el registro no tiene errores:

```bash
tail -5 ~/respaldos/breadcontrol/registro.log
ls -lh ~/respaldos/breadcontrol/diarias/
```

---

## 4. Restauración

### 4.1 Restaurar sobre una base de prueba (recomendado siempre primero)

Nunca se restaura directamente sobre producción sin haber comprobado antes que el archivo sirve.

```bash
DB=$(docker ps --format '{{.Names}}' | grep '^breadcontrol-breadcontroldb' | head -1)
ARCHIVO=~/respaldos/breadcontrol/diarias/breadcontrol_FECHA.sql.gz

# Crear una base aparte
docker exec "$DB" sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS prueba_restauracion;"'

# Restaurar ahí, quitando las líneas que apuntan a la base original
gunzip -c "$ARCHIVO" | grep -v '^CREATE DATABASE' | grep -v '^USE `' \
  | docker exec -i "$DB" sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" prueba_restauracion'

# Comparar contra la base real
docker exec "$DB" sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "
  SELECT table_schema, COUNT(*) AS tablas
  FROM information_schema.tables
  WHERE table_schema IN (\"breadcontrol\",\"prueba_restauracion\")
  GROUP BY table_schema;"'

# Limpiar
docker exec "$DB" sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE prueba_restauracion;"'
```

### 4.2 Restaurar sobre producción (solo ante una pérdida real)

```bash
# 1. Respaldo del estado actual, por dañado que esté: puede contener datos
#    posteriores al último respaldo que luego se quieran rescatar.
~/bin/respaldo_breadcontrol.sh

# 2. Restaurar
gunzip -c "$ARCHIVO" | docker exec -i "$DB" sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD"'

# 3. Verificar antes de dar por buena la operación
docker exec "$DB" sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" breadcontrol -e "
  SELECT (SELECT COUNT(*) FROM cliente) AS clientes,
         (SELECT COUNT(*) FROM venta) AS ventas,
         (SELECT COUNT(*) FROM produccion) AS producciones;"'
```

El volcado incluye `CREATE DATABASE` y `USE`, así que reconstruye la base completa sin necesidad de indicar el nombre.

---

## 5. Prueba de restauración ya realizada

Ejecutada el **2026-08-15** contra la base local, que replica el esquema de producción. Se volcó, se restauró en una base nueva y se compararon los conteos:

| | Origen | Restaurada | |
|---|---|---|---|
| Tablas | 35 | 35 | ✅ |
| `cliente` | 48 | 48 | ✅ |
| `venta` | 113 | 113 | ✅ |
| `pedido_cliente` | 1 | 1 | ✅ |
| `insumo` | 22 | 22 | ✅ |
| `lote` | 49 | 49 | ✅ |
| `produccion` | 44 | 44 | ✅ |

El marcador `Dump completed` estaba presente y la base original no se modificó en ningún momento.

**Conviene repetir esta prueba cada pocos meses.** Un procedimiento que funcionó una vez puede dejar de hacerlo cuando cambia el esquema, la versión del motor o la estructura de los contenedores.

---

## 6. Limitación conocida: el respaldo vive en el mismo servidor

Los archivos quedan en `~/respaldos/breadcontrol` del VPS. Eso protege contra los accidentes probables —una consulta mal escrita, una migración equivocada, una tabla borrada— pero **no protege contra la pérdida del servidor**: si el VPS se pierde, se pierden los datos y los respaldos a la vez.

La corrección es sacar una copia fuera del servidor. Opciones, de menor a mayor esfuerzo:

1. **Descarga manual periódica** a tu equipo (`scp manuel@servidor:~/respaldos/breadcontrol/diarias/ULTIMO.sql.gz .`). Cuesta un minuto al mes y ya cambia el escenario por completo.
2. **Copia automática** a un almacenamiento externo con `rclone` o similar.

Se documenta como pendiente en `LIMITACIONES_Y_TRABAJO_FUTURO.md` en lugar de darlo por resuelto.
