import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import type { ShortUrl } from '../../api/client.ts'
import { renderWithProviders } from '../../test/render.tsx'
import { server } from '../../test/server.ts'
import { UrlListPage } from './UrlListPage.tsx'

const ENDPOINT = '/api/v1/short-urls'

function shortUrl(overrides: Partial<ShortUrl> = {}): ShortUrl {
  return {
    code: 'aB3xK9q',
    short_url: 'http://localhost:8000/aB3xK9q',
    original_url: 'https://example.com/very/long/path?query=1',
    clicks_count: 12,
    created_at: '2026-10-30T07:30:00+00:00',
    ...overrides,
  }
}

function listResponse(
  data: ShortUrl[],
  { page = 1, lastPage = 1, total = data.length } = {},
) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: page,
      from: data.length > 0 ? 1 : null,
      last_page: lastPage,
      links: [],
      path: null,
      per_page: 20,
      to: data.length > 0 ? data.length : null,
      total,
    },
  }
}

function pageItem(page: number): ShortUrl {
  const code = `Page${String(page)}xx`
  return shortUrl({ code, short_url: `http://localhost:8000/${code}` })
}

/** 2ページ分のデータを返し、呼び出された page を記録する */
function respondTwoPages() {
  const requestedPages: (string | null)[] = []
  server.use(
    http.get(ENDPOINT, ({ request }) => {
      const page = new URL(request.url).searchParams.get('page')
      requestedPages.push(page)
      return page === '2'
        ? HttpResponse.json(
            listResponse([pageItem(2)], {
              page: 2,
              lastPage: 2,
              total: 21,
            }),
          )
        : HttpResponse.json(
            listResponse([pageItem(1)], {
              page: 1,
              lastPage: 2,
              total: 21,
            }),
          )
    }),
  )
  return requestedPages
}

