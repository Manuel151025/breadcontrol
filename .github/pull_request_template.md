## Qué cambia

<!-- En una o dos frases, lo que verá quien use el sistema. -->

## Por qué

<!-- El problema que resuelve. Si corrige un fallo: qué pasaba antes, y cómo se
     reproducía. Si es una mejora: qué era incómodo o imposible. -->

## Cómo se verificó

- [ ] `composer test`
- [ ] `vendor/bin/phpstan analyse`
- [ ] `composer calidad`
- [ ] Recorridos de navegador (`cd e2e && npx playwright test`), si toca controladores o vistas
- [ ] Probado a mano en el navegador, con el rol al que afecta

**Si corrige un fallo:** ¿la prueba nueva falla contra el código anterior?
<!-- Si pasa también sin la corrección, no demuestra nada. Di dónde está y qué
     aserción es la que falla. -->

## Riesgo

- [ ] Necesita una migración de base de datos (`sql/migraciones/`), documentada en el README
- [ ] Cambia la configuración o las variables de entorno
- [ ] Cambia el comportamiento de algo que ya funcionaba (dilo aquí abajo)
- [ ] Añade entradas a `phpstan-baseline.neon` o `phpmd.baseline.xml` (explica por qué)

## Capturas

<!-- Si cambia la interfaz, antes y después. -->

## Documentación

- [ ] `CHANGELOG.md`, bajo `[Sin publicar]`
- [ ] `LIMITACIONES_Y_TRABAJO_FUTURO.md`, si cierra o descubre una limitación (tabla del resumen **y** cuerpo)
- [ ] `docs/diagramas.md`, si cambia el esquema o el flujo de pedidos
