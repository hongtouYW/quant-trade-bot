import { useState, useRef, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";

interface CardData {
  id: number;
  title: string;
  subtitle: string;
  image: string;
  description: string;
}

const cards: CardData[] = [
  {
    id: 1,
    title: "Mountain Adventure",
    subtitle: "Explore the peaks",
    image:
      "https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&h=300&fit=crop",
    description:
      "Discover breathtaking mountain trails and experience nature at its finest. Perfect for hiking enthusiasts and adventure seekers looking for their next challenge.",
  },
  {
    id: 2,
    title: "Ocean Waves",
    subtitle: "Ride the tide",
    image:
      "https://images.unsplash.com/photo-1505142468610-359e7d316be0?w=400&h=300&fit=crop",
    description:
      "Experience the calming rhythm of ocean waves and pristine beaches. Ideal for surfing, swimming, or simply relaxing by the shore with stunning sunset views.n waves and pristine beaches. Ideal for surfing, swimming, or simply relaxing by the shore with stunning sunset views.n waves and pristine beaches. Ideal for surfing, swimming, or simply relaxing by the shore with stunning sunset views.n waves and pristine beaches. Ideal for surfing, swimming, or simply relaxing by the shore with stunning sunset views.n waves and pristine beaches. Ideal for surfing, swimming, or simply relaxing by the shore with stunning sunset views.",
  },
  {
    id: 3,
    title: "City Lights",
    subtitle: "Urban exploration",
    image:
      "https://images.unsplash.com/photo-1480714378408-67cf0d13bc1b?w=400&h=300&fit=crop",
    description:
      "Immerse yourself in the vibrant energy of city life. Explore modern architecture, bustling streets, and discover hidden gems in the urban landscape.",
  },
];

const CARD_HEADER_HEIGHT = 128; // h-32
const STACK_GAP = 16; // gap between stacked cards

export default function StackedAccordionDemo() {
  const [activeCard, setActiveCard] = useState<number | null>(null);
  const [expandedHeight, setExpandedHeight] = useState(0);

  const handleToggle = (index: number) => {
    setActiveCard((prev) => {
      // Reset height when switching cards or closing
      setExpandedHeight(0);
      return prev === index ? null : index;
    });
  };

  // Callback to receive the actual expanded height from CardItem
  const handleHeightChange = (height: number) => {
    setExpandedHeight(height);
  };

  // Calculate dynamic top position based on active state
  const getCardTop = (index: number): number => {
    const baseTop = index * (CARD_HEADER_HEIGHT + STACK_GAP);

    if (activeCard === null) {
      return baseTop;
    }

    const gap = 16; // Gap between cards when expanded

    // CASE 1: Top card active
    if (activeCard === 0) {
      if (index === 0) return baseTop;
      // Cards below move down based on expanded height
      if (index === 1) return CARD_HEADER_HEIGHT + expandedHeight + gap + 20;
      if (index === 2)
        return CARD_HEADER_HEIGHT + expandedHeight + gap + STACK_GAP + 40;
    }

    // CASE 2: Middle card active
    if (activeCard === 1) {
      if (index === 0) return baseTop; // Top card stays
      if (index === 1) return baseTop + 8; // Middle card slight offset
      // Card below moves down based on middle card's expanded height
      if (index === 2) return baseTop + 8 + expandedHeight + gap;
    }

    // CASE 3: Bottom card active
    if (activeCard === 2) {
      if (index === 0) return baseTop; // Card 0 stays
      if (index === 1) return baseTop - 70; // Card 1 moves up above card 0
      return baseTop - 140; // Card 2 moves up
    }

    return baseTop;
  };

  // Compute scale and zIndex only (layout handles position)
  const getCardTransform = (
    index: number,
  ): { scale: number; zIndex: number; opacity?: number } => {
    const total = cards.length;

    if (activeCard === null) {
      return {
        scale: 1,
        zIndex: total - index,
      };
    }

    // CASE 1: Top card active
    if (activeCard === 0) {
      if (index === 0) return { scale: 1, zIndex: 10 };
      return {
        scale: 0.95,
        zIndex: total - index,
        opacity: index === 2 ? 0.5 : 0.95,
      };
    }

    // CASE 2: Middle card active
    if (activeCard === 1) {
      if (index === 0) return { scale: 0.95, zIndex: 0 };
      if (index === 1) return { scale: 1, zIndex: 10 };
      return { scale: 0.95, zIndex: total - index };
    }

    // CASE 3: Bottom card active
    if (activeCard === 2) {
      if (index === 0) return { scale: 1, zIndex: 5 };
      if (index === 1) return { scale: 1, zIndex: 6 };
      if (index === 2) return { scale: 1, zIndex: 10 };
    }

    return { scale: 1, zIndex: index };
  };

  return (
    <div className="min-h-screen bg-gradient-to-b from-gray-900 to-gray-800 p-4 pb-20">
      <div
        className="max-w-md mx-auto pt-8 relative"
        style={{ height: cards.length * CARD_HEADER_HEIGHT + 600 }}
      >
        {cards.map((card, index) => {
          const isActive = activeCard === index;
          const transform = getCardTransform(index);
          const top = getCardTop(index);

          return (
            <motion.div
              key={card.id}
              layout
              animate={transform}
              transition={{
                layout: { type: "spring", stiffness: 300, damping: 30 },
                scale: { type: "spring", stiffness: 300, damping: 30 },
                opacity: { duration: 0.2 },
              }}
              className="absolute w-full"
              style={{
                top: `${top}px`,
              }}
            >
              <CardItem
                card={card}
                isActive={isActive}
                onClick={() => handleToggle(index)}
                onHeightChange={(height) => {
                  // Only accept height from the currently active card
                  if (isActive) {
                    handleHeightChange(height);
                  }
                }}
              />
            </motion.div>
          );
        })}
      </div>
    </div>
  );
}

interface CardItemProps {
  card: CardData;
  isActive: boolean;
  onClick: () => void;
  onHeightChange: (height: number) => void;
}

function CardItem({ card, isActive, onClick, onHeightChange }: CardItemProps) {
  const contentRef = useRef<HTMLDivElement>(null);
  const [contentHeight, setContentHeight] = useState(0);

  useEffect(() => {
    if (contentRef.current) {
      const height = contentRef.current.scrollHeight;
      setContentHeight(height);
      // Report the height back to parent (only when expanded)
      onHeightChange(isActive ? height : 0);
    }
  }, [isActive, onHeightChange]);

  return (
    <div
      role="button"
      tabIndex={0}
      onClick={onClick}
      onKeyDown={(e) => e.key === "Enter" && onClick()}
      className={`bg-gray-800 rounded-2xl overflow-hidden cursor-pointer shadow-xl will-change-transform focus:outline-none transition-all ${
        isActive ? "ring-2 ring-purple-500" : ""
      }`}
    >
      {/* Card header */}
      <div className="relative h-32 overflow-hidden">
        <img
          src={card.image}
          alt={card.title}
          className="w-full h-full object-cover"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-gray-900/90 to-transparent" />
        <div className="absolute bottom-0 left-0 right-0 p-4">
          <h3 className="text-white font-bold text-lg">{card.title}</h3>
          <p className="text-gray-300 text-sm">{card.subtitle}</p>
        </div>
      </div>

      {/* Expandable content */}
      <AnimatePresence>
        {isActive && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: contentHeight, opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{
              type: "spring",
              stiffness: 250,
              damping: 28,
            }}
            className="overflow-hidden"
          >
            <div
              ref={contentRef}
              className="p-6 bg-gray-800 border-t border-gray-700"
            >
              <p className="text-gray-300 text-sm leading-relaxed">
                {card.description}
              </p>
              <div className="mt-4 flex gap-2">
                <button className="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg text-sm font-medium transition-colors">
                  Learn More
                </button>
                <button className="flex-1 bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-lg text-sm font-medium transition-colors">
                  Share
                </button>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
