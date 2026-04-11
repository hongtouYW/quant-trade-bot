import u from "@/example/utils";

const TOKEN_EXPIRY_MS = 5 * 60 * 1000; // 5 minutes

interface AuthPayload {
  u: string; // username
  p: string; // password
  t: number; // timestamp
}

export function buildAuthLoginUrl(
  username: string,
  password: string,
): string {
  const payload: AuthPayload = {
    u: username,
    p: password,
    t: Date.now(),
  };
  const encrypted = u.encrypt(JSON.stringify(payload));
  const encoded = encodeURIComponent(encrypted);
  return `${window.location.origin}/auth_login#token=${encoded}`;
}

export function parseAuthLoginToken(): {
  username: string;
  password: string;
} | null {
  try {
    const hash = window.location.hash;
    const match = hash.match(/^#token=(.+)$/);
    if (!match) return null;

    const encrypted = decodeURIComponent(match[1]);
    const decrypted = u.decrypt(encrypted);
    if (!decrypted) return null;

    const payload: AuthPayload = JSON.parse(decrypted);
    if (!payload.u || !payload.p || !payload.t) return null;

    // Check expiry
    if (Date.now() - payload.t > TOKEN_EXPIRY_MS) return null;

    return { username: payload.u, password: payload.p };
  } catch {
    return null;
  }
}
