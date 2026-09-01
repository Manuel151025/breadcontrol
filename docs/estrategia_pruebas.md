# Estrategia de pruebas — BreadControl

Qué comprueba cada verificación, por qué el umbral está donde está, y qué hacer
cuando una falla.

El principio que ordena todo lo demás: **una prueba que no puede fallar no
prueba nada**. Cada verificación de este documento se comprobó contra un defecto
inyectado a propósito antes de ponerla a bloquear. Esa disciplina no es celo:
nació de encontrar en este mismo proyecto una prueba que llevaba catorce días
sin poder ejecutarse y que, mientras tanto, ocupaba el sitio del guardia que sí
habría avisado.

---

## Las capas, y qué ve cada una

Ninguna herramienta sobra, porque cada una ve algo que las demás no pueden ver.

| Capa | Ve | No ve |
|------|-----|-------|
| PHPStan | Tipos, ramas imposibles, llamadas inexistentes | Si el comportamiento es correcto |
| PHPUnit | Que la lógica hace lo que se espera | Vistas y controladores (terminan en `exit`) |
| Cobertura | Qué líneas se **ejecutan** | Si al ejecutarlas se **verifica** algo |
| Mutación | Si una prueba se enteraría de que el código cambió | Código que ninguna prueba ejecuta |
| PHPMD / PHPMetrics | Complejidad y acoplamiento | Correción |
| Auditoría de seguridad | Dependencias, secretos y patrones peligrosos | Fallos de lógica de negocio |
| Cabeceras | La respuesta HTTP real | El interior de la aplicación |
| Playwright | Controladores, vistas y JavaScript, en un navegador | Detalle interno de un cálculo |

La consecuencia práctica: **la cobertura y la mutación se leen juntas**. Una
cobertura alta con MSI bajo significa pruebas que recorren el código sin
comprobarlo. Fue exactamente el caso de `ProduccionModel`: sus líneas figuraban
cubiertas al 100 %, y aun así el redondeo del costeo no estaba verificado porque
todos los datos de prueba eran números redondos.

---

## Los umbrales

Son **suelos, no metas**. No dicen «esto está bien»; dicen «esto no puede
empeorar sin que alguien se entere». Subirlos conforme se escriben pruebas es
parte del trabajo, no una promesa vaga.

### Cobertura: 12 % global

Bajo a propósito, y por un motivo concreto: el `<source>` de `phpunit.xml`
incluye `controllers/`, que está al **0,0 %**. Las pruebas de PHPUnit cubren
modelos y helpers; los controladores los cubre Playwright, que no cuenta aquí.

| Capa | Cobertura |
|------|-----------|
| `helpers/` | 68,9 % |
| `includes/` | 36,4 % |
| `models/` | 23,9 % |
| `controllers/` | **0,0 %** |

El número global importa menos que ese reparto. Subir `models/` es trabajo de
PHPUnit y es barato; subir `controllers/` es trabajo de Playwright.

### Mutación: 69 % de MSI **del código cubierto**

Se usa `--min-covered-msi` y no `--min-msi` deliberadamente. Con un 12 % de
cobertura global, el MSI global mediría sobre todo lo que **no** está probado —y
eso ya lo dice el job de cobertura—. Lo que aporta la mutación es la calidad de
las pruebas que existen.

Se limita a `helpers/` y `models/`: ahí vive la lógica que hace perder dinero si
falla (FIFO de lotes, cuadre de caja, saldos del instructor). Mutar
`controllers/`, al 0 % de cobertura, produciría mutantes vivos por definición.

### PHPMD: línea base, no umbral

Igual que `phpstan-baseline.neon`: las 109 ocurrencias heredadas quedan
inventariadas y **cualquier hallazgo nuevo rompe el CI**. El conjunto de reglas
de `phpmd.xml` excluye a propósito lo que en esta arquitectura es ruido —por
ejemplo `UnusedLocalVariable`, que marca 304 falsos positivos porque los
controladores asignan variables y luego hacen `require` de la vista—. Cada
exclusión está justificada en el propio archivo.

### Semgrep: contra la rama base, no contra todo

El repositorio arrastra 5 hallazgos de severidad ERROR que se revisaron uno a
uno y son falsos positivos (`echo json_encode(...)` en endpoints AJAX, donde
aplicar `htmlentities` corrompería el JSON). Silenciar la regla los habría
apagado, pero también habría apagado los XSS de verdad. Comparar contra la rama
base deja la regla activa y solo bloquea lo que se añada.

---

## Qué hacer cuando algo falla

| Falla | Qué mirar primero |
|-------|-------------------|
| **Cobertura** | El desglose por capa que imprime el propio job, y la lista de los diez archivos peor cubiertos |
| **Mutación** | El artefacto `infection-informe`: dice qué mutante sobrevivió y en qué línea. Si el mutante es inocuo, documéntalo; si no, falta una aserción |
| **Calidad (PHPMD)** | Es un hallazgo **nuevo**. O se corrige, o se justifica y se regenera la línea base con `--update-baseline` |
| **Auditoría** | Gitleaks y `composer audit` no dan falsos positivos casi nunca. Semgrep sí: verifica el hallazgo antes de silenciarlo |
| **Cabeceras** | Si falla en el contenedor, es el `.htaccess` o el `Dockerfile`. Si falla en producción y no en el contenedor, es el Nginx del VPS o Traefik |
| **Extremo a extremo** | El reportero `github` publica cada fallo como anotación, legible sin abrir el registro. El artefacto trae trazas y capturas |

Un aviso sobre los tiempos: el job de cobertura y el de mutación **anclan la
fecha a la zona horaria de la aplicación** (`-05:00`). Si una prueba depende de
«hoy», recuerda que el runner corre en UTC y que entre las 00:00 y las 05:00 UTC
las dos fechas no coinciden. Ya rompió el CI una vez por esto.

---

## Lo que estas verificaciones **no** cubren

Decirlo importa tanto como decir lo que sí:

- **Rendimiento y carga.** Nada mide cuántas peticiones aguanta el sistema.
- **Recuperación de respaldos.** Existen y se han probado a mano, pero ninguna
  verificación automática los restaura (punto 23 del anexo de limitaciones).
- **Accesibilidad.** Playwright está montado y `axe-core` encajaría sin
  esfuerzo, pero no se ha añadido (punto 27).
- **El pago consolidado del portal**, que es el único recorrido de los
  planificados que quedó fuera: necesita configuración de Nequi y un pedido
  aprobado por instructor.
- **Compatibilidad entre versiones de PHP.** El CI corre solo 8.2. Con el fin de
  soporte el 31 de diciembre de 2026, añadir 8.3 a la matriz es el primer paso
  de esa migración.

---

## Ver también

- [`CONTRIBUTING.md`](../CONTRIBUTING.md) — cómo ejecutar cada cosa en local
- [`LIMITACIONES_Y_TRABAJO_FUTURO.md`](../LIMITACIONES_Y_TRABAJO_FUTURO.md) — deuda conocida, con su porqué
- [`phpmd.xml`](../phpmd.xml) — reglas activas y motivo de cada exclusión
- [`infection.json5`](../infection.json5) — alcance de la mutación
