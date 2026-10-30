# バックエンド（Laravel）

ルートの `CLAUDE.md` のルールに加えて、以下に従う。

## 構成

- 責務の分け方は `docs/02_architecture.md` 3.1 に従う（コントローラーは薄く、処理は `app/Actions`、検証は FormRequest / `app/Rules`）
- APIのレスポンスは API Resource で整形し、内部ID（`id`）を返さない

## コーディング

- 全ファイルに `declare(strict_types=1)`（Pint の `declare_strict_types` で自動付与される）
- 引数・戻り値・プロパティに型を書く。`env()` の戻り値など `mixed` は明示的に変換する（例: `(string) env('APP_URL')`）
- `env()` は `config/` の中でだけ使う。アプリのコードでは `config()` を使う

## テスト

- Pest で書く。`tests/Feature` には `RefreshDatabase` が自動で適用される（`tests/Pest.php`）
- テストはMySQLのテスト用DB（`testing`）で実行される。`phpunit.xml` の `<server>` 指定を変えない（開発用DBを消さないため。`tests/Feature/TestDatabaseTest.php` で検証している）
- テスト名の先頭にテストID（例: `API-01-T01`）を書く

## 完了前の確認

以下がすべて通ることを確認してからコミットする。

```
docker compose exec app composer lint
docker compose exec app composer analyse
docker compose exec app composer test
```
