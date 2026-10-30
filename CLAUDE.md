# URL短縮サービス

Laravel（API）+ React/TypeScript（SPA）のURL短縮サービス。転職用ポートフォリオで、機能の多さより設計判断の根拠・型安全・テスト・CIを重視する。ローカル環境のみで動かし、本番公開はしない。

## ディレクトリ構成

```
backend/    Laravel（API・リダイレクト）
frontend/   React + TypeScript（Vite）
docs/       計画書・設計書（下記）
compose.yaml
```

## 設計書

作業前に、対象のIDに対応する設計書を読むこと。設計書をすべて読む必要はない。

| 内容 | 場所 |
| --- | --- |
| 計画・スコープ | `docs/00_project-plan.md` |
| 要件・設計論点の決定 | `docs/01_requirements.md` |
| 構成・方式・CI | `docs/02_architecture.md` |
| DB | `docs/03_database.md` |
| API（API-01〜03） | `docs/api/API-XX_*.md` |
| 画面（SCR-01〜02） | `docs/screens/SCR-XX_*.md` |
| テスト | `docs/tests/README.md`（共通方針）、`docs/tests/{ID}_test.md` |

例: API-01 の実装 → `docs/api/API-01_*.md` と `docs/tests/API-01_test.md` を読む。

## ルール

- 設計書が確定（ステータス「確定」）するまで、その機能を実装しない
- 実装中に設計を変える場合は、先に設計書を更新してから実装する
- MVP（API-01〜03、SCR-01〜02）の完成前に、将来拡張の機能に着手しない
- 実装とテストは同時に書き、テストの説明文の先頭にテストID（例: `API-01-T01`）を付ける
- PHP: 全ファイルに `declare(strict_types=1)`、引数・戻り値の型宣言。Larastanは最大レベルで通す
- TypeScript: `strict: true`。APIの型は `src/api/schema.d.ts`（自動生成）を使い、手で書かない・編集しない
- コミット前に静的解析・整形・テストを通す

## Git・コミット

- mainへ直接コミットしない。作業は1項目ずつ、最新のmainからブランチを切って行う
- ブランチ名は `種類/内容`（種類: `feature` 機能、`fix` 修正、`docs` ドキュメント、`chore` 環境・設定。例: `feature/API-01-create-short-url`）
- 作業が終わったら利用者がpushしてPRを作成し、GitHub上でmainへマージする
- コミットは機能やテストのまとまりごとに小さく分ける
- コミットメッセージは日本語で、「何をしたか」が分かるように書く（例: `API-01 短縮URL登録APIと機能テストを追加`）
- コミットにはこのリポジトリのローカル設定（個人用アカウント）を使う。`git config` の user.name / user.email は変更しない
- メールアドレスはGitHubの非公開用アドレス（`@users.noreply.github.com`）を使う。個人・会社のメールアドレスを履歴に含めない
- pushは利用者がVS Code（個人用GitHubアカウントでログイン）から行う。Claudeはpushしない
- リモート `origin` はユーザー名入りのHTTPS形式（`https://fuga-setoguchi@github.com/...`）とし、会社用の認証情報が使われないようにする

## コマンド

ホストにPHPは不要。バックエンドのコマンドはすべてコンテナ内で実行する。

| 用途 | コマンド |
| --- | --- |
| 起動 / 停止 | `docker compose up -d` / `docker compose down` |
| バックエンドのテスト | `docker compose exec app composer test` |
| 静的解析（Larastan） | `docker compose exec app composer analyse` |
| 整形チェック / 整形（Pint） | `docker compose exec app composer lint` / `composer format` |
| OpenAPI出力 | `docker compose exec app php artisan scramble:export --path=openapi.json` |

バックエンド固有のルールは `backend/CLAUDE.md` を参照。
