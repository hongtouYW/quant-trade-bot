import useEmblaCarousel from "embla-carousel-react";
import channelIcon from "../assets/samples/channels/channel-icon.png";
import channel1 from "../assets/samples/channels/channel1.png";
import channel2 from "../assets/samples/channels/channel2.png";
import channel3 from "../assets/samples/channels/channel3.png";
import channel4 from "../assets/samples/channels/channel4.png";
import channel5 from "../assets/samples/channels/channel5.png";

import { Button } from "@/components/ui/button.tsx";
import { cn } from "@/lib/utils.ts";

const data = [
  {
    id: 1,
    name: "AV+",
    image: channel1,
  },
  {
    id: 2,
    name: "寅仙~🎀",
    image: channel2,
  },
  {
    id: 3,
    name: "紅燒小白兔",
    image: channel3,
  },
  {
    id: 4,
    name: "紅燒小白兔",
    image: channel4,
  },
  {
    id: 5,
    name: "紅燒小白兔",
    image: channel5,
    subscribed: true,
  },
];

export const ChannelList = () => {
  // Embla carousel setup with free scroll
  const [emblaRef] = useEmblaCarousel({
    align: "start",
    containScroll: "trimSnaps",
    dragFree: true,
    slidesToScroll: "auto",
    duration: 20,
    skipSnaps: true,
  });

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={channelIcon}
            className="size-6"
            alt="new logo icon"
          />
          <span className="font-bold">頻道主精選</span>
        </div>
        {/*<Button variant="outline" size="sm" className="rounded-2xl">*/}
        {/*  更多*/}
        {/*  <ChevronRight />*/}
        {/*</Button>*/}
      </div>

      {/* Embla Carousel Container */}
      <div
        className="embla mt-3.5 w-full overflow-hidden"
        style={{ maxWidth: 650 }}
      >
        <div ref={emblaRef} className={cn("embla__viewport")}>
          <div className="embla__container flex touch-pan-y">
            {data.map((item) => (
              <div
                key={item.id}
                className="embla__slide flex-[0_0_auto] min-w-0"
              >
                <div className="h-[200px] max-w-[120px] min-w-[120px]">
                  <div className="flex flex-col items-center border transition-all duration-300 hover:border-primary border-transparent hover:bg-[#FCE9FF] rounded-xl overflow-hidden p-2.5">
                    <div className="size-24">
                      <img
                        loading="lazy"
                        src={item.image || channelIcon}
                        alt="Card Background"
                        className="w-full h-full rounded-full object-cover border-2 border-primary"
                      />
                    </div>

                    <div className="flex flex-col items-center gap-1 justify-center">
                      <h2 className="mt-2 text-base font-bold text-center px-4 truncate">
                        {item.name}
                      </h2>
                      <Button
                        className={cn(
                          item?.subscribed ? "bg-[#781938]" : "bg-[#EA1E61]",
                          "rounded-2xl py-1.5 px-10 size-6",
                        )}
                        size="sm"
                      >
                        {item?.subscribed ? "已追踪" : "追踪"}
                      </Button>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};
