# El Tronador — Caché y Optimización de Rendimiento para WordPress

**El Tronador** es un plugin premium de rendimiento para WordPress diseñado para competir con los mejores del mercado. El nombre es un juego de palabras mexicano que hace referencia a un cohete que "truena" — despega a máxima potencia.

## Características

### Fase 1 — Motor de Caché y Delay JS

#### Caché de Página Estática
- Caché en disco basada en archivos HTML estáticos mediante el drop-in `advanced-cache.php` de WordPress.
- Sirve las páginas cacheadas **antes de que WordPress cargue**, logrando tiempos de respuesta ultra-rápidos.
- TTL de 10 horas para garantizar la validez de los nonces de Contact Form 7.
- Exclusiones inteligentes: usuarios logueados, peticiones POST, REST API, AJAX, WP-CLI.
- Compatibilidad con WPS Hide Login: las URLs personalizadas de login nunca se cachean.

#### Delay JavaScript
- Retrasa la ejecución de JavaScript no crítico hasta la primera interacción del usuario (scroll, movimiento del mouse, toque, teclado, clic).
- Reduce el bloqueo del hilo principal para mejorar Core Web Vitals (LCP, FID, INP).
- Los scripts de Contact Form 7 se excluyen automáticamente para preservar la funcionalidad de los formularios.
- Los scripts de Google Site Kit / Analytics / Tag Manager se retrasan correctamente.

### Fase 2 — Optimización de Archivos (CSS/JS)

#### Minificación de CSS
- Detecta hojas de estilo locales (`<link rel="stylesheet">`) que apunten a archivos dentro de `wp-content/` o `wp-includes/`.
- Elimina comentarios, espacios en blanco redundantes y saltos de línea innecesarios.
- Guarda los archivos minificados en un directorio de caché propio (`wp-content/cache/el-tronador/min/`).
- Reemplaza automáticamente las URLs originales por las de los archivos minificados en el HTML.
- **Seguridad**: Los archivos que ya incluyen `.min.css` en su nombre se omiten automáticamente.

#### Minificación de JavaScript
- Detecta scripts locales (`<script src="...">`) que apunten a archivos del sitio.
- Elimina comentarios y espacios innecesarios sin renombrar variables (seguro para producción).
- Caché inteligente: solo regenera el archivo minificado cuando el original ha cambiado.
- **Seguridad**: Los archivos que ya incluyen `.min.js` en su nombre se omiten automáticamente.

#### Optimización de Entrega de CSS (CSS Crítico)
- Convierte las hojas de estilo que bloquean el renderizado a carga diferida usando el patrón `<link rel="preload" as="style">`.
- Incluye un respaldo `<noscript>` para navegadores sin JavaScript.
- Las hojas con `media="print"` se omiten (ya son no-bloqueantes).
- Sistema de exclusiones configurable por el usuario.

### Fase 3 — Optimización de Medios (Lazy Load Inteligente)

#### Lazy Load de Imágenes con Protección LCP
- Carga las imágenes de forma diferida al hacer scroll usando **Intersection Observer** con un margen de precarga de 200px.
- **Exclusión automática de LCP**: Las primeras 2 imágenes del HTML (logo y hero) se excluyen del lazy load para no penalizar la métrica Largest Contentful Paint de Google.
- Reemplaza `src` y `srcset` por atributos `data-etr-lazy-*` con un placeholder SVG transparente.
- Respeta los atributos nativos `loading="eager"` y `data-no-lazy`.
- Sistema de exclusiones configurable por clase CSS o nombre de archivo.

#### Lazy Load de Iframes
- Difiere la carga de iframes (videos, widgets, mapas) hasta que el usuario haga scroll cerca de ellos.
- Reemplaza el `src` por `about:blank` y restaura la URL real vía Intersection Observer.
- Mejora significativamente el tiempo de carga en páginas con contenido embebido.

#### Fachada de YouTube (YouTube Facade)
- Reemplaza los iframes de YouTube por una **miniatura en alta resolución** (`maxresdefault.jpg`) con un botón de play.
- El iframe real de YouTube solo se inyecta cuando el usuario hace clic en la miniatura.
- Ahorra aproximadamente **500 KB por video embebido** al evitar cargar el reproductor de YouTube innecesariamente.
- Compatible con `youtube.com` y `youtube-nocookie.com`.

