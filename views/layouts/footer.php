</div><!-- /main-content -->

<?php
// Aquí se cargaba el paquete de JavaScript de Bootstrap (~80 KB desde un CDN,
// en todas las páginas del back-office). No se usaba: el proyecto tiene su
// propio sistema de diseño y no hay un solo atributo data-bs-* ni una llamada
// a su API en el código. Se eliminó junto con el resto de dependencias de
// terceros que retrasaban la carga inicial.
?>
</body>
</html>
