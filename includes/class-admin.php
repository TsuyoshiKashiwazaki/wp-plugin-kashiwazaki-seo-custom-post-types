<?php
if (!defined('ABSPATH')) {
    exit;
}


class KSTB_Admin {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_global_scripts'), 999);
        add_action('admin_notices', array($this, 'show_admin_notices'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
    }

    public function add_menu_page() {
        add_menu_page(
            'Kashiwazaki SEO Custom Post Types',
            'Kashiwazaki SEO Custom Post Types',
            'manage_options',
            'kashiwazaki-seo-type-builder',
            array($this, 'render_admin_page'),
            'dashicons-archive',
            81
        );
    }

    public function render_admin_page() {
        include KSTB_PLUGIN_PATH . 'templates/admin-page.php';
    }

    /**
     * docs/ 配下の HTML ファイルから <main class="content"> 部分を抽出して返す
     *
     * v1.0.25: マニュアルの二重管理を解消するため、admin の説明書タブから docs/ を直接読み込む。
     * 単一の真実源 (Single Source of Truth) として docs/ を扱い、admin 側はその抽出表示に徹する。
     *
     * - DOMDocument で安全にパース (LIBXML_NONET でネットワーク参照を禁止)
     * - <main class="content"> から <h1>, <nav class="page-nav">, <footer> を除去
     * - <img> の相対パス (images/...) を plugins_url('docs/images/...') に書き換え
     * - <a href> の内部リンク (setup.html 等) を plugins_url('docs/setup.html') に書き換え
     * - パース失敗時は空文字を返す (呼び出し元で fallback 表示すること)
     *
     * @param string $filename docs/ 配下のファイル名 (例: 'post-type-management.html')
     * @return string 抽出した HTML (失敗時は空文字)
     */
    public function get_docs_content($filename) {
        // DOM 拡張が無い環境では fatal を回避して空文字を返す (呼び出し元で fallback 表示される)
        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            return '';
        }

        // ファイル名のサニタイズ (path traversal 防止)
        $filename = basename($filename);
        if (!preg_match('/\.html$/', $filename)) {
            return '';
        }

        $filepath = KSTB_PLUGIN_PATH . 'docs/' . $filename;
        if (!file_exists($filepath) || !is_readable($filepath)) {
            return '';
        }

        // realpath で docs/ 配下に収まることを再確認 (symlink 経由の脱出防止)
        // 末尾区切り文字を付けて比較し、/path/docs_evil/ のような prefix 一致を防ぐ
        $real_filepath = realpath($filepath);
        $real_docs_dir = realpath(KSTB_PLUGIN_PATH . 'docs');
        if ($real_filepath === false || $real_docs_dir === false) {
            return '';
        }
        $real_docs_dir_with_sep = rtrim($real_docs_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($real_filepath, $real_docs_dir_with_sep) !== 0) {
            return '';
        }

        $html = file_get_contents($filepath);
        if ($html === false || $html === '') {
            return '';
        }

        // DOMDocument でパース (XML encoding 宣言で日本語文字化け回避)
        // libxml の元エラー設定を保存して復元する
        $previous_libxml_errors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous_libxml_errors);

        if (!$loaded) {
            return '';
        }

        // <main class="content"> を取得 (class 完全一致ではなく contains() で堅牢化)
        $xpath = new DOMXPath($dom);
        $class_match = 'contains(concat(" ", normalize-space(@class), " "), " content ")';
        $main_nodes = $xpath->query('//main[' . $class_match . ']');
        if ($main_nodes->length === 0) {
            return '';
        }
        $main = $main_nodes->item(0);

