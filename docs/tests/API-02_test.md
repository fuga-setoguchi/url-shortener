# API-02 短縮URL一覧取得 テスト設計

作成日: 2026-10-29
ステータス: 確定（2026-10-30）
対象: [API-02](../api/API-02_list-short-urls.md)

## 機能テスト（tests/Feature/ListShortUrlsTest.php）

| ID | 分類 | 条件 | 期待結果 |
| --- | --- | --- | --- |
| API-02-T01 | 正常 | 0件 | 200。`data` が空配列、`meta.total` が0 |
| API-02-T02 | 正常 | 3件登録 | 200。各要素が仕様の構造（`id` を含まない） |
| API-02-T03 | 正常 | 作成日時の異なる3件 | 作成日時の新しい順に並ぶ |
| API-02-T04 | 正常 | 作成日時が同じ2件 | IDの降順に並ぶ |
| API-02-T05 | 正常 | 短縮URL AにクリックA×3、Bに0 | Aの `clicks_count` が3、Bが0 |
| API-02-T06 | 正常 | 21件登録、`page` 指定なし | `data` が20件、`meta.last_page` が2、`meta.per_page` が20 |
| API-02-T07 | 正常 | 21件登録、`page=2` | `data` が1件（最も古いもの） |
| API-02-T08 | 境界 | 1件登録、`page=99` | 200。`data` が空配列 |
| API-02-T09 | 異常 | `page=abc`、`page=0`、`page=-1` | 422 |
| API-02-T10 | 性能 | 20件・各クリックあり | 実行クエリ数が件数に比例しない（N+1がない。`DB::enableQueryLog()` で件数を確認） |
| API-02-T11 | 異常 | 1分間に61回リクエスト | 61回目が429 |
| API-02-T12 | 異常 | `Origin: http://evil.example` 付きでリクエスト | `Access-Control-Allow-Origin` ヘッダーが付かない |
