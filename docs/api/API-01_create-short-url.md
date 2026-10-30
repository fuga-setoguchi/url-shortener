# API-01 短縮URL登録

作成日: 2026-10-29
ステータス: 確定（2026-10-30）
対応要件: FR-01, FR-02, NFR-03, NFR-04, NFR-05

## 1. 概要

元のURLを受け取り、短縮コードを発行して保存し、短いURLを返す。

| 項目 | 内容 |
| --- | --- |
| メソッド | POST |
| パス | /api/v1/short-urls |
| 認証 | なし |
| レート制限 | 10回/分/IP（API全体の60回/分/IPに加えて。超過時429） |
| 処理クラス | ShortUrlController@store → StoreShortUrlRequest → CreateShortUrl → ShortUrlResource |

## 2. リクエスト

ヘッダー: `Content-Type: application/json`、`Accept: application/json`

| 項目 | 型 | 必須 | 検証規則 |
| --- | --- | --- | --- |
| original_url | string | ○ | `required`、`string`、`max:2048`、`url:http,https`、`SafeRedirectUrl`（下記） |

`SafeRedirectUrl` で拒否するもの（理由は [方式設計書 6.1](../02_architecture.md)）:

- 認証情報（userinfo）を含むURL（例: `https://user:pass@example.com`）
- ホスト（ポート含む）が `SHORT_URL_BASE` と同じURL（自サービスの短縮URL）
- 改行・タブ・空白などの制御文字・空白を含むURL

上記以外の項目（`code` など）が送られても無視する（`$request->validated()` の値のみ使用）。

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
