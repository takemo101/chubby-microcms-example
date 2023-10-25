# Slim4[Chubby] MicroCMS Example
Slim4をラップしたパッケージ[Chubby](https://github.com/takemo101/chubby)を使用した、MicroCMSのサンプルです。  
Slim4と違い、アプリケーションの構築方法などが異なりますが、Slim4の機能をそのまま使用できます。

## 必須環境
- PHP 8.2 以上

## インストール
composerを使用して、依存ライブラリをインストールします。  
composerがインストールされていない場合は、[こちら](https://getcomposer.org/download/)からインストールしてください。  
```bash
composer install
```

> **注釈** *コマンドはプロジェクトのルートで実行してください*

## 設定内容
Chubbyでは、``.env``ファイルを使用して、環境変数を設定します。  
``.env``ファイルは、``.env.example``をコピーして作成してください。  
``.env``ファイルの内容は、以下の通りです。
```dotenv
### for microcms ###
MICROCMS_SERVICE_DOMAIN=MicroCMSのサービスID # https://サービスID.microcms.io
MICROCMS_API_KEY=MicroCMSのAPIキー

### for server ###
# 以下はphpのビルトサーバー設定です。
# 本番環境では、nginxやapacheなどのサーバーを使用してください。
SERVER_PORT=8080
SERVER_HOST=localhost
SERVER_SCRIPT=/public
```

## 起動方法
以下のコマンドを実行すると、phpのビルトインサーバーが起動します。  
http://localhost:8080 でアクセスできます。
```bash
php console serve
```

## その他
もしも、Chubbyを快適に利用したい場合は、[Chubbyのスケルトンプロジェクト](https://github.com/takemo101/chubby-skeleton)を使用できますので、ご興味があれば是非ご利用ください。

> **注釈** *Chubbyは、開発中のパッケージです。今後、仕様が変更される可能性があります*
