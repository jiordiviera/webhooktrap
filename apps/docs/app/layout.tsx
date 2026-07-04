import { RootProvider } from 'fumadocs-ui/provider/next';
import './global.css';
import { fontClasses } from '@workspace/ui/lib/fonts';
import "@workspace/ui/globals.css";

export default function Layout({ children }: LayoutProps<'/'>) {
  return (
    <html lang="en" className={fontClasses} suppressHydrationWarning>
      <body className="flex flex-col min-h-screen">
        <RootProvider>{children}</RootProvider>
      </body>
    </html>
  );
}
