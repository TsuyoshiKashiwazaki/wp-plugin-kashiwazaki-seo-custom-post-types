# Changelog

All notable changes to Kashiwazaki SEO Custom Post Types will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.25] - 2026-04-14

### Fixed
- **HIGH-1**: `public` / `publicly_queryable` / `show_ui` / `show_in_menu` / `query_var` / `show_in_rest` の設定値が無視されていた問題を修正
  - `class-post-type-registrar.php:621-628` でハードコードされていた値を DB 値に変更
  - `class-ajax-handler.php:234-238` のチェックボックス解析を修正（外したときに `false` として保存されるように）
  - `class-ajax-handler.php:427` (`force_reregister_post_type`) の `show_in_rest` ハードコードを DB 値に変更
  - `class-post-type-force-register.php:58-70` のハードコードを DB 値に変更
- **MEDIUM-1**: 静的キャッシュが CRUD 後にクリアされず古いデータが返る問題を修正
  - `class-database.php` の function-local `static $cache` をクラスプロパティに移行
  - `clear_cache()` メソッドを追加
  - `insert_post_type()` / `update_post_type()` / `delete_post_type()` で `clear_cache()` を呼び出すように変更
- **MEDIUM-9**: `delete_option('rewrite_rules')` と `flush_rules()` の間のレースコンディションを修正
  - `class-ajax-handler.php:444` の `delete_option` を削除（`flush_rules()` が内部で atomic に更新するため不要）
- **NEW-1**: カスタム投稿タイプ削除時に通常の permastruct 由来のゴーストルールが残存する問題を修正
  - `class-ajax-handler.php` の delete_post_type AJAX ハンドラで DB 削除後に `unregister_post_type()` → `flush_rewrite_rules()` の順で呼び出すように変更
  - 加えて `class-post-type-registrar.php` の単一投稿カスタム rewrite rule を旧形式 `index.php?{internal_slug}=$matches[1]` から標準形式 `index.php?post_type={slug}&name=$matches[1]` に変更
  - これにより WordPress コアの `WP_Post_Type::remove_rewrite_rules()` が permastruct/query_var 由来のルールを正規に削除できるようになった
  - 注意: 本プラグインが独自の `add_rewrite_rule()` で追加するカスタムルール (階層 URL 用) はこの修正だけでは消えない場合があり、完全な対処は v1.1.0 で予定
  - 副次効果: `query_var=false` × `url_slug !== slug` の組み合わせでも単一投稿 URL が解決可能になった (HIGH-1 と組み合わせた回帰リスクの解消)
- **HIGH-2**: 強制登録ヘルパー (`KSTB_Post_Type_Force_Register::force_register()`) が通常登録ロジックと乖離していた問題を修正
  - `register_single_post_type()` を呼び出す形に統合し、`url_slug` / `parent_directory` / `build_full_path()` / カスタム rewrite rule / 親メニュー設定がすべて反映されるようになった
  - これにより `check_and_fix_missing_post_types()` の自動修復経路でも階層 URL が壊れなくなる
- **HIGH-3**: `force_reregister_post_type()` (private) が通常登録ロジックと乖離していた問題を修正
  - `unset($wp_post_types[$slug])` の乱暴なクリーンアップを `unregister_post_type()` に置き換え、permastruct / query var / hooks 等が正規に解除されるようになった
  - 内部実装を `register_single_post_type()` 経由に統一
  - slug 変更時の旧 CPT も unregister するように `save_post_type` を改修
- **MEDIUM-5**: 1 リクエスト中に `flush_rewrite_rules()` が 3 回実行されていた問題を修正
  - `class-database.php` の insert/update/delete から `flush_rewrite_rules()` を削除（責務を AJAX 層に集約）
  - `force_register()` / `force_reregister_post_type()` 内の flush も削除
  - 各 AJAX エンドポイントの末尾で 1 回だけ flush するように整理
  - メニュー設定変更 (`update_menu_assignment` / `save_all_menu_assignments`) は rewrite rule に影響しないため flush 不要と判定