### Fase 4 — Optimización de Base de Datos

#### Limpieza de Contenido
- Elimina revisiones de posts de forma segura, dejando intacto el post original publicado y su versión actual.
- Limpia borradores automáticos (auto-drafts) y posts en la papelera, incluyendo sus metadatos asociados.

#### Limpieza de Comentarios
- Elimina comentarios marcados como spam y comentarios en la papelera, incluyendo sus metadatos.

#### Limpieza de Transients
- Elimina todos los transients expirados de la tabla de opciones de WordPress usando `delete_transient()` para máxima compatibilidad.

#### Limpieza Automática Programada
- Opción para activar una limpieza automática semanal mediante WP-Cron.
- El evento cron se programa/desprograma automáticamente según la configuración del usuario.
- Botón de **"¡Hacer limpieza ahora!"** para ejecución manual inmediata desde el panel de administración.

### Fase 5 — Motor de Precarga (Sitemap Crawler Bot)

#### Bot Rastreador de Sitemap
- Lee automáticamente el archivo XML del sitemap del sitio (compatible con sitemaps de WordPress, Yoast SEO, Rank Math, etc.).
- Soporta sitemaps índice (`<sitemapindex>`) que contienen múltiples sub-sitemaps.
- Construye una cola de precarga con todas las URLs del sitio.

#### Procesamiento por Lotes
- Procesa **25 URLs por lote** cada **5 minutos** mediante un evento recurrente de WP-Cron.
- Cada petición HTTP usa `wp_remote_get` con un timeout bajo (3 segundos) y modo no bloqueante para no afectar el rendimiento del servidor.
- La cola se elimina automáticamente cuando todas las URLs han sido procesadas.

#### Disparadores Automáticos
- **Publicación de contenido**: Al guardar o actualizar un post publicado (`save_post`), la cola se reconstruye automáticamente.
- **Purga de caché**: Al purgar toda la caché desde el panel o la barra de admin, la cola se reconstruye para re-calentar las páginas.

#### Administración
- URL del sitemap configurable (por defecto `/wp-sitemap.xml`).
- Indicador del estado de la cola (URLs pendientes).
- Botón de **"¡Ejecutar Precarga Ahora!"** para iniciar la precarga manual inmediata.

### Compatibilidad del Ecosistema

- **Breeze Plugin**: Detecta conflictos y advierte al administrador antes de la activación.
- **Object Cache Pro / Redis**: Limpia el caché de objetos cuando se purga el caché estático.
- **WPS Hide Login**: Lee el slug personalizado de login y lo excluye del caché.
- **Contact Form 7**: El TTL de 10 horas previene problemas de expiración de nonces.

## Requisitos

- WordPress 6.0 o superior (probado hasta 6.9.4)
- PHP 8.0 o superior
- Permisos de escritura en el directorio `wp-content/` (para el drop-in `advanced-cache.php` y el almacenamiento de caché)

## Instalación

### Instalación Manual
1. Descarga el archivo `.zip` de la última versión.
2. Ve a **WordPress Admin > Plugins > Añadir Nuevo > Subir Plugin**.
3. Sube el archivo `.zip` y haz clic en **Instalar Ahora**.
4. Activa el plugin.

### Desde el Código Fuente
1. Clona este repositorio en tu directorio `wp-content/plugins/`:
   ```bash
   git clone https://github.com/wichosaenz/El_Tronador.git wp-content/plugins/el-tronador
   ```
2. Activa el plugin desde el panel de administración de WordPress.

## Configuración

Después de activar el plugin, navega a **Ajustes > El Tronador** en tu panel de administración.

### Pestaña General
- **Caché de Página**: Activa o desactiva el caché estático de páginas.
- **Delay JS**: Activa o desactiva el retraso de JavaScript hasta la interacción del usuario.

### Pestaña Optimización de Archivos
- **Minificar CSS**: Activa la minificación automática de archivos CSS locales.
- **Minificar JS**: Activa la minificación automática de archivos JavaScript locales.
- **Optimizar Entrega de CSS**: Convierte hojas de estilo bloqueantes a carga diferida.
- **Excluir Archivos CSS/JS**: Campo de texto para ingresar URLs o palabras clave (una por línea) que el optimizador debe ignorar. Ejemplo: `jquery.js`, `elementor`, `mi-plugin/assets/`.

