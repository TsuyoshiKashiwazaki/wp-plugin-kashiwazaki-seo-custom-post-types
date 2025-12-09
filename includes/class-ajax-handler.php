<?php
if (!defined('ABSPATH')) {
    exit;
}


class KSTB_Ajax_Handler {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init() {
        add_action('wp_ajax_kstb_save_post_type', array($this, 'save_post_type'));
        add_action('wp_ajax_kstb_delete_post_type', array($this, 'delete_post_type'));
        add_action('wp_ajax_kstb_get_post_type', array($this, 'get_post_type'));
        add_action('wp_ajax_kstb_flush_rewrite_rules', array($this, 'flush_rewrite_rules'));
        add_action('wp_ajax_kstb_reregister_post_type', array($this, 'reregister_post_type'));
        add_action('wp_ajax_kstb_force_register_all', array($this, 'force_register_all'));
        add_action('wp_ajax_kstb_force_reregister_all', array($this, 'force_reregister_all'));
        add_action('wp_ajax_kstb_get_posts_by_type', array($this, 'get_posts_by_type'));
        add_action('wp_ajax_kstb_get_taxonomies_by_type', array($this, 'get_taxonomies_by_type'));
        add_action('wp_ajax_kstb_move_posts', array($this, 'move_posts'));

        // メニュー管理用のアクション
        add_action('wp_ajax_kstb_update_menu_assignment', array($this, 'update_menu_assignment'));
        add_action('wp_ajax_kstb_add_category', array($this, 'add_category'));
        add_action('wp_ajax_kstb_rename_category', array($this, 'rename_category'));
        add_action('wp_ajax_kstb_delete_category', array($this, 'delete_category'));
        add_action('wp_ajax_kstb_get_categories', array($this, 'get_categories'));
        add_action('wp_ajax_kstb_update_category_icon', array($this, 'update_category_icon'));
        add_action('wp_ajax_kstb_save_all_menu_assignments', array($this, 'save_all_menu_assignments'));

        // 親ページ検索用
        add_action('wp_ajax_kstb_search_parent_pages', array($this, 'search_parent_pages'));
    }

    public function save_post_type() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $url_slug = isset($_POST['url_slug']) ? sanitize_key($_POST['url_slug']) : '';
        $slug = isset($_POST['slug']) ? sanitize_key($_POST['slug']) : '';
        $label = isset($_POST['label']) ? sanitize_text_field($_POST['label']) : '';

        // URLスラッグは必須
        if (empty($url_slug) || empty($label)) {
            wp_send_json_error(__('URLスラッグとラベルを入力してください', 'kashiwazaki-seo-type-builder'));
            return;
        }

