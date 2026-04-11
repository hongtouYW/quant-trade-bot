import { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Copy, Share2, Check } from "lucide-react";
import { useTranslation } from "react-i18next";

interface ShareDialogProps {
  videoUrl?: string;
  children?: React.ReactNode;
}

export function ShareDialog({ videoUrl, children }: ShareDialogProps) {
  const { t } = useTranslation();
  const [copied, setCopied] = useState(false);

  // Generate the share URL (using current page URL as default)
  const shareUrl = videoUrl || window.location.href;

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(shareUrl);
      setCopied(true);
      setTimeout(() => setCopied(false), 2500);
    } catch (err) {
      console.error("Failed to copy:", err);
    }
  };

  return (
    <Dialog>
      <DialogTrigger asChild>
        {children || (
          <Button
            className="rounded-full font-semibold size-9 sm:w-fit px-2! sm:h-9 sm:px-4! bg-[#F4A8FF]/20 text-[#EC67FF] border-[#EC67FF] hover:text-[#EC67FF]"
            variant="outline"
          >
            <Share2 />
            <span className="hidden sm:inline">{t("video_player.share")}</span>
          </Button>
        )}
      </DialogTrigger>
      <DialogContent className="sm:max-w-md max-w-[90vw] rounded-lg">
        <DialogHeader>
          <DialogTitle className="text-[22px] font-semibold">
            复製分享
          </DialogTitle>
        </DialogHeader>
        <div className="flex flex-col gap-4 mt-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="share-url" className="sr-only">
              Share URL
            </Label>
            <div className="flex gap-2">
              <Input
                id="share-url"
                value={shareUrl}
                readOnly
                className="flex-1 text-sm "
                onClick={(e) => e.currentTarget.select()}
              />
              <Button
                className=" bg-[#E126FC] hover:bg-[#EC67FF]/90 text-white font-semibold"
                onClick={handleCopy}
              >
                {copied ? (
                  <Check className="w-4 h-4" />
                ) : (
                  <Copy className="w-4 h-4" />
                )}
                {copied ? "已複製!" : "複製分享"}
              </Button>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
