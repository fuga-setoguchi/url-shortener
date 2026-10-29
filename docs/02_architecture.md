# システム構成・方式設計書

作成日: 2026-10-29
ステータス: レビュー待ち
参照: [要件定義書](01_requirements.md)

## 1. システム構成

```mermaid
flowchart LR
  Browser[ブラウザ]
  subgraph compose[Docker Compose]
    FE[frontend<br/>Vite dev server :5173]
    APP[app<br/>Laravel :8000]
    Q[queue<br/>queue:work]
    DB[(db<br/>MySQL)]
  end
  Browser -->|画面 SCR-01/02| FE
  FE -->|/api/* をプロキシ| APP
  Browser -->|短いURL GET /{code}| APP
  APP --> DB
  APP -->|ジョブ投入 jobsテーブル| DB
  Q -->|ジョブ取得・clicks書き込み| DB
```

| サービス | イメージ / 起動内容 | ポート |
| --- | --- | --- |
| db | `mysql`（公式イメージ）。ヘルスチェック付き | 3306 |
| app | `backend/Dockerfile`（PHP CLI + pdo_mysql + Composer）。起動時に依存インストール・マイグレーション後 `php artisan serve` | 8000 |
| queue | app と同じイメージで `php artisan queue:work` | なし |
| frontend | `node`（公式LTSイメージ）。`npm ci` 後 `vite --host` | 5173 |

- app・queue は db のヘルスチェック成功後に起動する（`depends_on.condition: service_healthy`）
- ローカル開発用途のため、Webサーバーは `php artisan serve` とする（nginx + php-fpm は本番向けで、本プロジェクトの対象外）
- 初回起動時に `.env` が無ければ `.env.example` から作成し `APP_KEY` を生成する

## 2. 技術スタック

各バージョンは環境構築時点の最新安定版で確定し、本書とREADMEを更新する。

| 領域 | 採用技術 | 備考 |
| --- | --- | --- |
| バックエンド | PHP / Laravel | APIとリダイレクト |
| DB | MySQL | 文字コード utf8mb4 |
| キュー | Laravel Queue（database） | クリック記録 |
| API仕様 | Scramble | コードからOpenAPIを生成し `backend/openapi.json` に出力してコミット |
| フロントエンド | React + TypeScript + Vite | SPA |
| ルーティング | React Router | `/` と `/urls` の2画面 |
| サーバー状態管理 | TanStack Query | 取得・ローディング・エラー状態の管理 |
| APIクライアント | openapi-typescript + openapi-fetch | 生成した型でリクエスト・レスポンスを型付け |
| スタイル | CSS Modules | 追加ライブラリなし |
| バックエンド品質 | Larastan（最大レベル） / Pint / Pest | |
| フロントエンド品質 | tsc / ESLint / Prettier / Vitest + React Testing Library + MSW | MSWでAPIをモック |
| CI | GitHub Actions | 4章 |

## 3. 方式設計

### 3.1 バックエンドの構成

責務ごとにクラスを分け、コントローラーは薄く保つ。

```
backend/app/
├── Http/
│   ├── Controllers/
│   │   ├── ShortUrlController.php        # API-01 store / API-02 index
│   │   └── RedirectController.php        # API-03（__invoke）
│   ├── Requests/StoreShortUrlRequest.php # API-01 の入力検証
│   └── Resources/ShortUrlResource.php    # API-01/02 のレスポンス整形
├── Actions/CreateShortUrl.php            # コード発行と保存（衝突時の再生成）
├── Support/ShortCodeGenerator.php        # ランダム7文字の生成
├── Jobs/RecordClick.php                  # クリック記録（キュー）
└── Models/{ShortUrl.php, Click.php}
```

| クラス | 責務 |
| --- | --- |
| ShortCodeGenerator | `[A-Za-z0-9]` から暗号論的に安全な乱数（`random_int`）で7文字を生成する |
| CreateShortUrl | コードを生成して保存する。`UniqueConstraintViolationException` 発生時は再生成し、3回失敗したら例外をそのまま送出する（500） |
| RecordClick | `short_url_id` と `clicked_at` を受け取り clicks に1行挿入する。`clicked_at` はジョブ投入時（アクセス時）の時刻を渡す |

