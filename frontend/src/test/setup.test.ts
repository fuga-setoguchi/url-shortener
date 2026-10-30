import { http, HttpResponse } from 'msw'
import { expect, it } from 'vitest'
import { server } from './server.ts'

it('モックしていないAPIへのリクエストは失敗する', async () => {
  await expect(fetch('http://localhost/api/v1/unmocked')).rejects.toThrow()
})

it('server.use() で追加したハンドラーの応答を返す', async () => {
  server.use(
    http.get('http://localhost/api/v1/mocked', () =>
      HttpResponse.json({ ok: true }),
    ),
  )

  const response = await fetch('http://localhost/api/v1/mocked')

  expect(await response.json()).toEqual({ ok: true })
})
