import { render, screen } from '@testing-library/react'
import { expect, it } from 'vitest'
import { App } from './App.tsx'

it('アプリ名の見出しを表示する', () => {
  render(<App />)

  expect(
    screen.getByRole('heading', { name: 'URL Shortener' }),
  ).toBeInTheDocument()
})
