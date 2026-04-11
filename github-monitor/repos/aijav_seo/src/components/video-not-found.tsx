import { Button } from "@/components/ui/button.tsx";
import { AlertCircle, Home } from "lucide-react";
import { Link } from "react-router";

interface VideoNotFoundProps {
  message?: string;
  errorCode?: number;
}

export const VideoNotFound = ({
  message = "视频不存在",
  errorCode,
}: VideoNotFoundProps) => {
  return (
    <div className="flex flex-col items-center justify-center min-h-[400px] text-center space-y-6 p-8 text-foreground">
      <div className="flex items-center justify-center w-20 h-20 bg-red-100 dark:bg-red-500/20 rounded-full">
        <AlertCircle className="w-10 h-10 text-red-500 dark:text-red-400" />
      </div>

      <div className="space-y-2">
        <h2 className="text-2xl font-semibold text-foreground">{message}</h2>
        {errorCode && (
          <p className="text-sm text-muted-foreground">错误代码: {errorCode}</p>
        )}
        <p className="text-muted-foreground max-w-md">
          抱歉，您要查看的视频可能已被删除、移动或不存在。请检查链接是否正确，或尝试搜索其他内容。
        </p>
      </div>

      <div className="flex gap-4">
        <Button asChild className="bg-[#EC67FF] hover:bg-[#EC67FF]/90">
          <Link to="/">
            <Home className="w-4 h-4 mr-2" />
            返回首页
          </Link>
        </Button>
        {/*<Button variant="outline" asChild>*/}
        {/*  <Link to="/search">*/}
        {/*    <Search className="w-4 h-4 mr-2" />*/}
        {/*    搜索视频*/}
        {/*  </Link>*/}
        {/*</Button>*/}
      </div>
    </div>
  );
};
