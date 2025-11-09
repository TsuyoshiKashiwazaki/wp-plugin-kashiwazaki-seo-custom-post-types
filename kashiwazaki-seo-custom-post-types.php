<?php
/**
 * Plugin Name: Kashiwazaki SEO Custom Post Types
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Description: カスタム投稿タイプを簡単に作成・管理するWordPressプラグイン
 * Version: 1.0.11
 * Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
 * Text Domain: kashiwazaki-seo-type-builder
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}


define('KSTB_VERSION', '1.0.11');
define('KSTB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KSTB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('KSTB_PLUGIN_BASENAME', plugin_basename(__FILE__));

class KashiwazakiSeoTypeBuilder {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once KSTB_PLUGIN_PATH . 'includes/class-database.php';
        require_once KSTB_PLUGIN_PATH . 'includes/class-parent-menu-manager.php';
        require_once KSTB_PLUGIN_PATH . 'includes/class-post-type-registrar.php';
        require_once KSTB_PLUGIN_PATH . 'includes/class-post-type-force-register.php';
        require_once KSTB_PLUGIN_PATH . 'includes/class-post-type-menu-fix.php';
        require_once KSTB_PLUGIN_PATH . 'includes/class-parent-selector.php';
        require_once KSTB_PLUGIN_PATH . 'includes/class-archive-controller.php';
        require_once KSTB_PLUGIN_PATH . 'includes/class-post-mover.php';
        require_once KSTB_PLUGIN_PATH . 'includes/class-permalink-validator.php';
        require_once KSTB_PLUGIN_PATH . 'includes/class-admin.php';
        require_once KSTB_PLUGIN_PATH . 'includes/class-ajax-handler.php';
    }

    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('init', array($this, 'init'), 5); // 優先度を上げる
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_filter('plugin_action_links_' . KSTB_PLUGIN_BASENAME, array($this, 'add_settings_link'));
        add_action('kstb_delayed_flush_rewrite_rules', array($this, 'delayed_flush_rewrite_rules'));

        // アーカイブコントローラーを最も早いタイミングで初期化
        if (!is_admin()) {
            require_once KSTB_PLUGIN_PATH . 'includes/class-archive-controller.php';
            KSTB_Archive_Controller::get_instance()->init();
        }
    }

    public function init() {
        // シンプルな重複実行チェック
        static $initialized = false;
        if ($initialized) {
            return;
        }
        $initialized = true;

        // データベースの更新チェック
        KSTB_Database::update_database();

        // カスタム投稿タイプの登録
        $registrar = KSTB_Post_Type_Registrar::get_instance();
        $registrar->init();

        // 確実に登録を実行
        if (did_action('init')) {
            $registrar->register_post_types();
        }

        // Parent Selectorは管理画面とフロントエンドの両方で必要
        KSTB_Parent_Selector::get_instance()->init();

        // アーカイブページの表示制御（最優先で実行）
        if (!is_admin()) {
            KSTB_Archive_Controller::get_instance()->init();

            // パーマリンク検証を初期化
            KSTB_Permalink_Validator::get_instance()->init();
        }

        if (is_admin()) {
            // 親メニュー管理を初期化（投稿タイプ登録より前に実行）
            KSTB_Parent_Menu_Manager::init();

            KSTB_Admin::get_instance()->init();
            KSTB_Ajax_Handler::get_instance()->init();
            KSTB_Post_Type_Menu_Fix::init();

            // 管理画面でカスタム投稿タイプが登録されていない場合、強制登録を試みる
            add_action('admin_init', array($this, 'check_and_fix_missing_post_types'));
        }
    }

    public function check_and_fix_missing_post_types() {
        // Kashiwazaki SEO Custom Post Typesページの場合のみ実行
        if (!isset($_GET['page']) || $_GET['page'] !== 'kashiwazaki-seo-type-builder') {
            return;
        }

        $db_post_types = KSTB_Database::get_all_post_types();
        if (empty($db_post_types)) {
            return;
        }

        $needs_fix = false;
        foreach ($db_post_types as $post_type) {
            if (!post_type_exists($post_type->slug)) {
                $needs_fix = true;
                break;
            }
        }

        if ($needs_fix) {
            // 自動修復を試みる
            KSTB_Post_Type_Force_Register::force_register_all();
        }
    }

    public function activate() {
        try {
            KSTB_Database::create_tables();
        } catch (Exception $e) {
            throw $e;
        }

        // initアクションは実行しない（他のプラグインとの競合を避けるため）
        // do_action('init');

        // パーマリンクを強制的にフラッシュ
        flush_rewrite_rules(true);
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function delayed_flush_rewrite_rules() {
        flush_rewrite_rules(true);
    }

    public function load_textdomain() {
        load_plugin_textdomain('kashiwazaki-seo-type-builder', false, dirname(KSTB_PLUGIN_BASENAME) . '/languages');
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=kashiwazaki-seo-type-builder') . '">' . __('設定', 'kashiwazaki-seo-type-builder') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

try {
    KashiwazakiSeoTypeBuilder::get_instance();
} catch (Exception $e) {
    throw $e;
}
