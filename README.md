# Kashiwazaki SEO Custom Post Types

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.18-orange.svg)](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/releases)

WordPressのカスタム投稿タイプを簡単に作成・管理できる強力なプラグインです。コーディング不要で、管理画面から直感的にカスタム投稿タイプを作成でき、階層URL構造やアーカイブページの表示制御など高度な機能も搭載しています。

> **プロフェッショナルなサイト構築をシンプルに**

## 主な機能

### 基本機能
- **ノーコード対応**: 管理画面から直感的にカスタム投稿タイプを作成
- **日本語完全対応**: すべてのインターフェースが日本語化
- **即座にメニュー表示**: 作成したカスタム投稿タイプは管理メニューに自動表示
- **詳細なラベル設定**: 管理画面の表示テキストを自由にカスタマイズ

### 高度な機能
- **階層URL対応**: `親ページ/カスタム投稿タイプ/投稿名` の形式でURL構造を構築
- **アーカイブページ制御**: アーカイブページの表示/非表示を選択可能
- **固定ページ統合**: アーカイブを非表示にした場合、同じURLの固定ページを自動表示
- **REST API対応**: ブロックエディター（Gutenberg）完全対応
- **豊富なアイコン選択**: Dashiconsから最適なアイコンを選択可能

### サポート機能
- タイトル、エディター、アイキャッチ画像
- 抜粋、カスタムフィールド、コメント
- リビジョン、ページ属性
- 既存タクソノミー（カテゴリー、タグ）の関連付け

## クイックスタート

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

詳細な更新履歴は [CHANGELOG.md](CHANGELOG.md) をご覧ください。

## ライセンス

GPL-2.0-or-later

## サポート・開発者

**開発者**: 柏崎剛 (Tsuyoshi Kashiwazaki)
**ウェブサイト**: https://www.tsuyoshikashiwazaki.jp/
**サポート**: プラグインに関するご質問や不具合報告は、開発者ウェブサイトまでお問い合わせください。

## 貢献

バグ報告や機能提案は [Issues](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/issues) からお願いします。

プルリクエストも歓迎します:
1. このリポジトリをフォーク
2. 機能ブランチを作成 (`git checkout -b feature/AmazingFeature`)
3. 変更をコミット (`git commit -m 'Add some AmazingFeature'`)
4. ブランチにプッシュ (`git push origin feature/AmazingFeature`)
5. プルリクエストを作成

## サポート

- **公式サイト**: https://www.tsuyoshikashiwazaki.jp/
- **お問い合わせ**: 上記サイトのお問い合わせフォームから
- **バグ報告**: [GitHub Issues](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-custom-post-types/issues)

---

<div align="center">

**Keywords**: WordPress, カスタム投稿タイプ, Custom Post Type, CPT, 階層URL, SEO, 日本語対応

Made by [Tsuyoshi Kashiwazaki](https://github.com/TsuyoshiKashiwazaki)

</div>