describe('SCR-02 URL一覧画面', () => {
  it('SCR-02-T01 応答前は「読み込み中…」を表示する', () => {
    server.use(http.get(ENDPOINT, () => new Promise<Response>(() => undefined)))

    renderWithProviders(<UrlListPage />, { route: '/urls' })

    expect(screen.getByText('読み込み中…')).toBeInTheDocument()
  })

  it('SCR-02-T02 一覧の各項目と件数を表示する', async () => {
    server.use(
      http.get(ENDPOINT, () =>
        HttpResponse.json(
          listResponse([
            shortUrl(),
            shortUrl({
              code: 'Zz9Zz9Z',
              short_url: 'http://localhost:8000/Zz9Zz9Z',
              original_url: 'https://example.org/',
              clicks_count: 0,
              created_at: '2026-10-29T15:05:00+00:00',
            }),
          ]),
        ),
      ),
    )

    renderWithProviders(<UrlListPage />, { route: '/urls' })

    expect(await screen.findByText('全2件')).toBeInTheDocument()
    const rows = within(screen.getByRole('table')).getAllByRole('row')
    expect(rows).toHaveLength(3) // 見出し行 + 2件

    const first = within(screen.getByRole('row', { name: /aB3xK9q/ }))
    const link = first.getByRole('link', {
      name: 'http://localhost:8000/aB3xK9q',
    })
    expect(link).toHaveAttribute('href', 'http://localhost:8000/aB3xK9q')
    expect(link).toHaveAttribute('target', '_blank')
    expect(link).toHaveAttribute('rel', 'noopener noreferrer')
    expect(
      first.getByText('https://example.com/very/long/path?query=1'),
    ).toHaveAttribute('title', 'https://example.com/very/long/path?query=1')
    expect(first.getByText('12')).toBeInTheDocument()
    // 2026-10-30T07:30:00Z を日本時間（テストの TZ）で表示する
    expect(first.getByText('2026/10/30 16:30')).toBeInTheDocument()

    const second = within(screen.getByRole('row', { name: /Zz9Zz9Z/ }))
    expect(second.getByText('0')).toBeInTheDocument()
    expect(second.getByText('2026/10/30 00:05')).toBeInTheDocument()
  })

  it('SCR-02-T03 0件のときは案内と登録画面へのリンクを表示する', async () => {
    server.use(http.get(ENDPOINT, () => HttpResponse.json(listResponse([]))))

    renderWithProviders(<UrlListPage />, { route: '/urls' })

    expect(
      await screen.findByText('まだURLが登録されていません'),
    ).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'URLを登録する' })).toHaveAttribute(
      'href',
      '/',
    )
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('SCR-02-T04 1ページ目では「前へ」を無効、「次へ」を有効にする', async () => {
    respondTwoPages()

    renderWithProviders(<UrlListPage />, { route: '/urls' })

    expect(await screen.findByText('1 / 2')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: '前へ' })).toBeDisabled()
    expect(screen.getByRole('button', { name: '次へ' })).toBeEnabled()
  })

  it('SCR-02-T05 「次へ」を押すと2ページ目を取得して表示する', async () => {
    const requestedPages = respondTwoPages()
    const { user } = renderWithProviders(<UrlListPage />, { route: '/urls' })
    await screen.findByText('1 / 2')

    await user.click(screen.getByRole('button', { name: '次へ' }))

    expect(await screen.findByText('2 / 2')).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'http://localhost:8000/Page2xx' }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('link', { name: 'http://localhost:8000/Page1xx' }),
    ).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: '次へ' })).toBeDisabled()
    expect(screen.getByRole('button', { name: '前へ' })).toBeEnabled()
    expect(requestedPages).toEqual(['1', '2'])
  })

  it('SCR-02-T06 /urls?page=2 で開くと2ページ目を取得する', async () => {
    const requestedPages = respondTwoPages()

    renderWithProviders(<UrlListPage />, { route: '/urls?page=2' })

    expect(await screen.findByText('2 / 2')).toBeInTheDocument()
    expect(requestedPages).toEqual(['2'])
  })

  it.each(['abc', '0', '-1', '1.5'])(
    'SCR-02-T07 /urls?page=%s で開くと1ページ目を取得する',
    async (page) => {
      const requestedPages = respondTwoPages()

      renderWithProviders(<UrlListPage />, { route: `/urls?page=${page}` })

      expect(await screen.findByText('1 / 2')).toBeInTheDocument()
      expect(requestedPages).toEqual(['1'])
    },
  )

  it('SCR-02-T08 取得に失敗したらメッセージと「再試行」ボタンを表示する', async () => {
    server.use(
      http.get(ENDPOINT, () =>
        HttpResponse.json({ message: 'Server Error' }, { status: 500 }),
      ),
    )

    renderWithProviders(<UrlListPage />, { route: '/urls' })

    expect(await screen.findByRole('alert')).toHaveTextContent(
      '一覧を取得できませんでした',
    )
    expect(screen.getByRole('button', { name: '再試行' })).toBeInTheDocument()
  })

  it('SCR-02-T09 「再試行」を押して成功すると一覧を表示する', async () => {
    // server.use() は後から追加したハンドラーが優先される。1回目だけ500を返す
    server.use(
      http.get(ENDPOINT, () => HttpResponse.json(listResponse([shortUrl()]))),
    )
    server.use(
      http.get(
        ENDPOINT,
        () => HttpResponse.json({ message: 'Server Error' }, { status: 500 }),
        { once: true },
      ),
    )
    const { user } = renderWithProviders(<UrlListPage />, { route: '/urls' })

    await user.click(await screen.findByRole('button', { name: '再試行' }))

    expect(await screen.findByText('全1件')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('SCR-02-T10 元のURLはリンクにせず、テキストとして表示する', async () => {
    server.use(
      http.get(ENDPOINT, () =>
        HttpResponse.json(
          listResponse([shortUrl({ original_url: 'https://example.com/x' })]),
        ),
      ),
    )

    renderWithProviders(<UrlListPage />, { route: '/urls' })

    expect(await screen.findByText('https://example.com/x')).toBeInTheDocument()
    expect(
      screen.queryByRole('link', { name: 'https://example.com/x' }),
    ).not.toBeInTheDocument()
    expect(screen.getAllByRole('link')).toHaveLength(1) // 短いURLのリンクだけ
  })
})
