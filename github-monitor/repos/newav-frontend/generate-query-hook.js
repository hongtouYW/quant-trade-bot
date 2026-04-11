#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const args = process.argv.slice(2);

const getArg = (name) => {
  const arg = args.find((a) => a.startsWith(`--${name}=`));
  return arg ? arg.split("=")[1] : undefined;
};

const name = getArg("name"); // e.g. userProfile
const module = getArg("module"); // e.g. user

if (!name || !module) {
  console.error(
    "Usage: node generate-query-hook.js --name=userProfile --module=user",
  );
  process.exit(1);
}

const pascalCase = name.replace(/(^\w|-\w)/g, (m) =>
  m.replace("-", "").toUpperCase(),
);
const camelCase = name.replace(/-([a-z])/g, (_, l) => l.toUpperCase());

const HOOKS_DIR = `src/hooks/${module}`;
const SERVICES_DIR = `src/services`;
const TYPES_DIR = `src/types`;

if (!fs.existsSync(HOOKS_DIR)) fs.mkdirSync(HOOKS_DIR, { recursive: true });

const hookContent = `import { useQuery } from '@tanstack/react-query';
import { fetch${pascalCase} } from '@/services/${module}.service';
import type { ${pascalCase} } from '@/types/${module}';
import type { ApiResponse } from '@/types/api-response';

export const use${pascalCase} = () =>
  useQuery<${pascalCase}, Error>({
    queryKey: ['${camelCase}'],
    queryFn: fetch${pascalCase},
    select: (res) => res.data,
  });
`;

fs.writeFileSync(path.join(HOOKS_DIR, `use${pascalCase}.ts`), hookContent);

const serviceFile = path.join(SERVICES_DIR, `${module}.service.ts`);
const serviceStub = `export const fetch${pascalCase} = async (): Promise<ApiResponse<${pascalCase}>> => {
  const res = await fetch('/api/${camelCase}');
  if (!res.ok) throw new Error('Network error');
  return res.json();
};\n`;

if (!fs.existsSync(SERVICES_DIR))
  fs.mkdirSync(SERVICES_DIR, { recursive: true });
if (fs.existsSync(serviceFile)) {
  fs.appendFileSync(serviceFile, `\n${serviceStub}`);
} else {
  fs.writeFileSync(serviceFile, serviceStub);
}

const typeFile = path.join(TYPES_DIR, `${module}.ts`);
if (!fs.existsSync(typeFile)) {
  const typeStub = `export interface ${pascalCase} {
  // TODO: define fields
}\n`;
  fs.mkdirSync(TYPES_DIR, { recursive: true });
  fs.writeFileSync(typeFile, typeStub);
}

console.log(
  `✅ Generated hook, service, and type for '${name}' in module '${module}'.`,
);
