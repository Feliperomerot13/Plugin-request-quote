# Luma Quote Cart (MVP)

Un plugin ligero de "Solicitud de Cotización" (Request a Quote) para WooCommerce, diseñado para sitios tipo catálogo o portafolio.

## Características

- **Botón "Agregar a cotización"**: Reemplaza o convive con el botón de añadir al carrito.
- **Carrito de Cotización (Session)**: Permite agregar múltiples productos sin registro.
- **Mini Basket Flotante**: Icono fijo para ver rápidamente la cantidad de items.
- **Popup / Modal**: Vista rápida para editar cantidades y enviar solicitud.
- **Formulario de Contacto**: Envío de detalles del cliente y lista de productos por email.
- **Compatibilidad Elementor**: Shortcodes probados para insertar botones y listas.

## Instalación

1. Sube la carpeta `luma-quote-cart` al directorio `/wp-content/plugins/`.
2. Activa el plugin desde el menú **Plugins** de WordPress.
3. Asegúrate de tener **WooCommerce** instalado y activo.

## Configuración

Ve a **WooCommerce > Settings > Quote Settings** (o el submenú "Quote Settings" bajo WooCommerce):

- **Recipient Email(s)**: Correos que recibirán las cotizaciones (separados por coma).
- **Button Text**: Texto del botón (ej: "Cotizar Ahora").
- **Replace "Add to Cart"**: Oculta el botón nativo de compra.
- **Enable Mini Basket / Popup**: Activa/Desactiva elementos flotantes.
- **Quote Page**: Página donde has insertado el shortcode `[luma_quote_cart]` (fallback si el popup está desactivado).

## Shortcodes (Elementor)

Puedes usar estos shortcodes en widgets de texto o Shortcode de Elementor:

- `[luma_quote_button]`: Muestra el botón de agregar a cotización (útil en loops personalizados).
- `[luma_quote_basket]`: Muestra el icono del carrito con contador.
- `[luma_quote_cart]`: Muestra la tabla de cotización y el formulario (para la página de cotización).

## Pruebas Manuales (QA)

- [ ] **Agregar**: Navega a un producto, click en "Agregar a cotización". Verifica que el contador suba.
- **Editar**: Abre el popup, cambia la cantidad. Verifica que se actualice.
- **Eliminar**: Elimina un item del popup.
- **Enviar**: Llena el formulario y envía. Verifica que llegue el email y se limpie el carrito.
- **Móvil**: Verifica que el botón flotante y el modal se vean bien en pantallas pequeñas.

## Estructura

- `/includes`: Clases PHP (Settings, Cart, Ajax, Mailer, Frontend).
- `/assets`: CSS y JS (Vanilla/jQuery).
- Sin tablas extra en la base de datos (usa `WC_Session`).

## Piloto de cotización PDF (1.2.0)

En el editor clásico de WooCommerce, cada producto sencillo o variación tiene campos privados para el precio neto en COP, el IVA por unidad y la unidad comercial. Los valores vacíos quedan en revisión. El precio neto debe ser positivo. El IVA admite cero. En pisos configure la unidad m²; la lista permite editar el área con hasta dos decimales. El contador muestra las referencias seleccionadas.

En WooCommerce → Request a Quote, active **Emitir cotización PDF** después de conciliar las referencias con el tarifario y verificar el correo saliente en pruebas. La opción está desactivada inicialmente. Las tarifas se conservan en la tienda. El modo PDF solicita un correo válido, emite un documento tras el formulario, muestra la descarga y adjunta el mismo archivo a los dos correos. Un importe faltante se identifica como pendiente; en una solicitud mixta la suma incluye solo las líneas valoradas. El enlace de descarga de esta versión permanece en la pantalla de resultado. El histórico comercial permanente y el reenvío administrativo son trabajo posterior.

El generador incluye FPDF 1.9.0 (MIT), aislado en su propio espacio de nombres, con sus fuentes base. Instrument Serif se distribuye bajo su licencia OFL incluida en assets/fonts. El ajuste «Fotografía de la cotización PDF» admite una imagen panorámica opcional de la biblioteca de medios. Las imágenes provienen de medios locales JPEG/PNG y el texto comercial sigue disponible cuando no hay imagen compatible.
