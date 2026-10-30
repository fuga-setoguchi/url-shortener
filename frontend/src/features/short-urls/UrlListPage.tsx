import { Link, useSearchParams } from 'react-router'
import type { ShortUrl } from '../../api/client.ts'
import { useShortUrls } from './hooks.ts'
import styles from './UrlListPage.module.css'

const dateTimeFormat = new Intl.DateTimeFormat('ja-JP', {
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
})

/** 利用者のローカル時刻で YYYY/MM/DD HH:mm に整形する */
function formatDateTime(iso: string): string {
  return dateTimeFormat.format(new Date(iso))
}

/** URLクエリの page を解釈する。1以上の整数でなければ1ページ目とする */
function parsePage(value: string | null): number {
  return value !== null && /^[1-9]\d*$/.test(value) ? Number(value) : 1
}

/**
 * SCR-02 URL一覧画面
 */
export function UrlListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const page = parsePage(searchParams.get('page'))
  const shortUrls = useShortUrls(page)

  // ページ番号をURLクエリに持たせ、再読み込み・ブラウザの戻る/進むでも同じページを表示する
  const goToPage = (nextPage: number) => {
    setSearchParams({ page: String(nextPage) })
  }

  if (shortUrls.isPending) {
    return <p>読み込み中…</p>
  }

  if (shortUrls.isError) {
    return (
      <div className={styles.message}>
        <p role="alert">一覧を取得できませんでした</p>
        <button
          type="button"
          className={styles.button}
          onClick={() => {
            void shortUrls.refetch()
          }}
        >
          再試行
        </button>
      </div>
    )
  }

  const { data, meta } = shortUrls.data

  if (meta.total === 0) {
    return (
      <div className={styles.message}>
        <p>まだURLが登録されていません</p>
        <Link to="/">URLを登録する</Link>
      </div>
    )
  }

  return (
    <section className={styles.page}>
      <h1>登録済みURL一覧</h1>
      <p className={styles.total}>全{meta.total}件</p>

      <table className={styles.table}>
        <thead>
          <tr>
            <th scope="col">短いURL</th>
            <th scope="col">元のURL</th>
            <th scope="col" className={styles.number}>
              クリック数
            </th>
            <th scope="col">作成日時</th>
          </tr>
        </thead>
        <tbody>
          {data.map((shortUrl) => (
            <ShortUrlRow key={shortUrl.code} shortUrl={shortUrl} />
          ))}
        </tbody>
      </table>

      <nav className={styles.pagination} aria-label="ページ">
        <button
          type="button"
          className={styles.button}
          disabled={meta.current_page <= 1}
          onClick={() => {
            goToPage(meta.current_page - 1)
          }}
        >
          前へ
        </button>
        <span>
          {meta.current_page} / {meta.last_page}
        </span>
        <button
          type="button"
          className={styles.button}
          disabled={meta.current_page >= meta.last_page}
          onClick={() => {
            goToPage(meta.current_page + 1)
          }}
        >
          次へ
        </button>
      </nav>
    </section>
  )
}

function ShortUrlRow({ shortUrl }: { shortUrl: ShortUrl }) {
  return (
    <tr>
      <td>
        <a href={shortUrl.short_url} target="_blank" rel="noopener noreferrer">
          {shortUrl.short_url}
        </a>
      </td>
      {/* 元のURLは利用者の入力のため、リンクにせずテキストで表示する（方式設計書 6.2） */}
      <td className={styles.originalUrl} title={shortUrl.original_url}>
        {shortUrl.original_url}
      </td>
      <td className={styles.number}>{shortUrl.clicks_count}</td>
      <td>
        <time dateTime={shortUrl.created_at}>
          {formatDateTime(shortUrl.created_at)}
        </time>
      </td>
    </tr>
  )
}
