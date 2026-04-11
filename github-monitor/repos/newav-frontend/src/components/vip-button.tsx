import * as React from "react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

type BaseButtonProps = React.ComponentPropsWithoutRef<typeof Button>;

interface VipButtonProps
  extends Omit<BaseButtonProps, "variant" | "size" | "children"> {
  children?: React.ReactNode;
  /**
   * Optional small label rendered above the primary call-to-action.
   * Defaults to "Schedule".
   */
  tagline?: React.ReactNode;
  /**
   * Optional decorative overlay that will be blended on top of the gradient.
   * Provide a relative URL from the public directory or an imported asset.
   */
  sheenImage?: string;
}

const gradientBackground =
  "radial-gradient(99.75% 180.47% at 0% 0.25%, #FFF9F7 0%, #F5AC9C 50.01%, #FFCBC0 100%)";

const VipButton = React.forwardRef<HTMLButtonElement, VipButtonProps>(
  ({ className, children, sheenImage, style, ...props }, ref) => {
    const backgroundImage = sheenImage
      ? `url(${sheenImage}), ${gradientBackground}`
      : gradientBackground;

    const combinedStyle: React.CSSProperties = {
      backgroundImage,
      backgroundRepeat: "no-repeat",
      backgroundSize: "cover",
      backgroundPosition: "center",
      ...(sheenImage ? { backgroundBlendMode: "soft-light, normal" } : {}),
      ...style,
    };

    return (
      <Button
        ref={ref}
        variant="vip"
        size="vip"
        className={cn(
          "vip-button overflow-hidden font-semibold ring-3 ring-[#FFD6CE] text-[#674617]",
          "focus-visible:ring-offset-2",
          className,
        )}
        style={combinedStyle}
        {...props}
      >
        {children}
      </Button>
    );
  },
);

VipButton.displayName = "VipButton";

export { VipButton };
