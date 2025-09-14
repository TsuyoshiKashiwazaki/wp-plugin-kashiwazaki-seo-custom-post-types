<?php

if (!defined('ABSPATH')) {
    exit;
}

class KSTB_Post_Type_Registrar {
    private static $instance = null;
    private static $registered = false;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init() {
        add_action('init', array($this, 'register_post_types'), 0);
        add_action('admin_init', array($this, 'register_post_types'), 0);
        add_action('admin_menu', array($this, 'force_add_menus'), 999);
    }

    public function register_post_types() {
        if (self::$registered && current_action() !== 'kstb_force_register') {
            return;
        }

        $post_types = KSTB_Database::get_all_post_types();

        if (empty($post_types)) {
            return;
        }

        foreach ($post_types as $post_type) {
            $this->register_single_post_type($post_type);
        }

        self::$registered = true;
    }

    private function register_single_post_type($post_type) {
        $labels = json_decode($post_type->labels, true);
        $supports = json_decode($post_type->supports, true);

        if (empty($labels) || !is_array($labels)) {
            $labels = array();
        }

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
            'public' => (bool) $post_type->public,
            'publicly_queryable' => (bool) $post_type->publicly_queryable,
            'show_ui' => (bool) $post_type->show_ui,
            'show_in_menu' => (bool) $post_type->show_in_menu,
            'query_var' => (bool) $post_type->query_var,
            'rewrite' => $this->get_rewrite_args($post_type),
            'capability_type' => $post_type->capability_type,
            'has_archive' => (bool) $post_type->has_archive,
            'hierarchical' => (bool) $post_type->hierarchical,
            'menu_position' => $post_type->menu_position ? (int) $post_type->menu_position : null,
            'menu_icon' => $post_type->menu_icon ? $post_type->menu_icon : 'dashicons-admin-post',
            'supports' => $supports,
            'show_in_rest' => (bool) $post_type->show_in_rest,
            'rest_base' => $post_type->rest_base
        );

        if (post_type_exists($post_type->slug)) {
            return;
        }

        register_post_type($post_type->slug, $args);

        $taxonomies = json_decode($post_type->taxonomies, true);
        if (!empty($taxonomies) && is_array($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                if (taxonomy_exists($taxonomy)) {
                    register_taxonomy_for_object_type($taxonomy, $post_type->slug);
                }
            }
        }
    }

    private function get_rewrite_args($post_type) {
        $rewrite = json_decode($post_type->rewrite, true);

        if (empty($rewrite) || !is_array($rewrite)) {
            return array('slug' => $post_type->slug);
        }

        $args = array();
        if (!empty($rewrite['slug'])) {
            $args['slug'] = $rewrite['slug'];
        } else {
            $args['slug'] = $post_type->slug;
        }

        if (isset($rewrite['with_front'])) {
            $args['with_front'] = (bool) $rewrite['with_front'];
        }

        return $args;
    }

    public function force_add_menus() {
        $post_types = KSTB_Database::get_all_post_types();

        if (empty($post_types)) {
            return;
        }

        foreach ($post_types as $post_type) {
            if (post_type_exists($post_type->slug) && $post_type->show_in_menu) {
                $post_type_obj = get_post_type_object($post_type->slug);

                global $menu;
                $menu_exists = false;

                if (!empty($menu)) {
                    foreach ($menu as $menu_item) {
                        if (isset($menu_item[2]) && $menu_item[2] === 'edit.php?post_type=' . $post_type->slug) {
                            $menu_exists = true;
                            break;
                        }
                    }
                }

                if (!$menu_exists && $post_type_obj) {
                    add_menu_page(
                        $post_type_obj->labels->name,
                        $post_type_obj->labels->menu_name,
                        $post_type_obj->cap->edit_posts,
                        'edit.php?post_type=' . $post_type->slug,
                        '',
                        $post_type->menu_icon ? $post_type->menu_icon : 'dashicons-admin-post',
                        $post_type->menu_position ? (int) $post_type->menu_position : 25
                    );

                    add_submenu_page(
                        'edit.php?post_type=' . $post_type->slug,
                        $post_type_obj->labels->all_items,
                        $post_type_obj->labels->all_items,
                        $post_type_obj->cap->edit_posts,
                        'edit.php?post_type=' . $post_type->slug
                    );

                    add_submenu_page(
                        'edit.php?post_type=' . $post_type->slug,
                        $post_type_obj->labels->add_new_item,
                        $post_type_obj->labels->add_new,
                        $post_type_obj->cap->edit_posts,
                        'post-new.php?post_type=' . $post_type->slug
                    );
                }
            }
        }
    }
}

