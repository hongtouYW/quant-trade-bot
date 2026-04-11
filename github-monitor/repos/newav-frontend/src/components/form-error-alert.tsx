import { CircleAlertIcon } from "lucide-react";

export default function FormErrorMessage({ message }: { message: string }) {
  if (!message) return null;
  return (
    <div className="rounded-md border border-red-500/50 px-4 py-3 text-red-600">
      <div className="flex gap-3">
        <CircleAlertIcon
          className="mt-0.5 shrink-0 opacity-60"
          size={16}
          aria-hidden="true"
        />
        <div className="grow space-y-1">
          <p className="text-sm font-medium">{message}</p>
        </div>
      </div>
    </div>
  );
}
