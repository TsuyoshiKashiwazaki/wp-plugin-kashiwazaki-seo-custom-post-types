# Changelog

All notable changes to Kashiwazaki SEO Custom Post Types will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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