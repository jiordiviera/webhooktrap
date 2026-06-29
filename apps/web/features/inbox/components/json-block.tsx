"use client";

import JsonView from "@uiw/react-json-view";
import { lightTheme } from "@uiw/react-json-view/light";
import { darkTheme } from "@uiw/react-json-view/dark";
import { useTheme } from "next-themes";
import { useEffect, useState } from "react";

interface JsonBlockProps {
  value: object | unknown[];
  className?: string;
}

export function JsonBlock({ value, className }: JsonBlockProps) {
  const { resolvedTheme } = useTheme();
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  // Cast nécessaire à cause d'une incompatibilité de types entre la lib et React
  const themeStyle = (
    mounted && resolvedTheme === "dark" ? darkTheme : lightTheme
  ) as React.CSSProperties;

  return (
    <div className={className}>
      <JsonView
        className="font-mono!"
        value={value}
        style={themeStyle}
        collapsed={1}
        displayDataTypes={false}
        enableClipboard
        highlightUpdates
      />
    </div>
  );
}
