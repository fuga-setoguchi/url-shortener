# 自動テスト設計 共通方針

作成日: 2026-10-29
ステータス: 確定（2026-10-30）

## 1. テストの種類

| 種類 | ツール | 対象 | 配置 |
| --- | --- | --- | --- |
| 機能テスト | Pest | API-01〜03（HTTPリクエストからDBまで） | `backend/tests/Feature/` |
| 単体テスト | Pest | ShortCodeGenerator、CreateShortUrl、RecordClick | `backend/tests/Unit/`（DBを使うものは Feature） |
| コンポーネントテスト | Vitest + React Testing Library + MSW | SCR-01、SCR-02 | `frontend/src/**/*.test.tsx` |

## 2. 方針

- バックエンドはMySQLのテスト用DB（`testing`）を使い、`RefreshDatabase` で毎回初期化する
- テストデータはモデルファクトリ（`ShortUrlFactory`、`ClickFactory`）で作成する
- キューは `Queue::fake()` で投入を検証し、ジョブ自体の処理は別途ジョブを直接実行して検証する
- 時刻に依存するテストは `$this->freezeTime()` で固定する
- フロントエンドはMSWでAPIをモックし、利用者の操作（`@testing-library/user-event`）と画面の表示で検証する。実装の内部状態は検証しない
- 要素の取得はロール・ラベル（`getByRole`、`getByLabelText`）を優先し、アクセシビリティも同時に担保する

## 3. テストIDの付け方

`{設計書ID}-T{連番2桁}`（例: API-01-T01）。テストコードの `it()` の説明文の先頭にIDを書き、設計書と対応付ける。

## 4. 一覧

| 設計書 | 対象 |
| --- | --- |
| [API-01_test.md](API-01_test.md) | 短縮URL登録（+ ShortCodeGenerator、CreateShortUrl） |
| [API-02_test.md](API-02_test.md) | 短縮URL一覧取得 |
| [API-03_test.md](API-03_test.md) | リダイレクト（+ RecordClick） |
| [SCR-01_test.md](SCR-01_test.md) | URL登録画面 |
| [SCR-02_test.md](SCR-02_test.md) | URL一覧画面 |
