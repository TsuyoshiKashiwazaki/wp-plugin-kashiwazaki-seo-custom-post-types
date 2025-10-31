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
        if (isset($_GET['page']) && $_GET['page'] === 'kashiwazaki-seo-type-builder') {
            if (isset($_GET['flush_rewrite']) && $_GET['flush_rewrite'] === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('パーマリンクを更新しました。カスタム投稿タイプがメニューに表示されない場合は、ブラウザをリロードしてください。', 'kashiwazaki-seo-type-builder') . '</p></div>';
            }

            if (isset($_GET['kstb_message']) && $_GET['kstb_message'] === 'table_created') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('データベーステーブルを作成しました。', 'kashiwazaki-seo-type-builder') . '</p></div>';
            }











            if (isset($_GET['kstb_message']) && $_GET['kstb_message'] === 'orphaned_menus_removed') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('不要なメニューを削除しました。ページをリロードしてください。', 'kashiwazaki-seo-type-builder') . '</p></div>';
            }

            if (isset($_GET['kstb_message']) && $_GET['kstb_message'] === 'menu_cache_cleared') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('メニューキャッシュをクリアしました。ブラウザをリロードしてください。', 'kashiwazaki-seo-type-builder') . '</p></div>';
            }

            if (isset($_GET['kstb_message']) && $_GET['kstb_message'] === 'permalinks_flushed') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('パーマリンクを更新しました。', 'kashiwazaki-seo-type-builder') . '</p></div>';
            }
        }
    }

    public function handle_admin_actions() {
        if (isset($_POST['kstb_action']) && $_POST['kstb_action'] === 'create_table') {
            if (!isset($_POST['kstb_nonce']) || !wp_verify_nonce($_POST['kstb_nonce'], 'kstb_create_table')) {
                return;
            }

            if (!current_user_can('manage_options')) {
                return;
            }

            KSTB_Database::create_tables();

            wp_redirect(add_query_arg('kstb_message', 'table_created', admin_url('admin.php?page=kashiwazaki-seo-type-builder')));
            exit;
        }









        if (isset($_POST['kstb_action']) && $_POST['kstb_action'] === 'remove_orphaned_menus') {
            if (!isset($_POST['kstb_nonce']) || !wp_verify_nonce($_POST['kstb_nonce'], 'kstb_remove_orphaned_menus')) {
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

        if (isset($_POST['kstb_action']) && $_POST['kstb_action'] === 'clear_menu_cache') {
            if (!isset($_POST['kstb_nonce']) || !wp_verify_nonce($_POST['kstb_nonce'], 'kstb_clear_menu_cache')) {
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

        if (isset($_POST['kstb_action']) && $_POST['kstb_action'] === 'flush_permalinks') {
            if (!isset($_POST['kstb_nonce']) || !wp_verify_nonce($_POST['kstb_nonce'], 'kstb_flush_permalinks')) {
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
