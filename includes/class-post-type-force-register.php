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
        // v1.0.34: unregister_post_type() は WP_Post_Type::remove_rewrite_rules() 経由で
        // $wp_rewrite->extra_rules_top から「クエリに index.php?post_type={slug} を含むルール」を
        // 全て unset する。これは本プラグイン由来のルールだけでなく、テーマや他プラグインが
        // add_rewrite_rule() で登録したルールも巻き込む。それらの登録は init 優先度 10 前後で
        // 既に済んでおり、このリクエスト内で再登録される機会が無いため、直後に flush すると
        // 他コンポーネントのルールが欠けた状態で永続化される。
        // （例: テーマのページネーション「{path}/page-2/」→ flush のたびに 404 になる）
        // よって unregister の前に退避し、再登録後に復元する。
        $preserved_rules = array();
        if (post_type_exists($post_type->slug)) {
            $preserved_rules = self::capture_extra_rules_top($post_type->slug);
            unregister_post_type($post_type->slug);
        }

        // 通常登録ロジックに統合 ($force=true で post_type_exists ガードをスキップ)
        $registrar = KSTB_Post_Type_Registrar::get_instance();
        $registrar->register_single_post_type($post_type, true);

        // register_single_post_type() は void のため、登録成功は post_type_exists() で判定する
        if (!post_type_exists($post_type->slug)) {
            return new WP_Error('register_failed', sprintf('Failed to register post type: %s', $post_type->slug));
        }

        self::restore_extra_rules_top($preserved_rules, $post_type->slug);

        return true;
    }

    /**
     * unregister_post_type() が削除する対象のリライトルールを退避する
     *
     * v1.0.34 追加。WP_Post_Type::remove_rewrite_rules() と同一の判定条件
     * （クエリ文字列に "index.php?post_type={slug}" を含む）で抽出することで、
     * 実際に消される分を過不足なく捕捉する。
     *
     * @param string $slug 投稿タイプスラッグ
     * @return array regex => query の連想配列（元の順序を保持）
     */
    private static function capture_extra_rules_top($slug) {
        global $wp_rewrite;

        $captured = array();

        if (!isset($wp_rewrite) || !is_object($wp_rewrite)
            || !isset($wp_rewrite->extra_rules_top) || !is_array($wp_rewrite->extra_rules_top)) {
            return $captured;
        }

        $needle = 'index.php?post_type=' . $slug;

        foreach ($wp_rewrite->extra_rules_top as $regex => $query) {
            if (is_string($query) && strpos($query, $needle) !== false) {
                $captured[$regex] = $query;
            }
        }

        return $captured;
    }

    /**
     * 退避したリライトルールのうち、再登録で復活しなかったものを復元する
     *
     * v1.0.34 追加。本プラグイン由来のルールは register_single_post_type() が
     * 同じ regex で登録し直すため、ここで復元対象になるのはテーマ・他プラグイン由来の
     * ルールだけになる。
     *
     * 投稿タイプの URL パスが変更された場合に旧パスのルールを復活させると
     * ゴーストルール（旧 URL が生き続ける）になるため、復元対象は現在のフルパス配下に
     * 限定する。旧パスのルールは復元されず、テーマ側は次のリクエストの init で
     * 新パスのルールを登録し直すため、正しい状態に収束する。
     *
     * @param array  $captured capture_extra_rules_top() の戻り値
     * @param string $slug     投稿タイプスラッグ
     */
    private static function restore_extra_rules_top($captured, $slug) {
        global $wp_rewrite;

        if (empty($captured) || !is_array($captured)) {
            return;
        }

        if (!isset($wp_rewrite) || !is_object($wp_rewrite)
            || !isset($wp_rewrite->extra_rules_top) || !is_array($wp_rewrite->extra_rules_top)) {
            return;
        }

        $full_path = '';
        if (class_exists('KSTB_Post_Type_Registrar')) {
            $full_path = trim((string) KSTB_Post_Type_Registrar::build_full_path_static($slug), '/');
        }

        foreach ($captured as $regex => $query) {
            // 再登録で同じ regex が復活済み（＝本プラグイン由来）なら何もしない
            if (isset($wp_rewrite->extra_rules_top[$regex])) {
                continue;
            }

            // 現在のフルパス配下のルールだけを復元する（URL パス変更時のゴースト防止）
            if ($full_path !== '') {
                $normalized = ltrim((string) $regex, '^');
                if (strpos($normalized, $full_path) !== 0) {
                    continue;
                }
            }

            $wp_rewrite->extra_rules_top[$regex] = $query;
        }
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