        // URLスラッグの検証
        if (strlen($url_slug) > 64) {
            wp_send_json_error(__('URLスラッグは64文字以内で入力してください', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $url_slug)) {
            wp_send_json_error(__('URLスラッグは半角英数字、ハイフン、アンダースコアのみ使用できます', 'kashiwazaki-seo-type-builder'));
            return;
        }

        // WordPress既存の投稿タイプ（内部名として使用不可）
        $reserved_post_types = array('post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation');

        // WordPress管理画面・コア機能と競合する内部名（URLスラッグとしては使用可能、内部名は自動変換）
        $reserved_internal_names = array(
            'media',        // メディアライブラリ画面と競合
            'link',         // リンクマネージャーと競合
            'links',        // リンクマネージャーと競合
            'theme',        // テーマ管理と競合
            'themes',       // テーマ管理と競合
            'plugin',       // プラグイン管理と競合
            'plugins',      // プラグイン管理と競合
            'user',         // ユーザー管理と競合
            'users',        // ユーザー管理と競合
            'option',       // オプション管理と競合
            'options',      // オプション管理と競合
            'comment',      // コメント管理と競合
            'comments',     // コメント管理と競合
            'admin',        // 管理画面と競合
            'site',         // サイト管理と競合
            'sites',        // サイト管理と競合
            'network',      // ネットワーク管理と競合
            'dashboard',    // ダッシュボードと競合
            'upload',       // アップロードと競合
            'edit',         // 編集画面と競合
            'profile',      // プロフィール画面と競合
            'tools',        // ツール画面と競合
            'import',       // インポートと競合
            'export',       // エクスポートと競合
            'settings',     // 設定画面と競合
            'update',       // 更新画面と競合
            'menu',         // メニュー管理と競合
            'term',         // タームと競合
            'widget',       // ウィジェットと競合
            'widgets',      // ウィジェットと競合
        );

        // 内部名（短縮名）が空の場合はURLスラッグから自動生成
        if (empty($slug)) {
            // URLスラッグの最初の20文字を使用（ハイフンで区切られた単語を考慮）
            if (strlen($url_slug) <= 20) {
                $slug = $url_slug;
            } else {
                // 20文字まで切り詰め、最後のハイフン以降を削除して綺麗にする
                $slug = substr($url_slug, 0, 20);
                $last_hyphen = strrpos($slug, '-');
                if ($last_hyphen !== false && $last_hyphen > 10) {
                    $slug = substr($slug, 0, $last_hyphen);
                }
            }
        } else {
            // 内部名が入力されている場合は20文字以内に強制的に切り詰める
            if (strlen($slug) > 20) {
                $slug = substr($slug, 0, 20);
            }
        }

        // 内部名が予約語の場合は自動で別名に変換
        if (in_array($slug, $reserved_internal_names)) {
            $slug = $slug . '_cpt';
        }

        // 内部名の検証（ここでは文字種のみチェック）
        if (!empty($slug) && !preg_match('/^[a-z0-9_-]+$/', $slug)) {
            wp_send_json_error(__('内部名は半角英数字、ハイフン、アンダースコアのみ使用できます', 'kashiwazaki-seo-type-builder'));
            return;
        }

        // 内部名のチェック（既存投稿タイプ）
        if (in_array($slug, $reserved_post_types)) {
            wp_send_json_error(sprintf(__('「%s」はWordPressの既存投稿タイプのため使用できません', 'kashiwazaki-seo-type-builder'), $slug));
            return;
        }

        $existing = KSTB_Database::get_post_type_by_slug($slug);
        if ($existing && $existing->id != $id) {
            wp_send_json_error(__('このスラッグは既に使用されています', 'kashiwazaki-seo-type-builder'));
            return;
        }

        // 空または未設定の場合はデフォルト値を強制使用
        $labels = array(
            'name' => !empty($_POST['labels']['name']) ? sanitize_text_field($_POST['labels']['name']) : $label,
            'singular_name' => !empty($_POST['labels']['singular_name']) ? sanitize_text_field($_POST['labels']['singular_name']) : $label,
            'menu_name' => !empty($_POST['labels']['menu_name']) ? sanitize_text_field($_POST['labels']['menu_name']) : $label,
            'name_admin_bar' => !empty($_POST['labels']['name_admin_bar']) ? sanitize_text_field($_POST['labels']['name_admin_bar']) : $label,
            'add_new' => !empty($_POST['labels']['add_new']) ? sanitize_text_field($_POST['labels']['add_new']) : '新規追加',
            'add_new_item' => !empty($_POST['labels']['add_new_item']) ? sanitize_text_field($_POST['labels']['add_new_item']) : '新規' . $label . 'を追加',
            'new_item' => !empty($_POST['labels']['new_item']) ? sanitize_text_field($_POST['labels']['new_item']) : '新規' . $label,
            'edit_item' => !empty($_POST['labels']['edit_item']) ? sanitize_text_field($_POST['labels']['edit_item']) : $label . 'を編集',
            'view_item' => !empty($_POST['labels']['view_item']) ? sanitize_text_field($_POST['labels']['view_item']) : $label . 'を表示',
            'view_items' => !empty($_POST['labels']['view_items']) ? sanitize_text_field($_POST['labels']['view_items']) : $label . 'を表示',
            'all_items' => !empty($_POST['labels']['all_items']) ? sanitize_text_field($_POST['labels']['all_items']) : 'すべての' . $label,
            'search_items' => !empty($_POST['labels']['search_items']) ? sanitize_text_field($_POST['labels']['search_items']) : $label . 'を検索',
            'parent_item_colon' => !empty($_POST['labels']['parent_item_colon']) ? sanitize_text_field($_POST['labels']['parent_item_colon']) : '親' . $label . ':',
            'not_found' => !empty($_POST['labels']['not_found']) ? sanitize_text_field($_POST['labels']['not_found']) : $label . 'が見つかりません',
            'not_found_in_trash' => !empty($_POST['labels']['not_found_in_trash']) ? sanitize_text_field($_POST['labels']['not_found_in_trash']) : 'ゴミ箱に' . $label . 'が見つかりません',
            'featured_image' => !empty($_POST['labels']['featured_image']) ? sanitize_text_field($_POST['labels']['featured_image']) : 'アイキャッチ画像',
            'set_featured_image' => !empty($_POST['labels']['set_featured_image']) ? sanitize_text_field($_POST['labels']['set_featured_image']) : 'アイキャッチ画像を設定',
            'remove_featured_image' => !empty($_POST['labels']['remove_featured_image']) ? sanitize_text_field($_POST['labels']['remove_featured_image']) : 'アイキャッチ画像を削除',
            'use_featured_image' => !empty($_POST['labels']['use_featured_image']) ? sanitize_text_field($_POST['labels']['use_featured_image']) : 'アイキャッチ画像として使用',
            'archives' => !empty($_POST['labels']['archives']) ? sanitize_text_field($_POST['labels']['archives']) : $label . 'アーカイブ',
            'insert_into_item' => !empty($_POST['labels']['insert_into_item']) ? sanitize_text_field($_POST['labels']['insert_into_item']) : $label . 'に挿入',
            'uploaded_to_this_item' => !empty($_POST['labels']['uploaded_to_this_item']) ? sanitize_text_field($_POST['labels']['uploaded_to_this_item']) : 'この' . $label . 'にアップロード',
            'filter_items_list' => !empty($_POST['labels']['filter_items_list']) ? sanitize_text_field($_POST['labels']['filter_items_list']) : $label . 'リストをフィルター',
            'items_list_navigation' => !empty($_POST['labels']['items_list_navigation']) ? sanitize_text_field($_POST['labels']['items_list_navigation']) : $label . 'リストナビゲーション',
            'items_list' => !empty($_POST['labels']['items_list']) ? sanitize_text_field($_POST['labels']['items_list']) : $label . 'リスト',
            // 投稿公開後のボタン用ラベル
            'item_published' => $label . 'を公開しました。',
            'item_published_privately' => $label . 'を非公開で公開しました。',
            'item_reverted_to_draft' => $label . 'を下書きに戻しました。',
            'item_scheduled' => $label . 'を予約投稿しました。',
            'item_updated' => $label . 'を更新しました。',
            'item_link' => $label . 'リンク',
            'item_link_description' => $label . 'へのリンク。'
        );

        $supports = isset($_POST['supports']) && is_array($_POST['supports']) ? array_map('sanitize_key', $_POST['supports']) : array('title', 'editor');

        $rewrite = array(
            'slug' => $url_slug,
            'with_front' => false
        );

        $taxonomies = isset($_POST['taxonomies']) && is_array($_POST['taxonomies']) ? array_map('sanitize_key', $_POST['taxonomies']) : array();

        // スラッグトップページの設定を処理
        $slug_top_display = isset($_POST['slug_top_display']) ? sanitize_text_field($_POST['slug_top_display']) : 'unspecified';
        $has_archive = false;
        $archive_display_type = 'post_list';
        $archive_page_id = null;

        if ($slug_top_display === 'unspecified') {
            $has_archive = false;
            $archive_display_type = 'default';
        } elseif ($slug_top_display === 'none') {
            $has_archive = false;
            $archive_display_type = 'none';
        } elseif ($slug_top_display === 'archive') {
            $has_archive = true;
            $archive_display_type = 'post_list';
        } elseif ($slug_top_display === 'page') {
            $has_archive = true;
            $archive_display_type = 'custom_page';
            $archive_page_id = isset($_POST['archive_page_id']) && $_POST['archive_page_id'] !== '' ? intval($_POST['archive_page_id']) : null;
        }

        $data = array(
            'slug' => $slug,
            'url_slug' => $url_slug,
            'label' => $label,
            'labels' => $labels,
            'public' => isset($_POST['public']) ? (bool) $_POST['public'] : true,
            'publicly_queryable' => isset($_POST['publicly_queryable']) ? (bool) $_POST['publicly_queryable'] : true,
            'show_ui' => isset($_POST['show_ui']) ? (bool) $_POST['show_ui'] : true,
            'show_in_menu' => isset($_POST['show_in_menu']) ? (bool) $_POST['show_in_menu'] : true,
            'query_var' => isset($_POST['query_var']) ? (bool) $_POST['query_var'] : true,
            'rewrite' => $rewrite,
            'capability_type' => 'post',
            'has_archive' => $has_archive,
            'archive_display_type' => $archive_display_type,
            'archive_page_id' => $archive_page_id,
            'parent_directory' => isset($_POST['parent_directory']) ? sanitize_text_field($_POST['parent_directory']) : '',
            'hierarchical' => isset($_POST['hierarchical']) ? (bool) $_POST['hierarchical'] : false,
            'menu_position' => isset($_POST['menu_position']) && $_POST['menu_position'] !== '' ? intval($_POST['menu_position']) : null,
            'menu_icon' => isset($_POST['menu_icon']) ? sanitize_text_field($_POST['menu_icon']) : null,
            'menu_parent_category' => isset($_POST['menu_parent_category']) ? sanitize_text_field($_POST['menu_parent_category']) : null,
            'menu_parent_slug' => isset($_POST['menu_parent_slug']) ? sanitize_text_field($_POST['menu_parent_slug']) : null,
            'menu_display_mode' => isset($_POST['menu_display_mode']) ? sanitize_text_field($_POST['menu_display_mode']) : 'category',
            'supports' => $supports,
            'show_in_rest' => true,
            'rest_base' => $url_slug,
            'taxonomies' => $taxonomies
        );

        if ($id) {
            $result = KSTB_Database::update_post_type($id, $data);
            if ($result !== false) {
                // 投稿タイプを強制的に再登録
                $this->force_reregister_post_type($slug);

                // パーマリンクルールをフラッシュ
                flush_rewrite_rules();

                wp_send_json_success(array('message' => __('更新しました', 'kashiwazaki-seo-type-builder')));
            } else {
                wp_send_json_error(__('更新に失敗しました', 'kashiwazaki-seo-type-builder'));
            }
        } else {
            $result = KSTB_Database::insert_post_type($data);
            if ($result) {
                // 投稿タイプを強制的に登録
                $this->force_reregister_post_type($slug);

                // パーマリンクルールをフラッシュ
                flush_rewrite_rules();

                wp_send_json_success(array('message' => __('作成しました', 'kashiwazaki-seo-type-builder')));
            } else {
                wp_send_json_error(__('作成に失敗しました', 'kashiwazaki-seo-type-builder'));
            }
        }
    }

