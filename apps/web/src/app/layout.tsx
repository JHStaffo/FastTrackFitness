import type { Metadata } from "next";
import Link from "next/link";
import "./globals.css";

export const metadata: Metadata = {
  title: "Fast Track Fitness",
  description: "Fast Track EMS employee wellness program",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" className="h-full antialiased">
      <body className="min-h-full flex flex-col bg-zinc-50 text-zinc-900 font-sans">
        <header className="border-b border-zinc-200 bg-white">
          <nav className="mx-auto flex max-w-4xl items-center gap-6 px-4 py-3 text-sm">
            <span className="font-semibold">Fast Track Fitness</span>
            <Link href="/">My dashboard</Link>
            <Link href="/admin">Admin</Link>
          </nav>
        </header>
        <main className="mx-auto w-full max-w-4xl flex-1 px-4 py-6">{children}</main>
      </body>
    </html>
  );
}
