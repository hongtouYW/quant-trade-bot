import { useQuery } from "@tanstack/react-query";
import { getComicRandom } from "../api/comic-api";
import { randomIntFromInterval } from "../utils/utils";
import { useMemo, useState, useCallback } from "react";

const useRandomComic = () => {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ["comicRandom"],
    queryFn: getComicRandom,
  });

  // store current random index so it doesn't change on each render
  const [randomIndex, setRandomIndex] = useState<number | null>(null);

  // when new data arrives, pick a random index once
  const pickRandomData = useMemo(() => {
    if (!data?.groups?.length) return null;

    // if index already chosen, stick with it
    if (randomIndex !== null && randomIndex < data.groups.length) {
      return data.groups[randomIndex];
    }

    // otherwise pick new index
    const idx = randomIntFromInterval(0, data.groups.length - 1);
    setRandomIndex(idx);
    return data.groups[idx];
  }, [data, randomIndex]);

  // helper to re-pick a new random item from existing data (no API call)
  const repick = useCallback(() => {
    if (!data?.groups?.length) return;
    setRandomIndex(randomIntFromInterval(0, data.groups.length - 1));
  }, [data]);

  return {
    data: pickRandomData,
    isLoading,
    isError,
    refetch, // API refetch
    repick, // local re-randomize
  };
};

export default useRandomComic;