- **MEDIUM-4**: `post_name` / `post_parent` を `$wpdb->update()` で直接更新していた問題を修正
  - `wp_update_post()` 経由に変更し、WordPress の slug 一意化・親子循環チェック・`save_post`/`post_updated` フックが正規に走るようになった
  - 自分自身を親にしたり子孫を親にしたりが防がれる
  - `_kstb_parent_page` メタは `wp_update_post()` 成功後に整合性を取って更新（core が循環検出で `post_parent=0` に補正した場合はメタもクリア）
  - WP_Error チェックと `$_POST['post_ID']` の一致確認を追加
  - `update_post_parent_directly()` 廃止
- **LOW-2**: `$_POST` / `$_GET` の文字列入力に `wp_unslash()` を適用
  - WordPress が自動で付与するバックスラッシュを除去せずに `sanitize_text_field()` していたため、`O'Reilly` のような入力が `O\'Reilly` として保存される問題を修正
  - 対象入力に `wp_unslash()` を追加（`class-ajax-handler.php`, `class-parent-selector.php`, `class-parent-menu-manager.php`）
  - 整数値 (`intval` / `absint`) と boolean cast は対象外
- **LOW-5**: 親ページ検索結果の JS で raw `post_title` を `innerHTML` に挿入していた問題を修正
  - `textContent` + `createElement` ベースの DOM 構築に置換
  - DOM-based XSS の理論的リスクを排除（`includes/class-parent-selector.php` の inline JS）

- **HIGH-4**: `allow_shortlink=ON` 設定が `validate_permalink()` で無視されていた問題を修正
  - `class-permalink-validator.php:112-138` の `validate_permalink()` で `wp_redirect(..., 301)` する直前に対象 CPT の `allow_shortlink` 判定を追加
  - ON の場合はリダイレクトせずに break し、短縮 URL (`?post_type=xxx&p=ID` / `?p=ID`) でのアクセスを許可する仕様通りに動作するようになった
  - 同ファイル内の `disable_canonical_redirect_for_custom_post_types()` は元々 `allow_shortlink` を尊重していたが、`template_redirect` フックで先に動く `validate_permalink()` が無条件 301 していたため事実上 ON 設定が無効化されていた
- **HIGH-5**: `KSTB_Parent_Selector::clear_parent_cache()` が `wp_cache_flush_group()` を無条件呼び出しており、WordPress 5.0〜6.0 環境で fatal error になる可能性を修正
  - `class-parent-selector.php:486-491` に `function_exists('wp_cache_flush_group')` ガードを追加
  - 動作要件 WP 5.0+ を維持しつつ、WP 6.1+ では従来通り group flush が動く

### Added
- 管理画面の CPT 一覧に「再登録」ボタンを追加
  - `templates/admin-page.php:606` に `kstb-reregister-button` を追加し、既存の AJAX エンドポイント `wp_ajax_kstb_reregister_post_type` を呼び出すようにした
  - `assets/admin.js` に `reregisterPostType` ハンドラを追加 (confirm → AJAX → 成功/失敗/通信エラーの通知 → UI 復帰)
  - これまでバックエンド実装のみ存在して UI ボタンが未実装だった状態を解消

### Changed
- 管理画面の「説明書」タブのコンテンツを、ハードコードされた静的 HTML から `docs/post-type-management.html` の動的読み込み方式に変更（マニュアルの二重管理を解消）
  - `includes/class-admin.php` に `get_docs_content()` ヘルパーを追加
  - `DOMDocument` で `<main class="content">` を抽出、`<h1>` / `<nav class="page-nav">` / `<footer>` / `<figure class="screenshot">` を除去
  - 画像パス・内部リンクを `KSTB_PLUGIN_URL` 配下に書き換え
  - セキュリティ: path traversal 防御 (`basename()` + `.html` 拡張子強制 + `realpath()` 末尾区切り境界判定)、`DOMDocument` 拡張ガード、`libxml_use_internal_errors` 状態復元、`LIBXML_NONET` で XXE 防御
  - `assets/admin.css` に `#kstb-main-tab-guide` スコープで docs 用 CSS (`.lead` / `.step` / `.callout-tip|warning|note` / table) を追加し、admin 内でも docs と同等の表示品質を実現
  - フォールバック: `DOMDocument` 不在やパース失敗時は同梱マニュアルへのリンクボタンを表示
