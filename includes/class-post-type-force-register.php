<?php
if (!defined('ABSPATH')) {
    exit;
}


/**
 * カスタム投稿タイプを強制的に登録するためのヘルパークラス
 */
class KSTB_Post_Type_Force_Register {

    /**
     * 指定されたIDの投稿タイプを強制的に登録
     */
    public static function force_register_by_id($id) {
        $post_type = KSTB_Database::get_post_type($id);

        if (!$post_type) {
            return new WP_Error('not_found', 'Post type not found');
        }

        return self::force_register($post_type);
    }

    /**
     * 投稿タイプオブジェクトを強制的に登録
     */
    public static function force_register($post_type) {
        $labels = json_decode($post_type->labels, true);
        $supports = json_decode($post_type->supports, true);

        if (empty($labels) || !is_array($labels)) {
            $labels = array();
        }

        // 必須ラベルの補完
        if (empty($labels['name'])) $labels['name'] = $post_type->label;
        if (empty($labels['singular_name'])) $labels['singular_name'] = $post_type->label;
        if (empty($labels['menu_name'])) $labels['menu_name'] = $post_type->label;
        if (empty($labels['name_admin_bar'])) $labels['name_admin_bar'] = $post_type->label;
        if (empty($labels['add_new'])) $labels['add_new'] = __('新規追加', 'kashiwazaki-seo-type-builder');
        if (empty($labels['add_new_item'])) $labels['add_new_item'] = sprintf(__('新規%sを追加', 'kashiwazaki-seo-type-builder'), $post_type->label);
        if (empty($labels['new_item'])) $labels['new_item'] = sprintf(__('新規%s', 'kashiwazaki-seo-type-builder'), $post_type->label);
        if (empty($labels['edit_item'])) $labels['edit_item'] = sprintf(__('%sを編集', 'kashiwazaki-seo-type-builder'), $post_type->label);
        if (empty($labels['view_item'])) $labels['view_item'] = sprintf(__('%sを表示', 'kashiwazaki-seo-type-builder'), $post_type->label);
        if (empty($labels['all_items'])) $labels['all_items'] = sprintf(__('すべての%s', 'kashiwazaki-seo-type-builder'), $post_type->label);
        if (empty($labels['search_items'])) $labels['search_items'] = sprintf(__('%sを検索', 'kashiwazaki-seo-type-builder'), $post_type->label);
        if (empty($labels['not_found'])) $labels['not_found'] = sprintf(__('%sが見つかりません', 'kashiwazaki-seo-type-builder'), $post_type->label);
        if (empty($labels['not_found_in_trash'])) $labels['not_found_in_trash'] = sprintf(__('ゴミ箱に%sが見つかりません', 'kashiwazaki-seo-type-builder'), $post_type->label);

        if (empty($supports) || !is_array($supports)) {
            $supports = array('title', 'editor');
        }

        $args = array(
            'label' => $post_type->label,
            'labels' => $labels,
            'public' => true, // 強制的にtrue
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => $post_type->slug),
            'capability_type' => 'post',
            'has_archive' => (bool) $post_type->has_archive,
            'hierarchical' => (bool) $post_type->hierarchical,
            'menu_position' => $post_type->menu_position ? (int) $post_type->menu_position : 5,
            'menu_icon' => $post_type->menu_icon ? $post_type->menu_icon : 'dashicons-admin-post',
            'supports' => $supports,
            'show_in_rest' => true,
            'rest_base' => $post_type->rest_base ? $post_type->rest_base : $post_type->slug
        );

        // 既存の投稿タイプを削除してから再登録
        if (post_type_exists($post_type->slug)) {
            unregister_post_type($post_type->slug);
        }

        $result = register_post_type($post_type->slug, $args);

        if (is_wp_error($result)) {
            return $result;
        }

        // タクソノミーも登録
        if ($post_type->taxonomies) {
            $taxonomies = json_decode($post_type->taxonomies, true);
            if (is_array($taxonomies)) {
                foreach ($taxonomies as $taxonomy) {
                    register_taxonomy_for_object_type($taxonomy, $post_type->slug);
                }
            }
        }

        flush_rewrite_rules();

        return true;
    }

    /**
     * すべての投稿タイプを強制的に再登録
     */
    public static function force_register_all() {
        $post_types = KSTB_Database::get_all_post_types();

        if (empty($post_types)) {
            return;
        }

        foreach ($post_types as $post_type) {
            self::force_register($post_type);
        }
    }
}
