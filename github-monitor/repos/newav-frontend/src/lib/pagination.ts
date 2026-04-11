/**
 * Generates an array of page numbers with ellipsis for pagination components
 * @param currentPage - The currently active page number
 * @param totalPages - The total number of pages available
 * @returns Array of page numbers and "ellipsis" strings for pagination display
 */
export const generatePageNumbers = (currentPage: number, totalPages: number): (number | "ellipsis")[] => {
  const pages: (number | "ellipsis")[] = [];
  const delta = 1; // Number of pages to show around current page

  if (totalPages <= 5) {
    // If total pages <= 5, show all pages
    for (let i = 1; i <= totalPages; i++) {
      pages.push(i);
    }
  } else {
    // Complex pagination with ellipsis
    if (currentPage <= 4) {
      // Near the beginning: show 1 2 3 4 5 ... last
      for (let i = 1; i <= 5; i++) {
        pages.push(i);
      }
      pages.push("ellipsis");
      pages.push(totalPages);
    } else if (currentPage >= totalPages - 3) {
      // Near the end: show 1 ... (last-4) (last-3) (last-2) (last-1) last
      pages.push(1);
      pages.push("ellipsis");
      for (let i = totalPages - 4; i <= totalPages; i++) {
        pages.push(i);
      }
    } else {
      // In the middle: show 1 ... (current-1) current (current+1) ... last
      pages.push(1);
      pages.push("ellipsis");
      for (let i = currentPage - delta; i <= currentPage + delta; i++) {
        pages.push(i);
      }
      pages.push("ellipsis");
      pages.push(totalPages);
    }
  }

  return pages;
};