# API-01 短縮URL登録 テスト設計

作成日: 2026-10-29
ステータス: レビュー待ち
対象: [API-01](../api/API-01_create-short-url.md)

## 機能テスト（tests/Feature/CreateShortUrlTest.php）

| ID | 分類 | 条件 | 期待結果 |
| --- | --- | --- | --- |
| API-01-T01 | 正常 | `https://example.com/path?q=1` を送信 | 201。レスポンスが仕様の構造で、`clicks_count` が0、`short_url` が `{SHORT_URL_BASE}/{code}`。short_urls に1件保存される |
| API-01-T02 | 正常 | `http://` のURLを送信 | 201 |
| API-01-T03 | 正常 | ちょうど2048文字のURLを送信 | 201 |
| API-01-T04 | 正常 | 同じURLを2回送信 | 2回とも201で、異なる `code` が発行される |
| API-01-T05 | 異常 | `original_url` なし | 422。`errors.original_url` がある。DBに保存されない |
| API-01-T06 | 異常 | 空文字 | 422 |
| API-01-T07 | 異常 | URL形式でない文字列（`not-a-url`） | 422 |
| API-01-T08 | 異常 | `javascript:alert(1)` | 422 |
| API-01-T09 | 異常 | `ftp://example.com` | 422 |
| API-01-T10 | 異常 | 2049文字のURL | 422 |
| API-01-T11 | 異常 | 文字列以外（配列 `["https://example.com"]`） | 422 |
| API-01-T12 | 異常 | 1分間に61回送信 | 61回目が429 |
| API-01-T13 | 正常 | `SHORT_URL_BASE` を `https://sho.rt` に変更 | `short_url` が `https://sho.rt/{code}` |
| API-01-T14 | 正常 | `SHORT_URL_BASE` を末尾スラッシュ付きの `https://sho.rt/` に変更 | `short_url` が `https://sho.rt/{code}`（`/` が重ならない） |

## 単体テスト

### ShortCodeGenerator（tests/Unit/ShortCodeGeneratorTest.php）

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| API-01-T21 | 生成する | `/^[A-Za-z0-9]{7}$/` に一致する |
| API-01-T22 | 1,000回生成する | すべて形式に一致し、重複がない（偏りの簡易確認） |

### CreateShortUrl（tests/Feature/CreateShortUrlActionTest.php、DBを使うため Feature に配置）

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| API-01-T31 | 生成器が既存コード→新規コードの順に返すようモック | 再生成され、新規コードで保存される |
| API-01-T32 | 生成器が常に既存コードを返すようモック | 3回試行後に `UniqueConstraintViolationException` を送出し、新しい行は保存されない |
