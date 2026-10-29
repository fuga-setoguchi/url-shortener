# API-02 短縮URL一覧取得

作成日: 2026-10-29
ステータス: レビュー待ち
対応要件: FR-07, FR-08

## 1. 概要

登録済みの短縮URLをクリック数付きで、作成日時の新しい順に返す。

| 項目 | 内容 |
| --- | --- |
| メソッド | GET |
| パス | /api/short-urls |
| 認証 | なし |
| 処理クラス | ShortUrlController@index → ShortUrlResource::collection |

## 2. リクエスト

| クエリ | 型 | 必須 | 説明 |
| --- | --- | --- | --- |
| page | integer | × | ページ番号（既定1）。1ページ20件固定 |

## 3. 処理

1. `ShortUrl::withCount('clicks')->latest()->latest('id')->paginate(20)` で取得する（作成日時が同じ場合はIDの降順で並びを安定させる）
2. ShortUrlResource のコレクションとして返す

## 4. レスポンス

### 200 OK

Laravel標準のページネーション形式（`data` / `links` / `meta`）で返す。`data` の各要素は [API-01](API-01_create-short-url.md) のレスポンスと同じ構造。

```json
{
  "data": [
    {
      "code": "aB3xK9q",
      "short_url": "http://localhost:8000/aB3xK9q",
      "original_url": "https://example.com/very/long/path?query=1",
      "clicks_count": 12,
      "created_at": "2026-10-29T07:30:00+00:00"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 45, "from": 1, "to": 20, "path": "...", "links": [] }
}
```

- 0件の場合は `data: []`、`meta.total: 0` を返す
- 存在しないページ（`last_page` より大きい）は `data: []` の200を返す
- `page` に数値以外が渡された場合は1ページ目として扱う（Laravel標準の挙動）

### エラー

| ステータス | 条件 |
| --- | --- |
| 500 | 想定外のエラー |

## 5. 補足

- クリック記録は非同期のため、リダイレクト直後はクリック数に反映されていない場合がある（キュー処理後に反映）
