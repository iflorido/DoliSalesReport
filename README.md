# DoliSalesReport

Módulo para **Dolibarr** (compatible 16.0 → 22.x) que genera informes profesionales de ventas a partir de la **facturación emitida**, incluyendo tickets de TPV (TakePos).

Desarrollado por **Ignacio Florido** — https://cv.iflorido.es

## Características

- **Dashboard** con KPIs (total sin IVA, total con IVA, productos distintos, nº de facturas, unidades vendidas) y gráficas (Top 10 productos por importe, evolución mensual, más/menos vendidos). Por defecto muestra el año en curso, con selector de fechas.
- **Informe de ventas por producto**: filtra por producto (todos o uno concreto), por tercero/cliente (todos o uno concreto) y por rango de fechas (inicio/fin).
- Resultados con **paginación** y nº de líneas por página configurable. Cada fila muestra: referencia, nombre del producto, unidades vendidas, nº de facturas, total sin IVA y total con IVA. Columnas ordenables.
- **Exportación a Excel** (.xlsx con formato; fallback a CSV si PhpSpreadsheet no estuviera disponible).
- **Líneas de texto libre** (habituales en tickets de TPV) que no se pueden asociar a un producto: se agrupan automáticamente bajo un único pseudo-producto "Productos sin referencia (texto libre)".
- Sistema de **licencia remota** (mismo mecanismo que DoliWooSync).

## Origen de los datos

Las ventas se calculan sobre `llx_facture` + `llx_facturedet`. Como el TPV de Dolibarr genera facturas reales, este origen cubre tanto facturas normales como tickets de TPV. Solo se cuentan facturas **validadas o pagadas** (`fk_statut >= 1`), de tipo estándar/sustitutiva/anticipo.

## Instalación

1. Copiar la carpeta `dolisalesreport/` en `htdocs/custom/` (o en el directorio de módulos externos configurado).
2. Activar el módulo en **Inicio → Configuración → Módulos** (familia *Informes / Reporting*).
3. Ir a la pestaña **Licencia** e introducir la clave proporcionada.
4. Una vez la licencia figure como VÁLIDA, los informes y la exportación quedan habilitados.

## Licencia (servidor)

El módulo valida si tiene licencia activa, solicitar en iflorido@gmail.com. **Es necesario dar de alta el slug `dolisalesreport` en el servidor de licencias.**
