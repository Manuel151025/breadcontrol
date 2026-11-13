# 🍞 Manual de Usuario — BreadControl

Bienvenido al manual oficial de usuario de **BreadControl**, el sistema de gestión integral diseñado para panaderías artesanales. Esta guía explica cómo administrar la panadería desde el sistema.

---

# 👑 Sección 1: Manual de Usuario para el Propietario

Esta sección detalla cómo administrar la producción, ventas, insumos, finanzas y cierres de caja diarios de la panadería.

## 1. Tablero Principal (Dashboard)
El Tablero ofrece una vista general e interactiva del rendimiento del negocio en tiempo real.
* **KPIs Clave**: Muestra los ingresos por ventas hoy, cantidad de pedidos pendientes por confirmar, compras del día y la utilidad operativa.
* **Clima en Vivo**: Muestra la temperatura y pronóstico actual en Florencia, Caquetá (utilizando la API Open-Meteo) para ayudarte a planificar la producción diaria (los días fríos o lluviosos suelen tener mayor demanda de pan caliente).
* **Observaciones del Día Anterior**: Un banner destacado mostrará las notas importantes dejadas en el último cierre de caja (ej. *"Comprar azúcar mañana a primera hora"*) para asegurar el flujo de la operación.

## 2. Gestión de Insumos e Inventario
El sistema utiliza el método **FIFO (First In, First Out)** para la contabilidad y consumo de materias primas.
* **Alertas de Stock**: Cada insumo cuenta con una barra visual de nivel en color. Si el stock desciende por debajo del punto de reposición, la barra se tornará roja y el sistema enviará una alerta.
* **Compras con Lotes**: Al registrar una compra de insumos, se genera automáticamente un lote en formato único (`MAN-YYYY-MM-DD-NNN`). El precio de compra se registra para calcular el margen de ganancia.
* **Alerta de Variación de Precio**: Si el precio del insumo en una nueva compra varía más del 5% respecto a la última compra del proveedor, el sistema te lo notificará visualmente para que evalúes el ajuste de tus recetas.
* **Merma Automática**: El insumo de harina de trigo cuenta con un descuento de merma del 6% automático para registrar pérdidas por manipulación y humedad.

## 3. Producción Diaria
Permite registrar el pan producido diariamente por tandas y descontar materias primas.
* **Descuento de Insumos**: Al registrar una producción, se calculan las recetas e ingredientes necesarios y se restan automáticamente del inventario usando los lotes de compra más antiguos (FIFO).
* **Distribución de Precios**: El pan producido se clasifica por categorías de precio ($500, $1.000, $2.000, $3.000, $5.000) lo cual incrementa el stock disponible para ventas de cada categoría.
* **Producción Insuficiente**: Si no hay suficientes insumos para realizar la receta, el sistema te advertirá, permitiendo "forzar la producción" bajo tu responsabilidad, registrando stocks negativos transitorios.
* **Botón "Comprar" en la Alerta de Faltante**: Cuando un ingrediente no alcanza para la tanda, la alerta roja incluye un botón **Comprar** que te lleva directo al módulo de Compras con ese insumo ya seleccionado, para que solo tengas que elegir proveedor, cantidad y precio.

## 4. Gestión de Ventas
BreadControl cuenta con dos modalidades para registrar ingresos en la panadería física:
* **Venta Rápida**: Para transacciones en el mostrador. Seleccionas la categoría de precio, la cantidad vendida, y el método de salida (Venta, Consumo Interno de empleados, o Bonificación de regalo).
* **Venta Detallada (Carrito)**: Utiliza una interfaz tipo carrito de compras donde puedes seleccionar panes específicos con fotos reales de tus variedades de pan y acumularlos.
* **Cálculo de Ñapas y Bonificaciones**: 
  * Para clientes registrados como **Tiendas**, el sistema calcula y sugiere automáticamente una bonificación del 20% (crédito de $1.000 por cada $5.000 de compra en panes extra).
  * Para ventas de **Mostrador**, se sugiere una ñapa del 10% (crédito de $500 por cada $5.000 de compra).

## 5. Finanzas y Reportes
* **KPIs Financieros**: Análisis automático de rentabilidad, margen de utilidad, e ingresos netos.
* **Gráficas de Rendimiento**: Un desglose interactivo visualizado con Chart.js que muestra las ventas de los últimos 7 días, comparativas mes a mes y consolidados anuales.
* **Exportación de Reportes**: Posibilidad de exportar reportes detallados en formato PDF y Excel para la contabilidad formal del negocio.

## 6. Cierre del Día y Cuadre de Caja
Al finalizar la jornada laboral, debes realizar obligatoriamente el cierre de caja.
* **Efectivo y Cierre**: Ingresa el dinero en efectivo físico presente en caja para que el sistema calcule si hay sobrantes o faltantes comparados con el registro digital.
* **Observaciones para el Siguiente Día**: Escribe notas críticas sobre stock, pedidos especiales, o pendientes de mantenimiento. Estas aparecerán automáticamente en el Dashboard del propietario al día siguiente.
