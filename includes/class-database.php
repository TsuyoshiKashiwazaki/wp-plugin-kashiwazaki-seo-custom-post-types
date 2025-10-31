<?php
if (!defined('ABSPATH')) {
    exit;
}


class KSTB_Database {
    private static $table_name = 'kstb_post_types';
    private static $categories_table_name = 'kstb_menu_categories';

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_name;
    }

    public static function get_categories_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$categories_table_name;
    }

    public static function create_tables() {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            slug varchar(20) NOT NULL,
            url_slug varchar(64) DEFAULT NULL,
            label varchar(100) NOT NULL,
            labels text NOT NULL,
            public tinyint(1) DEFAULT 1,
            publicly_queryable tinyint(1) DEFAULT 1,
            show_ui tinyint(1) DEFAULT 1,
            show_in_menu tinyint(1) DEFAULT 1,
            query_var tinyint(1) DEFAULT 1,
            rewrite text DEFAULT NULL,
            capability_type varchar(50) DEFAULT 'post',
            has_archive tinyint(1) DEFAULT 0,
            archive_display_type varchar(20) DEFAULT 'post_list',
            archive_page_id int(11) DEFAULT NULL,
            parent_directory varchar(100) DEFAULT NULL,
            hierarchical tinyint(1) DEFAULT 0,
            menu_position int(11) DEFAULT NULL,
            menu_icon varchar(100) DEFAULT NULL,
            menu_parent_category varchar(200) DEFAULT NULL,
            menu_parent_slug varchar(200) DEFAULT NULL,
            menu_display_mode varchar(20) DEFAULT 'category',
            supports text NOT NULL,
            show_in_rest tinyint(1) DEFAULT 1,
            rest_base varchar(100) DEFAULT NULL,
            taxonomies text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        try {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql);
        } catch (Exception $e) {
            throw $e;
        }

        // アーカイブ設定カラムが存在しない場合は追加
        self::add_archive_columns_if_not_exists();

        // カテゴリーテーブルの作成
        self::create_categories_table();
    }

    /**
     * メニューカテゴリーテーブルを作成
     */
    public static function create_categories_table() {
        global $wpdb;

        $table_name = self::get_categories_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            icon varchar(100) DEFAULT 'dashicons-category',
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // デフォルトカテゴリーが存在しない場合は作成
        $default_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE name = %s",
            'カスタム投稿タイプ'
        ));

        if (!$default_exists) {
            $wpdb->insert(
                $table_name,
                array('name' => 'カスタム投稿タイプ', 'icon' => 'dashicons-category'),
                array('%s', '%s')
            );
        }

        // 既存のカテゴリーをマイグレーション
        self::migrate_existing_categories();
    }

    /**
     * 既存のカテゴリーをテーブルに移行
     */
    private static function migrate_existing_categories() {
        // マイグレーションは初回のみ実行
        $migrated = get_option('kstb_categories_migrated', false);
        if ($migrated) {
            return;
        }

        global $wpdb;
        $categories_table = self::get_categories_table_name();
        $post_types_table = self::get_table_name();

        // 既存のカテゴリーを取得
        $existing_categories = $wpdb->get_col(
            "SELECT DISTINCT menu_parent_category
             FROM $post_types_table
             WHERE menu_parent_category IS NOT NULL
             AND menu_parent_category != ''"
        );

        foreach ($existing_categories as $category) {
            // カテゴリーが既にテーブルに存在するかチェック
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $categories_table WHERE name = %s",
                $category
            ));

            if (!$exists) {
                $wpdb->insert(
                    $categories_table,
                    array('name' => $category, 'icon' => 'dashicons-category'),
                    array('%s', '%s')
                );
            }
        }

        // マイグレーション完了フラグを設定
        update_option('kstb_categories_migrated', true);
    }

    /**
     * アーカイブ設定のカラムが存在しない場合は追加
     */
    public static function add_archive_columns_if_not_exists() {
        global $wpdb;
        $table_name = self::get_table_name();

        // archive_display_type カラムが存在するかチェック
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'archive_display_type'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD archive_display_type varchar(20) DEFAULT 'post_list' AFTER has_archive");
        }

        // archive_page_id カラムが存在するかチェック
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'archive_page_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD archive_page_id int(11) DEFAULT NULL AFTER archive_display_type");
        }

        // parent_directory カラムが存在するかチェック
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'parent_directory'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD parent_directory varchar(100) DEFAULT NULL AFTER archive_page_id");
        }

        // url_slug カラムが存在するかチェック
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'url_slug'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD url_slug varchar(64) DEFAULT NULL AFTER slug");
        }

        // menu_parent_category カラムが存在するかチェック
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'menu_parent_category'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD menu_parent_category varchar(200) DEFAULT NULL AFTER menu_icon");
        }

        // menu_parent_slug カラムが存在するかチェック
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'menu_parent_slug'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD menu_parent_slug varchar(200) DEFAULT NULL AFTER menu_parent_category");
        }

        // menu_display_mode カラムが存在するかチェック
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'menu_display_mode'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD menu_display_mode varchar(20) DEFAULT 'category' AFTER menu_parent_slug");

            // 既存のレコードにデフォルトカテゴリーを設定
            $wpdb->query("UPDATE $table_name SET menu_parent_category = 'カスタム投稿タイプ' WHERE menu_parent_category IS NULL OR menu_parent_category = ''");
        }

    }

    /**
     * データベースを最新バージョンに更新
     */
    public static function update_database() {
        self::add_archive_columns_if_not_exists();
    }

    public static function get_all_post_types() {
        global $wpdb;
        $table_name = self::get_table_name();
        
        try {
            $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY label ASC");
        } catch (Exception $e) {
            throw $e;
        }

        // ラベルが空の場合はスラッグから生成
        foreach ($results as $post_type) {
            if (empty($post_type->label)) {
                $post_type->label = ucfirst($post_type->slug);
            }
        }

        return $results;
    }

    public static function get_post_type($id) {
        global $wpdb;
        $table_name = self::get_table_name();
        
        try {
            $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        } catch (Exception $e) {
            throw $e;
        }

        // ラベルが空の場合はスラッグから生成
        if ($result && empty($result->label)) {
            $result->label = ucfirst($result->slug);
        }

        return $result;
    }

    public static function get_post_type_by_slug($slug) {
        global $wpdb;
        $table_name = self::get_table_name();
        
        try {
            $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE slug = %s", $slug));
        } catch (Exception $e) {
            throw $e;
        }

        // ラベルが空の場合はスラッグから生成
        if ($result && empty($result->label)) {
            $result->label = ucfirst($result->slug);
        }

        return $result;
    }

    public static function insert_post_type($data) {
        global $wpdb;
        $table_name = self::get_table_name();

        $defaults = array(
            'public' => 1,
            'publicly_queryable' => 1,
            'show_ui' => 1,
            'show_in_menu' => 1,
            'query_var' => 1,
            'capability_type' => 'post',
            'has_archive' => 0,
            'archive_display_type' => 'post_list',
            'archive_page_id' => null,
            'parent_directory' => null,
            'hierarchical' => 0,
            'menu_parent_category' => 'カスタム投稿タイプ',
            'menu_parent_slug' => null,
            'menu_display_mode' => 'category',
            'show_in_rest' => 1
        );

        $data = wp_parse_args($data, $defaults);

        // url_slugが設定されていない場合はslugを使用
        $url_slug = !empty($data['url_slug']) ? sanitize_key($data['url_slug']) : $data['slug'];

        $result = $wpdb->insert(
            $table_name,
            array(
                'slug' => sanitize_key($data['slug']),
                'url_slug' => $url_slug,
                'label' => sanitize_text_field($data['label']),
                'labels' => json_encode($data['labels']),
                'public' => (int) $data['public'],
                'publicly_queryable' => (int) $data['publicly_queryable'],
                'show_ui' => (int) $data['show_ui'],
                'show_in_menu' => (int) $data['show_in_menu'],
                'query_var' => (int) $data['query_var'],
                'rewrite' => json_encode(array('slug' => $url_slug, 'with_front' => false)),
                'capability_type' => sanitize_key($data['capability_type']),
                'has_archive' => (int) $data['has_archive'],
                'archive_display_type' => !empty($data['archive_display_type']) ? sanitize_text_field($data['archive_display_type']) : 'post_list',
                'archive_page_id' => !empty($data['archive_page_id']) ? (int) $data['archive_page_id'] : null,
                'parent_directory' => isset($data['parent_directory']) && $data['parent_directory'] !== '' ? sanitize_text_field($data['parent_directory']) : null,
                'hierarchical' => (int) $data['hierarchical'],
                'menu_position' => !empty($data['menu_position']) ? (int) $data['menu_position'] : 25,
                'menu_icon' => !empty($data['menu_icon']) ? sanitize_text_field($data['menu_icon']) : null,
                'menu_parent_category' => !empty($data['menu_parent_category']) ? sanitize_text_field($data['menu_parent_category']) : null,
                'menu_parent_slug' => !empty($data['menu_parent_slug']) ? sanitize_text_field($data['menu_parent_slug']) : null,
                'menu_display_mode' => !empty($data['menu_display_mode']) ? sanitize_text_field($data['menu_display_mode']) : 'category',
                'supports' => json_encode($data['supports']),
                'show_in_rest' => (int) $data['show_in_rest'],
                'rest_base' => $url_slug,
                'taxonomies' => !empty($data['taxonomies']) ? json_encode($data['taxonomies']) : null
            )
        );

        if ($result) {
            flush_rewrite_rules();
        }

        return $result;
    }

    public static function update_post_type($id, $data) {
        global $wpdb;
        $table_name = self::get_table_name();

        $update_data = array();

        if (isset($data['slug'])) {
            $update_data['slug'] = sanitize_key($data['slug']);
        }
        if (isset($data['url_slug'])) {
            $update_data['url_slug'] = sanitize_key($data['url_slug']);
        }
        if (isset($data['label'])) {
            $update_data['label'] = sanitize_text_field($data['label']);
        }
        if (isset($data['labels'])) {
            $update_data['labels'] = json_encode($data['labels']);
        }
        if (isset($data['public'])) {
            $update_data['public'] = (int) $data['public'];
        }
        if (isset($data['publicly_queryable'])) {
            $update_data['publicly_queryable'] = (int) $data['publicly_queryable'];
        }
        if (isset($data['show_ui'])) {
            $update_data['show_ui'] = (int) $data['show_ui'];
        }
        if (isset($data['show_in_menu'])) {
            $update_data['show_in_menu'] = (int) $data['show_in_menu'];
        }
        if (isset($data['query_var'])) {
            $update_data['query_var'] = (int) $data['query_var'];
        }
        if (isset($data['rewrite'])) {
            $update_data['rewrite'] = !empty($data['rewrite']) ? json_encode($data['rewrite']) : null;
        } elseif (isset($data['url_slug'])) {
            // url_slugが更新された場合はrewriteも更新
            $update_data['rewrite'] = json_encode(array('slug' => sanitize_key($data['url_slug']), 'with_front' => false));
        }
        if (isset($data['capability_type'])) {
            $update_data['capability_type'] = sanitize_key($data['capability_type']);
        }
        if (isset($data['has_archive'])) {
            $update_data['has_archive'] = (int) $data['has_archive'];
        }
        if (isset($data['archive_display_type'])) {
            $update_data['archive_display_type'] = sanitize_text_field($data['archive_display_type']);
        }
        if (isset($data['archive_page_id'])) {
            $update_data['archive_page_id'] = !empty($data['archive_page_id']) ? (int) $data['archive_page_id'] : null;
        }
        if (isset($data['parent_directory'])) {
            $update_data['parent_directory'] = $data['parent_directory'] !== '' ? sanitize_text_field($data['parent_directory']) : null;
        }
        if (isset($data['hierarchical'])) {
            $update_data['hierarchical'] = (int) $data['hierarchical'];
        }
        if (isset($data['menu_position'])) {
            $update_data['menu_position'] = !empty($data['menu_position']) ? (int) $data['menu_position'] : null;
        }
        if (isset($data['menu_icon'])) {
            $update_data['menu_icon'] = !empty($data['menu_icon']) ? sanitize_text_field($data['menu_icon']) : null;
        }
        if (isset($data['menu_parent_category'])) {
            $update_data['menu_parent_category'] = !empty($data['menu_parent_category']) ? sanitize_text_field($data['menu_parent_category']) : null;
        }
        if (isset($data['menu_parent_slug'])) {
            $update_data['menu_parent_slug'] = !empty($data['menu_parent_slug']) ? sanitize_text_field($data['menu_parent_slug']) : null;
        }
        if (isset($data['menu_display_mode'])) {
            $update_data['menu_display_mode'] = !empty($data['menu_display_mode']) ? sanitize_text_field($data['menu_display_mode']) : 'category';
        }
        if (isset($data['supports'])) {
            $update_data['supports'] = json_encode($data['supports']);
        }
        if (isset($data['show_in_rest'])) {
            $update_data['show_in_rest'] = (int) $data['show_in_rest'];
        }
        if (isset($data['rest_base'])) {
            $update_data['rest_base'] = $data['slug'];
        }
        if (isset($data['taxonomies'])) {
            $update_data['taxonomies'] = !empty($data['taxonomies']) ? json_encode($data['taxonomies']) : null;
        }

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $id)
        );

        if ($result !== false) {
            // has_archiveが変更された場合は確実にパーマリンクをフラッシュ
            if (isset($update_data['has_archive'])) {
                delete_option('rewrite_rules');
            }
            flush_rewrite_rules();
        }

        return $result;
    }

    public static function delete_post_type($id) {
        global $wpdb;
        $table_name = self::get_table_name();

        $result = $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );

        if ($result !== false && $result > 0) {
            flush_rewrite_rules();
        }

        return $result;
    }

    public static function table_exists() {
        global $wpdb;
        $table_name = self::get_table_name();
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }

    public static function diagnose() {
        global $wpdb;
        $table_name = self::get_table_name();
        $diagnosis = array();

        $diagnosis['table_exists'] = self::table_exists();

        if ($diagnosis['table_exists']) {
            $diagnosis['row_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $diagnosis['last_error'] = $wpdb->last_error;
        }

        return $diagnosis;
    }

    /**
     * カテゴリーを取得
     */
    public static function get_category($name) {
        global $wpdb;
        $table_name = self::get_categories_table_name();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE name = %s",
            $name
        ));
    }

    /**
     * すべてのカテゴリーを取得
     */
    public static function get_all_categories() {
        global $wpdb;
        $table_name = self::get_categories_table_name();

        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY sort_order ASC, name ASC");
    }

    /**
     * カテゴリーを保存（新規または更新）
     */
    public static function save_category($name, $icon = 'dashicons-category') {
        global $wpdb;
        $table_name = self::get_categories_table_name();

        // 既存チェック
        $existing = self::get_category($name);

        if ($existing) {
            // 更新
            return $wpdb->update(
                $table_name,
                array('icon' => sanitize_text_field($icon)),
                array('name' => $name),
                array('%s'),
                array('%s')
            );
        } else {
            // 新規
            return $wpdb->insert(
                $table_name,
                array(
                    'name' => sanitize_text_field($name),
                    'icon' => sanitize_text_field($icon)
                ),
                array('%s', '%s')
            );
        }
    }

    /**
     * カテゴリーのアイコンを更新
     */
    public static function update_category_icon($name, $icon) {
        global $wpdb;
        $table_name = self::get_categories_table_name();

        return $wpdb->update(
            $table_name,
            array('icon' => sanitize_text_field($icon)),
            array('name' => $name),
            array('%s'),
            array('%s')
        );
    }

    /**
     * カテゴリーを削除
     */
    public static function delete_category($name) {
        global $wpdb;
        $table_name = self::get_categories_table_name();

        return $wpdb->delete(
            $table_name,
            array('name' => $name),
            array('%s')
        );
    }
}
