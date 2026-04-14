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
     *
     * v1.0.25 HIGH-2 修正:
     *   旧実装は通常登録 (KSTB_Post_Type_Registrar::register_single_post_type) と異なる引数を生成しており、
     *   url_slug / parent_directory / build_full_path / カスタム rewrite rule / 親メニュー設定が反映されず
     *   階層 URL を破壊していた。v1.0.25 で通常登録パスへ統合する。
     *
     * v1.0.25 MEDIUM-5 修正:
     *   ここでは flush_rewrite_rules() を呼ばない。flush は呼び出し元 (AJAX 層 / 自動修復処理) で実施する。
     *
     * @param object $post_type DB 行オブジェクト
     * @return true|WP_Error
     */
    public static function force_register($post_type) {
        if (empty($post_type) || empty($post_type->slug)) {
            return new WP_Error('invalid_post_type', 'Invalid post type object');
        }

        // 既存の登録があれば unregister（permastruct / query var / hooks 等を正規にクリーンアップ）
        if (post_type_exists($post_type->slug)) {
            unregister_post_type($post_type->slug);
        }

        // 通常登録ロジックに統合 ($force=true で post_type_exists ガードをスキップ)
        $registrar = KSTB_Post_Type_Registrar::get_instance();
        $registrar->register_single_post_type($post_type, true);

        // register_single_post_type() は void のため、登録成功は post_type_exists() で判定する
        if (!post_type_exists($post_type->slug)) {
            return new WP_Error('register_failed', sprintf('Failed to register post type: %s', $post_type->slug));
        }

        return true;
    }

    /**
     * すべての投稿タイプを強制的に再登録
     *
     * v1.0.25 MEDIUM-5: 全件再登録後に flush を 1 回だけ呼び出す。
     * 旧実装は force_register 内で毎回 flush していた (N 回 flush)。
     */
    public static function force_register_all() {
        $post_types = KSTB_Database::get_all_post_types();

        if (empty($post_types)) {
            return;
        }

        foreach ($post_types as $post_type) {
            self::force_register($post_type);
        }

        flush_rewrite_rules();
    }
}
