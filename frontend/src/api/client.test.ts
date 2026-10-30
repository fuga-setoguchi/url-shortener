import { http, HttpResponse } from 'msw'
import { expect, it } from 'vitest'
import { server } from '../test/server.ts'
import { apiClient } from './client.ts'

it('APIクライアントのリクエストはテストの MSW を経由する（実際の通信をしない）', async () => {
  server.use(
    http.get('/api/v1/short-urls', () =>
      HttpResponse.json({
        data: [],
        links: { first: null, last: null, prev: null, next: null },
        meta: {
          current_page: 1,
          from: null,
          last_page: 1,
          links: [],
          path: null,
          per_page: 20,
          to: null,
          total: 0,
        },
      }),
    ),
  )

  const { data, response } = await apiClient.GET('/short-urls')

  expect(response.status).toBe(200)
  expect(data?.meta.total).toBe(0)
})

it('APIクライアントは Accept: application/json を付けて /api/v1 を呼び出す', async () => {
  let request: Request | undefined
  server.use(
    http.post('/api/v1/short-urls', ({ request: received }) => {
      request = received
      return HttpResponse.json({}, { status: 422 })
    }),
  )

  await apiClient.POST('/short-urls', {
    body: { original_url: 'https://example.com' },
  })

  expect(request?.headers.get('Accept')).toBe('application/json')
  expect(new URL(request?.url ?? '').pathname).toBe('/api/v1/short-urls')
})
