import { Link } from "react-router";

export default function InProgress() {
  return (
    <>
      {/*
        This example requires updating your template:

        ```
        <html class="h-full">
        <body class="h-full">
        ```
      */}
      <main className="grid min-h-full place-items-center bg-background px-6 py-24 sm:py-32 lg:px-8">
        <div className="text-center">
          <h1 className="mt-4 text-5xl font-semibold tracking-tight text-balance text-foreground sm:text-7xl">
            Still In Progress
          </h1>
          <div className="mt-10 flex items-center justify-center gap-x-6">
            <Link
              to="/"
              className="rounded-md bg-primary px-3.5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            >
              Go back home
            </Link>
            {/*<a href="#" className="text-sm font-semibold text-gray-900">*/}
            {/*  Contact support <span aria-hidden="true">&rarr;</span>*/}
            {/*</a>*/}
          </div>
        </div>
      </main>
    </>
  );
}
