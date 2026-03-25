<?php
/**
 * Admin settings page template.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tabs        = ETR_Admin::get_tabs();
$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
$settings    = ETR_Admin_Options::get_all();
$purge_url   = wp_nonce_url( admin_url( 'admin.php?action=etr_purge_cache' ), 'etr_purge_cache' );
?>
<div class="wrap etr-admin-wrap">
    <h1><?php esc_html_e( 'El Tronador — Performance Optimization', 'el-tronador' ); ?></h1>

    <!-- Tab navigation -->
    <nav class="nav-tab-wrapper etr-tabs">
        <?php foreach ( $tabs as $slug => $label ) : ?>
            <a href="<?php echo esc_url( admin_url( 'options-general.php?page=el-tronador&tab=' . $slug ) ); ?>"
               class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Settings form -->
    <form method="post" action="options.php" class="etr-settings-form">
        <?php settings_fields( 'etr_settings_group' ); ?>

        <?php if ( 'general' === $current_tab ) : ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Page Cache', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[page_cache_enabled]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[page_cache_enabled]"
                                   value="1"
                                   <?php checked( $settings['page_cache_enabled'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Enable static page caching. Serves cached HTML files directly from disk for non-logged-in visitors.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Delay JavaScript', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[delay_js_enabled]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[delay_js_enabled]"
                                   value="1"
                                   <?php checked( $settings['delay_js_enabled'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Delay loading of JavaScript files until the user interacts with the page (scroll, click, touch). Improves Core Web Vitals scores.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        <?php endif; ?>

        <?php if ( 'file_optimization' === $current_tab ) : ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Minificar CSS', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[minify_css_enabled]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[minify_css_enabled]"
                                   value="1"
                                   <?php checked( $settings['minify_css_enabled'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Minifica archivos CSS locales eliminando comentarios, espacios en blanco y saltos de línea. Los archivos .min.css se omiten automáticamente.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Minificar JS', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[minify_js_enabled]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[minify_js_enabled]"
                                   value="1"
                                   <?php checked( $settings['minify_js_enabled'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Minifica archivos JavaScript locales eliminando comentarios y espacios innecesarios. Los archivos .min.js se omiten automáticamente.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Optimizar Entrega de CSS', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[optimize_css_delivery_enabled]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[optimize_css_delivery_enabled]"
                                   value="1"
                                   <?php checked( $settings['optimize_css_delivery_enabled'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Carga las hojas de estilo de forma diferida usando preload, eliminando el bloqueo de renderizado. Incluye respaldo con <noscript> para navegadores sin JavaScript.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="etr-file-exclusions">
                            <?php esc_html_e( 'Excluir Archivos CSS/JS', 'el-tronador' ); ?>
                        </label>
                    </th>
                    <td>
                        <textarea id="etr-file-exclusions"
                                  name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[file_optimization_exclusions]"
                                  class="etr-exclusions-textarea"
                                  rows="6"><?php echo esc_textarea( $settings['file_optimization_exclusions'] ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Ingresa una URL o palabra clave por línea. Los archivos que coincidan serán ignorados por el minificador y la optimización de entrega CSS. Ejemplo: jquery.js, elementor, mi-plugin/assets/', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        <?php endif; ?>

        <?php if ( 'media' === $current_tab ) : ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Lazy Load para Imágenes', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[lazy_images_enabled]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[lazy_images_enabled]"
                                   value="1"
                                   <?php checked( $settings['lazy_images_enabled'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Carga las imágenes de forma diferida al hacer scroll usando Intersection Observer. Las primeras 2 imágenes se excluyen automáticamente para proteger la métrica LCP.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Lazy Load para Iframes/Videos', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[lazy_iframes_enabled]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[lazy_iframes_enabled]"
                                   value="1"
                                   <?php checked( $settings['lazy_iframes_enabled'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Carga los iframes de forma diferida al hacer scroll. Mejora significativamente el tiempo de carga en páginas con videos embebidos o widgets externos.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Reemplazar YouTube por Miniatura', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[youtube_facade_enabled]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[youtube_facade_enabled]"
                                   value="1"
                                   <?php checked( $settings['youtube_facade_enabled'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Reemplaza los iframes de YouTube por una imagen de miniatura en alta resolución. El video real solo se carga cuando el usuario hace clic. Ahorra ~500 KB por video embebido.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="etr-media-exclusions">
                            <?php esc_html_e( 'Excluir del Lazy Load', 'el-tronador' ); ?>
                        </label>
                    </th>
                    <td>
                        <textarea id="etr-media-exclusions"
                                  name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[media_lazy_exclusions]"
                                  class="etr-exclusions-textarea"
                                  rows="6"><?php echo esc_textarea( $settings['media_lazy_exclusions'] ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Ingresa una clase CSS o nombre de archivo por línea. Las imágenes e iframes que coincidan serán ignorados por el lazy load. Ejemplo: logo, hero-image, no-lazy, mi-banner.jpg', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        <?php endif; ?>

        <?php if ( 'database' === $current_tab ) : ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Limpiar Revisiones', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[db_clean_revisions]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[db_clean_revisions]"
                                   value="1"
                                   <?php checked( $settings['db_clean_revisions'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Elimina todas las revisiones de posts. Los posts publicados y sus versiones actuales no se tocan.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Limpiar Borradores y Papelera', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[db_clean_drafts]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[db_clean_drafts]"
                                   value="1"
                                   <?php checked( $settings['db_clean_drafts'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Elimina borradores automáticos (auto-drafts) y posts en la papelera.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Limpiar Spam y Papelera de Comentarios', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[db_clean_spam]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[db_clean_spam]"
                                   value="1"
                                   <?php checked( $settings['db_clean_spam'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Elimina comentarios marcados como spam y comentarios en la papelera.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Limpiar Transients Expirados', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[db_clean_transients]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[db_clean_transients]"
                                   value="1"
                                   <?php checked( $settings['db_clean_transients'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Elimina todos los transients expirados de la tabla de opciones de WordPress.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Limpieza Automática Semanal', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[db_auto_cleanup]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[db_auto_cleanup]"
                                   value="1"
                                   <?php checked( $settings['db_auto_cleanup'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Ejecuta automáticamente la limpieza de los elementos seleccionados una vez por semana mediante WP-Cron.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        <?php endif; ?>

        <?php if ( 'preload' === $current_tab ) : ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Motor de Precarga', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[preload_enabled]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[preload_enabled]"
                                   value="1"
                                   <?php checked( $settings['preload_enabled'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Activa el bot rastreador que calienta la caché visitando automáticamente las URLs de tu sitemap. Procesa lotes de 25 URLs cada 5 minutos.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="etr-sitemap-url">
                            <?php esc_html_e( 'URL del Sitemap', 'el-tronador' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="url"
                               id="etr-sitemap-url"
                               name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[preload_sitemap_url]"
                               value="<?php echo esc_attr( $settings['preload_sitemap_url'] ); ?>"
                               class="regular-text"
                               placeholder="<?php echo esc_attr( home_url( '/wp-sitemap.xml' ) ); ?>">
                        <p class="description">
                            <?php esc_html_e( 'URL completa de tu sitemap XML. Déjalo vacío para usar el sitemap predeterminado de WordPress (/wp-sitemap.xml). Compatible con sitemaps de Yoast SEO, Rank Math, etc.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Estado de la Cola', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <?php
                        $queue_size = ETR_Preload_Engine::get_queue_size();
                        if ( $queue_size > 0 ) :
                            printf(
                                /* translators: %d: number of URLs remaining */
                                esc_html__( '%d URLs pendientes en la cola de precarga.', 'el-tronador' ),
                                $queue_size
                            );
                        else :
                            esc_html_e( 'La cola de precarga está vacía. Todas las páginas han sido procesadas o la precarga no se ha iniciado.', 'el-tronador' );
                        endif;
                        ?>
                    </td>
                </tr>
            </table>
        <?php endif; ?>

        <?php submit_button( __( 'Save Settings', 'el-tronador' ) ); ?>
    </form>

    <?php if ( 'preload' === $current_tab ) : ?>
        <!-- Preload now action button -->
        <div class="etr-purge-section etr-db-cleanup-section">
            <h2><?php esc_html_e( 'Precarga Manual', 'el-tronador' ); ?></h2>
            <p><?php esc_html_e( 'Lee el sitemap y llena la cola de precarga ahora mismo. Las URLs se procesarán en lotes cada 5 minutos.', 'el-tronador' ); ?></p>
            <form method="post">
                <?php wp_nonce_field( 'etr_preload_now' ); ?>
                <button type="submit" name="etr_preload_now" value="1" class="button button-primary etr-cleanup-btn">
                    <?php esc_html_e( '¡Ejecutar Precarga Ahora!', 'el-tronador' ); ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ( 'database' === $current_tab ) : ?>
        <!-- Database cleanup action button -->
        <div class="etr-purge-section etr-db-cleanup-section">
            <h2><?php esc_html_e( 'Limpieza Manual', 'el-tronador' ); ?></h2>
            <p><?php esc_html_e( 'Ejecuta la limpieza ahora mismo con las opciones seleccionadas arriba. Asegúrate de guardar los cambios primero.', 'el-tronador' ); ?></p>
            <form method="post">
                <?php wp_nonce_field( 'etr_db_cleanup' ); ?>
                <button type="submit" name="etr_db_cleanup" value="1" class="button button-primary etr-cleanup-btn">
                    <?php esc_html_e( '¡Hacer limpieza ahora!', 'el-tronador' ); ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Purge cache button -->
    <div class="etr-purge-section">
        <h2><?php esc_html_e( 'Cache Management', 'el-tronador' ); ?></h2>
        <p><?php esc_html_e( 'Clear all cached pages. This also flushes the object cache (Redis / Object Cache Pro) if active.', 'el-tronador' ); ?></p>
        <a href="<?php echo esc_url( $purge_url ); ?>" class="button button-secondary etr-purge-btn">
            <?php esc_html_e( 'Purge All Cache', 'el-tronador' ); ?>
        </a>
    </div>

    <div class="etr-footer">
        <p>El Tronador v<?php echo esc_html( ETR_VERSION ); ?></p>
    </div>
</div>
