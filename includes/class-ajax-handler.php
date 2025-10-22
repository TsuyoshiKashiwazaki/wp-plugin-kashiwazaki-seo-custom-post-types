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
        add_action('wp_ajax_kstb_move_posts', array($this, 'move_posts'));
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
        $slug = isset($_POST['slug']) ? sanitize_key($_POST['slug']) : '';
        $label = isset($_POST['label']) ? sanitize_text_field($_POST['label']) : '';

        if (empty($slug) || empty($label)) {
            wp_send_json_error(__('スラッグとラベルを入力してください', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (strlen($slug) > 20) {
            wp_send_json_error(__('スラッグは20文字以内で入力してください', 'kashiwazaki-seo-type-builder'));
            return;
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $slug)) {
            wp_send_json_error(__('スラッグは半角英数字、ハイフン、アンダースコアのみ使用できます', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $reserved_slugs = array('post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation');
        if (in_array($slug, $reserved_slugs)) {
            wp_send_json_error(__('このスラッグは予約語のため使用できません', 'kashiwazaki-seo-type-builder'));
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
            'slug' => $slug,
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
            'supports' => $supports,
            'show_in_rest' => true,
            'rest_base' => $slug,
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

        $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';

        if (empty($post_type)) {
            wp_send_json_error(__('投稿タイプが指定されていません', 'kashiwazaki-seo-type-builder'));
            return;
        }

        $post_mover = KSTB_Post_Mover::get_instance();
        $posts = $post_mover->get_posts_by_type($post_type);

        wp_send_json_success(array(
            'posts' => $posts,
            'count' => count($posts)
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
}
