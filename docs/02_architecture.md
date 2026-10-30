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

- ポートはすべて `127.0.0.1` にのみ公開する（例: `127.0.0.1:8000:8000`）。Docker の既定では全インターフェース（0.0.0.0）に公開され、同じネットワーク上の他の端末からDB等に接続できてしまうため
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
│   ├── Middleware/SecurityHeaders.php    # セキュリティ関連ヘッダーの付与（6.3）
│   ├── Requests/StoreShortUrlRequest.php # API-01 の入力検証
│   └── Resources/ShortUrlResource.php    # API-01/02 のレスポンス整形
├── Rules/SafeRedirectUrl.php            # 転送先URLの安全性検証（6.1）
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
| `routes/api.php` | `POST /api/v1/short-urls`、`GET /api/v1/short-urls` | `api`、`throttle:api`（60回/分）。POSTには `throttle:create-short-url`（10回/分）も適用 |
| `routes/web.php` | `GET /{code}`（`code` は `[A-Za-z0-9]{7}` の正規表現制約） | なし（後述） |

- リダイレクトはステートレスなため、セッション・Cookie・CSRFを扱う `web` ミドルウェアグループを適用しない（`bootstrap/app.php` の `withRouting` で web ルートをミドルウェアなしで登録する）。不要なセッション書き込みを避けるため
- コード形式の制約により、`/up`（ヘルスチェック）や `/docs/api`（Scramble）とは衝突しない

#### APIのバージョニング

- APIはURLにメジャーバージョンを付ける（`bootstrap/app.php` の `withRouting(apiPrefix: 'api/v1')`）
- 互換性を壊す変更（項目の削除・型や意味の変更）を行う場合だけ `v2` を追加し、移行期間は `v1` と並行して提供する。項目の追加など互換性を保つ変更は `v1` のまま行う
- リダイレクト（`/{code}`）にはバージョンを付けない。短いURLは外部に配布され、変わらないことが前提のため
- コントローラー等の `V1` 名前空間への分割は、`v2` が必要になった時点で行う（MVPでは1バージョンのみのため）

### 3.3 短いURLの組み立て

`config/shorturl.php` に `base_url`（`env('SHORT_URL_BASE', env('APP_URL'))`）を定義し、`ShortUrlResource` で `rtrim(base_url, '/') . '/' . code` を返す。フロントエンドは組み立てを行わず、APIの `short_url` をそのまま表示する。

- `SHORT_URL_BASE` は開発者が `.env` で管理する設定値であり、利用者の入力ではないため、値の形式は検証しない（`APP_URL` と同じ扱い）。`.env.example` に正しい形式の値を記載する
- 末尾の `/` の有無による二重スラッシュは `rtrim` で吸収する

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

- `/api/*` のエラーは `Accept` ヘッダーに関わらず常にJSONで返す（`bootstrap/app.php` の `shouldRenderJsonWhen`）。フロントエンドも `Accept: application/json` を付与する
- 検証エラーメッセージは日本語化する（`lang/ja/validation.php`、`APP_LOCALE=ja`）

## 4. CI（GitHub Actions）

PRとmainへのpushで実行する。backendとfrontendは別ジョブで並列に実行する。

| ジョブ | 手順 |
| --- | --- |
| backend | Composer install → `composer audit` → Pint（`--test`）→ Larastan → OpenAPI再生成の差分チェック → Pest（MySQLサービスコンテナ使用） |
| frontend | `npm ci` → `npm audit --audit-level=high` → 型生成の差分チェック → ESLint → Prettier（`--check`）→ tsc → Vitest |

- ワークフローの権限は最小にする（`permissions: contents: read`）
- 外部のアクションはコミットSHAで固定する（タグの付け替えによる改ざんを防ぐ）。更新は Dependabot に任せる
- Dependabot で Composer・npm・GitHub Actions の依存更新PRを週1回作成する

- バックエンドのテストはMySQLで実行する（照合順序 `utf8mb4_bin` による大小文字の区別など、本番と同じDBの挙動を検証するため）
- ローカルでは `docker compose exec app php artisan test` で同じテストを実行できる（テスト用DB `testing` を使用）

## 5. 開発フロー

- 機能ごとにIssueとブランチ（`feature/API-01-create-short-url` など）を作成し、PRでmainへマージする
- mainはブランチ保護を設定し、CI通過をマージ条件とする

## 6. セキュリティ設計

本サービスは「利用者が入力したURLへ転送する」ことが本質であり、悪用（不正なURLの登録、転送先の偽装）と、ローカル環境の不用意な公開を主な脅威とする。

### 6.1 入力（URL登録）

