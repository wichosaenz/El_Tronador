<?php
/**
 * Media Optimizer module — smart lazy load for images/iframes and YouTube facade.
 *
 * Hooks into the OB pipeline at priority 75 (between File Optimizer=50 and
 * Delay JS=99) to defer off-screen images and iframes via Intersection Observer,
 * while preserving the first 2 images for LCP performance.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Media_Optimizer implements ETR_Module_Interface {

    /**
     * Transparent SVG placeholder for lazy-loaded images.
     */
    private const PLACEHOLDER = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 1'%3E%3C/svg%3E";

    /**
     * Number of leading images to exclude from lazy load (LCP protection).
     */
    private const LCP_SKIP_COUNT = 2;

    /**
     * Image counter shared across callbacks.
     */
    private int $img_index = 0;

    /**
     * User exclusion keywords.
     *
     * @var string[]
     */
    private array $exclusions = [];

    /**
     * {@inheritdoc}
     */
    public function get_id(): string {
        return 'media_optimizer';
    }

    /**
     * {@inheritdoc}
     */
    public function get_name(): string {
        return __( 'Optimización de Medios', 'el-tronador' );
    }

    /**
     * {@inheritdoc}
     */
    public function is_enabled(): bool {
        return (bool) ETR_Admin_Options::get( 'lazy_images_enabled', false )
            || (bool) ETR_Admin_Options::get( 'lazy_iframes_enabled', false )
            || (bool) ETR_Admin_Options::get( 'youtube_facade_enabled', false );
    }

    /**
     * {@inheritdoc}
     */
    public function init(): void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        // Register as a processor in the unified output buffer (priority 75).
        ETR_Output_Buffer::instance()->register_processor( [ $this, 'process_html' ], 75 );
    }

    /**
     * Process the buffered HTML.
     *
     * @param string $html Full page HTML.
     * @return string Modified HTML.
     */
    public function process_html( string $html ): string {
        if ( ! str_contains( $html, '</html>' ) ) {
            return $html;
        }

        $this->exclusions = $this->get_exclusions();
        $this->img_index  = 0;

        $lazy_images  = (bool) ETR_Admin_Options::get( 'lazy_images_enabled', false );
        $lazy_iframes = (bool) ETR_Admin_Options::get( 'lazy_iframes_enabled', false );
        $yt_facade    = (bool) ETR_Admin_Options::get( 'youtube_facade_enabled', false );

        $needs_observer = false;
        $needs_yt_handler = false;

        // 1. YouTube facade (must run before iframe lazy load to avoid double-processing).
        if ( $yt_facade ) {
            $html = preg_replace_callback(
                '/<iframe\b([^>]*)\bsrc\s*=\s*["\']https?:\/\/(?:www\.)?(?:youtube\.com|youtube-nocookie\.com)\/embed\/([a-zA-Z0-9_-]+)[^"\']*["\']([^>]*)>\s*<\/iframe>/i',
                [ $this, 'replace_youtube_iframe' ],
                $html,
                -1,
                $yt_count
            );
            if ( $yt_count > 0 ) {
                $needs_yt_handler = true;
            }
        }

        // 2. Lazy load images.
        if ( $lazy_images ) {
            $html = preg_replace_callback(
                '/<img\b([^>]*)\/?\s*>/i',
                [ $this, 'process_img_tag' ],
                $html
            );
            $needs_observer = true;
        }

        // 3. Lazy load iframes (non-YouTube, since YouTube was already replaced).
        if ( $lazy_iframes ) {
            $html = preg_replace_callback(
                '/<iframe\b([^>]*)>\s*<\/iframe>/i',
                [ $this, 'process_iframe_tag' ],
                $html
            );
            $needs_observer = true;
        }

        // 4. Inject scripts before </body>.
        $scripts = $this->get_inline_scripts( $needs_observer, $needs_yt_handler );
        if ( '' !== $scripts ) {
            $html = str_replace( '</body>', $scripts . "\n</body>", $html );
        }

        return $html;
    }

    /**
     * Process a single <img> tag for lazy loading.
     *
     * @param array<int, string> $matches Regex matches.
     * @return string Modified or original tag.
     */
    private function process_img_tag( array $matches ): string {
        $full_tag = $matches[0];
        $attrs    = $matches[1];

        $this->img_index++;

        // LCP guard: skip the first N images.
        if ( $this->img_index <= self::LCP_SKIP_COUNT ) {
            return $full_tag;
        }

        // Respect native eager loading.
        if ( preg_match( '/loading\s*=\s*["\']eager["\']/', $attrs ) ) {
            return $full_tag;
        }

        // Respect data-no-lazy attribute.
        if ( str_contains( $attrs, 'data-no-lazy' ) ) {
            return $full_tag;
        }

        // Check user exclusions against full tag (src, class, id, etc.).
        if ( $this->matches_exclusion( $full_tag ) ) {
            return $full_tag;
        }

        // Replace src with placeholder.
        if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/', $attrs, $src_match ) ) {
            $full_tag = str_replace(
                $src_match[0],
                'src="' . self::PLACEHOLDER . '" data-etr-lazy-src="' . esc_attr( $src_match[1] ) . '"',
                $full_tag
            );
        }

        // Replace srcset.
        if ( preg_match( '/\bsrcset\s*=\s*["\']([^"\']+)["\']/', $full_tag, $srcset_match ) ) {
            $full_tag = str_replace(
                $srcset_match[0],
                'data-etr-lazy-srcset="' . esc_attr( $srcset_match[1] ) . '"',
                $full_tag
            );
        }

        // Add etr-lazyload class.
        $full_tag = $this->add_class( $full_tag, 'etr-lazyload' );

        // Add native lazy loading as fallback.
        if ( ! str_contains( $full_tag, 'loading=' ) ) {
            $full_tag = str_replace( '<img ', '<img loading="lazy" ', $full_tag );
        }

        return $full_tag;
    }

    /**
     * Process a single <iframe> tag for lazy loading.
     *
     * @param array<int, string> $matches Regex matches.
     * @return string Modified or original tag.
     */
    private function process_iframe_tag( array $matches ): string {
        $full_tag = $matches[0];
        $attrs    = $matches[1];

        // Respect native eager loading.
        if ( preg_match( '/loading\s*=\s*["\']eager["\']/', $attrs ) ) {
            return $full_tag;
        }

        // Respect data-no-lazy attribute.
        if ( str_contains( $attrs, 'data-no-lazy' ) ) {
            return $full_tag;
        }

        // Check user exclusions.
        if ( $this->matches_exclusion( $full_tag ) ) {
            return $full_tag;
        }

        // Replace src with about:blank.
        if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/', $attrs, $src_match ) ) {
            $full_tag = str_replace(
                $src_match[0],
                'src="about:blank" data-etr-lazy-src="' . esc_attr( $src_match[1] ) . '"',
                $full_tag
            );
        }

        // Add etr-lazyload class.
        $full_tag = $this->add_class( $full_tag, 'etr-lazyload' );

        return $full_tag;
    }

    /**
     * Replace a YouTube iframe with a lightweight facade.
     *
     * @param array<int, string> $matches Regex matches.
     * @return string Facade HTML.
     */
    private function replace_youtube_iframe( array $matches ): string {
        $video_id = $matches[2];
        $thumb    = 'https://i.ytimg.com/vi/' . $video_id . '/maxresdefault.jpg';

        $play_svg = '<svg viewBox="0 0 68 48" width="68" height="48"><path d="M66.52 7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79.13 34 0 34 0S12.21.13 6.9 1.55c-2.93.78-4.64 3.26-5.42 6.19C.06 13.05 0 24 0 24s.06 10.95 1.48 16.26c.78 2.93 2.49 5.41 5.42 6.19C12.21 47.87 34 48 34 48s21.79-.13 27.1-1.55c2.93-.78 4.64-3.26 5.42-6.19C67.94 34.95 68 24 68 24s-.06-10.95-1.48-16.26z" fill="red"/><path d="M45 24L27 14v20" fill="#fff"/></svg>';

        return '<div class="etr-yt-facade" data-etr-yt-id="' . esc_attr( $video_id ) . '" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;cursor:pointer;background:#000">'
            . '<img src="' . esc_url( $thumb ) . '" alt="" loading="lazy" style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover">'
            . '<button class="etr-yt-play" aria-label="Play" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:none;border:none;cursor:pointer;padding:0">'
            . $play_svg
            . '</button>'
            . '</div>';
    }

    /**
     * Add a CSS class to an HTML tag.
     *
     * @param string $tag        Full HTML tag string.
     * @param string $class_name CSS class to add.
     * @return string Modified tag.
     */
    private function add_class( string $tag, string $class_name ): string {
        if ( preg_match( '/class\s*=\s*["\']([^"\']*)["\']/', $tag, $m ) ) {
            return str_replace(
                $m[0],
                'class="' . $m[1] . ' ' . $class_name . '"',
                $tag
            );
        }

        // No existing class attribute — add one after the opening tag name.
        return preg_replace( '/^(<\w+)/', '$1 class="' . $class_name . '"', $tag );
    }

    /**
     * Check if a tag matches any user-defined exclusion keyword.
     *
     * @param string $tag Full HTML tag.
     * @return bool
     */
    private function matches_exclusion( string $tag ): bool {
        foreach ( $this->exclusions as $keyword ) {
            if ( str_contains( $tag, $keyword ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get user-defined exclusion keywords from settings.
     *
     * @return string[]
     */
    private function get_exclusions(): array {
        $raw = ETR_Admin_Options::get( 'media_lazy_exclusions', '' );
        if ( '' === $raw ) {
            return [];
        }

        $lines = explode( "\n", $raw );
        return array_filter( array_map( 'trim', $lines ), fn( string $line ) => '' !== $line );
    }

    /**
     * Build inline scripts for Intersection Observer and YouTube facade handler.
     *
     * @param bool $observer   Include the IO lazy-load script.
     * @param bool $yt_handler Include the YouTube click handler.
     * @return string Script tag(s) or empty string.
     */
    private function get_inline_scripts( bool $observer, bool $yt_handler ): string {
        if ( ! $observer && ! $yt_handler ) {
            return '';
        }

        $js = '';

        if ( $observer ) {
            $js .= <<<'JS'
(function(){
var io=new IntersectionObserver(function(entries){
entries.forEach(function(e){
if(!e.isIntersecting)return;
var el=e.target;
if(el.dataset.etrLazySrc){el.src=el.dataset.etrLazySrc;delete el.dataset.etrLazySrc;}
if(el.dataset.etrLazySrcset){el.srcset=el.dataset.etrLazySrcset;delete el.dataset.etrLazySrcset;}
el.classList.remove('etr-lazyload');
io.unobserve(el);
});
},{rootMargin:'200px'});
document.querySelectorAll('.etr-lazyload').forEach(function(el){io.observe(el);});
})();
JS;
        }

        if ( $yt_handler ) {
            $js .= <<<'JS'
document.addEventListener('click',function(e){
var f=e.target.closest('.etr-yt-facade');
if(!f)return;
var id=f.dataset.etrYtId;
var i=document.createElement('iframe');
i.src='https://www.youtube.com/embed/'+id+'?autoplay=1';
i.allow='accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture';
i.allowFullscreen=true;
i.style.cssText='position:absolute;top:0;left:0;width:100%;height:100%;border:0';
f.innerHTML='';
f.appendChild(i);
});
JS;
        }

        return '<script id="etr-media-optimizer" type="text/javascript">' . $js . '</script>';
    }
}
