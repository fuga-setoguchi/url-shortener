import { setupServer } from 'msw/node'

// テスト用のAPIモックサーバー。各テストで server.use() によりハンドラーを追加する
export const server = setupServer()