- `templates/admin-page.php` の階層化チェックボックスの説明文を訂正
  - 従来「チェックすると編集画面で親ページを選択できる」と書いていたが、実装では「親ページ選択 &amp; スラッグ編集」メタボックス (`KSTB_Parent_Selector`) は hierarchical の ON/OFF に関係なく全 CPT に無条件で追加される
  - 修正後は「hierarchical は同じ投稿タイプ内の親子関係 (`post_parent` / `page-attributes`) を有効化する設定で、親ディレクトリ設定は独立したメタボックスから行う」と明記
- `assets/admin.js` のメニューカテゴリーアイコンプレビュー表示サイズを `font-size: 32px / width: 32px / height: 32px` から `20px` に変更
  - サイドメニューの実表示サイズ (16〜20px 相当) と一致させ、編集 UI のプレビューが過度に大きくならないように調整

### Chore
- `.gitignore` に監査メモ (`.fix-*.md`, `3ai-prompt.md`, `.明日やること.txt`, `NIKKI.TXT`) と WSL/Windows メタデータ (`*Zone.Identifier*`) の ignore ルールを追加

### Docs
- `docs/` 配下を 6 ページ構成に全面改訂 (`index.html` / `setup.html` / `post-type-management.html` / `hierarchical-urls.html` / `menu-and-category.html` / `troubleshooting.html`)
  - `hierarchical-urls.html` と `menu-and-category.html` は新規追加
  - `troubleshooting.html` は全面書き直し + アーカイブ表示モード / archive_include_children / 短縮 URL / publicly_queryable&show_ui&query_var の 4 セクションを新設
  - 全ページを Claude / Codex / Gemini (gemini-3.1-pro-preview) の三者協議で検証し、実装と完全一致するよう修正
  - 主な訂正項目:
    - `archive_display_type` の `default` / `none` モード説明を実装に合わせて修正 (`default` は同名ページへのフォールスルー、`none` は強制 404)
    - `allow_shortlink` の OFF/ON 挙動表記を実装 (および本バージョンで修正した `validate_permalink()`) と一致させた
    - `query_var=false` の影響範囲を「WP 標準の公開クエリ変数 URL のみ無効化、`WP_Query(post_type=>...)` や階層 URL ルーティングには影響しない」と訂正
    - テーブル名のハードコード `wp_kstb_*` を `{prefix}kstb_*` 表記に統一し、`$wpdb->prefix` に依存することを注記
    - `KSTB_Parent_Selector` / `KSTB_Ajax_Handler` の責務分離を正確に記述 (AJAX 親ページ検索は `KSTB_Ajax_Handler` 側)
    - スキーマ自動マイグレーションのタイミングを「activate 時は `create_tables()`、init フック内で `update_database()`」と書き分け
    - 投稿タイプ一覧の「編集」操作行の説明で、url_slug を変更すると hidden の内部 slug も再生成される点を明記

## [1.0.24] - 2026-04-13

### Fixed
- 管理画面テンプレートで `$missing_in_wp` 配列の出力に `esc_html()` を追加（XSS 対策の防御的修正）
- CPT 編集画面のメタボックスレンダリング時に毎回出力されていたデバッグ用 `error_log` を削除（`debug.log` 肥大化の解消）
- `force_flush_rules_if_needed()` メソッドを削除（開発用コードで権限チェックなしに `admin_init` で 1 時間毎の hard flush を誘発していた）
- フロントエンドで `KSTB_Archive_Controller::init()` が `init_hooks()` と `init()` の両方から呼ばれていた二重初期化を解消
- 到達不能だった `suppress_post_type_errors()` メソッドおよびその登録フックを削除（`set_error_handler()` のグローバル上書きが他プラグインの監視ツールを妨害する問題の解消）

### Removed
- `force_flush_rules_if_needed()` メソッド（class-parent-selector.php）
- `suppress_post_type_errors()` メソッド（class-archive-controller.php）
- `kstb_last_flush_time` オプション参照（コードからの参照を削除、既存のオプション値は自動クリーンアップされないため注意）

