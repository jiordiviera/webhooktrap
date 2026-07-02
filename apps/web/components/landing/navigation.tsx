"use client";

import Image from "next/image";
import Link from "next/link";
import { useCallback, useEffect, useId, useState } from "react";
import { IconMenu2, IconX } from "@tabler/icons-react";
import { cn } from "@workspace/ui/lib/utils";
import { AuthNav } from "@/app/components/auth-nav";
import { productName } from "@/lib/config";

const navLinks = [
  { name: "Features", href: "#features" },
  { name: "How it works", href: "#how-it-works" },
  { name: "Integrations", href: "#integrations" },
  { name: "Developers", href: "#developers" },
  { name: "Pricing", href: "#pricing" },
] as const;

export function Navigation() {
  const menuId = useId();
  const [isScrolled, setIsScrolled] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  const closeMobileMenu = useCallback(() => {
    setIsMobileMenuOpen(false);
  }, []);

  const toggleMobileMenu = useCallback(() => {
    setIsMobileMenuOpen((open) => !open);
  }, []);

  useEffect(() => {
    const handleScroll = () => setIsScrolled(window.scrollY > 20);
    window.addEventListener("scroll", handleScroll, { passive: true });
    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  useEffect(() => {
    if (!isMobileMenuOpen) return;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") closeMobileMenu();
    };

    const mediaQuery = window.matchMedia("(min-width: 768px)");
    const onViewportChange = () => {
      if (mediaQuery.matches) closeMobileMenu();
    };

    window.addEventListener("keydown", onKeyDown);
    mediaQuery.addEventListener("change", onViewportChange);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", onKeyDown);
      mediaQuery.removeEventListener("change", onViewportChange);
    };
  }, [isMobileMenuOpen, closeMobileMenu]);

  return (
    <header
      className={cn(
        "fixed z-50 transition-all duration-500",
        isScrolled ? "top-4 right-4 left-4" : "top-0 right-0 left-0",
      )}
    >
      <div
        className={cn(
          "fixed inset-0 z-40 md:hidden",
          isMobileMenuOpen ? "pointer-events-auto" : "pointer-events-none",
        )}
        aria-hidden={!isMobileMenuOpen}
      >
        <button
          type="button"
          className={cn(
            "absolute inset-0 bg-background/95 backdrop-blur-sm transition-opacity duration-500",
            isMobileMenuOpen ? "opacity-100" : "opacity-0",
          )}
          aria-label="Close menu"
          tabIndex={isMobileMenuOpen ? 0 : -1}
          onClick={closeMobileMenu}
        />

        <div
          id={menuId}
          className={cn(
            "relative z-10 flex h-full flex-col px-8 pt-28 pb-8 transition-opacity duration-500",
            isMobileMenuOpen ? "opacity-100" : "opacity-0",
          )}
        >
          <div className="flex flex-1 flex-col justify-center gap-8">
            {navLinks.map((link, i) => (
              <a
                key={link.name}
                href={link.href}
                onClick={closeMobileMenu}
                className={cn(
                  "font-heading text-5xl text-foreground transition-all duration-500 hover:text-primary",
                  isMobileMenuOpen
                    ? "translate-y-0 opacity-100"
                    : "translate-y-4 opacity-0",
                )}
                style={{
                  transitionDelay: isMobileMenuOpen ? `${i * 75}ms` : "0ms",
                }}
                tabIndex={isMobileMenuOpen ? 0 : -1}
              >
                {link.name}
              </a>
            ))}
          </div>

          <div
            className={cn(
              "flex flex-col gap-4 border-t border-border pt-8 transition-all duration-500",
              isMobileMenuOpen
                ? "translate-y-0 opacity-100"
                : "translate-y-4 opacity-0",
            )}
            style={{ transitionDelay: isMobileMenuOpen ? "300ms" : "0ms" }}
          >
            <AuthNav />
          </div>
        </div>
      </div>

      <nav
        className={cn(
          "relative z-50 mx-auto transition-all duration-500",
          isScrolled || isMobileMenuOpen
            ? "max-w-300 rounded-2xl border border-border/80 bg-background/90 shadow-[0_8px_32px_oklch(0.35_0.04_48/0.08)] backdrop-blur-xl"
            : "max-w-350 bg-transparent",
        )}
      >
        <div
          className={cn(
            "flex items-center justify-between px-6 transition-all duration-500 lg:px-8",
            isScrolled || isMobileMenuOpen ? "h-14" : "h-20",
          )}
        >
          <Link
            href="/"
            className="flex items-center gap-2.5"
            onClick={closeMobileMenu}
          >
            <Image
              src="/logo.png"
              alt={productName}
              width={120}
              height={32}
              className={cn(
                "h-auto w-auto transition-all duration-500",
                isScrolled || isMobileMenuOpen ? "max-h-7" : "max-h-8",
              )}
              priority
            />
          </Link>

          <div className="hidden items-center gap-10 md:flex">
            {navLinks.map((link) => (
              <a
                key={link.name}
                href={link.href}
                className="group relative text-sm text-foreground/70 transition-colors duration-300 hover:text-primary"
              >
                {link.name}
                <span className="absolute -bottom-1 left-0 h-px w-0 bg-primary transition-all duration-300 group-hover:w-full" />
              </a>
            ))}
          </div>

          <div className="hidden items-center gap-4 md:flex">
            <AuthNav />
          </div>

          <button
            type="button"
            onClick={toggleMobileMenu}
            className="relative z-50 rounded-lg p-2 md:hidden"
            aria-label={isMobileMenuOpen ? "Close menu" : "Open menu"}
            aria-expanded={isMobileMenuOpen}
            aria-controls={menuId}
          >
            {isMobileMenuOpen ? (
              <IconX className="size-6" />
            ) : (
              <IconMenu2 className="size-6" />
            )}
          </button>
        </div>
      </nav>
    </header>
  );
}
