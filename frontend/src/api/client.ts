import createClient from 'openapi-fetch'
import type { components, paths } from './schema'

export type ShortUrl = components['schemas']['ShortUrlResource']

// Vite のプロキシ経由で同一オリジンの API を呼び出す（方式設計書 3.4）
export const apiClient = createClient<paths>({
  baseUrl: new URL('/api/v1', window.location.origin).toString(),
  headers: { Accept: 'application/json' },
  // fetch は呼び出しのたびに参照する。作成時の fetch を保持すると、テストで MSW が
  // 差し替えた fetch が使われず、モックを素通りして実際の通信が発生するため
  fetch: (request) => globalThis.fetch(request),
})

/**
 * API がエラー（2xx 以外）を返したことを表す。
 */
export class ApiError extends Error {
  readonly status: number
  /** 422 のときだけ設定される、項目ごとの検証エラーメッセージ */
  readonly fieldErrors: Record<string, string[]> | undefined

  constructor(status: number, fieldErrors?: Record<string, string[]>) {
    super(`API request failed with status ${String(status)}`)
    this.name = 'ApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}
