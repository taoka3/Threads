# taoka3/Threads (Laravel用Threads投稿ライブラリ)

## ライブラリ概要・目的

**taoka3/Threads** は LaravelプロジェクトからFacebook/MetaのThreads（*Threads API*）へ投稿するためのライブラリです。このライブラリを使うことで、OAuth認証・アクセストークン管理・投稿の作成・公開を簡単に実装できます。なお利用にはあらかじめFacebook for DevelopersでThreadsアプリの設定を完了しておく必要があります。また、Threads ライブラリ は現時点では公式提供のものではないため、仕様変更や利用制限により動作しなくなるリスクがある点にご注意ください。

## インストール方法

以下の手順でインストール・設定します。

1. **Composerでパッケージをインストール**

   ```bash
   composer require taoka3/threads
   ```

   これにより `taoka3/threads` がプロジェクトに追加されます。

2. **設定ファイルを公開**

   ```bash
   php artisan vendor:publish --tag=threads-config
   ```

   公開された `config/threads.php` で Threadsアプリの各種設定（アプリID、シークレット、リダイレクトURIなど）を入力します。

3. **マイグレーションファイルを公開**

   ```bash
   php artisan vendor:publish --tag=threads-migrations
   ```

   公開されたマイグレーションで `threads` テーブル（`user_id`, `long_access_token`, `limit_date` などのカラム）を作成します。

4. **データベースマイグレーション実行**

   ```bash
   php artisan migrate
   ```

   これにより `threads` テーブルがデータベースに作成されます。

## 使い方

インストール・設定後は、次のようにメソッドを呼び出してOAuth認証と投稿処理を行います。

1. **認証用URLを取得**

   ```php
   use taoka3\Threads\Threads;

   $threads = new Threads();
   $authUrl = $threads->authorize();
   ```

   `authorize()` は認証画面へリダイレクトするためのURL文字列を返します。生成したURLへユーザーを遷移させ、ログイン・権限付与を行ってもらいます。

2. **コールバック処理（アクセストークン取得）**

   ```php
   $threads = new Threads();
   $threads->redirectCallback()
           ->getAccessToken()
           ->changeLongAccessToken()
           ->save();
   ```

   リダイレクト先でクエリパラメータの `code` を取得し、`getAccessToken()` で短期アクセストークンを取得します。続けて `changeLongAccessToken()` によりロングアクセストークンに変換し、`save()` でデータベースに保存します。

3. **投稿作成・公開**

   ```php
   $threads = new Threads();
   $threads->getlongAccessTokenAndUserId()    // DBから user_id と long_access_token を読み込む
           ->post('投稿するテキスト', $imageUrl, 'IMAGE')
           ->publishPost();
   ```

   `post()` では投稿テキスト（と必要に応じて画像URL）を指定し、`publishPost()` で実際にThreads上に投稿を公開します。投稿成功時はJSONレスポンス（投稿IDなど）が出力されます。

4. **アクセストークンの更新**

   ```php
   if ((new Threads())->checkRefreshLongAccessToken()) {
       (new Threads())
           ->getlongAccessTokenAndUserId()
           ->refreshLongAccessToken()
           ->setUpdate();
   }
   ```

   `checkRefreshLongAccessToken()` は保存済みトークンの有効期限が近い場合に `true` を返します。必要に応じて `refreshLongAccessToken()` で新しいロングトークンを取得し、`setUpdate()` でDBを更新します。

各メソッドは fluent インターフェイスになっており、チェーンで呼び出し可能です。例えば `authorize()` は認証URL文字列を返し、`publishPost()` 実行時は投稿IDを含むレスポンスが得られます。

## Laravelプロジェクトへの組み込み

このパッケージはLaravelのサービスプロバイダが自動的に登録されるため、特別な設定は不要です。上記の `vendor:publish` により `config/threads.php` が生成されるので、そこで以下のキーに値を設定してください:

* `threads.appid` – ThreadsアプリのApp ID
* `threads.apiSecret` – アプリのAPIシークレット
* `threads.redirectUri` – 認証後に戻ってくるコールバックURI
* `threads.endPointUri` – APIエンドポイント（例: `https://graph.threads.net/`）
* `threads.version` – APIバージョン（例: `v1.0/` など）

これらの設定値は Facebook for Developers で作成したThreadsアプリの情報に基づいて入力します。先述のマイグレーション実行により `threads` テーブルが作成され、認証ユーザーIDとロングトークンを保存できるようになります。

## 対応バージョン・依存ライブラリ

* **PHP:** ^8.2 または ^8.3
* **Laravel:** ^10.0, ^11.0, ^12.0
* **依存拡張:** cURL（`ext-curl`）、`allow_url_fopen` を有効化してください。
* **ライセンス:** MIT

パッケージの `composer.json` に記載された要件に基づきます。

## 注意事項

* Threads ライブラリ は公式に公開されているものではないため、Meta/Threads側の仕様変更や制限により動作しなくなる可能性があります。利用時は最新のFacebook開発者ドキュメントを参照してください。
* 本ライブラリ内でも述べたように、ロングアクセストークンは約60日で期限切れになるため、定期的な更新処理が必要です（`checkRefreshLongAccessToken()` を参照）。
* パッケージはデータベースへの直接書き込みを行うため、エラー処理や権限周りの例外を適切に実装してください。

## ライセンス

本ライブラリは **MITライセンス** のもとで公開されています。ソースコードは自由に改変・再配布できます。

**参考:** パッケージの詳細情報は [Packagist: taoka3/threads](https://packagist.org/packages/taoka3/threads) および [README（日本語）](https://packagist.org/packages/taoka3/threads) を参照してください。
