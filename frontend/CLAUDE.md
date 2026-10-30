# フロントエンド（React + TypeScript）

ルートの `CLAUDE.md` のルールに加えて、以下に従う。

## 構成

- ディレクトリ構成は `docs/02_architecture.md` 3.4 に従う（画面は `src/features/short-urls/`）
- APIの呼び出しは openapi-fetch のクライアント（`src/api/client.ts`）と TanStack Query を使う。`fetch` を直接書かない
- APIの型は `src/api/schema.d.ts`（`npm run generate:api` で生成）を使い、手で書かない・編集しない
- `dangerouslySetInnerHTML` は使用禁止（ESLintでエラーになる。方式設計書 6.2）

## コーディング

- `any`・非nullアサーション（`!`）・型アサーション（`as`）で型エラーを回避しない。typescript-eslint の strict ルールに従う
- import はファイルの拡張子付きで書く（例: `./App.tsx`）

## テスト

- Vitest + React Testing Library で書き、テストファイルは対象と同じ場所に `*.test.tsx` で置く
- 要素は `getByRole`・`getByLabelText` で取得する。実装の内部状態は検証しない
- APIは MSW でモックする（`server.use()`）。モックしていないリクエストはテスト失敗になる
- テスト名の先頭にテストID（例: `SCR-01-T01`）を書く

## 完了前の確認

以下がすべて通ることを確認してからコミットする。

```
docker compose exec frontend npm run typecheck
docker compose exec frontend npm run lint
docker compose exec frontend npm run format:check
docker compose exec frontend npm test
```
