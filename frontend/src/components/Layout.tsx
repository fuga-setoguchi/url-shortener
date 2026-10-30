import { Link, NavLink, Outlet } from 'react-router'
import styles from './Layout.module.css'

/**
 * 全画面共通のレイアウト（ナビゲーション・フッター）
 */
export function Layout() {
  return (
    <div className={styles.layout}>
      <header className={styles.header}>
        <div className={styles.navBar}>
          <Link to="/" className={styles.brand}>
            URL Shortener
          </Link>
          <nav aria-label="メイン">
            <ul className={styles.nav}>
              <li>
                <NavLink to="/" end className={styles.navLink}>
                  登録
                </NavLink>
              </li>
              <li>
                <NavLink to="/urls" className={styles.navLink}>
                  一覧
                </NavLink>
              </li>
            </ul>
          </nav>
        </div>
        <p className={styles.subStrip}>長いURLを、共有しやすい短いURLに。</p>
      </header>
      <main className={styles.main}>
        <Outlet />
      </main>
      <footer className={styles.footer}>
        <small>URL Shortener — Laravel + React ポートフォリオ</small>
      </footer>
    </div>
  )
}