## [1.0.23] - 2026-02-10

### Fixed
- タクソノミーアーカイブのページネーションがCPT階層検証により誤って404になる問題を修正
  - 影響: CPTスラッグと同名のタグ/カテゴリで `page-2` 形式やフィードにアクセスすると404
  - 修正: `validate_permalink()` でアーカイブ・検索リクエストをCPT階層検証の対象外に変更

## [1.0.22] - 2026-02-09

### Fixed
- 非公開・下書き等の投稿が未ログインユーザーに表示される脆弱性を修正（Registrarの8メソッドに `is_post_viewable()` チェックを追加）
- クエリ文字列URL（`?post_type=xxx&p=ID`）で正規URLと同一コンテンツが表示される重複コンテンツ問題を修正（301リダイレクトを追加）

### Added
- `is_post_viewable()` メソッド - 投稿ステータスとユーザー権限に基づく表示可否判定

## [1.0.21] - 2026-02-08

### Added
- アーカイブ一覧に子階層の投稿タイプの記事を含める機能を追加
- 管理画面に「子階層の投稿タイプの記事も含める」チェックボックスを追加
- `archive_include_children` DBカラムおよびAJAX保存処理を追加
- 子投稿タイプを再帰的に取得する `get_child_post_type_slugs()` メソッドを追加

### Fixed
- 子投稿タイプ含有時に `block_old_urls()` が誤って404を返す問題を修正
- 子投稿タイプ含有時のページネーション不具合を修正（post_type復元タイミングを `template_include` に変更）
- `final_redirect_defense()` が `control_query()` で設定済みの子投稿タイプ配列を上書きする問題を修正

## [1.0.20] - 2026-02-08

### Added
- 階層的カスタム投稿タイプの旧スラッグ保存機能（`KSTB_Old_Slug_Tracker` クラス）
- WordPress コアが除外する hierarchical CPT でスラッグ変更時に `_wp_old_slug` を保存
- 旧URLから新URLへの301リダイレクトが階層的CPTでも機能するよう対応

## [1.0.19] - 2026-01-22

### Added
- 短縮URL形式（?p=ID）許可機能を追加
- カスタム投稿タイプごとに `?post_type=xxx&p=ID` 形式のURLでのリダイレクト動作を制御可能に
- 「詳細設定」タブに「短縮URL形式（?p=ID）を許可」チェックボックスを追加

### Fixed
- 短縮URL設定がデータベースに保存されないバグを修正（class-ajax-handler.php）

## [1.0.18] - 2026-01-07

### Added
- 著者アーカイブページにカスタム投稿タイプの記事を含めるよう対応

## [1.0.17] - 2025-12-10

### Added
- 親ページセレクターに検索機能を追加（ページ名でフィルタリング）
- 検索時にセレクトボックスを展開表示（size=8で複数項目表示）
- 選択中の親ページを常時表示するUI（タイプ名付き）
- クリアボタンで親ページ解除可能に

### Improved
- 標準の親セレクター・スラッグとの双方向連動
- スラッグ入力時にWordPress標準フィールドもリアルタイム更新

## [1.0.16] - 2025-12-08

### Fixed
- 管理バーの「編集」リンクが間違った記事（post=8650等）に遷移する問題を修正
- 下書き・非公開・予約投稿記事が編集権限ユーザーでも404になる問題を修正

### Added
- `get_allowed_post_statuses()` メソッド - ユーザー権限に応じて許可されるpost_statusの配列を返す
- `is_post_accessible()` メソッド - 投稿が現在のユーザーにアクセス可能かチェック
- `fix_admin_bar_edit_link()` メソッド - admin_bar_menuフックで queried_object を正しく同期

### Improved
- 18箇所のハードコードされた `post_status === 'publish'` チェックをヘルパーメソッドに置換
- 権限ロジック: 公開記事は全ユーザー、下書き・非公開等は `edit_post` 権限ユーザーのみアクセス可能

## [1.0.15] - 2025-12-02

