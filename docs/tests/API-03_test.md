# API-03 リダイレクト テスト設計

作成日: 2026-10-29
ステータス: レビュー待ち
対象: [API-03](../api/API-03_redirect.md)

## 機能テスト（tests/Feature/RedirectTest.php）

| ID | 分類 | 条件 | 期待結果 |
| --- | --- | --- | --- |
| API-03-T01 | 正常 | 登録済みコードにアクセス | 302。`Location` が元のURL |
| API-03-T02 | 正常 | 登録済みコードにアクセス（`Queue::fake()`、時刻固定） | RecordClick が1回投入され、`short_url_id` と `clicked_at`（アクセス時刻）が正しい |
| API-03-T03 | 正常 | 登録済みコードにアクセス | レスポンスに `Set-Cookie` ヘッダーが無い（セッションを使っていない） |
| API-03-T04 | 異常 | 未登録のコード（形式は正しい） | 404。ジョブは投入されない |
| API-03-T05 | 異常 | 形式不正のコード（6文字、8文字、記号を含む） | 404 |
| API-03-T06 | 異常 | `aB3xK9q` を登録し、`AB3XK9Q` にアクセス | 404（大小文字を区別する） |
| API-03-T07 | 正常 | `/up` にアクセス | 200（ヘルスチェックがリダイレクトのルートと衝突しない） |
| API-03-T08 | 正常 | 登録済みコードにアクセス | `X-Content-Type-Options: nosniff`、`X-Frame-Options: DENY`、`Referrer-Policy: no-referrer` が付いている |

## ジョブのテスト（tests/Feature/RecordClickTest.php）

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| API-03-T11 | RecordClick を直接実行 | clicks に1行挿入され、`short_url_id` と `clicked_at` が渡した値と一致する |
| API-03-T12 | 同期キュー（`QUEUE_CONNECTION=sync`）で登録済みコードに2回アクセス | clicks が2行になり、API-02の `clicks_count` が2 |
