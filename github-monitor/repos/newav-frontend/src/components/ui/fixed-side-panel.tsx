import type { ReactNode } from "react";

interface FixedSidePanelProps {
  children: ReactNode;
  className?: string;
}

export function FixedSidePanel({
  children,
  className = "fixed w-[368px] h-[calc(100vh-56px)] bg-white border-r z-[5] overflow-hidden shadow-lg",
}: FixedSidePanelProps) {
  return <div className={className}>{children}</div>;
}