### Fixed
- WordPress予約語（media, link等）をURLスラッグに使用した際、内部名を自動変換して競合を回避

### Improved
- 予約語リストを拡充（media, link, links, theme, themes, plugin, plugins, user, users, option, options, comment, comments, admin, site, sites, network, dashboard, upload, edit, profile, tools, import, export, settings, update, menu, term, widget, widgets）

## [1.0.14] - 2025-11-26

### Fixed
- 「指定なし」設定のカスタム投稿タイプスラッグトップページで、同名の投稿が正しく単一投稿として表示されない問題を修正
- `template_include` フィルター実行時に `is_singular` フラグがリセットされる問題を修正

### Added
- `fix_body_class()` メソッド - 単一投稿用のクラス（`wp-singular`, `single`, `single-{post_type}`, `postid-{ID}`）を追加
- `ensure_singular_state()` メソッド - `wp` アクションで `is_singular` フラグを確実に設定
- `fix_queried_object_before_template()` メソッドを拡張 - 正しいテンプレート（`single-{post_type}.php` または `single.php`）を返す処理を追加

## [1.0.13] - 2025-11-26

### Added
- 階層的カスタム投稿タイプのパーマリンク解決機能（親子関係を持つ投稿のURL解決）
- `resolve_hierarchical_request()` メソッド - requestフィルターで階層的URLを解析
- `find_hierarchical_post()` メソッド - 親階層を検証して投稿を検索
- `fix_document_title()` メソッド - 階層的投稿で正しいタイトルを表示

### Fixed
- 階層的な投稿URL（例: /blog/parent/child/）が404になる問題を修正

### Improved
- パーマリンク検証ロジックを改善（query_varsを使用した正確な投稿取得）
- `class-permalink-validator.php` で `$wp_query->get('p')` を使用するよう改善

## [1.0.12] - 2025-11-23

### Added
- 循環参照検出機能（階層構造の無限ループを防止）
- 循環参照自動修正機能（データベースの整合性を自動修復）

### Improved
- パフォーマンス向上のためのキャッシュ機能を追加

## [1.0.11] - 2025-11-09

### Fixed
- カテゴリーテーブルが作成されない問題を修正

### Improved
- `update_database()` メソッドにカテゴリーテーブルの存在確認と作成処理を追加
- `create_categories_table()` メソッドにエラーハンドリングとログ出力を追加
- テーブル作成の成功/失敗を確認する仕組みを実装

## [1.0.10] - 2025-11-07

### Added
- パーマリンク検証機能（正しいパーマリンク以外でのアクセスを404でブロック）

### Improved
- カスタム投稿タイプのCanonical Redirectを無効化

## [1.0.9] - 2025-10-31

### Added
- メニュー管理タブを追加（カスタム投稿タイプをカテゴリーでフォルダ化）
- カテゴリー機能（複数の投稿タイプを管理画面サイドメニューでグループ化）
- カテゴリーアイコン選択機能（21種類のフォルダ系アイコンから選択可能）
- 親メニューページ（カテゴリークリック時に所属する投稿タイプ一覧を表示）
- 一括保存機能（すべての投稿タイプのメニュー設定を一度に保存）
- 階層構造表示（サブメニューを├と└で視覚化）
- `includes/class-parent-menu-manager.php` - 親メニュー管理クラス
- `wp_kstb_menu_categories` テーブル（カテゴリー情報を保存）

### Improved
- サブメニューの文字折り返しを防止（white-space: nowrap、15文字超過時は省略）
- アセットファイルのキャッシュクリア機構（ファイルタイムスタンプベースのバージョニング）
- メニュー重複チェックロジックを改善（URLベースの検索を追加）

### Fixed
- メニュー重複表示の問題を修正（admin-global.js、class-post-type-menu-fix.php）
- カテゴリーテーブルの自動作成とデフォルトカテゴリー作成を改善
- カテゴリー削除・名前変更時のテーブル同期処理を追加

## [1.0.8] - 2025-10-30

### Fixed
- url_slugが設定されている投稿タイプでURLマッチングが正しく動作しない問題を修正

