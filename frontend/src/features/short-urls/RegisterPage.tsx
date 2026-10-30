import { useEffect, useId, useState, type SubmitEvent } from 'react'
import { ApiError, type ShortUrl } from '../../api/client.ts'
import { useCreateShortUrl } from './hooks.ts'
import styles from './RegisterPage.module.css'

const MESSAGES = {
  rateLimited: 'リクエストが多すぎます。時間をおいて再度お試しください。',
  generic: 'エラーが発生しました。時間をおいて再度お試しください。',
} as const

function toErrorMessage(error: Error): string {
  if (error instanceof ApiError) {
    if (error.status === 422) {
      return error.fieldErrors?.original_url?.[0] ?? MESSAGES.generic
    }
    if (error.status === 429) {
      return MESSAGES.rateLimited
    }
  }
  return MESSAGES.generic
}

/**
 * SCR-01 URL登録画面
 */
export function RegisterPage() {
  const inputId = useId()
  const errorId = useId()
  const [originalUrl, setOriginalUrl] = useState('')
  const createShortUrl = useCreateShortUrl()

  const handleSubmit = (event: SubmitEvent<HTMLFormElement>) => {
    event.preventDefault()
    // 送信すると前回のエラー・結果は消え、送信中の状態になる
    createShortUrl.mutate(originalUrl, {
      onSuccess: () => {
        setOriginalUrl('')
      },
    })
  }

  return (
    <section className={styles.page}>
      <h1>URLを短縮する</h1>

      <form className={styles.form} onSubmit={handleSubmit}>
        <label htmlFor={inputId}>元のURL</label>
        <div className={styles.row}>
          <input
            id={inputId}
            className={styles.input}
            type="url"
            required
            maxLength={2048}
            placeholder="https://example.com/..."
            value={originalUrl}
            onChange={(event) => {
              setOriginalUrl(event.target.value)
            }}
            aria-invalid={createShortUrl.isError}
            aria-describedby={createShortUrl.isError ? errorId : undefined}
          />
          <button
            type="submit"
            className={styles.button}
            disabled={createShortUrl.isPending}
          >
            {createShortUrl.isPending ? '短縮中…' : '短縮'}
          </button>
        </div>
        {createShortUrl.isError && (
          <p id={errorId} className={styles.error} role="alert">
            {toErrorMessage(createShortUrl.error)}
          </p>
        )}
      </form>

      {createShortUrl.isSuccess && (
        // 新しい結果ごとにコピーの状態をリセットする
        <ShortUrlResult
          key={createShortUrl.data.code}
          shortUrl={createShortUrl.data}
        />
      )}
    </section>
  )
}

type CopyStatus = 'idle' | 'copied' | 'failed'

const COPY_MESSAGES: Record<CopyStatus, string> = {
  idle: '',
  copied: 'コピーしました',
  failed: 'コピーできませんでした',
}

function ShortUrlResult({ shortUrl }: { shortUrl: ShortUrl }) {
  const [copyStatus, setCopyStatus] = useState<CopyStatus>('idle')

  // 「コピーしました」は2秒後に消す
  useEffect(() => {
    if (copyStatus !== 'copied') {
      return
    }
    const timerId = setTimeout(() => {
      setCopyStatus('idle')
    }, 2000)
    return () => {
      clearTimeout(timerId)
    }
  }, [copyStatus])

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(shortUrl.short_url)
      setCopyStatus('copied')
    } catch {
      setCopyStatus('failed')
    }
  }

  return (
    <div className={styles.result}>
      <div className={styles.row}>
        <a
          className={styles.shortUrl}
          href={shortUrl.short_url}
          target="_blank"
          rel="noopener noreferrer"
        >
          {shortUrl.short_url}
        </a>
        <button
          type="button"
          className={styles.button}
          onClick={() => {
            void handleCopy()
          }}
        >
          コピー
        </button>
      </div>
      <p className={styles.status} role="status">
        {COPY_MESSAGES[copyStatus]}
      </p>
      <p className={styles.originalUrl}>元のURL: {shortUrl.original_url}</p>
    </div>
  )
}
