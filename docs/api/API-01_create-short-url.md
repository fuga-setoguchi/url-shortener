# API-01 短縮URL登録

作成日: 2026-10-29
ステータス: レビュー待ち
対応要件: FR-01, FR-02, NFR-03, NFR-04, NFR-05

## 1. 概要

元のURLを受け取り、短縮コードを発行して保存し、短いURLを返す。

| 項目 | 内容 |
| --- | --- |
| メソッド | POST |
| パス | /api/v1/short-urls |
| 認証 | なし |
| レート制限 | 60回/分/IP（超過時429） |
| 処理クラス | ShortUrlController@store → StoreShortUrlRequest → CreateShortUrl → ShortUrlResource |

## 2. リクエスト

ヘッダー: `Content-Type: application/json`、`Accept: application/json`

| 項目 | 型 | 必須 | 検証規則 |
| --- | --- | --- | --- |
| original_url | string | ○ | `required`、`string`、`max:2048`、`url:http,https` |

```json
{ "original_url": "https://example.com/very/long/path?query=1" }
```

## 3. 処理

1. StoreShortUrlRequest で入力を検証する（失敗時422）
2. CreateShortUrl が ShortCodeGenerator で7文字のコードを生成し、short_urls に保存する
3. 一意制約違反（`UniqueConstraintViolationException`）の場合はコードを再生成する。3回失敗したら例外を送出する（500）
4. 201 で ShortUrlResource を返す（`clicks_count` は 0）

## 4. レスポンス

### 201 Created

| 項目 | 型 | 説明 |
| --- | --- | --- |
| data.code | string | 短縮コード（7文字） |
| data.short_url | string | 短いURL |
| data.original_url | string | 元のURL |
| data.clicks_count | integer | クリック数 |
| data.created_at | string | 作成日時（ISO 8601） |

```json
{
  "data": {
    "code": "aB3xK9q",
    "short_url": "http://localhost:8000/aB3xK9q",
    "original_url": "https://example.com/very/long/path?query=1",
    "clicks_count": 0,
    "created_at": "2026-10-29T07:30:00+00:00"
  }
}
```

### エラー

| ステータス | 条件 | 本文 |
| --- | --- | --- |
| 422 | 入力検証エラー | `{"message": "...", "errors": {"original_url": ["..."]}}` |
| 429 | レート制限超過 | `{"message": "Too Many Attempts."}` |
| 500 | コード生成の再試行上限超過など | `{"message": "Server Error"}` |

## 5. 補足

- 内部ID（`id`）は外部に公開しない。識別子は `code` を使う
- 同じ元のURLを再登録しても、新しいコードを発行する