### Improved
- `build_full_path()` 関数でurl_slugを優先的に使用するように改善
  - `includes/class-parent-selector.php` の `build_full_path()` メソッド
  - `includes/class-post-type-registrar.php` の `build_full_path()` と `build_full_path_static()` メソッド
  - `includes/class-archive-controller.php` の `build_full_path_for_post_type()` メソッド

## [1.0.7] - 2025-10-27

### Added
- 64文字までの長いスラッグに対応（url_slugカラム追加とデータベースマイグレーション）
- URLスラッグと内部スラッグの分離システム
- カスタムリライトルールによる長いURL→短い内部名のマッピング機能
- 個別投稿、アーカイブページ、ページネーション用のリライトルール
- スラッグ入力時の20文字超過警告表示機能（黄色い警告ボックス）
- 記事移動機能にカテゴリー/タクソノミーフィルタリング機能
- タームごとの投稿数カウント機能
- 短いURLへのアクセスをブロックする機能（長いURLのみ許可）
- 管理画面一覧に投稿数列を追加（クリックで投稿一覧へ遷移）
- テーブルソート機能を追加（ラベル、投稿数、パス、スラッグの各列でソート可能）

### Improved
- WordPress内部は20文字制限を維持しつつ、URLは64文字まで使用可能に
- 他のプラグインやテーマとの互換性を向上
- スラッグ入力フィールドを単一化（url_slugのみ表示、内部slugは自動生成）
- パーマリンク生成でurl_slugを優先使用
- 階層化URLにもurl_slug対応
- 記事移動タブのUI改善（カテゴリーフィルター追加、投稿数表示）
- 管理画面一覧のデフォルトソートをパス順に変更
- スラッグ列でアーカイブURLを表示（サンプル投稿ではなく）
- ラベル列のリンクを削除し、投稿数でアクセスする方式に変更

### Changed
- スラッグ入力の最大文字数を20→64文字に変更
- 内部スラッグフィールドをhiddenに変更
- url_slugが設定されていない場合はslugをフォールバック

### Fixed
- メニュー位置が正しく機能するように修正（show_in_menuを整数値に変更）

### Note
- v1.0.5の記載漏れを修正: 記事移動機能（カスタム投稿タイプ間での記事移動、管理画面への記事移動タブ追加）はv1.0.5で実装されました

## [1.0.6] - 2025-10-23

### Fixed
- カスタム投稿タイプの階層URLで投稿が表示されない問題を修正
- `find_post_by_path()` メソッドで階層URL構造（例：`/parent/post-type/post-slug/`）のカスタム投稿を正しく検索できるように改善
- `display_post()` メソッドで投稿タイプを `'post'` に固定していた問題を修正し、カスタム投稿の本文が表示されない不具合を解消
- `filter_request()`, `handle_request()`, `control_query()`, `template_control()` でカスタム投稿タイプの投稿を検索するように修正

### Added
- 管理画面にタブ切り替え機能を実装（カスタム投稿タイプ一覧/説明書）
- パス列を追加し、階層構造を完全パス（ルートから）で表示
- 編集フォームに閉じるボタン（×）を追加

### Improved
- リライトルールのソート処理を実装し、固定文字列の長さで優先順位を決定
- より具体的なルール（例：`seo-note/report/(.+?)`）が抽象的なルール（例：`seo-note/(.+?)`）より先にマッチするように改善
- リライトルールフィルタの優先順位を99999に変更し、最後に実行されるように調整
- 管理画面の横幅を100%に変更し、表示領域を拡大
- パス列の折り返しを防止（white-space: nowrap）
- ラベルクリックで投稿管理画面へ遷移可能に
- 操作列から「✓ 登録済み」表示を削除し簡素化

## [1.0.5] - 2025-10-22

### Added
- 記事移動機能の実装（カスタム投稿タイプ間で記事を移動）
- 管理画面に記事移動タブを追加

### Fixed
- 「スラッグトップページ」で「表示しない」を設定しているのに固定ページが表示される問題を修正
- `archive_display_type !== 'default'` 条件で `'none'` 設定時も固定ページを探していた不具合を解消
- ループで最初にマッチした投稿タイプで処理が終了し、最も長いパスまでチェックされない問題を修正

