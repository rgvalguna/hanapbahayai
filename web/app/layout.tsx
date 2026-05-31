import type { Metadata, Viewport } from "next"
import "./globals.css"
import { Providers } from "@/components/providers"

export const metadata: Metadata = {
  title: {
    template: "%s | HanapBahay AI",
    default: "HanapBahay AI — Find your ideal home in the Philippines",
  },
  description:
    "AI-powered real estate advisory for middle-class Filipino families. Affordability-first recommendations with explainable scores and financial risk warnings.",
  keywords: [
    "Philippines real estate",
    "bahay",
    "condo for sale Philippines",
    "Pag-IBIG housing loan",
    "Filipino home buyer",
    "AI real estate advisor",
  ],
  openGraph: {
    type: "website",
    locale: "en_PH",
    url: "https://hanapbahay.ph",
    siteName: "HanapBahay AI",
    images: [{ url: "/og-default.jpg", width: 1200, height: 630 }],
  },
  twitter: { card: "summary_large_image" },
  robots: { index: true, follow: true },
  manifest: "/manifest.webmanifest",
  icons: {
    icon: "/icon.svg",
    apple: "/apple-touch-icon.png",
  },
}

export const viewport: Viewport = {
  themeColor: "#3d8b74",
  width: "device-width",
  initialScale: 1,
  maximumScale: 5,
}

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en-PH" suppressHydrationWarning>
      <body>
        <Providers>{children}</Providers>
      </body>
    </html>
  )
}
