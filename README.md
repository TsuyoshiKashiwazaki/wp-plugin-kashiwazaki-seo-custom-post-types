# 🚀 Kashiwazaki SEO Custom Post Types

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.6--dev-orange.svg)](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/releases)

WordPressのカスタム投稿タイプを簡単に作成・管理できる強力なプラグインです。コーディング不要で、管理画面から直感的にカスタム投稿タイプを作成でき、階層URL構造やアーカイブページの表示制御など高度な機能も搭載しています。

> 🎯 **プロフェッショナルなサイト構築をシンプルに**

## 主な機能

### ✨ 基本機能
- **ノーコード対応**: 管理画面から直感的にカスタム投稿タイプを作成
- **日本語完全対応**: すべてのインターフェースが日本語化
- **即座にメニュー表示**: 作成したカスタム投稿タイプは管理メニューに自動表示
- **詳細なラベル設定**: 管理画面の表示テキストを自由にカスタマイズ

### 🔧 高度な機能
- **階層URL対応**: `親ページ/カスタム投稿タイプ/投稿名` の形式でURL構造を構築
- **アーカイブページ制御**: アーカイブページの表示/非表示を選択可能
- **固定ページ統合**: アーカイブを非表示にした場合、同じURLの固定ページを自動表示
- **REST API対応**: ブロックエディター（Gutenberg）完全対応
- **豊富なアイコン選択**: Dashiconsから最適なアイコンを選択可能

### 📋 サポート機能
- タイトル、エディター、アイキャッチ画像
- 抜粋、カスタムフィールド、コメント
- リビジョン、ページ属性
- 既存タクソノミー（カテゴリー、タグ）の関連付け

## 🚀 クイックスタート

### インストール

1. プラグインファイルを `/wp-content/plugins/kashiwazaki-seo-custom-post-types` にアップロード
2. WordPress管理画面の「プラグイン」メニューから有効化
3. 「Kashiwazaki SEO Custom Post Types」メニューが表示されます

### 基本的な使い方

1. **新規作成**
   - 管理メニューから「Kashiwazaki SEO Custom Post Types」をクリック
   - 「新規追加」ボタンをクリック
   - スラッグとラベルを入力（例: スラッグ「news」、ラベル「お知らせ」）

2. **詳細設定**
   - 「基本設定」タブで公開設定や表示設定を調整
   - 「ラベル設定」タブで管理画面の表示テキストをカスタマイズ
   - 「詳細設定」タブでサポート機能やタクソノミーを選択

3. **階層URL設定**
   - 「親ディレクトリ」で親となる固定ページを選択
   - 自動的に階層化されたURL構造が生成されます

## 使い方

### カスタム投稿タイプの管理

#### 編集
- リスト画面で「編集」ボタンをクリック
- 設定を変更して「保存」をクリック

#### 削除
- リスト画面で「削除」ボタンをクリック
- 確認ダイアログで「OK」をクリック
- ※投稿済みのコンテンツは保持されます

### アーカイブページ設定

「スラッグトップページ」設定で以下を選択できます:

- **アーカイブを表示**: 投稿一覧をアーカイブページとして表示
- **固定ページを表示**: 指定した固定ページの内容を表示
- **表示しない**: アーカイブを無効化（同じURLの固定ページがあれば自動表示）

## 技術仕様

### システム要件
- WordPress 5.0以上
- PHP 7.4以上
- MySQL 5.6以上

### データベース
- カスタムテーブル `wp_kstb_post_types` でデータ管理
- 投稿データは標準のWordPressテーブルを使用

### 互換性
- マルチサイト対応
- 主要テーマとの互換性確認済み
- 他のSEOプラグインと併用可能

## 更新履歴

### Version 1.0.6 - 2025-10-23
- **修正**: カスタム投稿タイプの階層URLで投稿が表示されない問題を修正
- **修正**: `find_post_by_path()` メソッドで階層URL構造のカスタム投稿を正しく検索できるように改善
- **修正**: `display_post()` メソッドで投稿タイプを動的に取得し、カスタム投稿の本文が表示されない問題を解消
- **改善**: リライトルールのソート処理を実装し、固定文字列の長さでより具体的なルールを優先

### Version 1.0.5 - 2025-10-22
- **修正**: 「スラッグトップページ」で「表示しない」設定時に固定ページが表示される問題を修正
- **修正**: `archive_display_type !== 'default'` 条件で `'none'` 設定時も固定ページを探していた不具合を解消
- **改善**: 階層URL構造で「best match」ロジックを導入し、最も長いパスにマッチする投稿タイプを優先
- **改善**: アーカイブ表示制御の条件を `=== 'default'` に統一

### Version 1.0.4 - 2025-10-21
- **修正**: 3階層ネストされたカスタム投稿タイプのアーカイブページで404エラーが発生する問題を修正
- **改善**: URL検証ロジックで再帰的なフルパス構築に対応

### Version 1.0.3 - 2025-10-11
- **追加**: 親ページ選択メタボックスにスラッグ編集フィールドを追加
- **改善**: 階層構造のカスタム投稿タイプで親を選択してもスラッグが編集可能に
- **改善**: スラッグ入力時にリアルタイムで既存パネルと同期

### Version 1.0.2 - 2025-10-05
- **変更**: 管理画面UIのシンプル化（不要な設定項目を削除）
- **改善**: 操作性向上とWordPress現代標準への準拠
- **削除**: リライトスラッグ、RESTベース、REST API、権限タイプ、リライト設定を削除

### Version 1.0.1 - 2025-09-24
- **修正**: カスタム投稿タイプのスラッグ以下に設置された固定ページが404になる問題を修正
- **改善**: アーカイブコントローラーのURL判定ロジックを改善
- **改善**: スラッグトップページとサブディレクトリページの処理を明確に分離

### Version 1.0.0 - 2025-09-14
- 初回リリース
- カスタム投稿タイプの作成・編集・削除機能
- 階層URL対応
- アーカイブページ制御機能
- 日本語完全対応

## ライセンス

GPL-2.0-or-later

## サポート・開発者

**開発者**: 柏崎剛 (Tsuyoshi Kashiwazaki)
**ウェブサイト**: https://www.tsuyoshikashiwazaki.jp/
**サポート**: プラグインに関するご質問や不具合報告は、開発者ウェブサイトまでお問い合わせください。

## 🤝 貢献

バグ報告や機能提案は [Issues](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/issues) からお願いします。

プルリクエストも歓迎します:
1. このリポジトリをフォーク
2. 機能ブランチを作成 (`git checkout -b feature/AmazingFeature`)
3. 変更をコミット (`git commit -m 'Add some AmazingFeature'`)
4. ブランチにプッシュ (`git push origin feature/AmazingFeature`)
5. プルリクエストを作成

## 📞 サポート

- **公式サイト**: https://www.tsuyoshikashiwazaki.jp/
- **お問い合わせ**: 上記サイトのお問い合わせフォームから
- **バグ報告**: [GitHub Issues](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/issues)

---

<div align="center">

**🔍 Keywords**: WordPress, カスタム投稿タイプ, Custom Post Type, CPT, 階層URL, SEO, 日本語対応

Made with ❤️ by [Tsuyoshi Kashiwazaki](https://github.com/TsuyoshiKashiwazaki)

</div>