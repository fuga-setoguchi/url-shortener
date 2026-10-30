import { render, screen } from '@testing-library/react'
import { expect, it } from 'vitest'
import { App } from './App.tsx'

it('トップページ（/）にURL登録画面を表示する', () => {
  render(<App />)

  expect(
    screen.getByRole('link', { name: 'URL Shortener' }),
  ).toBeInTheDocument()
  expect(
    screen.getByRole('heading', { name: 'URLを短縮する' }),
  ).toBeInTheDocument()
})
