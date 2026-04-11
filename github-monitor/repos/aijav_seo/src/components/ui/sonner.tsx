import { useTheme } from "next-themes";
import { Toaster as Sonner, type ToasterProps } from "sonner";

const Toaster = ({ ...props }: ToasterProps) => {
  const { theme = "system" } = useTheme();

  return (
    <Sonner
      theme={theme as ToasterProps["theme"]}
      className="toaster group"
      position="bottom-center"
      toastOptions={{
        style: {
          background: "#EC67FF",
          border: "1px solid #EC67FF",
          color: "white",
          borderRadius: "16px",
          fontSize: "16px",
          fontWeight: "600",
          padding: "12px 20px",
          minHeight: "48px",
          boxShadow: "0 8px 16px rgba(236, 103, 255, 0.3)",
        },
      }}
      style={
        {
          "--normal-bg": "#EC67FF",
          "--normal-text": "white",
          "--normal-border": "#EC67FF",
          "--success-bg": "#EC67FF",
          "--success-text": "white",
          "--success-border": "#EC67FF",
          "--error-bg": "#EC67FF",
          "--error-text": "white",
          "--error-border": "#EC67FF",
          "--info-bg": "#EC67FF",
          "--info-text": "white",
          "--info-border": "#EC67FF",
          "--warning-bg": "#EC67FF",
          "--warning-text": "white",
          "--warning-border": "#EC67FF",
        } as React.CSSProperties
      }
      {...props}
    />
  );
};

export { Toaster };
