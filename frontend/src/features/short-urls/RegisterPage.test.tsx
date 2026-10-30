import { screen, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import { server } from '../../test/server.ts'
import { renderWithProviders } from '../../test/render.tsx'
import { RegisterPage } from './RegisterPage.tsx'

const ENDPOINT = '/api/v1/short-urls'

const createdShortUrl = {
  code: 'aB3xK9q',
  short_url: 'http://localhost:8000/aB3xK9q',
  original_url: 'https://example.com/very/long/path',
  clicks_count: 0,
  created_at: '2026-10-30T07:30:00+00:00',
}

function respondCreated() {
  server.use(
    http.post(ENDPOINT, () =>
      HttpResponse.json({ data: createdShortUrl }, { status: 201 }),
    ),
  )
}

function setup() {
  const view = renderWithProviders(<RegisterPage />)
  const input = screen.getByLabelText('元のURL')

  const submit = async (url: string) => {
    await view.user.clear(input)
    await view.user.type(input, url)
    await view.user.click(screen.getByRole('button', { name: '短縮' }))
  }

  return { ...view, input, submit }
}

describe('SCR-01 URL登録画面', () => {
  it('SCR-01-T01 初期表示では入力欄と短縮ボタンがあり、発行結果は表示しない', () => {
    setup()

    expect(screen.getByLabelText('元のURL')).toHaveAttribute('type', 'url')
    expect(screen.getByLabelText('元のURL')).toBeRequired()
    expect(screen.getByRole('button', { name: '短縮' })).toBeEnabled()
    expect(
      screen.queryByRole('button', { name: 'コピー' }),
    ).not.toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('SCR-01-T02 短縮すると短いURLのリンクと元のURLを表示し、入力欄を空にする', async () => {
    let requestBody: unknown
    server.use(
      http.post(ENDPOINT, async ({ request }) => {
        requestBody = await request.json()
        return HttpResponse.json({ data: createdShortUrl }, { status: 201 })
      }),
    )
    const { input, submit } = setup()

    await submit('https://example.com/very/long/path')

    const link = await screen.findByRole('link', {
      name: 'http://localhost:8000/aB3xK9q',
    })
    expect(link).toHaveAttribute('href', 'http://localhost:8000/aB3xK9q')
    expect(link).toHaveAttribute('target', '_blank')
    expect(link).toHaveAttribute('rel', 'noopener noreferrer')
    expect(
      screen.getByText('元のURL: https://example.com/very/long/path'),
    ).toBeInTheDocument()
    expect(input).toHaveValue('')
    expect(requestBody).toEqual({
      original_url: 'https://example.com/very/long/path',
    })
  })

  it('SCR-01-T03 送信中はボタンを無効化し「短縮中…」と表示する', async () => {
    let respond: () => void = () => undefined
    server.use(
      http.post(
        ENDPOINT,
        () =>
          new Promise<Response>((resolve) => {
            respond = () => {
              resolve(
                HttpResponse.json({ data: createdShortUrl }, { status: 201 }),
              )
            }
          }),
      ),
    )
    const { submit } = setup()

    await submit('https://example.com')

    const button = await screen.findByRole('button', { name: '短縮中…' })
    expect(button).toBeDisabled()

    respond()
    expect(await screen.findByRole('button', { name: '短縮' })).toBeEnabled()
  })

  it('SCR-01-T04 コピーを押すと短いURLをクリップボードにコピーし「コピーしました」と表示する', async () => {
    respondCreated()
    const { user, submit } = setup()
    const writeText = vi.spyOn(navigator.clipboard, 'writeText')

    await submit('https://example.com/very/long/path')
    await user.click(await screen.findByRole('button', { name: 'コピー' }))

    expect(writeText).toHaveBeenCalledWith('http://localhost:8000/aB3xK9q')
    expect(screen.getByRole('status')).toHaveTextContent('コピーしました')
  })

  it('SCR-01-T04 「コピーしました」は2秒後に消える', async () => {
    respondCreated()
    const { user, submit } = setup()

    await submit('https://example.com/very/long/path')
    await user.click(await screen.findByRole('button', { name: 'コピー' }))
    expect(screen.getByRole('status')).toHaveTextContent('コピーしました')

    await waitFor(
      () => {
        expect(screen.getByRole('status')).toHaveTextContent('')
      },
      { timeout: 3000 },
    )
  })

  it('SCR-01-T05 コピーに失敗すると「コピーできませんでした」と表示する', async () => {
    respondCreated()
    const { user, submit } = setup()
    vi.spyOn(navigator.clipboard, 'writeText').mockRejectedValue(
      new Error('denied'),
    )

    await submit('https://example.com/very/long/path')
    await user.click(await screen.findByRole('button', { name: 'コピー' }))

    expect(await screen.findByRole('status')).toHaveTextContent(
      'コピーできませんでした',
    )
  })

  it('SCR-01-T06 検証エラー（422）はAPIのメッセージを表示し、入力値を保持する', async () => {
    server.use(
      http.post(ENDPOINT, () =>
        HttpResponse.json(
          {
            message: 'ユーザー名やパスワードを含むURLは登録できません。',
            errors: {
              original_url: [
                'ユーザー名やパスワードを含むURLは登録できません。',
              ],
            },
          },
          { status: 422 },
        ),
      ),
    )
    const { input, submit } = setup()

    await submit('https://user:pass@example.com')

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'ユーザー名やパスワードを含むURLは登録できません。',
    )
    expect(input).toHaveValue('https://user:pass@example.com')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input).toHaveAccessibleDescription(
      'ユーザー名やパスワードを含むURLは登録できません。',
    )
  })

  it('SCR-01-T07 レート制限（429）のメッセージを表示する', async () => {
    server.use(
      http.post(ENDPOINT, () =>
        HttpResponse.json({ message: 'Too Many Attempts.' }, { status: 429 }),
      ),
    )
    const { submit } = setup()

    await submit('https://example.com')

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'リクエストが多すぎます。時間をおいて再度お試しください。',
    )
  })

  it.each([
    [
      '500',
      () => HttpResponse.json({ message: 'Server Error' }, { status: 500 }),
    ],
    ['通信エラー', () => HttpResponse.error()],
  ])(
    'SCR-01-T08 %s のときは汎用のエラーメッセージを表示する',
    async (_, resolver) => {
      server.use(http.post(ENDPOINT, resolver))
      const { submit } = setup()

      await submit('https://example.com')

      expect(await screen.findByRole('alert')).toHaveTextContent(
        'エラーが発生しました。時間をおいて再度お試しください。',
      )
    },
  )

  it('SCR-01-T09 エラーの後に再送信して成功すると、エラーを消して結果を表示する', async () => {
    // server.use() は後から追加したハンドラーが優先される。1回目だけ500を返す
    respondCreated()
    server.use(
      http.post(
        ENDPOINT,
        () => HttpResponse.json({ message: 'Server Error' }, { status: 500 }),
        { once: true },
      ),
    )
    const { submit } = setup()

    await submit('https://example.com/very/long/path')
    expect(await screen.findByRole('alert')).toBeInTheDocument()

    await submit('https://example.com/very/long/path')

    expect(
      await screen.findByRole('link', {
        name: 'http://localhost:8000/aB3xK9q',
      }),
    ).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('登録に成功すると一覧のキャッシュを無効化する', async () => {
    respondCreated()
    const { queryClient, submit } = setup()
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries')

    await submit('https://example.com/very/long/path')
    await screen.findByRole('link', { name: 'http://localhost:8000/aB3xK9q' })

    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['short-urls'] })
  })
})