### Pestaña Medios
- **Lazy Load para Imágenes**: Activa la carga diferida de imágenes con protección automática de LCP (primeras 2 imágenes excluidas).
- **Lazy Load para Iframes/Videos**: Activa la carga diferida de iframes embebidos.
- **Reemplazar YouTube por Miniatura**: Sustituye los iframes de YouTube por una imagen de miniatura ligera. El video se carga al hacer clic.
- **Excluir del Lazy Load**: Campo de texto para ingresar clases CSS o nombres de archivo (uno por línea) que el lazy load debe ignorar. Ejemplo: `logo`, `hero-image`, `mi-banner.jpg`.

### Pestaña Precarga
- **Motor de Precarga**: Activa o desactiva el bot rastreador que calienta la caché visitando las URLs del sitemap.
- **URL del Sitemap**: Campo de texto para ingresar la URL completa del sitemap XML. Déjalo vacío para usar el sitemap predeterminado de WordPress (`/wp-sitemap.xml`).
- **Estado de la Cola**: Muestra el número de URLs pendientes en la cola de precarga.
- **¡Ejecutar Precarga Ahora!**: Botón para leer el sitemap y llenar la cola de precarga inmediatamente.

### Pestaña Base de Datos
- **Limpiar Revisiones**: Activa la limpieza de todas las revisiones de posts.
- **Limpiar Borradores y Papelera**: Activa la limpieza de auto-drafts y posts en papelera.
- **Limpiar Spam y Papelera de Comentarios**: Activa la limpieza de comentarios spam y en papelera.
- **Limpiar Transients Expirados**: Activa la limpieza de transients expirados de la base de datos.
- **Limpieza Automática Semanal**: Programa una tarea WP-Cron para ejecutar la limpieza automáticamente cada semana.
- **¡Hacer limpieza ahora!**: Botón para ejecutar la limpieza manual inmediata con las opciones seleccionadas.

### Gestión de Caché
- **Purgar Todo el Caché**: Disponible en la página de ajustes y en la barra de administración. Limpia las páginas cacheadas, los archivos minificados y el caché de objetos (Redis/Object Cache Pro).

## Arquitectura

El Tronador está construido con un patrón de **Registro de Módulos** diseñado para escalabilidad:

- **OOP / PHP 8.0+**: Tipado estricto, interfaces y jerarquía de clases limpia.
- **Autoloader tipo PSR-4**: Las clases se cargan bajo demanda.
- **Diseño Modular**: Cada funcionalidad es un módulo independiente que implementa una interfaz común (`ETR_Module_Interface`).
- **Pipeline de Output Buffering**: Los módulos se enganchan al buffer de salida con prioridades estratégicas para procesar el HTML en el orden correcto.
- **Preparado para el Futuro**: La arquitectura permite añadir nuevos motores de optimización sin modificar el núcleo.

### Cómo Registrar un Nuevo Módulo

1. Crea la clase del módulo en `modules/tu-modulo/class-etr-tu-modulo.php` implementando `ETR_Module_Interface`.
2. Añade el directorio al autoloader en `includes/class-etr-autoloader.php`.
3. Registra el módulo en `ETR_Plugin::register_modules()` dentro de `includes/class-etr-plugin.php`.
4. Añade las opciones predeterminadas en `admin/class-etr-admin-options.php`.
5. Añade la sanitización de opciones en `admin/class-etr-admin.php`.
6. Si es necesario, añade una pestaña y sus campos en `admin/views/admin-page.php`.

## Hoja de Ruta

- [x] **Fase 1** — Caché de Página Estática + Delay JS
- [x] **Fase 2** — Optimización de Archivos (Minificación CSS/JS, CSS Crítico)
- [x] **Fase 3** — Optimización de Medios (Lazy Load Inteligente excluyendo LCP)
- [x] **Fase 4** — Optimización de Base de Datos (transients, revisiones, opciones expiradas)
- [x] **Fase 5** — Motor de Precarga (bot rastreador de sitemap)

## Licencia

GPLv2 o posterior. Consulta la [LICENCIA](https://www.gnu.org/licenses/gpl-2.0.html) para más detalles.