### Improved
- 階層URL構造で「best match」ロジックを導入し、最も長いパスにマッチする投稿タイプを優先
- `filter_request()`, `handle_request()` のマッチングロジックを改善
- アーカイブ表示制御の条件を `=== 'default'` に統一（「指定なし」の場合のみ固定ページを探す）

## [1.0.4] - 2025-10-21

### Fixed
- 3階層ネストされたカスタム投稿タイプのアーカイブページで404エラーが発生する問題を修正
- `KSTB_Parent_Selector::block_old_urls()`メソッドで直接の親のみを確認していた問題を解決

### Improved
- URL検証ロジックで再帰的なフルパス構築に対応
- `KSTB_Post_Type_Registrar::build_full_path_static()`静的メソッドを追加し、階層構造のパスを正しく構築

## [1.0.3] - 2025-10-11

### Added
- 親ページ選択メタボックスにスラッグ編集フィールドを追加

### Improved
- 階層構造のカスタム投稿タイプで親を選択してもスラッグが編集可能に
- スラッグ入力時にリアルタイムで既存パネルと同期

## [1.0.2] - 2025-10-05

### Changed
- 管理画面UIのシンプル化：不要な設定項目を削除
- リライトスラッグ設定を削除（常に投稿タイプIDを使用）
- RESTベース設定を削除（常に投稿タイプIDを使用）
- REST API設定を削除（常に有効）
- 権限タイプ設定を削除（常に'post'を使用）
- リライト設定を削除（常にきれいなURL、フロントベース無効）

### Improved
- 管理画面の操作性向上
- WordPress現代標準に準拠した設定

## [1.0.1] - 2025-09-24

### Fixed
- カスタム投稿タイプのスラッグ以下に設置された固定ページが404になる問題を修正
- カスタム投稿タイプの個別記事と固定ページの両方が正しく表示されるように改善

### Improved
- アーカイブコントローラーのURL判定ロジックを改善
- スラッグトップページとサブディレクトリページの処理を明確に分離

## [1.0.0] - 2025-09-14

### Added
- カスタム投稿タイプの作成・編集・削除機能
- 管理画面からの直感的な操作インターフェース
- 日本語完全対応
- 階層URL対応（親ディレクトリ/カスタム投稿タイプ/投稿名）
- アーカイブページの表示制御機能
- 固定ページとの自動統合機能
- REST API対応（ブロックエディター対応）
- 豊富なDashiconsアイコン選択
- 詳細なラベルカスタマイズ機能
- サポート機能の選択（タイトル、エディター、アイキャッチ画像など）
- タクソノミー（カテゴリー、タグ）の関連付け
- URLリライト設定
- メニュー位置のカスタマイズ

### Fixed
- アーカイブ非表示時の固定ページ表示問題を解決
- 階層URLでの404エラーを修正
- カスタム投稿タイプメニューの自動表示機能

### Improved
- パフォーマンスの最適化
- コードのリファクタリング
- セキュリティの強化

### Technical
- WordPress 5.0以上対応
- PHP 7.4以上対応
- カスタムデータベーステーブル実装
- Ajax通信による非同期処理
- 自動リライトルールフラッシュ機能

[1.0.25]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.24...v1.0.25
[1.0.24]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.23...v1.0.24
[1.0.23]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.22...v1.0.23
[1.0.22]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.21...v1.0.22
[1.0.21]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.20...v1.0.21
[1.0.20]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.19...v1.0.20
[1.0.19]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.18...v1.0.19
[1.0.18]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.17...v1.0.18
[1.0.17]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.16...v1.0.17
[1.0.16]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.15...v1.0.16
[1.0.15]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.14...v1.0.15
[1.0.14]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.13...v1.0.14
[1.0.13]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.12...v1.0.13
[1.0.12]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.11...v1.0.12
[1.0.11]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.10...v1.0.11
[1.0.10]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.9...v1.0.10
[1.0.9]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.8...v1.0.9
[1.0.8]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/compare/v1.0.7...v1.0.8
[1.0.0]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/releases/tag/v1.0.0