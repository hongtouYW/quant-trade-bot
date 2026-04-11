import React from 'react';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

interface IdentityCardProps {
  className?: string;
  onClose?: () => void;
  children?: React.ReactNode;
}

export function IdentityCard({ className, onClose, children }: IdentityCardProps) {
  return (
    <div
      className={cn(
        "relative w-[260px] h-[529px] rounded-[20px] overflow-hidden",
        "bg-cover bg-center bg-no-repeat",
        "shadow-lg",
        className
      )}
      style={{
        backgroundImage: `url('/id-card-bg.svg')`
      }}
    >
      {/* Title */}
      <div className="absolute top-[3px] left-[13px] w-[60px] h-[28px]">
        <span
          className="text-white text-[20px] leading-[1.4] tracking-[-0.02em] font-normal"
          style={{ fontFamily: 'YS HelloFont BangBangTi' }}
        >
          身份卡
        </span>
      </div>

      {/* Close Button */}
      {onClose && (
        <button
          onClick={onClose}
          className="absolute top-4 right-4 flex items-center justify-center w-[26px] h-[26px] bg-[#F9CDFF] rounded-md gap-2.5 p-1.5 hover:bg-[#F0BDFF] transition-colors"
        >
          <X className="w-3.5 h-3.5 text-white" />
        </button>
      )}

      {/* Content Area */}
      <div className="absolute top-[46px] left-[21px] w-[218px] flex flex-col items-center gap-5">
        {/* Profile Section */}
        <div className="w-full flex flex-col items-center gap-5">
          {/* Avatar and Status Frames */}
          <div className="w-full flex flex-col items-center gap-[11px]">
            {/* Avatar placeholder - this would be replaced with actual content */}
            <div className="w-full h-[60px] bg-gray-200 rounded-lg flex items-center justify-center">
              {/* Avatar content goes here */}
            </div>

            {/* Status Frame */}
            <div className="flex items-center gap-2 px-2 py-1 border-2 border-[#E126FC] rounded-[7.74px]">
              {/* Status indicators would go here */}
            </div>

            {/* Additional status frame placeholder */}
            <div className="w-full h-[60px] bg-gray-200 rounded-lg flex items-center justify-center">
              {/* Additional status content goes here */}
            </div>
          </div>

          {/* Information Section */}
          <div className="w-full flex flex-col gap-2.5">
            {/* Info row 1 */}
            <div className="w-full flex flex-col gap-[3px]">
              {/* Info content placeholder */}
            </div>

            {/* Info row 2 */}
            <div className="w-full flex flex-col gap-[3px]">
              {/* Info content placeholder */}
            </div>

            {/* Warning Text */}
            <div className="w-[218px]">
              <span className="text-[#F54336] text-[10px] font-medium leading-[1.4] text-left font-pingfang">
                *如果截图保存失败，请手动截图保存！
              </span>
            </div>
          </div>
        </div>

        {/* Custom Content */}
        {children}
      </div>
    </div>
  );
}

export default IdentityCard;