### 3.2 ルーティング

| 定義場所 | ルート | ミドルウェア |
| --- | --- | --- |
| `routes/api.php` | `POST /api/short-urls`、`GET /api/short-urls` | `api`（POSTには `throttle:60,1`） |
| `routes/web.php` | `GET /{code}`（`code` は `[A-Za-z0-9]{7}` の正規表現制約） | なし（後述） |

- リダイレクトはステートレスなため、セッション・Cookie・CSRFを扱う `web` ミドルウェアグループを適用しない（`bootstrap/app.php` の `withRouting` で web ルートをミドルウェアなしで登録する）。不要なセッション書き込みを避けるため
- コード形式の制約により、`/up`（ヘルスチェック）や `/docs/api`（Scramble）とは衝突しない

### 3.3 短いURLの組み立て

`config/shorturl.php` に `base_url`（`env('SHORT_URL_BASE', env('APP_URL'))`）を定義し、`ShortUrlResource` で `rtrim(base_url, '/') . '/' . code` を返す。フロントエンドは組み立てを行わず、APIの `short_url` をそのまま表示する。

### 3.4 フロントエンドの構成

```
frontend/src/
├── api/
│   ├── schema.d.ts        # openapi-typescript で生成（手で編集しない）
│   └── client.ts          # openapi-fetch のクライアント
├── features/short-urls/
│   ├── RegisterPage.tsx   # SCR-01
│   ├── UrlListPage.tsx    # SCR-02
│   └── hooks.ts           # useCreateShortUrl / useShortUrls（TanStack Query）
├── components/Layout.tsx  # ヘッダー（画面切り替えリンク）
├── main.tsx
└── router.tsx
```

- 開発時、Vite の `server.proxy` で `/api` を app サービス（`http://app:8000`）へ転送する。同一オリジンとなるためCORS設定は不要
- 日時は `Intl.DateTimeFormat('ja-JP', …)` で利用者のローカル時刻に整形する

### 3.5 API型連携の流れ

1. バックエンドを変更したら `php artisan scramble:export --path=openapi.json` で `backend/openapi.json` を更新する
2. フロントエンドで `npm run generate:api`（`openapi-typescript ../backend/openapi.json -o src/api/schema.d.ts`）を実行する
3. CIで両方を再生成し、差分が出たら失敗させる（生成物の更新漏れを防ぐ）

### 3.6 エラー処理

| 状況 | バックエンド | フロントエンド |
| --- | --- | --- |
| 入力検証エラー | 422（Laravel標準形式 `{message, errors}`） | 項目のエラーメッセージをフォーム下に表示 |
| レート制限 | 429 | 「時間をおいて再度お試しください」を表示 |
| 想定外のエラー | 500（本文に詳細を含めない。`APP_DEBUG=false` 時） | 汎用エラーメッセージを表示 |
| 存在しないコード | 404（Laravel標準の404ページ） | 対象外（ブラウザが直接アクセス） |

- APIへのリクエストには `Accept: application/json` を付与し、エラーをJSONで受け取る
- 検証エラーメッセージは日本語化する（`lang/ja/validation.php`、`APP_LOCALE=ja`）

## 4. CI（GitHub Actions）

PRとmainへのpushで実行する。backendとfrontendは別ジョブで並列に実行する。

| ジョブ | 手順 |
| --- | --- |
| backend | Composer install → Pint（`--test`）→ Larastan → OpenAPI再生成の差分チェック → Pest（MySQLサービスコンテナ使用） |
| frontend | `npm ci` → 型生成の差分チェック → ESLint → Prettier（`--check`）→ tsc → Vitest |

- バックエンドのテストはMySQLで実行する（照合順序 `utf8mb4_bin` による大小文字の区別など、本番と同じDBの挙動を検証するため）
- ローカルでは `docker compose exec app php artisan test` で同じテストを実行できる（テスト用DB `testing` を使用）

## 5. 開発フロー

- 機能ごとにIssueとブランチ（`feature/API-01-create-short-url` など）を作成し、PRでmainへマージする
- mainはブランチ保護を設定し、CI通過をマージ条件とする