    public function delete_post_type() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(__('無効なIDです', 'kashiwazaki-seo-type-builder'));
            return;
        }

        // 削除前にスラッグを取得（メニュー削除のため）
        $post_type_data = KSTB_Database::get_post_type($id);
        if (!$post_type_data) {
            wp_send_json_error(__('投稿タイプが見つかりません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $slug = $post_type_data->slug;

        $result = KSTB_Database::delete_post_type($id);

        if ($result !== false) {
            // パーマリンクをフラッシュ
            flush_rewrite_rules();

            wp_send_json_success(array(
                'message' => __('削除しました', 'kashiwazaki-seo-type-builder'),
                'deleted_slug' => $slug
            ));
        } else {
            global $wpdb;
            $error_message = $wpdb->last_error ? $wpdb->last_error : __('削除に失敗しました', 'kashiwazaki-seo-type-builder');
            wp_send_json_error($error_message);
        }
    }

    public function get_post_type() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (!$id) {
            wp_send_json_error(__('無効なIDです', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $post_type = KSTB_Database::get_post_type($id);

        if ($post_type) {
            $post_type->labels = json_decode($post_type->labels, true);
            $post_type->supports = json_decode($post_type->supports, true);
            $post_type->rewrite = $post_type->rewrite ? json_decode($post_type->rewrite, true) : false;
            $post_type->taxonomies = $post_type->taxonomies ? json_decode($post_type->taxonomies, true) : array();

            wp_send_json_success($post_type);
        } else {
            wp_send_json_error(__('投稿タイプが見つかりません', 'kashiwazaki-seo-type-builder'));
        }
    }

    /**
     * 投稿タイプを強制的に再登録してリライトルールを更新
     */
    private function force_reregister_post_type($slug) {
        global $wp_post_types, $wp_rewrite;
        
        // 既存の投稿タイプを削除
        if (isset($wp_post_types[$slug])) {
            unset($wp_post_types[$slug]);
        }
        
        // データベースから最新の設定を取得
        $post_type = KSTB_Database::get_post_type_by_slug($slug);
        if (!$post_type) {
            return;
        }
        
        // 投稿タイプを再登録
        $labels = json_decode($post_type->labels, true);
        $supports = json_decode($post_type->supports, true);
        
        if (empty($labels) || !is_array($labels)) {
            $labels = array();
        }
        
        if (empty($supports) || !is_array($supports)) {
            $supports = array('title', 'editor');
        }
        
        // rewrite設定の準備
        $rewrite = json_decode($post_type->rewrite, true);
        if (empty($rewrite)) {
            $rewrite = array(
                'slug' => $post_type->slug,
                'with_front' => false
            );
        } else {
            // slug は常に投稿タイプのslugを使用
            $rewrite['slug'] = $post_type->slug;
        }
        
        // 親ディレクトリの設定を適用
        if (!empty($post_type->parent_directory)) {
            $parent_dir = trim($post_type->parent_directory, '/');
            $rewrite['slug'] = $parent_dir . '/' . $post_type->slug;
        }
        
        $args = array(
            'label' => $post_type->label,
            'labels' => $labels,
            'public' => (bool) $post_type->public,
            'publicly_queryable' => (bool) $post_type->publicly_queryable,
            'show_ui' => (bool) $post_type->show_ui,
            'show_in_menu' => (bool) $post_type->show_in_menu,
            'query_var' => (bool) $post_type->query_var,
            'rewrite' => $rewrite,
            'capability_type' => 'post',
            'has_archive' => (bool) $post_type->has_archive,
            'hierarchical' => (bool) $post_type->hierarchical,
            'menu_position' => (int) $post_type->menu_position ?: 25,
            'menu_icon' => $post_type->menu_icon,
            'supports' => $supports,
            'show_in_rest' => true,
            'rest_base' => $post_type->slug
        );
        
        // 投稿タイプを登録
        register_post_type($post_type->slug, $args);
        
        // タクソノミーも再登録
        $taxonomies = json_decode($post_type->taxonomies, true);
        if (!empty($taxonomies) && is_array($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                if (taxonomy_exists($taxonomy)) {
                    register_taxonomy_for_object_type($taxonomy, $post_type->slug);
                }
            }
        }
        
        // 古いリライトルールを完全にクリア
        delete_option('rewrite_rules');
        
        // リライトルールを完全に再構築
        $wp_rewrite->init();
        $wp_rewrite->set_permalink_structure(get_option('permalink_structure'));
        $wp_rewrite->flush_rules(true);
    }
    
    public function flush_rewrite_rules() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        flush_rewrite_rules();

        wp_send_json_success(array('message' => __('パーマリンクを更新しました', 'kashiwazaki-seo-type-builder')));
    }

    public function reregister_post_type() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(__('無効なIDです', 'kashiwazaki-seo-type-builder'));
            return;
        }

        // データベースから投稿タイプを取得
        $post_type = KSTB_Database::get_post_type($id);

        if (!$post_type) {
            wp_send_json_error(__('投稿タイプが見つかりません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        // 強制登録ヘルパーを使用
        $result = KSTB_Post_Type_Force_Register::force_register($post_type);

        if (is_wp_error($result)) {
            wp_send_json_error(__('再登録中にエラーが発生しました: ', 'kashiwazaki-seo-type-builder') . $result->get_error_message());
        } else {
            wp_send_json_success(array('message' => sprintf(__('%sを再登録しました。ページをリロードしてください。', 'kashiwazaki-seo-type-builder'), $post_type->label)));
        }
    }

    public function force_register_all() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        KSTB_Post_Type_Force_Register::force_register_all();

        wp_send_json_success(array('message' => __('すべてのカスタム投稿タイプを再登録しました。', 'kashiwazaki-seo-type-builder')));
    }

    public function force_reregister_all() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        // 静的変数をリセットするためにリフレクションを使用
        $reflector = new ReflectionMethod('KSTB_Post_Type_Registrar', 'register_post_types');
        $static_vars = $reflector->getStaticVariables();

        // 強制的に再登録
        KSTB_Post_Type_Registrar::get_instance()->register_post_types();

        wp_send_json_success(__('カスタム投稿タイプを強制再登録しました', 'kashiwazaki-seo-type-builder'));
    }

    /**
     * 投稿タイプの記事一覧を取得（Ajax）
     */
    public function get_posts_by_type() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';
        $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;

        if (empty($post_type)) {
            wp_send_json_error(__('投稿タイプが指定されていません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $args = array();

        // カテゴリフィルタが指定されている場合
        if (!empty($taxonomy) && $term_id > 0) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $term_id
                )
            );
        }

        $post_mover = KSTB_Post_Mover::get_instance();
        $posts = $post_mover->get_posts_by_type($post_type, $args);

        wp_send_json_success(array(
            'posts' => $posts,
            'count' => count($posts)
        ));
    }

    /**
     * 投稿タイプに紐づくタクソノミー一覧を取得（Ajax）
     */
    public function get_taxonomies_by_type() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';

        if (empty($post_type)) {
            wp_send_json_error(__('投稿タイプが指定されていません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $post_mover = KSTB_Post_Mover::get_instance();
        $taxonomies = $post_mover->get_taxonomies_by_post_type($post_type);

        wp_send_json_success(array(
            'taxonomies' => $taxonomies,
            'count' => count($taxonomies)
        ));
    }

    /**
     * 記事を移動（Ajax）
     */
    public function move_posts() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
        $from_type = isset($_POST['from_type']) ? sanitize_key($_POST['from_type']) : '';
        $to_type = isset($_POST['to_type']) ? sanitize_key($_POST['to_type']) : '';

        if (empty($post_ids)) {
            wp_send_json_error(__('移動する記事が選択されていません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (empty($from_type) || empty($to_type)) {
            wp_send_json_error(__('移動元または移動先の投稿タイプが指定されていません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $post_mover = KSTB_Post_Mover::get_instance();
        $result = $post_mover->move_posts($post_ids, $from_type, $to_type);

        if ($result['success']) {
            $message = sprintf(
                __('%d件の記事を移動しました。', 'kashiwazaki-seo-type-builder'),
                $result['moved_count']
            );
            if ($result['failed_count'] > 0) {
                $message .= sprintf(
                    __(' (%d件は失敗しました)', 'kashiwazaki-seo-type-builder'),
                    $result['failed_count']
                );
            }
            wp_send_json_success(array(
                'message' => $message,
                'result' => $result
            ));
        } else {
            $error_message = !empty($result['errors']) ? implode("\n", $result['errors']) : __('記事の移動に失敗しました', 'kashiwazaki-seo-type-builder');
            wp_send_json_error($error_message);
        }
    }

    /**
     * メニュー割り当ての更新
     */
    public function update_menu_assignment() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $post_type_id = isset($_POST['post_type_id']) ? intval($_POST['post_type_id']) : 0;
        $menu_mode = isset($_POST['menu_mode']) ? sanitize_text_field($_POST['menu_mode']) : '';

        if (empty($post_type_id)) {
            wp_send_json_error(__('投稿タイプIDが指定されていません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        // menu_modeをパース
        $data = array();
        if ($menu_mode === 'toplevel') {
            $data['menu_display_mode'] = 'toplevel';
            $data['menu_parent_category'] = null;
            $data['menu_parent_slug'] = null;
        } elseif (strpos($menu_mode, 'category:') === 0) {
            $category = substr($menu_mode, 9); // "category:" を削除
            $data['menu_display_mode'] = 'category';
            $data['menu_parent_category'] = sanitize_text_field($category);
            $data['menu_parent_slug'] = null;
        } else {
            wp_send_json_error(__('無効なメニューモードです', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $result = KSTB_Database::update_post_type($post_type_id, $data);

        if ($result !== false) {
            // 投稿タイプを再登録
            $post_type = KSTB_Database::get_post_type($post_type_id);
            if ($post_type) {
                $this->force_reregister_post_type($post_type->slug);
            }

            wp_send_json_success(__('メニュー設定を更新しました', 'kashiwazaki-seo-type-builder'));
        } else {
            wp_send_json_error(__('メニュー設定の更新に失敗しました', 'kashiwazaki-seo-type-builder'));
        }
    }

    /**
     * カテゴリーの追加
     */
    public function add_category() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $category_name = isset($_POST['category_name']) ? trim(sanitize_text_field($_POST['category_name'])) : '';
        $icon = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : 'dashicons-category';

        if (empty($category_name)) {
            wp_send_json_error(__('カテゴリー名を入力してください', 'kashiwazaki-seo-type-builder'));
            return;
        }

        // テーブルが存在するか確認
        global $wpdb;
        $categories_table = KSTB_Database::get_categories_table_name();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$categories_table'") === $categories_table;

        if (!$table_exists) {
            // テーブルが存在しない場合は作成
            KSTB_Database::create_categories_table();

            // 再チェック
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$categories_table'") === $categories_table;
            if (!$table_exists) {
                wp_send_json_error(__('カテゴリーテーブルの作成に失敗しました。データベース権限を確認してください。', 'kashiwazaki-seo-type-builder'));
                return;
            }
        }

        // 既存のカテゴリーをチェック
        $existing = KSTB_Database::get_category($category_name);
        if ($existing) {
            wp_send_json_error(__('このカテゴリー名は既に存在します: ' . $category_name, 'kashiwazaki-seo-type-builder'));
            return;
        }

        // カテゴリーを保存
        $result = KSTB_Database::save_category($category_name, $icon);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('カテゴリーを追加しました', 'kashiwazaki-seo-type-builder'),
                'category' => $category_name,
                'icon' => $icon
            ));
        } else {
            // 詳細なエラー情報を返す
            $error_msg = __('カテゴリーの追加に失敗しました', 'kashiwazaki-seo-type-builder');
            if (!empty($wpdb->last_error)) {
                $error_msg .= ': ' . $wpdb->last_error;
            }
            wp_send_json_error($error_msg);
        }
    }

    /**
     * カテゴリーの名前変更
     */
    public function rename_category() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $old_name = isset($_POST['old_name']) ? sanitize_text_field($_POST['old_name']) : '';
        $new_name = isset($_POST['new_name']) ? sanitize_text_field($_POST['new_name']) : '';

        if (empty($old_name) || empty($new_name)) {
            wp_send_json_error(__('カテゴリー名を入力してください', 'kashiwazaki-seo-type-builder'));
            return;
        }

        // このカテゴリーを使用しているすべての投稿タイプを更新
        global $wpdb;
        $table_name = KSTB_Database::get_table_name();

        $result = $wpdb->update(
            $table_name,
            array('menu_parent_category' => $new_name),
            array('menu_parent_category' => $old_name),
            array('%s'),
            array('%s')
        );

        // カテゴリーテーブルも更新
        $categories_table = KSTB_Database::get_categories_table_name();
        $wpdb->update(
            $categories_table,
            array('name' => $new_name),
            array('name' => $old_name),
            array('%s'),
            array('%s')
        );

        if ($result !== false) {
            wp_send_json_success(__('カテゴリー名を変更しました', 'kashiwazaki-seo-type-builder'));
        } else {
            wp_send_json_error(__('カテゴリー名の変更に失敗しました', 'kashiwazaki-seo-type-builder'));
        }
    }

    /**
     * カテゴリーの削除（投稿タイプをトップレベルに移動）
     */
    public function delete_category() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';

        if (empty($category_name)) {
            wp_send_json_error(__('カテゴリー名が指定されていません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        // このカテゴリーを使用しているすべての投稿タイプをトップレベルに変更
        global $wpdb;
        $table_name = KSTB_Database::get_table_name();

        $result1 = $wpdb->update(
            $table_name,
            array(
                'menu_display_mode' => 'toplevel',
                'menu_parent_category' => null
            ),
            array('menu_parent_category' => $category_name),
            array('%s', '%s'),
            array('%s')
        );

        // カテゴリーテーブルからも削除
        $result2 = KSTB_Database::delete_category($category_name);

        // 投稿タイプの更新は0件でも成功（該当なし）、カテゴリー削除が成功すればOK
        if ($result2 !== false && $result2 > 0) {
            wp_send_json_success(__('カテゴリーを削除しました（投稿タイプはトップレベルに移動）', 'kashiwazaki-seo-type-builder'));
        } else if ($result1 !== false) {
            // カテゴリーテーブルからの削除は失敗したが、投稿タイプは更新できた
            wp_send_json_success(__('カテゴリーを削除しました', 'kashiwazaki-seo-type-builder'));
        } else {
            wp_send_json_error(__('カテゴリーの削除に失敗しました', 'kashiwazaki-seo-type-builder'));
        }
    }

    /**
     * カテゴリー一覧の取得
     */
    public function get_categories() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        // カテゴリーテーブルからすべてのカテゴリーを取得
        $all_categories = KSTB_Database::get_all_categories();
        $categories = array();

        foreach ($all_categories as $cat_data) {
            $categories[$cat_data->name] = array(
                'name' => $cat_data->name,
                'icon' => $cat_data->icon ?: 'dashicons-category',
                'post_types' => array()
            );
        }

        // 各カテゴリーに属する投稿タイプを追加
        $post_types = KSTB_Database::get_all_post_types();
        foreach ($post_types as $post_type) {
            if ($post_type->menu_display_mode === 'category' && !empty($post_type->menu_parent_category)) {
                $cat_name = $post_type->menu_parent_category;

                if (isset($categories[$cat_name])) {
                    $categories[$cat_name]['post_types'][] = array(
                        'id' => $post_type->id,
                        'slug' => $post_type->slug,
                        'label' => $post_type->label
                    );
                }
            }
        }

        wp_send_json_success(array_values($categories));
    }

    /**
     * カテゴリーアイコンの更新
     */
    public function update_category_icon() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';
        $icon = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : '';

        if (empty($category_name) || empty($icon)) {
            wp_send_json_error(__('カテゴリー名とアイコンが必要です', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $result = KSTB_Database::update_category_icon($category_name, $icon);

        if ($result !== false) {
            wp_send_json_success(__('アイコンを更新しました', 'kashiwazaki-seo-type-builder'));
        } else {
            wp_send_json_error(__('アイコンの更新に失敗しました', 'kashiwazaki-seo-type-builder'));
        }
    }

    /**
     * すべてのメニュー割り当てを一括保存
     */
    public function save_all_menu_assignments() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $assignments = isset($_POST['assignments']) ? $_POST['assignments'] : array();

        if (empty($assignments) || !is_array($assignments)) {
            wp_send_json_error(__('保存するデータがありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $success_count = 0;
        $error_count = 0;

        foreach ($assignments as $assignment) {
            $post_type_id = isset($assignment['id']) ? intval($assignment['id']) : 0;
            $menu_mode = isset($assignment['mode']) ? sanitize_text_field($assignment['mode']) : '';

            if (empty($post_type_id) || empty($menu_mode)) {
                $error_count++;
                continue;
            }

            // menu_modeをパース
            $data = array();
            if ($menu_mode === 'toplevel') {
                $data['menu_display_mode'] = 'toplevel';
                $data['menu_parent_category'] = null;
                $data['menu_parent_slug'] = null;
            } elseif (strpos($menu_mode, 'category:') === 0) {
                $category = substr($menu_mode, 9);
                $data['menu_display_mode'] = 'category';
                $data['menu_parent_category'] = sanitize_text_field($category);
                $data['menu_parent_slug'] = null;
            } else {
                $error_count++;
                continue;
            }

            $result = KSTB_Database::update_post_type($post_type_id, $data);

            if ($result !== false) {
                $success_count++;
            } else {
                $error_count++;
            }
        }

        if ($success_count > 0) {
            wp_send_json_success(sprintf(
                __('%d件のメニュー設定を更新しました', 'kashiwazaki-seo-type-builder'),
                $success_count
            ));
        } else {
            wp_send_json_error(__('メニュー設定の更新に失敗しました', 'kashiwazaki-seo-type-builder'));
        }
    }

    /**
     * 親ページ検索（AJAX）
     */
    public function search_parent_pages() {
        if (!check_ajax_referer('kstb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('権限がありません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $exclude_id = isset($_POST['exclude_id']) ? intval($_POST['exclude_id']) : 0;

        if (empty($search) || mb_strlen($search) < 2) {
            wp_send_json_success(array('results' => array()));
            return;
        }

        global $wpdb;
        $results = array();
        $search_like = '%' . $wpdb->esc_like($search) . '%';

        // 固定ページを検索
        $pages = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_type FROM {$wpdb->posts}
             WHERE post_type = 'page'
             AND post_status = 'publish'
             AND ID != %d
             AND post_title LIKE %s
             ORDER BY post_title ASC
             LIMIT 50",
            $exclude_id,
            $search_like
        ));

        foreach ($pages as $page) {
            $results[] = array(
                'id' => $page->ID,
                'title' => $page->post_title,
                'type' => '固定ページ'
            );
        }

        // 投稿ページを検索
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_type FROM {$wpdb->posts}
             WHERE post_type = 'post'
             AND post_status = 'publish'
             AND ID != %d
             AND post_title LIKE %s
             ORDER BY post_title ASC
             LIMIT 50",
            $exclude_id,
            $search_like
        ));

        foreach ($posts as $post) {
            $results[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'type' => '投稿'
            );
        }

        // カスタム投稿タイプを検索
        $custom_post_types = KSTB_Database::get_all_post_types();
        foreach ($custom_post_types as $cpt) {
            if (!post_type_exists($cpt->slug)) {
                continue;
            }

            $custom_posts = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_title, post_type FROM {$wpdb->posts}
                 WHERE post_type = %s
                 AND post_status = 'publish'
                 AND ID != %d
                 AND post_title LIKE %s
                 ORDER BY post_title ASC
                 LIMIT 30",
                $cpt->slug,
                $exclude_id,
                $search_like
            ));

            foreach ($custom_posts as $custom_post) {
                $results[] = array(
                    'id' => $custom_post->ID,
                    'title' => $custom_post->post_title,
                    'type' => $cpt->label
                );
            }
        }

        wp_send_json_success(array('results' => $results));
    }
}
