import { Link, NavLink, Outlet } from 'react-router'
import styles from './Layout.module.css'

/**
 * 全画面共通のレイアウト（ヘッダーと画面の切り替えリンク）
 */
export function Layout() {
  return (
    <div className={styles.layout}>
      <header className={styles.header}>
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
          </ul>
        </nav>
      </header>
      <main className={styles.main}>
        <Outlet />
      </main>
    </div>
  )
}
