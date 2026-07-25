import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";

const geistSans = Geist({ variable: "--font-geist-sans", subsets: ["latin"] });
const geistMono = Geist_Mono({ variable: "--font-geist-mono", subsets: ["latin"] });

export const metadata: Metadata = {
  title: "AI Planning Studio — dari ide ke prompt siap-pakai",
  description:
    "Ubah satu ide jadi dokumentasi & prompt lengkap untuk AI coding agent. Web & Mobile. Untuk solo developer.",
};

// Set tema sebelum paint (hindari flash)
const themeScript = `try{var t=localStorage.getItem('theme');if(t==='light')document.documentElement.setAttribute('data-theme','light');}catch(e){}`;

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="id" className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}>
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeScript }} />
      </head>
      <body className="bg-aurora min-h-full flex flex-col">{children}</body>
    </html>
  );
}