        // 不要な要素を削除: <h1>, <nav class="page-nav">, <footer>, <figure class="screenshot">
        $remove_selectors = array(
            './/h1',
            './/nav[contains(concat(" ", normalize-space(@class), " "), " page-nav ")]',
            './/footer',
            './/figure[contains(concat(" ", normalize-space(@class), " "), " screenshot ")]',
        );
        foreach ($remove_selectors as $selector) {
            $nodes_to_remove = $xpath->query($selector, $main);
            foreach ($nodes_to_remove as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        // <img src="images/..."> を docs URL に書き換え
        $docs_base = KSTB_PLUGIN_URL . 'docs/';
        $img_nodes = $xpath->query('.//img', $main);
        foreach ($img_nodes as $img) {
            $src = $img->getAttribute('src');
            if ($src !== '' && strpos($src, 'http') !== 0 && strpos($src, '//') !== 0) {
                $img->setAttribute('src', $docs_base . ltrim($src, '/'));
            }
        }

        // <a href="*.html"> の内部リンクを docs URL に書き換え + target="_blank"
        $link_nodes = $xpath->query('.//a[@href]', $main);
        foreach ($link_nodes as $link) {
            $href = $link->getAttribute('href');
            if (preg_match('/^[a-z0-9_-]+\.html(#.*)?$/i', $href)) {
                $link->setAttribute('href', $docs_base . $href);
                $link->setAttribute('target', '_blank');
                $link->setAttribute('rel', 'noopener');
            }
        }

        // <main> の innerHTML を取得
        $output = '';
        foreach ($main->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return $output;
    }

    public function enqueue_scripts($hook) {
        if ('toplevel_page_kashiwazaki-seo-type-builder' !== $hook) {
            return;
        }

        // ファイルのタイムスタンプを使用してキャッシュを強制クリア
        $css_file = KSTB_PLUGIN_PATH . 'assets/admin.css';
        $js_file = KSTB_PLUGIN_PATH . 'assets/admin.js';
        $css_version = file_exists($css_file) ? filemtime($css_file) : KSTB_VERSION;
        $js_version = file_exists($js_file) ? filemtime($js_file) : KSTB_VERSION;

        wp_enqueue_style(
            'kstb-admin',
            KSTB_PLUGIN_URL . 'assets/admin.css',
            array(),
            $css_version
        );

        wp_enqueue_script(
            'kstb-admin',
            KSTB_PLUGIN_URL . 'assets/admin.js',
            array('jquery', 'wp-util'),
            $js_version,
            true
        );

        wp_localize_script('kstb-admin', 'kstb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'admin_url' => admin_url(),
            'nonce' => wp_create_nonce('kstb_ajax_nonce'),
            'labels' => array(
                'confirm_delete' => __('この投稿タイプを削除してもよろしいですか？', 'kashiwazaki-seo-type-builder'),
                'save_success' => __('保存しました', 'kashiwazaki-seo-type-builder'),
                'save_error' => __('保存中にエラーが発生しました', 'kashiwazaki-seo-type-builder'),
                'delete_success' => __('削除しました', 'kashiwazaki-seo-type-builder'),
                'delete_error' => __('削除中にエラーが発生しました', 'kashiwazaki-seo-type-builder')
            )
        ));
    }

        public function enqueue_global_scripts() {

        // カスタム投稿タイプメニューの表示に必要なスクリプトとスタイルを全管理画面で読み込む
        // ファイルのタイムスタンプを使用してキャッシュを強制クリア
        $css_global_file = KSTB_PLUGIN_PATH . 'assets/admin-global.css';
        $js_global_file = KSTB_PLUGIN_PATH . 'assets/admin-global.js';
        $css_global_version = file_exists($css_global_file) ? filemtime($css_global_file) : KSTB_VERSION;
        $js_global_version = file_exists($js_global_file) ? filemtime($js_global_file) : KSTB_VERSION;

        wp_enqueue_style(
            'kstb-admin-global',
            KSTB_PLUGIN_URL . 'assets/admin-global.css',
            array(),
            $css_global_version
        );

        wp_enqueue_script(
            'kstb-admin-global',
            KSTB_PLUGIN_URL . 'assets/admin-global.js',
            array('jquery'),
            $js_global_version,
            true
        );

        // データベースから全カスタム投稿タイプを取得
        $custom_post_types = array();
        $db_post_types = KSTB_Database::get_all_post_types();

        foreach ($db_post_types as $post_type) {
            // ラベルをパース
            $labels = json_decode($post_type->labels, true);
            $menu_name = (!empty($labels['menu_name'])) ? $labels['menu_name'] : $post_type->label;

            $custom_post_types[] = array(
                'slug' => $post_type->slug,
                'label' => $post_type->label,
                'menu_name' => $menu_name,
                'menu_icon' => $post_type->menu_icon ?: 'dashicons-admin-post',
                'menu_position' => $post_type->menu_position ?: 25,
                'menu_display_mode' => $post_type->menu_display_mode ?: 'category',
                'menu_parent_category' => $post_type->menu_parent_category
            );
        }

        wp_localize_script('kstb-admin-global', 'kstb_global', array(
            'admin_url' => admin_url(),
            'custom_post_types' => $custom_post_types
        ));
    }

    public function show_admin_notices() {
        // v1.0.29 LOW-2: raw $_GET を wp_unslash() + sanitize_key() 経由で比較
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page === 'kashiwazaki-seo-type-builder') {
            $flush_rewrite = isset($_GET['flush_rewrite']) ? sanitize_key(wp_unslash($_GET['flush_rewrite'])) : '';
            $kstb_message = isset($_GET['kstb_message']) ? sanitize_key(wp_unslash($_GET['kstb_message'])) : '';

            if ($flush_rewrite === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('パーマリンクを更新しました。カスタム投稿タイプがメニューに表示されない場合は、ブラウザをリロードしてください。', 'kashiwazaki-seo-type-builder') . '</p></div>';
            }

            if ($kstb_message === 'table_created') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('データベーステーブルを作成しました。', 'kashiwazaki-seo-type-builder') . '</p></div>';
            }











            if ($kstb_message === 'orphaned_menus_removed') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('不要なメニューを削除しました。ページをリロードしてください。', 'kashiwazaki-seo-type-builder') . '</p></div>';
            }

