import { useState } from 'react';
import { IdentityCard } from './identity-card';
import { IdentityCardSkeleton } from './skeletons/IdentityCardSkeleton';
import { Button } from './ui/button';

/**
 * Example usage of the IdentityCard component
 * This demonstrates how to use the component with loading states
 */
export function IdentityCardExample() {
  const [isLoading, setIsLoading] = useState(false);
  const [showCard, setShowCard] = useState(false);

  const handleShowCard = () => {
    setIsLoading(true);
    setShowCard(true);
    
    // Simulate loading delay
    setTimeout(() => {
      setIsLoading(false);
    }, 2000);
  };

  const handleCloseCard = () => {
    setShowCard(false);
    setIsLoading(false);
  };

  return (
    <div className="p-8 space-y-4">
      <div className="space-y-2">
        <h2 className="text-2xl font-bold text-foreground">身份卡组件示例</h2>
        <p className="text-muted-foreground">
          基于 Figma 设计创建的身份卡组件，支持加载状态和关闭功能
        </p>
      </div>
      
      <div className="flex gap-4">
        <Button onClick={handleShowCard} disabled={showCard}>
          显示身份卡
        </Button>
        
        {showCard && (
          <Button variant="outline" onClick={handleCloseCard}>
            隐藏身份卡
          </Button>
        )}
      </div>
      
      {showCard && (
        <div className="flex justify-center">
          {isLoading ? (
            <IdentityCardSkeleton />
          ) : (
            <IdentityCard onClose={handleCloseCard}>
              {/* Custom content can be added here */}
              <div className="text-center text-sm text-muted-foreground mt-4">
                <p>自定义内容区域</p>
                <p>可以在这里添加用户信息、状态等</p>
              </div>
            </IdentityCard>
          )}
        </div>
      )}
    </div>
  );
}

export default IdentityCardExample;