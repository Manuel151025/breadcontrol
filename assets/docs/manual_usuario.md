# 🍞 Manual de Usuario — BreadControl

Bienvenido al manual oficial de usuario de **BreadControl**, el sistema de gestión integral diseñado para panaderías artesanales. Esta guía está dividida en dos secciones principales: una para el **Propietario (Administración)** y otra para los **Clientes (Portal de Pedidos)**.

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

---

# 👥 Sección 2: Manual de Usuario para el Cliente

Esta sección detalla cómo los clientes registrados (Tiendas, Mostrador o Aprendices del SENA) pueden usar el portal web de BreadControl para hacer pedidos, pagar y gestionar sus saldos.

## 1. Crear y Editar Pedidos
Los clientes pueden armar sus pedidos de panadería desde cualquier dispositivo móvil o computador.
* **Armar Pedido**: Accede a **Armar Pedido** desde el portal. Selecciona una pestaña de precio ($500, $1.000, $2.000, etc.) y toca sobre las fotos de los panes para agregarlos al carrito.
* **Seleccionar Fecha de Entrega**: Selecciona la fecha en la que deseas recibir el pan. No puedes programar entregas en el pasado ni a un plazo mayor a 3 meses.
* **Límite de Modificación/Cancelación (Regla de las 48 Horas)**: Para garantizar que la panadería planifique las compras de insumos y hornee a tiempo, **solo se permite editar o cancelar un pedido si faltan más de 48 horas para su entrega**. Si el plazo es menor, el pedido quedará bloqueado y deberás comunicarte con la panadería.

## 2. Portal de Clientes SENA (Aprendices)
Los aprendices del SENA tienen un flujo especial diseñado para sus prácticas de comercialización:
* **Toggles de Cuenta (Destinatario)**: Al armar el pedido, el aprendiz puede alternar con botones interactivos para quién va dirigido el pedido:
  * **Cuenta ADSO (Tienda ADSO)**: El pedido se cargará a la cuenta colectiva del instructor ADSO. Estos pedidos se validan a la tasa de bonificación del **20% (Tienda)**.
  * **Mi cuenta (Personal)**: El pedido es para consumo propio del aprendiz y se cargará a su cuenta personal con la tasa de **10% (Mostrador/Ñapa)**.
* **Sincronización de Tasas**: Las pantallas y validadores de la bonificación de regalo se adaptan automáticamente en tiempo real al destinatario seleccionado.

## 3. Bonificaciones y Ñapas de Regalo
Por tus compras detalladas, el sistema te otorga crédito para llevar pan gratis.
* **Crédito Disponible**: A medida que agregas productos al carrito, verás un panel azul o naranja que te indica cuánto dinero acumulaste de crédito de regalo.
* **Selección del Pan de Regalo**: Escoge qué panes deseas llevar gratis utilizando tu crédito disponible.
* **Seguridad y Validación**: El sistema valida el costo real de los panes de regalo en el servidor. Si intentas alterar el JSON del navegador para pedir panes más caros o exceder tu saldo, el pedido será rechazado con un error por motivos de seguridad.

## 4. Pagos Digitales e Integración con Wompi
BreadControl admite pagos seguros a través de la pasarela de pagos Wompi (Bancolombia) y PSE/Nequi.
* **Habilitar Pago Consolidado**: Si tienes varios pedidos pendientes de pago, puedes agruparlos en una sola transacción desde el menú de pagos. El sistema calcula el déficit pendiente y genera un botón de **Pagar ahora**.
* **Completar la Transacción**: Serás redirigido de manera segura a la pasarela de Wompi. Puedes pagar con tarjetas de crédito, cuentas de ahorro o Nequi.
* **Reintentar Pagos Rechazados**: Si tu pago es rechazado por el banco, el sistema no te bloqueará. Podrás volver a ingresar a tu portal de pagos y reintentar la transacción de forma inmediata.
* **Validación de Saldos**: Una vez que Wompi confirme tu transacción como aprobada, el webhook del servidor validará el monto cobrado y registrará el saldo a favor de tus pedidos de forma automática.