| 対策 | 内容 | 防ぐもの |
| --- | --- | --- |
| スキーム制限 | http・httpsのみ（`url:http,https`） | `javascript:`・`data:` 等によるXSS、アプリ起動スキームの悪用 |
| 認証情報付きURLの拒否 | `user:pass@host` 形式（URLのuserinfo部）を拒否 | `https://google.com@evil.example` のような転送先の偽装 |
| 自サービスへの短縮を拒否 | ホスト（ポート含む）が `SHORT_URL_BASE` と同じURLを拒否 | 短縮URLの多重化による転送先の隠蔽、転送ループ |
| 制御文字・空白の拒否 | 改行（CR/LF）・タブ・空白などを含むURLを拒否 | `Location` ヘッダーへの改行混入（ヘッダーインジェクション） |
| 長さ制限 | 最大2048文字 | 過大な入力 |
| 検証済みの値のみ使用 | `$request->validated()` の値だけを保存し、モデルは `$fillable` で列を限定 | マスアサインメント（`code` 等の指定による上書き） |

- サーバーは登録されたURLへ自らアクセスしない（プレビュー取得等を行わない）。そのため、内部ネットワークへの不正アクセス（SSRF）は発生しない。将来この種の機能を足す場合はSSRF対策を別途設計する
- 検証規則は独自ルール `SafeRedirectUrl`（`app/Rules/`）にまとめ、単体でテストできるようにする

### 6.2 出力

| 対策 | 内容 |
| --- | --- |
| 内部IDを返さない | 識別子は `code` のみ公開する |
| エラー詳細を返さない | `.env.example` は `APP_DEBUG=false` とし、500の本文にスタックトレース等を含めない（調査時のみ手元で `true` にする） |
| 元のURLはテキストで表示 | 画面では元のURLをリンクにせず、Reactの通常の文字列表示（自動エスケープ）で出す。`dangerouslySetInnerHTML` は使わない（ESLintで禁止） |
| 外部リンクの保護 | 短いURLのリンクは `rel="noopener noreferrer"` を付ける |

### 6.3 HTTPヘッダー

ミドルウェア `SecurityHeaders` で付与する。

| ヘッダー | 値 | 対象 |
| --- | --- | --- |
| X-Content-Type-Options | `nosniff` | 全レスポンス |
| X-Frame-Options | `DENY` | 全レスポンス |
| Referrer-Policy | `no-referrer` | 全レスポンス（転送先にリファラーを渡さない） |
| Content-Security-Policy | `default-src 'none'; frame-ancestors 'none'` | `/api/*` のみ（JSONしか返さないため、何も読み込ませない） |
| Cache-Control | `no-store` | `/api/*` のみ |

- HSTSはHTTPSを使わないローカル環境のため付与しない（公開時に追加する）

### 6.4 レート制限

| 対象 | 制限（IP単位） | 理由 |
| --- | --- | --- |
| API全体（`/api/*`） | 60回/分 | 一覧取得の連打などによる負荷の抑制 |
| URL登録（API-01） | 10回/分（API全体の制限に加えて） | 大量登録・悪用の抑制 |
| リダイレクト（API-03） | なし | 後述 |

- リダイレクトに制限を設けない理由：制限のカウンタ更新（cacheテーブルへの書き込み）が転送のたびに発生し、応答を遅らせない方針（NFR-02）と矛盾するため。コードは62^7通りあり、総当たりで有効なコードを見つけることは現実的でない
- クライアントIPはプロキシのヘッダー（`X-Forwarded-For`）を信頼せず、接続元IPを使う（Laravel既定。プロキシを置かないため）

### 6.5 CSRF・CORS

- APIはCookie・セッションを使わないため、CSRFの対象外とする。将来ログインをCookie方式で追加する場合はCSRF対策を合わせて導入する
- CORSは許可オリジンを空にする（`config/cors.php` の `allowed_origins => []`）。画面はViteのプロキシ経由で同一オリジンからアクセスするため不要であり、Laravel既定の `*`（全オリジン許可）を残さない

### 6.6 環境・秘密情報

- `.env` はコミットしない（`.gitignore`）。`.env.example` には秘密情報を含めない
- DBはアプリ専用ユーザーで接続し、rootを使わない。権限は対象DBのみに限定する
- コンテナ内のプロセスは非rootユーザーで実行する
- 依存パッケージの既知の脆弱性はCIの `composer audit` / `npm audit` で検出し、Dependabotで更新する

### 6.7 受け入れるリスク

MVP（ローカル環境のみ）で対策しないものと、その理由。公開する場合は対策を必須とする。

| リスク | 理由・公開時の対策 |
| --- | --- |
| 一覧APIで誰でも全件を閲覧できる | ログインなしの仕様のため。公開時はログイン機能（将来拡張）で利用者ごとに制限する |
| フィッシング・マルウェアのURLを登録できる | 悪性URLの判定には外部サービスが必要で、本プロジェクトの範囲外。公開時は Google Safe Browsing 等で判定する |
| ボットのアクセスもクリック数に含まれる | 分析機能は対象外のため |
| HTTPS非対応 | ローカル環境のみのため。公開時はHTTPS化とHSTSを必須とする |

- 個人情報の保護のため、クリック記録にIPアドレス・User-Agentは保存しない
