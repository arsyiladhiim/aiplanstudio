import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";
import { SentryInit } from "./sentry-init-client";

const geistSans = Geist({ variable: "--font-geist-sans", subsets: ["latin"] });
const geistMono = Geist_Mono({ variable: "--font-geist-mono", subsets: ["latin"] });

export const metadata: Metadata = {
  title: "AI Planning Studio — dari ide ke prompt siap-pakai",
  description:
    "Ubah satu ide jadi dokumentasi & prompt lengkap untuk AI coding agent. Web & Mobile. Untuk solo developer.",
};

// CP-17.L1: theme script reads localStorage first, falls back to prefers-color-scheme.
// Runs synchronously before React hydration to prevent FOUC.
const themeScript = `try{var t=localStorage.getItem('theme');if(t==null&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)t='dark';if(t==='dark')document.documentElement.setAttribute('data-theme','dark');else document.documentElement.removeAttribute('data-theme');}catch(e){}`;

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="id" className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}>
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeScript }} />
      </head>
      <body className="bg-aurora min-h-full flex flex-col">
        <SentryInit dsn={process.env.NEXT_PUBLIC_SENTRY_DSN || ""} />
        {children}
      </body>
    </html>
  );
}
