"use client";
import { useEffect } from "react";
import * as Sentry from "@sentry/nextjs";

interface SentryInitProps {
  dsn: string;
}

export function SentryInit({ dsn }: SentryInitProps) {
  useEffect(() => {
    if (!dsn) return;
    Sentry.init({
      dsn,
      tracesSampleRate: 0,
      enabled: true,
    });
  }, [dsn]);
  return null;
}