            if ($kstb_message === 'menu_cache_cleared') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('メニューキャッシュをクリアしました。ブラウザをリロードしてください。', 'kashiwazaki-seo-type-builder') . '</p></div>';
            }

            if ($kstb_message === 'permalinks_flushed') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('パーマリンクを更新しました。', 'kashiwazaki-seo-type-builder') . '</p></div>';
            }
        }
    }

    public function handle_admin_actions() {
        // v1.0.29 LOW-2: raw $_POST を wp_unslash() 経由で読む
        $action = isset($_POST['kstb_action']) ? sanitize_key(wp_unslash($_POST['kstb_action'])) : '';
        $nonce = isset($_POST['kstb_nonce']) ? wp_unslash($_POST['kstb_nonce']) : '';

        if ($action === 'create_table') {
            if (!$nonce || !wp_verify_nonce($nonce, 'kstb_create_table')) {
                return;
            }

            if (!current_user_can('manage_options')) {
                return;
            }

            KSTB_Database::create_tables();

            wp_redirect(add_query_arg('kstb_message', 'table_created', admin_url('admin.php?page=kashiwazaki-seo-type-builder')));
            exit;
        }









        if ($action === 'remove_orphaned_menus') {
            if (!$nonce || !wp_verify_nonce($nonce, 'kstb_remove_orphaned_menus')) {
                return;
            }

            if (!current_user_can('manage_options')) {
                return;
            }

            // データベースに存在しないカスタム投稿タイプを確認
            $db_post_types = KSTB_Database::get_all_post_types();
            $db_slugs = array();
            foreach ($db_post_types as $pt) {
                $db_slugs[] = $pt->slug;
            }

            // データベースにないカスタム投稿タイプがWordPressに登録されている場合は削除
            $registered_post_types = get_post_types(array('_builtin' => false), 'names');
            foreach ($registered_post_types as $registered_slug) {
                if (!in_array($registered_slug, $db_slugs)) {
                    global $wp_post_types;
                    if (isset($wp_post_types[$registered_slug])) {
                        unset($wp_post_types[$registered_slug]);
                    }
                }
            }

            wp_redirect(add_query_arg('kstb_message', 'orphaned_menus_removed', admin_url('admin.php?page=kashiwazaki-seo-type-builder')));
            exit;
        }

        if ($action === 'clear_menu_cache') {
            if (!$nonce || !wp_verify_nonce($nonce, 'kstb_clear_menu_cache')) {
                return;
            }

            if (!current_user_can('manage_options')) {
                return;
            }

            // メニューキャッシュを強制的にクリア
            global $wp_post_types, $menu, $submenu;

            // データベースにないカスタム投稿タイプをWordPressからも削除
            $db_post_types = KSTB_Database::get_all_post_types();
            $db_slugs = array();
            foreach ($db_post_types as $pt) {
                $db_slugs[] = $pt->slug;
            }

            $registered_post_types = get_post_types(array('_builtin' => false), 'names');
            foreach ($registered_post_types as $registered_slug) {
                if (!in_array($registered_slug, $db_slugs)) {
                    if (isset($wp_post_types[$registered_slug])) {
                        unset($wp_post_types[$registered_slug]);
                    }
                }
            }

            // WordPressのメニューキャッシュをクリア
            wp_cache_flush();

            // パーマリンクもフラッシュ
            flush_rewrite_rules();

            // オブジェクトキャッシュもクリア
            if (function_exists('wp_cache_delete')) {
                wp_cache_delete('post_types', 'options');
            }

            wp_redirect(add_query_arg('kstb_message', 'menu_cache_cleared', admin_url('admin.php?page=kashiwazaki-seo-type-builder')));
            exit;
        }

        if ($action === 'flush_permalinks') {
            if (!$nonce || !wp_verify_nonce($nonce, 'kstb_flush_permalinks')) {
                return;
            }

            if (!current_user_can('manage_options')) {
                return;
            }

            flush_rewrite_rules();

            wp_redirect(add_query_arg('kstb_message', 'permalinks_flushed', admin_url('admin.php?page=kashiwazaki-seo-type-builder')));
            exit;
        }
    }
}
